<?php
/**
 * Shortcode handler — renders all front-end TMS portal pages.
 *
 * Each public method corresponds to one shortcode registered by PublicController.
 * Templates are procedural PHP views included via output buffering.
 *
 * @package Noor_TMS\PublicFacing
 */

namespace Noor_TMS\PublicFacing;

use Noor_TMS\Includes\DatabaseHandler;
use Noor_TMS\Includes\Repositories\FeeRepository;
use Noor_TMS\Admin\Settings;
use Noor_TMS\Admin\WhatsApp;

defined( 'ABSPATH' ) || exit;

/**
 * Class ShortcodeHandler
 */
class ShortcodeHandler {

	// -----------------------------------------------------------------------
	// [noor_tms_login]
	// -----------------------------------------------------------------------

	/**
	 * Render the login form shortcode.
	 * Already-logged-in TMS users are redirected to their dashboard.
	 */
	public function sc_login(): string {
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'noor_tms_manage' ) ) {
				wp_safe_redirect( home_url( '/tms-students/' ) );
				exit;
			}
			if ( current_user_can( 'noor_tms_teacher' ) ) {
				wp_safe_redirect( home_url( '/tms-attendance/' ) );
				exit;
			}
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/login.php';
		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_students]
	// -----------------------------------------------------------------------

	/**
	 * Student list + add/edit form.
	 * Managers see all; teachers see students in their assigned classes only.
	 */
	public function sc_students(): string {
		$action     = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$is_manager = current_user_can( 'noor_tms_manage' );

		ob_start();

		if ( 'add' === $action || ( 'edit' === $action && $student_id ) ) {
			if ( ! $is_manager ) {
				wp_safe_redirect( home_url( '/tms-students/' ) );
				exit;
			}
			$student = $student_id ? DatabaseHandler::get_student( $student_id ) : null;
			$classes = DatabaseHandler::get_classes_dropdown();
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/student-form.php';
		} else {
			$search   = sanitize_text_field( $_GET['s']             ?? '' );
			$status   = sanitize_key( $_GET['status_filter']        ?? '' );
			$class_id = (int) ( $_GET['class_id']                   ?? 0 );
			$paged    = max( 1, (int) ( $_GET['paged']              ?? 1 ) );

			if ( ! $is_manager ) {
				$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
				if ( $class_id && ! in_array( $class_id, $class_ids, true ) ) {
					$class_id = 0;
				}
				if ( ! $class_id && ! empty( $class_ids ) ) {
					$class_id = $class_ids[0];
				}
			}

			$result      = DatabaseHandler::get_students( [
				'per_page' => 20,
				'page'     => $paged,
				'search'   => $search,
				'status'   => $status,
				'class_id' => $class_id,
			] );
			$students    = $result['rows'];
			$total       = $result['total'];
			$total_pages = (int) ceil( $total / 20 );
			$classes     = $is_manager
				? DatabaseHandler::get_classes_dropdown()
				: ( isset( $class_ids )
					? array_values( array_filter( DatabaseHandler::get_classes_dropdown(), fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) )
					: [] );
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/students.php';
		}

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_classes]
	// -----------------------------------------------------------------------

	/**
	 * Classes grid + create/edit form.
	 * Teachers see only their assigned classes (limited access).
	 */
	public function sc_classes(): string {
		$action   = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );

		ob_start();

		if ( ( $is_manager || $is_teacher ) && ( 'new' === $action || ( 'edit' === $action && $class_id ) ) ) {
			$cls      = $class_id ? DatabaseHandler::get_class( $class_id ) : null;
			$subjects = $class_id ? DatabaseHandler::get_subjects_by_class( $class_id ) : [];
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/class-form.php';
		} else {
			if ( ! $is_manager ) {
				$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
				$all       = DatabaseHandler::get_classes();
				$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
			} else {
				$classes = DatabaseHandler::get_classes();
			}
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/classes.php';
		}

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_results]
	// -----------------------------------------------------------------------

	/**
	 * Results overview + class drill-down.
	 */
	public function sc_results(): string {
		$class_id   = (int) ( $_GET['class_id']   ?? 0 );
		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$exam_date  = sanitize_text_field( $_GET['exam_date']    ?? '' );
		$action     = sanitize_key( $_GET['tms_action']          ?? 'list' );

		$is_manager        = current_user_can( 'noor_tms_manage' );
		$is_teacher        = current_user_can( 'noor_tms_teacher' );
		$allowed_class_ids = [];

		if ( $is_teacher && ! $is_manager ) {
			$teacher           = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$allowed_class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
		}

		if ( $is_teacher && ! $is_manager && $class_id && ! in_array( $class_id, $allowed_class_ids, true ) ) {
			wp_safe_redirect( home_url( '/tms-results/' ) );
			exit;
		}

		ob_start();

		if ( $class_id ) {
			$class      = DatabaseHandler::get_class( $class_id );
			$subjects   = DatabaseHandler::get_subjects_by_class( $class_id );
			$students   = DatabaseHandler::get_students_dropdown( $class_id );
			$exam_dates = DatabaseHandler::get_exam_dates_by_class( $class_id );
			$summary    = $exam_date ? DatabaseHandler::get_results_summary_by_class( $class_id, $exam_date ) : [];
			$opts       = Settings::get_options();
			$is_ctc     = ( $opts['gateway_provider'] ?? 'click_to_chat' ) === 'click_to_chat';
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/results-class.php';
		} else {
			$classes = $is_manager
				? DatabaseHandler::get_classes()
				: array_values( array_filter( DatabaseHandler::get_classes(), fn( $c ) => in_array( (int) $c['id'], $allowed_class_ids, true ) ) );
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/results.php';
		}

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_attendance]
	// -----------------------------------------------------------------------

	/**
	 * Student attendance mark + history.
	 */
	public function sc_attendance(): string {
		$is_manager = current_user_can( 'noor_tms_manage' );

		if ( $is_manager ) {
			$classes = DatabaseHandler::get_classes_dropdown();
		} else {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			$all       = DatabaseHandler::get_classes_dropdown();
			$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
		}

		$tab      = sanitize_key( $_GET['tab']       ?? 'mark' );
		$class_id = (int) ( $_GET['class_id']        ?? ( $classes[0]['id'] ?? 0 ) );
		$att_date = sanitize_text_field( $_GET['att_date'] ?? current_time( 'Y-m-d' ) );
		$month    = (int) ( $_GET['att_month']       ?? (int) current_time( 'n' ) );
		$year     = (int) ( $_GET['att_year']        ?? (int) current_time( 'Y' ) );

		$students = [];
		$marked   = [];
		$summary  = [];

		if ( 'mark' === $tab && $class_id ) {
			$students = DatabaseHandler::get_students_by_class( $class_id );
			$marked   = DatabaseHandler::get_student_attendance_for_date( $class_id, $att_date );
		} elseif ( 'history' === $tab ) {
			$summary = DatabaseHandler::get_student_attendance_summary( $month, $year, $class_id ?: null );
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/attendance.php';
		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_fees]
	// -----------------------------------------------------------------------

	/**
	 * Fee management portal (managers only).
	 * Shows tabs: Invoices | Payment Entry | Defaulters | Collection Report.
	 */
	public function sc_fees(): string {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}

		// Refresh late fines on every load.
		FeeRepository::apply_late_fines();

		$tab      = sanitize_key( $_GET['tab'] ?? 'invoices' );
		$classes  = DatabaseHandler::get_classes_dropdown();
		$cur_month = current_time( 'Y-m' );

		// Data per tab.
		switch ( $tab ) {
			case 'defaulters':
				$def_month  = sanitize_text_field( $_GET['def_month'] ?? $cur_month );
				$defaulters = FeeRepository::get_defaulters( $def_month );
				break;

			case 'report':
				$group_by     = sanitize_key( $_GET['group_by'] ?? 'month' );
				$report_month = sanitize_text_field( $_GET['report_month'] ?? '' );
				$report_class = (int) ( $_GET['class_id'] ?? 0 );
				$report_rows  = FeeRepository::get_collection_summary( [
					'group_by'      => $group_by,
					'invoice_month' => $report_month,
					'class_id'      => $report_class,
				] );
				break;

			case 'payment':
				// All data loaded via AJAX on this tab — nothing to pre-fetch.
				break;

			default: // invoices
				$tab            = 'invoices';
				$inv_month      = sanitize_text_field( $_GET['invoice_month'] ?? $cur_month );
				$inv_class      = (int) ( $_GET['class_id'] ?? 0 );
				$inv_status     = sanitize_key( $_GET['status'] ?? '' );
				$inv_paged      = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
				$inv_result     = FeeRepository::get_invoices( [
					'per_page'      => 25,
					'page'          => $inv_paged,
					'invoice_month' => $inv_month,
					'class_id'      => $inv_class,
					'status'        => $inv_status,
				] );
				$invoices       = $inv_result['rows'];
				$inv_total      = $inv_result['total'];
				$inv_total_pages = (int) ceil( $inv_total / 25 );
				break;
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/fees.php';
		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_settings]
	// -----------------------------------------------------------------------

	/**
	 * Gateway / WhatsApp settings.
	 */
	public function sc_settings(): string {
		$opts = Settings::get_options();
		$msg  = sanitize_key( $_GET['msg'] ?? '' );

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/settings.php';
		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// [noor_tms_teachers]
	// -----------------------------------------------------------------------

	/**
	 * Teacher list + create/edit form (managers only).
	 */
	public function sc_teachers(): string {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}

		$action     = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$teacher_id = (int) ( $_GET['teacher_id'] ?? 0 );
		$classes    = DatabaseHandler::get_classes_dropdown();
		$teachers   = DatabaseHandler::get_teachers();
		$teacher    = $teacher_id ? DatabaseHandler::get_teacher( $teacher_id ) : null;
		$assignments = $teacher_id ? DatabaseHandler::get_teacher_assignments( $teacher_id ) : [];

		$existing_homerooms = [];
		$existing_subjects  = [];
		foreach ( $assignments as $a ) {
			if ( 'homeroom' === $a['role_type'] ) {
				$existing_homerooms[] = (int) $a['class_id'];
			} else {
				$existing_subjects[] = $a;
			}
		}

		$subjects_by_class = [];
		foreach ( $classes as $cls ) {
			$subjects_by_class[ (int) $cls['id'] ] = DatabaseHandler::get_subjects_by_class( (int) $cls['id'] );
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/teachers.php';
		return ob_get_clean();
	}
}
