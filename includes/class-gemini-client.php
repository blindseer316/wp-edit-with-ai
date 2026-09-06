<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin adapter over the Gemini generateContent REST API. Kept separate from
 * the tool-execution classes so swapping providers later is contained here.
 *
 * Uses the current v1beta generateContent endpoint and the
 * "gemini-flash-latest" style model alias by default, to avoid pinning to a
 * model version that later gets deprecated.
 */
class WP_Edit_With_AI_Gemini_Client {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
	const MAX_TOOL_TURNS = 6;

	private array $keys;
	private string $model;

	public function __construct() {
		$options    = WP_Edit_With_AI_Settings::get_options();
		$this->keys = array_values( array_filter( array( $options['gemini_key_1'], $options['gemini_key_2'] ) ) );
		$this->model = $options['model'] ?: 'gemini-flash-latest';
	}

	/**
	 * Runs a full chat turn: sends the user message with the content-tool
	 * definitions, executes any function calls Gemini requests via
	 * WP_Edit_With_AI_Content_Tools, feeds the results back, and repeats
	 * until Gemini returns a final text reply (or MAX_TOOL_TURNS is hit).
	 *
	 * @return array { ok, text?, error?, actions: array of {tool,args,result} }
	 */
	public function run_conversation( string $user_message, WP_Edit_With_AI_Content_Tools $tools ): array {
		if ( empty( $this->keys ) ) {
			return array(
				'ok'      => false,
				'error'   => 'No Gemini API key configured. Add one under WP Edit With AI > Settings.',
				'actions' => array(),
			);
		}

		$contents = array(
			array(
				'role'  => 'user',
				'parts' => array( array( 'text' => $user_message ) ),
			),
		);

		$tool_declarations = array( array( 'function_declarations' => WP_Edit_With_AI_Content_Tools::get_tool_declarations() ) );
		$actions           = array();

		for ( $turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++ ) {
			$response = $this->request_with_rotation(
				array(
					'contents' => $contents,
					'tools'    => $tool_declarations,
				)
			);

			if ( ! $response['ok'] ) {
				return array(
					'ok'      => false,
					'error'   => $response['error'],
					'actions' => $actions,
				);
			}

			$function_call = null;
			$text          = '';

			foreach ( $response['parts'] as $part ) {
				if ( isset( $part['functionCall'] ) ) {
					$function_call = $part['functionCall'];
				} elseif ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}

			if ( ! $function_call ) {
				return array(
					'ok'      => true,
					'text'    => $text,
					'actions' => $actions,
				);
			}

			$name   = $function_call['name'] ?? '';
			$args   = $function_call['args'] ?? array();
			$result = $tools->dispatch( $name, $args );

			$actions[] = array(
				'tool'   => $name,
				'args'   => $args,
				'result' => $result,
			);

			$contents[] = array(
				'role'  => 'model',
				'parts' => array( array( 'functionCall' => $function_call ) ),
			);
			$contents[] = array(
				'role'  => 'function',
				'parts' => array(
					array(
						'functionResponse' => array(
							'name'     => $name,
							'response' => $result,
						),
					),
				),
			);
		}

		return array(
			'ok'      => false,
			'error'   => 'Reached the tool-call limit without a final answer. The request may be too complex, or a tool call is looping.',
			'actions' => $actions,
		);
	}

	/**
	 * Simple single-turn text prompt with no tools, used by the Settings
	 * page "Test API Connection" check via test_each_key().
	 */
	private function generate_text( string $prompt ): array {
		$response = $this->request_with_rotation(
			array(
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $prompt ) ),
					),
				),
			)
		);

		if ( ! $response['ok'] ) {
			return $response;
		}

		$text = '';
		foreach ( $response['parts'] as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}

		return array( 'ok' => true, 'text' => $text );
	}

	/**
	 * Tries each configured key in order for one request, falling through to
	 * the next key only on auth/quota-type failures (401/403/429).
	 *
	 * @return array { ok: bool, parts?: array, error?: string, key_index?: int }
	 */
	private function request_with_rotation( array $body ): array {
		$last_error = '';

		foreach ( $this->keys as $index => $key ) {
			$response = $this->call_api( $key, $body );

			if ( $response['ok'] ) {
				$response['key_index'] = $index + 1;
				return $response;
			}

			$last_error = $response['error'];

			if ( ! in_array( $response['status'] ?? 0, array( 401, 403, 429 ), true ) ) {
				break;
			}
		}

		return array(
			'ok'    => false,
			'error' => $last_error,
		);
	}

	private function call_api( string $key, array $body ): array {
		$url = self::API_BASE . rawurlencode( $this->model ) . ':generateContent?key=' . rawurlencode( $key );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = $decoded['error']['message'] ?? ( 'HTTP ' . $status );
			return array(
				'ok'     => false,
				'error'  => $message,
				'status' => $status,
			);
		}

		$parts = $decoded['candidates'][0]['content']['parts'] ?? null;

		if ( null === $parts ) {
			return array(
				'ok'    => false,
				'error' => 'Unexpected response shape from Gemini API.',
			);
		}

		return array(
			'ok'    => true,
			'parts' => $parts,
		);
	}

	public function key_count(): int {
		return count( $this->keys );
	}

	/**
	 * Tests each configured key independently with a trivial prompt, used by
	 * the Settings page "Test API Connection" button.
	 *
	 * @return array List of { label, ok, message }
	 */
	public function test_each_key(): array {
		$results = array();

		if ( empty( $this->keys ) ) {
			return array(
				array(
					'label'   => 'Gemini',
					'ok'      => false,
					'message' => 'No API key configured.',
				),
			);
		}

		foreach ( $this->keys as $index => $key ) {
			$response = $this->call_api(
				$key,
				array(
					'contents' => array(
						array(
							'role'  => 'user',
							'parts' => array( array( 'text' => 'Reply with exactly: OK' ) ),
						),
					),
				)
			);

			$message = 'Unexpected response.';
			if ( $response['ok'] ) {
				foreach ( $response['parts'] as $part ) {
					if ( isset( $part['text'] ) ) {
						$message = trim( $part['text'] );
						break;
					}
				}
			} else {
				$message = $response['error'];
			}

			$results[] = array(
				'label'   => 'Key ' . ( $index + 1 ) . ' (' . $this->model . ')',
				'ok'      => $response['ok'],
				'message' => $message,
			);
		}

		return $results;
	}
}
