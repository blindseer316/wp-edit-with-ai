<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Edit_With_AI_REST_Controller {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'wp-edit-with-ai/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'message' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			'wp-edit-with-ai/v1',
			'/test-connection',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_test_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handles a chat turn. Tool-calling loop (function_declarations wired to
	 * WP_Edit_With_AI_Content_Tools) lands in the next milestone — this sends
	 * the message to Gemini and returns its plain-text reply.
	 */
	public function handle_chat( WP_REST_Request $request ) {
		$message = $request->get_param( 'message' );

		$client   = new WP_Edit_With_AI_Gemini_Client();
		$response = $client->generate_text( $message );

		if ( ! $response['ok'] ) {
			return new WP_REST_Response( array( 'reply' => 'Error: ' . $response['error'] ), 200 );
		}

		return new WP_REST_Response( array( 'reply' => $response['text'] ), 200 );
	}

	public function handle_test_connection( WP_REST_Request $request ) {
		$client  = new WP_Edit_With_AI_Gemini_Client();
		$results = $client->test_each_key();

		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}
}
