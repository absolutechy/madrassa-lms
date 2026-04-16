<?php
/**
 * Form processor — handles all admin-post.php form submissions from the
 * front-end TMS portal.
 *
 * Hooks registered (via Loader in Plugin::define_public_hooks()):
 *   admin_post_noor_tms_save_student  → process_student_form
 *   admin_post_noor_tms_save_class    → process_class_form
 *   admin_post_noor_tms_save_settings → process_settings_form
 *   admin_post_noor_tms_save_teacher  → process_teacher_form
 *
 * @package Noor_TMS\PublicFacing
 */

namespace Noor_TMS\PublicFacing;

use Noor_TMS\Includes\DatabaseHandler;
use Noor_TMS\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class FormProcessor
 */
class FormProcessor {

	// -----------------------------------------------------------------------
	// Public entry points (hooked via Loader)
	// -----------------------------------------------------------------------

	/**
	 * Handle student create/update form.
	 * Hook: admin_post_noor_tms_save_student
	 */
	public function process_student_form(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$student_id = (int) ( $_POST['student_id'] ?? 0 );
		$this->handle_student_save( $student_id );
	}

	/**
	 * Handle class create/update form.
	 * Hook: admin_post_noor_tms_save_class
	 */
	public function process_class_form(): void {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'noor_tms_manage' ) && ! current_user_can( 'noor_tms_teacher' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$this->handle_class_save( $class_id );
	}

	/**
	 * Handle settings form.
	 * Hook: admin_post_noor_tms_save_settings
	 */
	public function process_settings_form(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$this->handle_settings_save();
	}

