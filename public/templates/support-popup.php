<?php
/**
 * Public support popup modal.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$support_title = (string) ( $opts['cta_support_label'] ?? __( 'Contact Support', 'noor-tms' ) );
if ( '' === $support_title ) {
	$support_title = __( 'Contact Support', 'noor-tms' );
}
?>
<div class="noor-support-modal" id="noor-support-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="noor-support-title">
	<div class="noor-support-modal__dialog">
		<button type="button" class="noor-support-modal__close" data-noor-support-close="1" aria-label="<?php esc_attr_e( 'Close support form', 'noor-tms' ); ?>">&times;</button>
		<h3 id="noor-support-title"><?php echo esc_html( $support_title ); ?></h3>
		<p class="noor-support-modal__lead"><?php esc_html_e( 'Share your question and our team will respond shortly.', 'noor-tms' ); ?></p>

		<form id="noor-support-form" class="noor-support-form">
			<div class="noor-form-row">
				<div class="noor-form-group">
					<label for="noor-support-name"><?php esc_html_e( 'Your Name', 'noor-tms' ); ?> <span class="required">*</span></label>
					<input id="noor-support-name" type="text" name="support_name" required />
				</div>
				<div class="noor-form-group">
					<label for="noor-support-email"><?php esc_html_e( 'Email', 'noor-tms' ); ?></label>
					<input id="noor-support-email" type="email" name="support_email" />
				</div>
			</div>

			<div class="noor-form-row">
				<div class="noor-form-group">
					<label for="noor-support-phone"><?php esc_html_e( 'Phone', 'noor-tms' ); ?></label>
					<input id="noor-support-phone" type="text" name="support_phone" />
				</div>
				<div class="noor-form-group">
					<label for="noor-support-subject"><?php esc_html_e( 'Subject', 'noor-tms' ); ?></label>
					<input id="noor-support-subject" type="text" name="support_subject" />
				</div>
			</div>

			<div class="noor-form-group">
				<label for="noor-support-message"><?php esc_html_e( 'Message', 'noor-tms' ); ?> <span class="required">*</span></label>
				<textarea id="noor-support-message" name="support_message" rows="5" required></textarea>
			</div>

			<input type="hidden" name="support_source_url" id="noor-support-source-url" value="" />
			<div id="noor-support-feedback" class="noor-ajax-feedback" aria-live="polite"></div>

			<div class="noor-form-actions">
				<button type="submit" class="noor-btn noor-btn--primary" id="noor-support-submit-btn"><?php esc_html_e( 'Send Request', 'noor-tms' ); ?></button>
				<button type="button" class="noor-btn noor-btn--secondary" data-noor-support-close="1"><?php esc_html_e( 'Cancel', 'noor-tms' ); ?></button>
			</div>
		</form>
	</div>
</div>
