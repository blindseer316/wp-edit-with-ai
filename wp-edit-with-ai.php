<?php
/**
 * Plugin Name: WP Edit With AI
 * Plugin URI: https://example.com/wp-edit-with-ai
 * Description: Chat-driven content editing for non-technical clients — no MCP connector, no external app. Settings/dashboard: WP Admin > WP Edit With AI.
 * Version: 0.5.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Earthbreakdesigns.com
 * License: GPL v2 or later
 * Text Domain: wp-edit-with-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_EDIT_WITH_AI_VERSION', '0.5.1' );
define( 'WP_EDIT_WITH_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_EDIT_WITH_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Optional local-only file for default API keys during development.
// Never commit this file — see local-secrets.sample.php.
if ( file_exists( WP_EDIT_WITH_AI_PLUGIN_DIR . 'local-secrets.php' ) ) {
	require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'local-secrets.php';
}

require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-settings.php';
require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-gemini-client.php';
require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-kie-client.php';
require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WP_EDIT_WITH_AI_PLUGIN_DIR . 'includes/class-content-tools.php';

function wp_edit_with_ai_init() {
	new WP_Edit_With_AI_Settings();
	new WP_Edit_With_AI_Admin_Page();
	new WP_Edit_With_AI_REST_Controller();
}
add_action( 'plugins_loaded', 'wp_edit_with_ai_init' );
