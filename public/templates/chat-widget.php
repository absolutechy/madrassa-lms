<?php
/**
 * Floating chat widget markup.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$user_name  = '';
$user_email = '';

if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	$user_name    = (string) ( $current_user->display_name ?? '' );
	$user_email   = (string) ( $current_user->user_email ?? '' );
}

$widget_title = __( 'Support Chat', 'noor-tms' );
$widget_lead  = __( 'Chat with our team. We usually reply shortly.', 'noor-tms' );
?>
<div class="noor-chat-widget" id="noor-chat-widget" data-noor-chat-widget>
	<button type="button" class="noor-chat-toggle" data-noor-chat-toggle aria-expanded="false" aria-controls="noor-chat-panel">
		<span class="noor-chat-toggle__dot" aria-hidden="true"></span>
		<span class="noor-chat-toggle__label"><?php esc_html_e( 'Live Chat', 'noor-tms' ); ?></span>
	</button>

	<div class="noor-chat-panel" id="noor-chat-panel" hidden>
		<div class="noor-chat-panel__head">
			<div>
				<h3><?php echo esc_html( $widget_title ); ?></h3>
				<p><?php echo esc_html( $widget_lead ); ?></p>
			</div>
			<button type="button" class="noor-chat-panel__close" data-noor-chat-close aria-label="<?php esc_attr_e( 'Close chat', 'noor-tms' ); ?>">&times;</button>
		</div>

		<div class="noor-chat-feedback" id="noor-chat-feedback" role="status" aria-live="polite"></div>

		<div class="noor-chat-identity" data-noor-chat-identity>
			<p><?php esc_html_e( 'Please share your details to start the conversation.', 'noor-tms' ); ?></p>
			<form id="noor-chat-bootstrap-form" class="noor-chat-identity__form">
				<input type="text" name="chat_name" id="noor-chat-name" placeholder="<?php esc_attr_e( 'Your name', 'noor-tms' ); ?>" value="<?php echo esc_attr( $user_name ); ?>" required />
				<input type="email" name="chat_email" id="noor-chat-email" placeholder="<?php esc_attr_e( 'Email address', 'noor-tms' ); ?>" value="<?php echo esc_attr( $user_email ); ?>" />
				<input type="text" name="chat_phone" id="noor-chat-phone" placeholder="<?php esc_attr_e( 'Phone number', 'noor-tms' ); ?>" />
				<input type="hidden" name="chat_source_url" id="noor-chat-source-url" value="" />
				<button type="submit" class="noor-btn noor-btn--primary" id="noor-chat-bootstrap-btn"><?php esc_html_e( 'Start Chat', 'noor-tms' ); ?></button>
			</form>
		</div>

		<div class="noor-chat-thread" data-noor-chat-thread hidden>
			<div class="noor-chat-messages" id="noor-chat-messages" aria-live="polite"></div>
			<form id="noor-chat-send-form" class="noor-chat-send-form">
				<textarea id="noor-chat-message" name="message" rows="2" placeholder="<?php esc_attr_e( 'Write your message...', 'noor-tms' ); ?>" required></textarea>
				<button type="submit" class="noor-btn noor-btn--success" id="noor-chat-send-btn"><?php esc_html_e( 'Send', 'noor-tms' ); ?></button>
			</form>
		</div>
	</div>
</div>
