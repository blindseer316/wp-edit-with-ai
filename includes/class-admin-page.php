<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Edit_With_AI_Admin_Page {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			'WP Edit With AI',
			'WP Edit With AI',
			'edit_posts',
			'wp-edit-with-ai',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wp-edit-with-ai' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wp-edit-with-ai-admin',
			WP_EDIT_WITH_AI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_EDIT_WITH_AI_VERSION
		);
		wp_enqueue_script(
			'wp-edit-with-ai-admin',
			WP_EDIT_WITH_AI_PLUGIN_URL . 'assets/js/admin-chat.js',
			array(),
			WP_EDIT_WITH_AI_VERSION,
			true
		);
		wp_localize_script(
			'wp-edit-with-ai-admin',
			'wpEditWithAI',
			array(
				'restUrl' => esc_url_raw( rest_url( 'wp-edit-with-ai/v1/chat' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public function render_page() {
		?>
		<div class="wrap wp-edit-with-ai-wrap">
			<h1>WP Edit With AI <span class="wp-edit-with-ai-version">v<?php echo esc_html( WP_EDIT_WITH_AI_VERSION ); ?></span></h1>
			<p>Tell it what to change on your site — no page builder needed.</p>

			<div id="wp-edit-with-ai-chat">
				<div id="wp-edit-with-ai-messages" class="wp-edit-with-ai-messages"></div>
				<div class="wp-edit-with-ai-input-row">
					<textarea id="wp-edit-with-ai-input" placeholder="e.g. Update the hours on the Contact page to close at 6pm on Fridays"></textarea>
					<button id="wp-edit-with-ai-send" class="button button-primary">Send</button>
				</div>
			</div>

			<p class="wp-edit-with-ai-footer">WP Edit With AI &middot; version <?php echo esc_html( WP_EDIT_WITH_AI_VERSION ); ?></p>
		</div>
		<?php
	}
}
