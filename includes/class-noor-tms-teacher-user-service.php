<?php
/**
 * Service for WordPress user creation linked to a teacher record.
 *
 * Centralises the "create new WP user when saving a teacher" logic so it is
 * shared by both the admin Teachers class and the front-end FormProcessor
 * without registering duplicate action-hook subscribers.
 *
 * Usage: call TeacherUserService::handle_wp_user_fields() wherever
 * noor_tms_teacher_handle_wp_user_fields is fired, or subscribe this class
 * to that action hook exactly once.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class TeacherUserService
 */
class TeacherUserService {

	/**
	 * Optionally create a new WordPress user from submitted form fields and
	 * write the resulting user ID back into $_POST['wp_user_id'] so the
	 * calling save handler picks it up.
	 *
	 * Silently bails when:
	 *  - wp_user_id is already set in $_POST (existing user selected), or
	 *  - neither new_wp_user_login nor new_wp_user_email is provided.
	 *
	 * Terminates with wp_die() on validation failure so the caller does not
	 * need to check the return value.
	 */
	public static function handle_wp_user_fields(): void {
		// Existing WP user already chosen — nothing to do.
		if ( ! empty( $_POST['wp_user_id'] ) ) {
			return;
		}

		$login = trim( sanitize_text_field( $_POST['new_wp_user_login'] ?? '' ) );
		$email = trim( sanitize_email( $_POST['new_wp_user_email'] ?? '' ) );
		$pass  = sanitize_text_field( $_POST['new_wp_user_pass'] ?? '' );

		// No new user fields provided — skip silently.
		if ( empty( $login ) && empty( $email ) ) {
			return;
		}

		if ( empty( $login ) ) {
			wp_die( esc_html__( 'Please provide a username to create a new WP user.', 'noor-tms' ) );
		}
		if ( ! validate_username( $login ) ) {
			wp_die( esc_html__( 'Invalid username.', 'noor-tms' ) );
		}
		if ( username_exists( $login ) ) {
			wp_die( esc_html__( 'Username already exists.', 'noor-tms' ) );
		}
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_die( esc_html__( 'Please provide a valid email address.', 'noor-tms' ) );
		}
		if ( email_exists( $email ) ) {
			wp_die( esc_html__( 'Email address already in use.', 'noor-tms' ) );
		}

		if ( empty( $pass ) ) {
			$pass = wp_generate_password( 12 );
		}

		$user_id = wp_create_user( $login, $pass, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html__( 'Could not create WP user: ', 'noor-tms' ) . $user_id->get_error_message() );
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );
		$user->add_cap( 'noor_tms_teacher' );

		// Write new user ID back so the calling save handler picks it up.
		$_POST['wp_user_id'] = $user_id;
	}
}
