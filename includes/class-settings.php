<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores/reads Gemini API config. Values entered via the settings page
 * (wp_options) take priority; WP_EDIT_WITH_AI_GEMINI_KEY_1/2 constants
 * from local-secrets.php are used as a dev-only fallback.
 */
class WP_Edit_With_AI_Settings {

	const OPTION_KEY = 'wp_edit_with_ai_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_options(): array {
		$defaults = array(
			'gemini_key_1' => defined( 'WP_EDIT_WITH_AI_GEMINI_KEY_1' ) ? WP_EDIT_WITH_AI_GEMINI_KEY_1 : '',
			'gemini_key_2' => defined( 'WP_EDIT_WITH_AI_GEMINI_KEY_2' ) ? WP_EDIT_WITH_AI_GEMINI_KEY_2 : '',
			'model'        => 'gemini-flash-latest',
		);
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $stored, $defaults );
	}

	public function register_submenu() {
		add_submenu_page(
			'wp-edit-with-ai',
			'Settings',
			'Settings',
			'manage_options',
			'wp-edit-with-ai-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wp_edit_with_ai_settings_group',
			self::OPTION_KEY,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function sanitize( $input ): array {
		return array(
			'gemini_key_1' => isset( $input['gemini_key_1'] ) ? sanitize_text_field( $input['gemini_key_1'] ) : '',
			'gemini_key_2' => isset( $input['gemini_key_2'] ) ? sanitize_text_field( $input['gemini_key_2'] ) : '',
			'model'        => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gemini-flash-latest',
		);
	}

	public function render_settings_page() {
		$options = self::get_options();
		?>
		<div class="wrap">
			<h1>WP Edit With AI — Settings <span style="font-size:13px;font-weight:400;color:#666;">v<?php echo esc_html( WP_EDIT_WITH_AI_VERSION ); ?></span></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wp_edit_with_ai_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gemini_key_1">Gemini API Key 1</label></th>
						<td><input type="password" id="gemini_key_1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[gemini_key_1]" value="<?php echo esc_attr( $options['gemini_key_1'] ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_key_2">Gemini API Key 2 (fallback)</label></th>
						<td><input type="password" id="gemini_key_2" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[gemini_key_2]" value="<?php echo esc_attr( $options['gemini_key_2'] ); ?>" class="regular-text" autocomplete="off" />
						<p class="description">Used automatically if key 1 hits a rate limit or quota error (useful on the free tier).</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="model">Model</label></th>
						<td><input type="text" id="model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $options['model'] ); ?>" class="regular-text" />
						<p class="description">Default <code>gemini-flash-latest</code> tracks Google's current flash model automatically. Set an exact model name (e.g. <code>gemini-2.5-pro</code>) to pin a version.</p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2>Connection Test</h2>
			<button type="button" id="wp-edit-with-ai-test-connection" class="button">Test API Connection</button>
			<div id="wp-edit-with-ai-test-result" style="margin-top:12px;"></div>
		</div>
		<script>
		document.getElementById('wp-edit-with-ai-test-connection').addEventListener('click', async function () {
			const resultEl = document.getElementById('wp-edit-with-ai-test-result');
			resultEl.textContent = 'Testing…';
			try {
				const res = await fetch('<?php echo esc_url_raw( rest_url( 'wp-edit-with-ai/v1/test-connection' ) ); ?>', {
					method: 'POST',
					headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
				});
				const data = await res.json();
				resultEl.innerHTML = (data.results || [])
					.map(r => (r.ok ? '✅ ' : '❌ ') + r.label + ': ' + r.message)
					.join('<br>');
			} catch (e) {
				resultEl.textContent = 'Request failed: ' + e.message;
			}
		});
		</script>
		<?php
	}
}