	/**
	 * Handle teacher create/update form.
	 * Hook: admin_post_noor_tms_save_teacher
	 */
	public function process_teacher_form(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		if ( ! check_admin_referer( 'noor_tms_teacher_nonce', 'noor_tms_teacher_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$teacher_id = (int) ( $_POST['teacher_id'] ?? 0 );
		$name       = sanitize_text_field( $_POST['teacher_name'] ?? '' );
		$phone      = sanitize_text_field( $_POST['teacher_phone'] ?? '' );
		$wp_user_id = (int) ( $_POST['wp_user_id'] ?? 0 );

		do_action( 'noor_tms_teacher_handle_wp_user_fields' );

		// Re-fetch wp_user_id after hook (hook may have created a new WP user).
		$wp_user_id = (int) ( $_POST['wp_user_id'] ?? 0 );
		$is_active  = (int) ( $_POST['is_active'] ?? 1 );

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Teacher name cannot be empty.', 'noor-tms' ) );
		}
		if ( ! $wp_user_id || ! get_user_by( 'id', $wp_user_id ) ) {
			wp_die( esc_html__( 'Please select a valid WordPress user.', 'noor-tms' ) );
		}

		if ( $teacher_id > 0 ) {
			$old = DatabaseHandler::get_teacher( $teacher_id );
			if ( $old && (int) $old['wp_user_id'] !== $wp_user_id ) {
				$old_user = get_user_by( 'id', (int) $old['wp_user_id'] );
				if ( $old_user ) {
					$old_user->remove_cap( 'noor_tms_teacher' );
				}
				$new_user = get_user_by( 'id', $wp_user_id );
				if ( $new_user ) {
					$new_user->add_cap( 'noor_tms_teacher' );
				}
			}
			DatabaseHandler::update_teacher( $teacher_id, compact( 'name', 'phone', 'is_active' ) );
			$msg = 'teacher_updated';
		} else {
			$teacher_id = DatabaseHandler::insert_teacher( compact( 'wp_user_id', 'name', 'phone', 'is_active' ) );
			$msg        = 'teacher_added';
		}

		if ( $teacher_id ) {
			$assignments        = [];
			$homeroom_class_ids = (array) ( $_POST['homeroom_class_ids'] ?? [] );
			foreach ( $homeroom_class_ids as $cls_id ) {
				$cls_id = (int) $cls_id;
				if ( $cls_id ) {
					$assignments[] = [ 'class_id' => $cls_id, 'role_type' => 'homeroom', 'subject_id' => null ];
				}
			}

			$subject_assignments = (array) ( $_POST['subject_assignments'] ?? [] );
			foreach ( $subject_assignments as $c_id => $subject_ids ) {
				$c_id = (int) $c_id;
				if ( ! $c_id ) {
					continue;
				}
				foreach ( (array) $subject_ids as $s_id ) {
					$s_id = (int) $s_id;
					if ( $s_id ) {
						$assignments[] = [ 'class_id' => $c_id, 'role_type' => 'subject', 'subject_id' => $s_id ];
					}
				}
			}

			DatabaseHandler::save_teacher_assignments( $teacher_id, $assignments );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'tms-teachers', 'msg' => $msg ], home_url( '/tms-teachers/' ) ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Private save helpers
	// -----------------------------------------------------------------------

	/**
	 * Process student create/update.
	 */
	private function handle_student_save( int $student_id ): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_student_nonce'] ?? '' ) ), 'noor_tms_save_student' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$data = [
			'class_id'        => (int) ( $_POST['class_id']        ?? 0 ),
			'name'            => sanitize_text_field( $_POST['name']            ?? '' ),
			'parent_phone'    => sanitize_text_field( $_POST['parent_phone']    ?? '' ),
			'enrollment_date' => sanitize_text_field( $_POST['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
			'status'          => sanitize_key( $_POST['status']                 ?? 'active' ),
		];

		if ( empty( $data['name'] ) ) {
			wp_die( esc_html__( 'Student name cannot be empty.', 'noor-tms' ) );
		}

		if ( ! empty( $data['parent_phone'] ) && ! preg_match( '/^\+[1-9]\d{7,14}$/', $data['parent_phone'] ) ) {
			wp_die( esc_html__( 'Invalid phone number. Use international format, e.g. +923001234567', 'noor-tms' ) );
		}

		if ( ! empty( $_FILES['student_photo']['name'] ) ) {
			$finfo     = new \finfo( FILEINFO_MIME_TYPE );
			$real_mime = $finfo->file( $_FILES['student_photo']['tmp_name'] );
			if ( ! in_array( $real_mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
				wp_die( esc_html__( 'Invalid file type. Only JPEG, PNG and WebP are allowed.', 'noor-tms' ) );
			}
			if ( $_FILES['student_photo']['size'] > 2 * MB_IN_BYTES ) {
				wp_die( esc_html__( 'Photo must be under 2 MB.', 'noor-tms' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$photo_id = media_handle_upload( 'student_photo', 0 );
			if ( ! is_wp_error( $photo_id ) ) {
				$data['photo_id'] = $photo_id;
			}
		} elseif ( $student_id > 0 && ! empty( $_POST['remove_photo'] ) ) {
			$data['photo_id'] = null;
		}

		if ( $student_id > 0 ) {
			DatabaseHandler::update_student( $student_id, $data );
			$msg = 'updated';
		} else {
			DatabaseHandler::insert_student( $data );
			$msg = 'added';
		}

		wp_safe_redirect( add_query_arg( 'msg', $msg, home_url( '/tms-students/' ) ) );
		exit;
	}

	/**
	 * Process class create/update.
	 */
	private function handle_class_save( int $class_id ): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_class_nonce'] ?? '' ) ), 'noor_tms_save_class' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$name     = sanitize_text_field( $_POST['class_name'] ?? '' );
		$subjects = array_filter(
			array_map( 'sanitize_text_field', (array) ( $_POST['subjects'] ?? [] ) )
		);

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Class name cannot be empty.', 'noor-tms' ) );
		}

		if ( $class_id > 0 ) {
			DatabaseHandler::update_class( $class_id, $name, array_values( $subjects ) );
			$msg = 'class_updated';
		} else {
			$new_class_id = DatabaseHandler::insert_class( $name, array_values( $subjects ) );
			if ( $new_class_id && ! current_user_can( 'noor_tms_manage' ) && current_user_can( 'noor_tms_teacher' ) ) {
				$teacher = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				if ( $teacher ) {
					$existing    = DatabaseHandler::get_teacher_assignments( (int) $teacher['id'] );
					$existing[]  = [ 'class_id' => $new_class_id, 'role_type' => 'homeroom', 'subject_id' => null ];
					DatabaseHandler::save_teacher_assignments( (int) $teacher['id'], $existing );
				}
			}
			$msg = 'class_added';
		}

		wp_safe_redirect( add_query_arg( 'msg', $msg, home_url( '/tms-classes/' ) ) );
		exit;
	}

	/**
	 * Process settings save.
	 */
	private function handle_settings_save(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_settings_nonce'] ?? '' ) ), 'noor_tms_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$allowed_providers = [ 'click_to_chat', 'mock', 'ultramsg', 'twilio' ];
		$provider          = sanitize_key( $_POST['gateway_provider'] ?? 'click_to_chat' );

		update_option( 'noor_tms_options', [
			'gateway_provider' => in_array( $provider, $allowed_providers, true ) ? $provider : 'click_to_chat',
			'api_instance_id'  => sanitize_text_field( $_POST['api_instance_id']  ?? '' ),
			'api_token'        => sanitize_text_field( $_POST['api_token']         ?? '' ),
			'sender_number'    => sanitize_text_field( $_POST['sender_number']     ?? '' ),
			'message_template' => wp_kses_post( $_POST['message_template']         ?? '' ),
		] );

		wp_safe_redirect( add_query_arg( 'msg', 'saved', home_url( '/tms-settings/' ) ) );
		exit;
	}
}
