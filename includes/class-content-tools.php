<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tool implementations the chat loop can call. Target order per project
 * plan: Gutenberg/plain HTML first, then ACF fields, then Elementor widgets.
 */
class WP_Edit_With_AI_Content_Tools {

	public function find_pages( string $search ): array {
		$query = new WP_Query(
			array(
				'post_type'      => array( 'page', 'post' ),
				's'              => $search,
				'posts_per_page' => 10,
				'post_status'    => array( 'publish', 'draft', 'private' ),
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
			);
		}

		return array( 'results' => $results );
	}

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
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'Current user is not allowed to edit this post.' );
		}

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

		return array(
			'success'  => true,
			'id'       => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		);
	}

	public function update_post_title( int $post_id, string $title ): array {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'Current user is not allowed to edit this post.' );
		}

		$result = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		return array(
			'success'  => true,
			'id'       => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		);
	}

	/**
	 * Gemini function_declarations for every tool above. Kept in sync by hand
	 * for now — small enough set that generating this via reflection isn't
	 * worth the complexity yet.
	 */
	public static function get_tool_declarations(): array {
		return array(
			array(
				'name'        => 'find_pages',
				'description' => 'Search for pages or posts by title or keyword to find their numeric ID. Always call this first when the user refers to a page/post by name instead of ID.',
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'search' => array(
							'type'        => 'STRING',
							'description' => 'Keyword or title to search for, e.g. "Contact" or "Schedule"',
						),
					),
					'required'   => array( 'search' ),
				),
			),
			array(
				'name'        => 'get_page_content',
				'description' => 'Get the current HTML/Gutenberg content of a page or post by its numeric ID. Always call this before update_page_content so edits are based on the real current structure.',
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'post_id' => array( 'type' => 'INTEGER' ),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'update_page_content',
				'description' => 'Replace the full content of a page or post with new HTML/Gutenberg content. Preserve any part of the existing content the user did not ask to change.',
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'post_id' => array( 'type' => 'INTEGER' ),
						'content' => array(
							'type'        => 'STRING',
							'description' => 'The complete new post_content HTML/Gutenberg markup, not just the changed fragment.',
						),
					),
					'required'   => array( 'post_id', 'content' ),
				),
			),
			array(
				'name'        => 'update_post_title',
				'description' => 'Change the title of a page or post (the post_title field, shown in the post list and often as the page heading). Separate from update_page_content, which only changes the body.',
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'post_id' => array( 'type' => 'INTEGER' ),
						'title'   => array( 'type' => 'STRING' ),
					),
					'required'   => array( 'post_id', 'title' ),
				),
			),
		);
	}

	/**
	 * Routes a Gemini function call (by name) to the matching method.
	 */
	public function dispatch( string $name, array $args ) {
		switch ( $name ) {
			case 'find_pages':
				return $this->find_pages( (string) ( $args['search'] ?? '' ) );
			case 'get_page_content':
				return $this->get_page_content( (int) ( $args['post_id'] ?? 0 ) );
			case 'update_page_content':
				return $this->update_page_content( (int) ( $args['post_id'] ?? 0 ), (string) ( $args['content'] ?? '' ) );
			case 'update_post_title':
				return $this->update_post_title( (int) ( $args['post_id'] ?? 0 ), (string) ( $args['title'] ?? '' ) );
			default:
				return array( 'error' => 'Unknown tool: ' . $name );
		}
	}
}
