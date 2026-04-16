<?php
/**
 * Settings page – store WhatsApp gateway credentials.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	private const OPTION_GROUP = 'noor_tms_settings';
	private const OPTION_NAME  = 'noor_tms_options';

	/**
	 * Render settings page and handle form submission.
	 */
	public function page_settings(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		// Handle save.
		if ( isset( $_POST['noor_tms_settings_nonce'] ) ) {
			$this->handle_save();
		}

		$opts = self::get_options();
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php esc_html_e( 'Noor-TMS – Settings', 'noor-tms' ); ?></h1>

			<?php settings_errors( 'noor_tms_messages' ); ?>

			<div class="noor-tms-card">
				<h2><?php esc_html_e( 'WhatsApp Gateway Credentials', 'noor-tms' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enter your Ultramsg / Twilio credentials. Leave blank to disable automatic notifications.', 'noor-tms' ); ?>
				</p>

				<form method="post" action="">
					<?php wp_nonce_field( 'noor_tms_save_settings', 'noor_tms_settings_nonce' ); ?>

					<table class="form-table" role="presentation" id="noor-settings-table">
						<tr>
							<th scope="row">
								<label for="gateway_provider"><?php esc_html_e( 'Gateway Provider', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select name="gateway_provider" id="gateway_provider">
									<?php
									$providers = [
									'click_to_chat' => __( 'WhatsApp Web (Click-to-Chat) — Free', 'noor-tms' ),
									'mock'          => __( 'Mock / Test Mode', 'noor-tms' ),
									'ultramsg'      => 'Ultramsg',
									'twilio'        => 'Twilio',
									];
									foreach ( $providers as $key => $label ) {
										printf(
											'<option value="%s"%s>%s</option>',
											esc_attr( $key ),
											selected( $opts['gateway_provider'] ?? 'click_to_chat', $key, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p id="noor-ctc-hint" class="description" style="color:#25D366;font-weight:600;margin-top:6px;">
									<?php esc_html_e( "\u2713 No API keys needed. Opens WhatsApp Web with message pre-filled \u2014 admin clicks Send.", 'noor-tms' ); ?>
								</p>
							</td>
						</tr>
						<tr class="noor-api-row">
							<th scope="row">
								<label for="api_instance_id"><?php esc_html_e( 'Instance ID / Account SID', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="text" id="api_instance_id" name="api_instance_id"
									value="<?php echo esc_attr( $opts['api_instance_id'] ?? '' ); ?>"
									class="regular-text" />
								<p class="description"><?php esc_html_e( 'Ultramsg: Instance ID — Twilio: Account SID', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr class="noor-api-row">
							<th scope="row">
								<label for="api_token"><?php esc_html_e( 'API Token / Auth Token', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="password" id="api_token" name="api_token"
									value="<?php echo esc_attr( $opts['api_token'] ?? '' ); ?>"
									class="regular-text" autocomplete="new-password" />
								<p class="description"><?php esc_html_e( 'Ultramsg: API Token — Twilio: Auth Token', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr class="noor-api-row">
							<th scope="row">
								<label for="sender_number"><?php esc_html_e( 'Sender / From Number', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="text" id="sender_number" name="sender_number"
									value="<?php echo esc_attr( $opts['sender_number'] ?? '' ); ?>"
									class="regular-text"
									placeholder="+923001234567" />
								<p class="description"><?php esc_html_e( 'International format, e.g. +923001234567', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="message_template"><?php esc_html_e( 'Message Template', 'noor-tms' ); ?></label>
							</th>
							<td>
								<textarea id="message_template" name="message_template" rows="4"
									class="large-text"><?php echo esc_textarea( $opts['message_template'] ?? self::default_template() ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Available placeholders: {student_name}, {subject}, {marks_obtained}, {total_marks}, {exam_date}', 'noor-tms' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'noor-tms' ) ); ?>
				</form>
			</div>
		</div>

		<script>
		( function() {
			var sel   = document.getElementById( 'gateway_provider' );
			var hint  = document.getElementById( 'noor-ctc-hint' );
			var rows  = document.querySelectorAll( '.noor-api-row' );
			function toggle() {
				var isCtc = sel.value === 'click_to_chat';
				var isMock = sel.value === 'mock';
				hint.style.display  = isCtc ? '' : 'none';
				rows.forEach( function( r ) {
					r.style.display = ( isCtc || isMock ) ? 'none' : '';
				} );
			}
			sel.addEventListener( 'change', toggle );
			toggle();
		} )();
		</script>
		<?php
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Process settings form POST.
	 */
	private function handle_save(): void {
		if ( ! check_admin_referer( 'noor_tms_save_settings', 'noor_tms_settings_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$allowed_providers = [ 'click_to_chat', 'mock', 'ultramsg', 'twilio' ];
		$provider          = sanitize_key( $_POST['gateway_provider'] ?? 'click_to_chat' );

		update_option(
			self::OPTION_NAME,
			[
				'gateway_provider' => in_array( $provider, $allowed_providers, true ) ? $provider : 'click_to_chat',
				'api_instance_id'  => sanitize_text_field( $_POST['api_instance_id'] ?? '' ),
				'api_token'        => sanitize_text_field( $_POST['api_token']        ?? '' ),
				'sender_number'    => sanitize_text_field( $_POST['sender_number']    ?? '' ),
				'message_template' => wp_kses_post( $_POST['message_template'] ?? self::default_template() ),
			]
		);

		add_settings_error( 'noor_tms_messages', 'settings_saved', __( 'Settings saved.', 'noor-tms' ), 'updated' );
	}

	/**
	 * Return saved plugin options (merged with defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options(): array {
		return wp_parse_args(
			(array) get_option( self::OPTION_NAME, [] ),
			[
				'gateway_provider' => 'click_to_chat',
				'api_instance_id'  => '',
				'api_token'        => '',
				'sender_number'    => '',
				'message_template' => self::default_template(),
			]
		);
	}

	/**
	 * Default WhatsApp message template.
	 */
	public static function default_template(): string {
		return "Assalam-o-Alaikum,\n\nDear Parent,\n\n{student_name} has received the following result:\n📚 Subject: {subject}\n✏️ Marks: {marks_obtained} / {total_marks}\n📅 Exam Date: {exam_date}\n\nRegards,\nMadrasa Management";
	}
}
