<?php
/**
 * Settings page – store WhatsApp, homepage, support, and AI-ready options.
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
		if ( ! noor_tms_can_manage() ) {
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

						<tr>
							<th colspan="2" style="padding-top:24px;">
								<h2 style="margin:0;"><?php esc_html_e( 'Public Homepage Content', 'noor-tms' ); ?></h2>
							</th>
						</tr>
						<tr>
							<th scope="row"><label for="madrassa_name"><?php esc_html_e( 'Madrassa Name', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="madrassa_name" name="madrassa_name" class="regular-text" value="<?php echo esc_attr( $opts['madrassa_name'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="madrassa_tagline"><?php esc_html_e( 'Tagline', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="madrassa_tagline" name="madrassa_tagline" class="regular-text" value="<?php echo esc_attr( $opts['madrassa_tagline'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="madrassa_about"><?php esc_html_e( 'About Text', 'noor-tms' ); ?></label></th>
							<td>
								<textarea id="madrassa_about" name="madrassa_about" rows="4" class="large-text"><?php echo esc_textarea( $opts['madrassa_about'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Shown on the public homepage hero section.', 'noor-tms' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="cta_apply_label"><?php esc_html_e( 'CTA: Apply Label', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="cta_apply_label" name="cta_apply_label" class="regular-text" value="<?php echo esc_attr( $opts['cta_apply_label'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cta_apply_url"><?php esc_html_e( 'CTA: Apply URL', 'noor-tms' ); ?></label></th>
							<td><input type="url" id="cta_apply_url" name="cta_apply_url" class="regular-text" value="<?php echo esc_attr( $opts['cta_apply_url'] ?? '' ); ?>" placeholder="https://" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cta_classes_label"><?php esc_html_e( 'CTA: Classes Label', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="cta_classes_label" name="cta_classes_label" class="regular-text" value="<?php echo esc_attr( $opts['cta_classes_label'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cta_classes_url"><?php esc_html_e( 'CTA: Classes URL', 'noor-tms' ); ?></label></th>
							<td><input type="url" id="cta_classes_url" name="cta_classes_url" class="regular-text" value="<?php echo esc_attr( $opts['cta_classes_url'] ?? '' ); ?>" placeholder="https://" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cta_login_label"><?php esc_html_e( 'CTA: Login Label', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="cta_login_label" name="cta_login_label" class="regular-text" value="<?php echo esc_attr( $opts['cta_login_label'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cta_support_label"><?php esc_html_e( 'CTA: Support Label', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="cta_support_label" name="cta_support_label" class="regular-text" value="<?php echo esc_attr( $opts['cta_support_label'] ?? '' ); ?>" /></td>
						</tr>

						<tr>
							<th colspan="2" style="padding-top:24px;">
								<h2 style="margin:0;"><?php esc_html_e( 'Support Routing', 'noor-tms' ); ?></h2>
							</th>
						</tr>
						<tr>
							<th scope="row"><label for="support_email"><?php esc_html_e( 'Support Email', 'noor-tms' ); ?></label></th>
							<td>
								<input type="email" id="support_email" name="support_email" class="regular-text" value="<?php echo esc_attr( $opts['support_email'] ?? '' ); ?>" />
								<p class="description"><?php esc_html_e( 'Popup submissions are emailed to this address.', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="support_phone"><?php esc_html_e( 'Support Phone', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="support_phone" name="support_phone" class="regular-text" value="<?php echo esc_attr( $opts['support_phone'] ?? '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="support_whatsapp"><?php esc_html_e( 'Support WhatsApp', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="support_whatsapp" name="support_whatsapp" class="regular-text" value="<?php echo esc_attr( $opts['support_whatsapp'] ?? '' ); ?>" placeholder="+923001234567" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="support_success_message"><?php esc_html_e( 'Popup Success Message', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="support_success_message" name="support_success_message" class="regular-text" value="<?php echo esc_attr( $opts['support_success_message'] ?? '' ); ?>" /></td>
						</tr>

						<tr>
							<th colspan="2" style="padding-top:24px;">
								<h2 style="margin:0;"><?php esc_html_e( 'AI Agent (OpenAI-ready)', 'noor-tms' ); ?></h2>
							</th>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable AI Agent', 'noor-tms' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="openai_enabled" value="1" <?php checked( ! empty( $opts['openai_enabled'] ) ); ?> />
									<?php esc_html_e( 'Store OpenAI configuration for future chat agent rollout.', 'noor-tms' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="openai_model"><?php esc_html_e( 'OpenAI Model', 'noor-tms' ); ?></label></th>
							<td><input type="text" id="openai_model" name="openai_model" class="regular-text" value="<?php echo esc_attr( $opts['openai_model'] ?? '' ); ?>" placeholder="gpt-4o-mini" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'noor-tms' ); ?></label></th>
							<td><input type="password" id="openai_api_key" name="openai_api_key" class="regular-text" value="<?php echo esc_attr( $opts['openai_api_key'] ?? '' ); ?>" autocomplete="new-password" /></td>
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
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		update_option( self::OPTION_NAME, self::sanitize_options_input( $_POST ) );

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
				'madrassa_name'    => 'Noor-TMS Madrassa',
				'madrassa_tagline' => 'A trusted place for Quran and Islamic learning',
				'madrassa_about'   => __( 'Welcome to our madrassa portal. Explore our classes, admissions, and support options.', 'noor-tms' ),
				'cta_apply_label'  => __( 'Apply Admission', 'noor-tms' ),
				'cta_apply_url'    => '',
				'cta_classes_label'=> __( 'View Classes', 'noor-tms' ),
				'cta_classes_url'  => '',
				'cta_login_label'  => __( 'Login Portal', 'noor-tms' ),
				'cta_support_label'=> __( 'Contact Support', 'noor-tms' ),
				'support_email'    => sanitize_email( (string) get_option( 'admin_email' ) ),
				'support_phone'    => '',
				'support_whatsapp' => '',
				'support_success_message' => __( 'Your support request has been sent. We will contact you shortly.', 'noor-tms' ),
				'openai_enabled'   => 0,
				'openai_model'     => 'gpt-4o-mini',
				'openai_api_key'   => '',
			]
		);
	}

	/**
	 * Sanitize incoming settings values.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function sanitize_options_input( array $input ): array {
		$input = wp_unslash( $input );

		$allowed_providers = [ 'click_to_chat', 'mock', 'ultramsg', 'twilio' ];
		$provider          = sanitize_key( $input['gateway_provider'] ?? 'click_to_chat' );

		return [
			'gateway_provider' => in_array( $provider, $allowed_providers, true ) ? $provider : 'click_to_chat',
			'api_instance_id'  => sanitize_text_field( $input['api_instance_id'] ?? '' ),
			'api_token'        => sanitize_text_field( $input['api_token'] ?? '' ),
			'sender_number'    => sanitize_text_field( $input['sender_number'] ?? '' ),
			'message_template' => wp_kses_post( $input['message_template'] ?? self::default_template() ),
			'madrassa_name'    => sanitize_text_field( $input['madrassa_name'] ?? '' ),
			'madrassa_tagline' => sanitize_text_field( $input['madrassa_tagline'] ?? '' ),
			'madrassa_about'   => sanitize_textarea_field( $input['madrassa_about'] ?? '' ),
			'cta_apply_label'  => sanitize_text_field( $input['cta_apply_label'] ?? '' ),
			'cta_apply_url'    => esc_url_raw( $input['cta_apply_url'] ?? '' ),
			'cta_classes_label'=> sanitize_text_field( $input['cta_classes_label'] ?? '' ),
			'cta_classes_url'  => esc_url_raw( $input['cta_classes_url'] ?? '' ),
			'cta_login_label'  => sanitize_text_field( $input['cta_login_label'] ?? '' ),
			'cta_support_label'=> sanitize_text_field( $input['cta_support_label'] ?? '' ),
			'support_email'    => sanitize_email( $input['support_email'] ?? '' ),
			'support_phone'    => sanitize_text_field( $input['support_phone'] ?? '' ),
			'support_whatsapp' => sanitize_text_field( $input['support_whatsapp'] ?? '' ),
			'support_success_message' => sanitize_text_field( $input['support_success_message'] ?? '' ),
			'openai_enabled'   => empty( $input['openai_enabled'] ) ? 0 : 1,
			'openai_model'     => sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' ),
			'openai_api_key'   => sanitize_text_field( $input['openai_api_key'] ?? '' ),
		];
	}

	/**
	 * Default WhatsApp message template.
	 */
	public static function default_template(): string {
		return "Assalam-o-Alaikum,\n\nDear Parent,\n\n{student_name} has received the following result:\n📚 Subject: {subject}\n✏️ Marks: {marks_obtained} / {total_marks}\n📅 Exam Date: {exam_date}\n\nRegards,\nMadrasa Management";
	}
}
