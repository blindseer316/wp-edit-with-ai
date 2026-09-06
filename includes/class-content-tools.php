<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tool implementations the chat loop can call. Target order per project
 * plan: Gutenberg/plain HTML first, then ACF fields, then Elementor widgets.
 */
class WP_Edit_With_AI_Content_Tools {

	public function get_page_content( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}
		return array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'content' => $post->post_content,
		);
	}

	public function update_page_content( int $post_id, string $content ): array {
		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array( 'success' => true, 'id' => $result );
	}
}
