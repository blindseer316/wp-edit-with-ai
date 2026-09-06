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

	private array $keys;
	private string $model;

	public function __construct() {
		$options   = WP_Edit_With_AI_Settings::get_options();
		$this->keys = array_values( array_filter( array( $options['gemini_key_1'], $options['gemini_key_2'] ) ) );
		$this->model = $options['model'] ?: 'gemini-flash-latest';
	}

	/**
	 * Sends a single-turn text prompt, trying each configured key in order.
	 * Falls through to the next key on auth/quota errors (401/403/429).
	 *
	 * @return array { ok: bool, text?: string, error?: string, key_index?: int }
	 */
	public function generate_text( string $prompt, array $tools = array() ): array {
		if ( empty( $this->keys ) ) {
			return array(
				'ok'    => false,
				'error' => 'No Gemini API key configured. Add one under WP Edit With AI > Settings.',
			);
		}

		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
		);

		if ( ! empty( $tools ) ) {
			$body['tools'] = array( array( 'function_declarations' => $tools ) );
		}

		$last_error = '';

		foreach ( $this->keys as $index => $key ) {
			$response = $this->call_api( $key, $body );

			if ( $response['ok'] ) {
				$response['key_index'] = $index + 1;
				return $response;
			}

			$last_error = $response['error'];

			// Only fall through to the next key on auth/quota-type failures.
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

		$text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ( null === $text ) {
			return array(
				'ok'    => false,
				'error' => 'Unexpected response shape from Gemini API.',
			);
		}

		return array(
			'ok'   => true,
			'text' => $text,
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

			$results[] = array(
				'label'   => 'Key ' . ( $index + 1 ) . ' (' . $this->model . ')',
				'ok'      => $response['ok'],
				'message' => $response['ok'] ? trim( $response['text'] ) : $response['error'],
			);
		}

		return $results;
	}
}
