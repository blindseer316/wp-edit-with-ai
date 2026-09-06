<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter for kie.ai's GPT-5.6 Luna endpoint (a Responses-API-style wrapper
 * around GPT-5). Same public interface as WP_Edit_With_AI_Gemini_Client
 * (run_conversation / test_connection) so the REST controller can use
 * either provider interchangeably based on the Settings toggle.
 *
 * Note: kie.ai's own docs (docs.kie.ai) document the request format and a
 * plain-text response, but do not document the function-call output shape
 * or how to submit a function result back. This implementation follows the
 * standard OpenAI Responses API convention their endpoint mirrors
 * (function_call output items, function_call_output input items) — this is
 * inferred, not confirmed, and may need correction after a real test, same
 * as the Gemini adapter did.
 */
class WP_Edit_With_AI_Kie_Client {

	const API_URL = 'https://api.kie.ai/codex/v1/responses';
	const MAX_TOOL_TURNS = 6;

	private string $api_key;
	private string $model;

	public function __construct() {
		$options       = WP_Edit_With_AI_Settings::get_options();
		$this->api_key = $options['kie_api_key'];
		$this->model   = $options['kie_model'] ?: 'gpt-5-6-luna';
	}

	/**
	 * @return array { ok, text?, error?, actions: array of {tool,args,result} }
	 */
	public function run_conversation( string $user_message, WP_Edit_With_AI_Content_Tools $tools ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'      => false,
				'error'   => 'No kie.ai API key configured. Add one under WP Edit With AI > Settings.',
				'actions' => array(),
			);
		}

		$input = array(
			array(
				'role'    => 'user',
				'content' => array( array( 'type' => 'input_text', 'text' => $user_message ) ),
			),
		);

		$tool_declarations = $this->to_kie_tool_format( WP_Edit_With_AI_Content_Tools::get_tool_declarations() );
		$actions           = array();

		for ( $turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++ ) {
			$body = array(
				'model'  => $this->model,
				'stream' => false,
				'input'  => $input,
			);

			if ( ! empty( $tool_declarations ) ) {
				$body['tools']       = $tool_declarations;
				$body['tool_choice'] = 'auto';
			}

			$response = $this->call_api( $body );

			if ( ! $response['ok'] ) {
				return array(
					'ok'      => false,
					'error'   => $response['error'],
					'actions' => $actions,
				);
			}

			$function_call_item = null;
			$text                = '';

			foreach ( $response['output'] as $item ) {
				if ( 'function_call' === ( $item['type'] ?? '' ) ) {
					$function_call_item = $item;
				} elseif ( 'message' === ( $item['type'] ?? '' ) ) {
					foreach ( $item['content'] ?? array() as $content_part ) {
						if ( 'output_text' === ( $content_part['type'] ?? '' ) ) {
							$text .= $content_part['text'] ?? '';
						}
					}
				}
			}

			if ( ! $function_call_item ) {
				return array(
					'ok'      => true,
					'text'    => $text,
					'actions' => $actions,
				);
			}

			$name = $function_call_item['name'] ?? '';
			$args = json_decode( $function_call_item['arguments'] ?? '{}', true ) ?: array();
			$result = $tools->dispatch( $name, $args );

			$actions[] = array(
				'tool'   => $name,
				'args'   => $args,
				'result' => $result,
			);

			// Echo the model's own function_call item back, then follow it
			// with our function_call_output — standard Responses API pairing.
			$input[] = $function_call_item;
			$input[] = array(
				'type'    => 'function_call_output',
				'call_id' => $function_call_item['call_id'] ?? '',
				'output'  => wp_json_encode( $result ),
			);
		}

		return array(
			'ok'      => false,
			'error'   => 'Reached the tool-call limit without a final answer.',
			'actions' => $actions,
		);
	}

	private function to_kie_tool_format( array $gemini_style_tools ): array {
		$tools = array();
		foreach ( $gemini_style_tools as $tool ) {
			$tools[] = array(
				'type'        => 'function',
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'parameters'  => $this->lowercase_types( $tool['parameters'] ),
			);
		}
		return $tools;
	}

	/**
	 * Gemini's schema uses uppercase types (OBJECT, STRING, INTEGER); the
	 * OpenAI-style schema kie.ai expects uses lowercase JSON Schema types.
	 */
	private function lowercase_types( array $schema ): array {
		if ( isset( $schema['type'] ) ) {
			$schema['type'] = strtolower( $schema['type'] );
		}
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $prop ) {
				$schema['properties'][ $key ] = $this->lowercase_types( $prop );
			}
		}
		return $schema;
	}

	private function call_api( array $body ): array {
		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
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
			$message = $decoded['error']['message'] ?? $decoded['error'] ?? ( 'HTTP ' . $status );
			return array(
				'ok'    => false,
				'error' => is_string( $message ) ? $message : wp_json_encode( $message ),
			);
		}

		if ( ! isset( $decoded['output'] ) ) {
			return array(
				'ok'    => false,
				'error' => 'Unexpected response shape from kie.ai API.',
			);
		}

		return array(
			'ok'     => true,
			'output' => $decoded['output'],
		);
	}

	/**
	 * @return array List of { label, ok, message }
	 */
	public function test_connection(): array {
		if ( empty( $this->api_key ) ) {
			return array(
				array(
					'label'   => 'kie.ai (' . $this->model . ')',
					'ok'      => false,
					'message' => 'No API key configured.',
				),
			);
		}

		$response = $this->call_api(
			array(
				'model'  => $this->model,
				'stream' => false,
				'input'  => array(
					array(
						'role'    => 'user',
						'content' => array( array( 'type' => 'input_text', 'text' => 'Reply with exactly: OK' ) ),
					),
				),
			)
		);

		$message = 'Unexpected response.';
		if ( $response['ok'] ) {
			foreach ( $response['output'] as $item ) {
				if ( 'message' === ( $item['type'] ?? '' ) ) {
					foreach ( $item['content'] ?? array() as $content_part ) {
						if ( 'output_text' === ( $content_part['type'] ?? '' ) ) {
							$message = trim( $content_part['text'] ?? '' );
						}
					}
				}
			}
		} else {
			$message = $response['error'];
		}

		return array(
			array(
				'label'   => 'kie.ai (' . $this->model . ')',
				'ok'      => $response['ok'],
				'message' => $message,
			),
		);
	}
}
