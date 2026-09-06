<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores/reads Gemini API config. Values entered via the settings form
 * (wp_options) take priority; WP_EDIT_WITH_AI_GEMINI_KEY_1/2 constants
 * from local-secrets.php are used as a dev-only fallback.
 *
 * Deliberately has no separate admin page/menu of its own — its fields
 * render inline on the main WP_Edit_With_AI_Admin_Page (gated by
 * manage_options there) so there's only ever one menu item and one URL to
 * register, avoiding conflicts with menu-customization plugins/plugins that
 * rewrite admin submenu links.
 */
class WP_Edit_With_AI_Settings {

	const OPTION_KEY = 'wp_edit_with_ai_options';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function get_options(): array {
		$defaults = array(
			'provider'     => 'gemini',
			'gemini_key_1' => defined( 'WP_EDIT_WITH_AI_GEMINI_KEY_1' ) ? WP_EDIT_WITH_AI_GEMINI_KEY_1 : '',
			'gemini_key_2' => defined( 'WP_EDIT_WITH_AI_GEMINI_KEY_2' ) ? WP_EDIT_WITH_AI_GEMINI_KEY_2 : '',
			'model'        => 'gemini-flash-latest',
			'kie_api_key'  => defined( 'WP_EDIT_WITH_AI_KIE_API_KEY' ) ? WP_EDIT_WITH_AI_KIE_API_KEY : '',
			'kie_model'    => 'gpt-5-6-luna',
		);
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Returns the active AI client (Gemini or kie.ai) based on the Settings
	 * provider toggle. Both classes share the same run_conversation() /
	 * test_connection() interface.
	 */
	public static function get_active_client() {
		$options = self::get_options();
		if ( 'kie' === $options['provider'] ) {
			return new WP_Edit_With_AI_Kie_Client();
		}
		return new WP_Edit_With_AI_Gemini_Client();
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
			'provider'     => ( isset( $input['provider'] ) && 'kie' === $input['provider'] ) ? 'kie' : 'gemini',
			'gemini_key_1' => isset( $input['gemini_key_1'] ) ? sanitize_text_field( $input['gemini_key_1'] ) : '',
			'gemini_key_2' => isset( $input['gemini_key_2'] ) ? sanitize_text_field( $input['gemini_key_2'] ) : '',
			'model'        => isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gemini-flash-latest',
			'kie_api_key'  => isset( $input['kie_api_key'] ) ? sanitize_text_field( $input['kie_api_key'] ) : '',
			'kie_model'    => isset( $input['kie_model'] ) ? sanitize_text_field( $input['kie_model'] ) : 'gpt-5-6-luna',
		);
	}

	/**
	 * Renders the settings form + connection-test UI. Called directly from
	 * WP_Edit_With_AI_Admin_Page::render_page() — caller is responsible for
	 * the manage_options capability check before calling this.
	 */
	public function render_fields() {
		$options = self::get_options();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wp_edit_with_ai_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="provider">AI Provider</label></th>
					<td>
						<select id="provider" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]">
							<option value="gemini" <?php selected( $options['provider'], 'gemini' ); ?>>Google Gemini</option>
							<option value="kie" <?php selected( $options['provider'], 'kie' ); ?>>kie.ai (GPT-5.6 Luna)</option>
						</select>
						<p class="description">Which provider the chat actually uses. Switch here if Gemini's free tier is rate-limited.</p>
					</td>
				</tr>
			</table>

			<h3>Google Gemini</h3>
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

			<h3>kie.ai</h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="kie_api_key">kie.ai API Key</label></th>
					<td><input type="password" id="kie_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[kie_api_key]" value="<?php echo esc_attr( $options['kie_api_key'] ); ?>" class="regular-text" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kie_model">Model</label></th>
					<td><input type="text" id="kie_model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[kie_model]" value="<?php echo esc_attr( $options['kie_model'] ); ?>" class="regular-text" />
					<p class="description">Default <code>gpt-5-6-luna</code>.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<h2>Connection Test</h2>
		<button type="button" id="wp-edit-with-ai-test-connection" class="button">Test API Connection</button>
		<div id="wp-edit-with-ai-test-result" style="margin-top:12px;"></div>
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
