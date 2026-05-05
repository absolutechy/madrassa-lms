<?php
/**
 * WhatsApp notification gateway.
 *
 * Acts as the integration hook between Noor-TMS and an external
 * WhatsApp API provider (Ultramsg, Twilio, or a mock).
 *
 * To swap in a real provider, set the "Gateway Provider" on the
 * Noor-TMS → Settings page and fill in your credentials.
 * No code changes are required.
 *
 * The public entry point is:
 *   WhatsApp::send_notification( $student, $result_data )
 *
 * You can also fire the action hook directly:
 *   do_action( 'noor_tms_send_whatsapp', $phone, $message )
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class WhatsApp
 */
class WhatsApp {

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Build the message from the template and dispatch the notification.
	 *
	 * @param array<string, mixed> $student     Student row from DB.
	 * @param array<string, mixed> $result_data {
	 *     @type string $subject
	 *     @type float  $marks_obtained
	 *     @type float  $total_marks
	 *     @type string $exam_date
	 * }
	 * @return bool  True on success (or mock mode), false on failure.
	 */
	public static function send_notification( array $student, array $result_data ): bool {
		$phone   = sanitize_text_field( $student['parent_phone'] ?? '' );
		$message = self::build_message( $student, $result_data );

		if ( empty( $phone ) || empty( $message ) ) {
			return false;
		}

		/**
		 * Action: noor_tms_send_whatsapp
		 *
		 * Fires before the notification is dispatched.
		 * Third-party plugins can hook here to log, modify, or override.
		 *
		 * @param string $phone   Recipient phone in international format.
		 * @param string $message The message body.
		 */
		do_action( 'noor_tms_send_whatsapp', $phone, $message );

		return self::dispatch( $phone, $message );
	}

	// -----------------------------------------------------------------------
	// Internal dispatch
	// -----------------------------------------------------------------------

	/**
	 * Route to the correct provider.
	 *
	 * @param string $phone
	 * @param string $message
	 * @return bool
	 */
	private static function dispatch( string $phone, string $message ): bool {
		$opts     = Settings::get_options();
		$provider = $opts['gateway_provider'] ?? 'mock';

		return match ( $provider ) {
			'click_to_chat' => self::send_via_click_to_chat( $phone, $message ),
			'ultramsg'      => self::send_via_ultramsg( $phone, $message, $opts ),
			'twilio'        => self::send_via_twilio( $phone, $message, $opts ),
			default         => self::send_mock( $phone, $message ),
		};
	}

	// -----------------------------------------------------------------------
	// Click-to-Chat  (wa.me URL – 100% free, no API required)
	// -----------------------------------------------------------------------

	/**
	 * Build a wa.me Click-to-Chat URL for a given phone number and message.
	 *
	 * The URL is opened in a new browser tab by the admin; WhatsApp Web
	 * pre-fills the recipient and message body. The admin presses Send.
	 *
	 * Phone format: strip leading '+' – wa.me expects digits only.
	 *   +92 300 1234567  →  https://wa.me/923001234567?text=...
	 *
	 * @param array<string, mixed> $student
	 * @param array<string, mixed> $result_data
	 * @return string  Full wa.me URL ready for a hyperlink / window.open().
	 */
	public static function generate_click_to_chat_url( array $student, array $result_data ): string {
		$phone   = preg_replace( '/[^0-9]/', '', $student['parent_phone'] ?? '' );
		$message = self::build_message( $student, $result_data );

		if ( empty( $phone ) ) {
			return '';
		}

		return 'https://wa.me/' . $phone . '/?text=' . rawurlencode( $message );
	}

	/**
	 * Click-to-Chat provider stub.
	 *
	 * Background sending is not applicable for this provider – the URL must
	 * be opened by the admin. Returns false so the caller knows no automatic
	 * send happened. The URL is returned separately via generate_click_to_chat_url().
	 *
	 * @param string $phone
	 * @param string $message
	 * @return bool
	 */
	private static function send_via_click_to_chat( string $phone, string $message ): bool {
		return false; // Cannot send in background – admin must click the link.
	}

	// -----------------------------------------------------------------------
	// Mock provider  (logs to error_log, always returns true)
	// -----------------------------------------------------------------------

