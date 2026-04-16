<?php
/**
 * Front-end settings template.
 *
 * Variables in scope:
 *   $opts  array   Current settings.
 *   $msg   string  'saved' after successful save.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title = __( 'Settings', 'noor-tms' );
$active_nav = 'settings';

include __DIR__ . '/layout.php';

if ( 'saved' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Settings saved successfully.', 'noor-tms' ) . '</div>';
}
?>

<div class="noor-card">
	<h2><?php esc_html_e( 'WhatsApp Gateway', 'noor-tms' ); ?></h2>
	<p style="margin:0 0 24px;color:var(--tms-muted);font-size:14px;">
		<?php esc_html_e( 'Configure how WhatsApp notifications are sent to parents.', 'noor-tms' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'noor_tms_save_settings', 'noor_tms_settings_nonce' ); ?>
		<input type="hidden" name="action" value="noor_tms_save_settings" />

		<div class="noor-form-group" style="max-width:420px;">
			<label for="gateway_provider"><?php esc_html_e( 'Gateway Provider', 'noor-tms' ); ?></label>
			<select name="gateway_provider" id="gateway_provider">
				<?php
				$providers = [
					'click_to_chat' => __( 'WhatsApp Web (Click-to-Chat) — Free', 'noor-tms' ),
					'mock'          => __( 'Mock / Test Mode',                     'noor-tms' ),
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
			<p id="noor-ctc-hint" class="noor-form-description" style="color:#25a05c;font-weight:600;">
				&#10003; <?php esc_html_e( 'No API keys needed. Opens WhatsApp Web with message pre-filled — just click Send.', 'noor-tms' ); ?>
			</p>
		</div>

		<div class="noor-api-row noor-form-group" style="max-width:420px;">
			<label for="api_instance_id"><?php esc_html_e( 'Instance ID / Account SID', 'noor-tms' ); ?></label>
			<input type="text" id="api_instance_id" name="api_instance_id"
				   value="<?php echo esc_attr( $opts['api_instance_id'] ?? '' ); ?>" />
			<p class="noor-form-description"><?php esc_html_e( 'Ultramsg: Instance ID — Twilio: Account SID', 'noor-tms' ); ?></p>
		</div>

		<div class="noor-api-row noor-form-group" style="max-width:420px;">
			<label for="api_token"><?php esc_html_e( 'API Token / Auth Token', 'noor-tms' ); ?></label>
			<input type="password" id="api_token" name="api_token"
				   value="<?php echo esc_attr( $opts['api_token'] ?? '' ); ?>"
				   autocomplete="new-password" />
			<p class="noor-form-description"><?php esc_html_e( 'Ultramsg: API Token — Twilio: Auth Token', 'noor-tms' ); ?></p>
		</div>

		<div class="noor-api-row noor-form-group" style="max-width:420px;">
			<label for="sender_number"><?php esc_html_e( 'Sender / From Number', 'noor-tms' ); ?></label>
			<input type="text" id="sender_number" name="sender_number"
				   value="<?php echo esc_attr( $opts['sender_number'] ?? '' ); ?>"
				   placeholder="+923001234567" />
			<p class="noor-form-description"><?php esc_html_e( 'International format, e.g. +923001234567', 'noor-tms' ); ?></p>
		</div>

		<div class="noor-form-group" style="max-width:560px;">
			<label for="message_template"><?php esc_html_e( 'Message Template', 'noor-tms' ); ?></label>
			<textarea id="message_template" name="message_template" rows="5"><?php
				echo esc_textarea( $opts['message_template'] ?? '' );
			?></textarea>
			<p class="noor-form-description">
				<?php esc_html_e( 'Placeholders: {student_name}, {subject}, {marks_obtained}, {total_marks}, {exam_date}', 'noor-tms' ); ?>
			</p>
		</div>

		<div class="noor-form-actions">
			<button type="submit" class="noor-btn noor-btn--primary">
				<?php esc_html_e( 'Save Settings', 'noor-tms' ); ?>
			</button>
		</div>
	</form>
</div>

<?php include __DIR__ . '/layout-close.php'; ?>