	/**
	 * Mock implementation – safe to use in development / staging.
	 *
	 * Replace this method body with your custom SDK call if needed.
	 *
	 * @param string $phone
	 * @param string $message
	 * @return bool
	 */
	private static function send_mock( string $phone, string $message ): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[Noor-TMS | Mock WhatsApp] TO: %s | MSG: %s',
				$phone,
				$message
			) );
		}
		// Store last mock call as transient so admin can verify in-browser.
		set_transient(
			'noor_tms_last_mock_wa',
			[ 'phone' => $phone, 'message' => $message, 'time' => current_time( 'mysql' ) ],
			HOUR_IN_SECONDS
		);
		return true;
	}

	// -----------------------------------------------------------------------
	// Ultramsg provider
	// https://ultramsg.com/api/send/message
	// -----------------------------------------------------------------------

	/**
	 * Send via Ultramsg REST API.
	 *
	 * @param string               $phone
	 * @param string               $message
	 * @param array<string, mixed> $opts  Plugin options.
	 * @return bool
	 */
	private static function send_via_ultramsg(
		string $phone,
		string $message,
		array  $opts
	): bool {
		$instance_id = sanitize_text_field( $opts['api_instance_id'] ?? '' );
		$token       = sanitize_text_field( $opts['api_token']        ?? '' );

		if ( empty( $instance_id ) || empty( $token ) ) {
			return false;
		}

		$url  = "https://api.ultramsg.com/{$instance_id}/messages/chat";
		$body = [
			'token'  => $token,
			'to'     => $phone,
			'body'   => $message,
		];

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Noor-TMS] Ultramsg error: ' . $response->get_error_message() );
			return false;
		}

		$body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body_decoded['sent'] ) && 'true' === (string) $body_decoded['sent'];
	}

	// -----------------------------------------------------------------------
	// Twilio provider
	// https://www.twilio.com/docs/whatsapp/api
	// -----------------------------------------------------------------------

	/**
	 * Send via Twilio WhatsApp API.
	 *
	 * @param string               $phone
	 * @param string               $message
	 * @param array<string, mixed> $opts  Plugin options.
	 * @return bool
	 */
	private static function send_via_twilio(
		string $phone,
		string $message,
		array  $opts
	): bool {
		$account_sid = sanitize_text_field( $opts['api_instance_id'] ?? '' );
		$auth_token  = sanitize_text_field( $opts['api_token']        ?? '' );
		$from_number = sanitize_text_field( $opts['sender_number']    ?? '' );

		if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from_number ) ) {
			return false;
		}

		$url  = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
		$body = [
			'From' => 'whatsapp:' . $from_number,
			'To'   => 'whatsapp:' . $phone,
			'Body' => $message,
		];

		$response = wp_remote_post( $url, [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Noor-TMS] Twilio error: ' . $response->get_error_message() );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		return in_array( $http_code, [ 200, 201 ], true );
	}

	// -----------------------------------------------------------------------
	// Full report (multiple subjects) – URL and message builder
	// -----------------------------------------------------------------------

	/**
	 * Build a wa.me Click-to-Chat URL containing a full per-subject report.
	 *
	 * @param array<string, mixed>                                     $student
	 * @param array<int, array{subject:string,obtained:float,total:float,pct:float}> $subjects
	 * @param string                                                   $exam_date
	 * @return string
	 */
	public static function generate_report_url( array $student, array $subjects, string $exam_date ): string {
		$phone   = preg_replace( '/[^0-9]/', '', $student['parent_phone'] ?? '' );
		$message = self::build_report_message( $student, $subjects, $exam_date );

		if ( empty( $phone ) ) {
			return '';
		}

		return 'https://wa.me/' . $phone . '/?text=' . rawurlencode( $message );
	}

	/**
	 * Build a formatted multi-subject report card message.
	 *
	 * @param array<string, mixed>                                     $student
	 * @param array<int, array{subject:string,obtained:float,total:float,pct:float}> $subjects
	 * @param string                                                   $exam_date
	 * @return string
	 */
	private static function build_report_message( array $student, array $subjects, string $exam_date ): string {
		$name      = $student['name'] ?? '';
		$sum_obt   = 0.0;
		$sum_total = 0.0;
		$lines     = [];

		foreach ( $subjects as $s ) {
			$pct     = $s['total'] > 0 ? round( ( $s['obtained'] / $s['total'] ) * 100, 1 ) : 0;
			$lines[] = '* ' . $s['subject'] . ': ' . $s['obtained'] . '/' . $s['total'] . ' (' . $pct . '%)';
			$sum_obt   += (float) $s['obtained'];
			$sum_total += (float) $s['total'];
		}

		$overall_pct    = $sum_total > 0 ? round( ( $sum_obt / $sum_total ) * 100, 1 ) : 0;
		$overall_result = $overall_pct >= 50 ? 'Pass' : 'Fail';

		$msg  = "Assalam-o-Alikum dear parents,\n\n";
		$msg .= "Please find the exam report for *{$name}* below:\n\n";
		$msg .= "Exam Date: {$exam_date}\n\n";
		$msg .= "--- Subject Marks ---\n";
		$msg .= implode( "\n", $lines );
		$msg .= "\n\n";
		$msg .= "Total: {$sum_obt}/{$sum_total} ({$overall_pct}%)\n";
		$msg .= "Result: {$overall_result}\n\n";
		$msg .= "JazakAllah Khair.";

		return $msg;
	}

	// -----------------------------------------------------------------------
	// Message builder
	// -----------------------------------------------------------------------

	/**
	 * Interpolate the settings message template with actual values.
	 *
	 * @param array<string, mixed> $student
	 * @param array<string, mixed> $result
	 * @return string
	 */
	private static function build_message( array $student, array $result ): string {
		$opts     = Settings::get_options();
		$template = $opts['message_template'] ?? Settings::default_template();

		return str_replace(
			[
				'{student_name}',
				'{subject}',
				'{marks_obtained}',
				'{total_marks}',
				'{exam_date}',
			],
			[
				$student['name']              ?? '',
				$result['subject']            ?? '',
				$result['marks_obtained']     ?? '',
				$result['total_marks']        ?? '',
				$result['exam_date']          ?? '',
			],
			$template
		);
	}
}
