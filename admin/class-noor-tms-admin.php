<?php
/**
 * Admin façade – menu registration, asset enqueueing, and AJAX dispatch.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	private Students   $students;
	private Results    $results;
	private Settings   $settings;
	private Classes    $classes;
	private Teachers   $teachers;
	private Attendance $attendance;
	private Fees       $fees;
	private Support    $support;
	private \Noor_TMS\Admin\Chat $chat;

	public function __construct() {
		$this->students   = new Students();
		$this->results    = new Results();
		$this->settings   = new Settings();
		$this->classes    = new Classes();
		$this->teachers   = new Teachers();
		$this->attendance = new Attendance();
		$this->fees       = new Fees();
		$this->support    = new Support();
		$this->chat       = new \Noor_TMS\Admin\Chat();
	}

	// -----------------------------------------------------------------------
	// Menu & Pages
	// -----------------------------------------------------------------------

	/**
	 * Register top-level menu and sub-menus.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Noor-TMS', 'noor-tms' ),
			__( 'Noor-TMS', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms',
			[ $this->students, 'page_list' ],
			'dashicons-welcome-learn-more',
			30
		);

		add_submenu_page(
			'noor-tms',
			__( 'Students', 'noor-tms' ),
			__( 'Students', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms',
			[ $this->students, 'page_list' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Add Student', 'noor-tms' ),
			__( 'Add Student', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-add-student',
			[ $this->students, 'page_form' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Exam Results', 'noor-tms' ),
			__( 'Exam Results', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-results',
			[ $this->results, 'page_results' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Classes', 'noor-tms' ),
			__( 'Classes', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-classes',
			[ $this->classes, 'page_classes' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Settings', 'noor-tms' ),
			__( 'Settings', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-settings',
			[ $this->settings, 'page_settings' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Teachers', 'noor-tms' ),
			__( 'Teachers', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-teachers',
			[ $this->teachers, 'page_teachers' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Student Attendance', 'noor-tms' ),
			__( 'Attendance', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-attendance',
			[ $this->attendance, 'page_attendance' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Teacher Attendance', 'noor-tms' ),
			__( 'Teacher Attendance', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-teacher-attendance',
			[ $this->teachers, 'page_teacher_attendance' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Fee Management', 'noor-tms' ),
			__( 'Fee Management', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-fees',
			[ $this->fees, 'render_page' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Live Chat', 'noor-tms' ),
			__( 'Live Chat', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-chat',
			[ $this->chat, 'page_chat' ]
		);

		add_submenu_page(
			'noor-tms',
			__( 'Support Inbox', 'noor-tms' ),
			__( 'Support Inbox', 'noor-tms' ),
			'noor_tms_manage',
			'noor-tms-support',
			[ $this->support, 'page_support' ]
		);
	}

	/**
	 * Handle admin POST actions before page output.
	 */
	public function handle_admin_actions(): void {
		$this->chat->maybe_handle_actions();
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	/**
	 * Enqueue CSS and JS on plugin admin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$noor_pages = [
			'toplevel_page_noor-tms',
			'noor-tms_page_noor-tms-add-student',
			'noor-tms_page_noor-tms-results',
			'noor-tms_page_noor-tms-classes',
			'noor-tms_page_noor-tms-settings',
			'noor-tms_page_noor-tms-teachers',
			'noor-tms_page_noor-tms-attendance',
			'noor-tms_page_noor-tms-teacher-attendance',
			'noor-tms_page_noor-tms-fees',
			'noor-tms_page_noor-tms-chat',
			'noor-tms_page_noor-tms-support',
		];

		if ( ! in_array( $hook_suffix, $noor_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'noor-tms-admin',
			NOOR_TMS_PLUGIN_URL . 'admin/css/noor-tms-admin.css',
			[],
			NOOR_TMS_VERSION
		);

		wp_enqueue_script(
			'noor-tms-admin',
			NOOR_TMS_PLUGIN_URL . 'admin/js/noor-tms-admin.js',
			[ 'jquery' ],
			NOOR_TMS_VERSION,
			true
		);

		// Pass data to JS.
		wp_localize_script(
			'noor-tms-admin',
			'noorTMS',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'noor_tms_ajax' ),
				'i18n'    => [
					'saving'        => __( 'Saving…',              'noor-tms' ),
					'save'          => __( 'Save Result',           'noor-tms' ),
					'saveReport'    => __( 'Save All Results',      'noor-tms' ),
					'deleting'      => __( 'Deleting…',             'noor-tms' ),
					'confirmDelete' => __( 'Are you sure?',         'noor-tms' ),
					'error'         => __( 'An error occurred.',    'noor-tms' ),
				],
			]
		);
	}

	// -----------------------------------------------------------------------
	// AJAX handlers (delegated to sub-classes)
	// -----------------------------------------------------------------------

	public function ajax_save_result(): void {
		$this->results->ajax_save_result();
	}

	public function ajax_delete_student(): void {
		$this->students->ajax_delete_student();
	}

	public function ajax_delete_result(): void {
		$this->results->ajax_delete_result();
	}

	public function ajax_delete_class(): void {
		$this->classes->ajax_delete_class();
	}

	public function ajax_get_subjects(): void {
		$this->classes->ajax_get_subjects();
	}

	public function ajax_get_students_for_class(): void {
		$this->classes->ajax_get_students_for_class();
	}

	public function ajax_save_report(): void {
		$this->results->ajax_save_report();
	}

	public function ajax_delete_teacher(): void {
		$this->teachers->ajax_delete_teacher();
	}

	public function ajax_save_student_attendance(): void {
		$this->attendance->ajax_save_student_attendance();
	}

	public function ajax_save_teacher_attendance(): void {
		$this->teachers->ajax_save_teacher_attendance();
	}

	public function ajax_correct_attendance(): void {
		$this->attendance->ajax_correct_attendance();
	}

	// -----------------------------------------------------------------------
	// Admin live-chat AJAX handlers
	// -----------------------------------------------------------------------

	/**
	 * AJAX: Fetch new messages for the active admin chat thread.
	 * Accepts: thread_id, after_id.  Returns: messages[].
	 */
	public function ajax_admin_chat_fetch(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( null, 403 );
		}

		$thread_id = (int) ( $_POST['thread_id'] ?? 0 );
		$after_id  = max( 0, (int) ( $_POST['after_id'] ?? 0 ) );

		if ( $thread_id <= 0 ) {
			wp_send_json_error( null, 400 );
		}

		$rows = \Noor_TMS\Includes\DatabaseHandler::get_chat_messages( $thread_id, $after_id, 50 );

		$messages = array_map(
			static function ( array $row ): array {
				return [
					'id'           => (int) $row['id'],
					'sender_role'  => sanitize_key( (string) ( $row['sender_role'] ?? 'visitor' ) ),
					'message_text' => wp_kses_post( (string) ( $row['message_text'] ?? '' ) ),
					'created_at'   => (string) ( $row['created_at'] ?? '' ),
				];
			},
			$rows
		);

		wp_send_json_success( [ 'messages' => $messages ] );
	}

	/**
	 * AJAX: Send an agent reply from the admin inbox (replaces the form-POST path).
	 * Accepts: thread_id, message.  Returns: message{}.
	 */
	public function ajax_admin_chat_reply(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( null, 403 );
		}

		$thread_id = (int) ( $_POST['thread_id'] ?? 0 );
		$message   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		if ( $thread_id <= 0 || '' === $message ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'noor-tms' ) ], 400 );
		}

		$msg_id = \Noor_TMS\Includes\DatabaseHandler::insert_chat_message(
			$thread_id, 'agent', $message, get_current_user_id()
		);

		if ( ! $msg_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to send reply.', 'noor-tms' ) ], 500 );
		}

		\Noor_TMS\Includes\DatabaseHandler::update_chat_thread_status( $thread_id, 'in_progress' );
		do_action( 'noor_tms_chat_message_sent', $thread_id, 'agent', $message, get_current_user_id() );

		wp_send_json_success( [
			'message' => [
				'id'           => (int) $msg_id,
				'sender_role'  => 'agent',
				'message_text' => $message,
				'created_at'   => current_time( 'mysql' ),
			],
		] );
	}

	/**
	 * AJAX: Count threads that received new messages after a given timestamp.
	 * Used to show the "new conversations" badge in the sidebar.
	 * Accepts: since (MySQL datetime), thread_id (exclude current thread).
	 */
	public function ajax_admin_chat_ping(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( null, 403 );
		}

		$since     = sanitize_text_field( wp_unslash( $_POST['since'] ?? '' ) );
		$thread_id = (int) ( $_POST['thread_id'] ?? 0 );

		$count = \Noor_TMS\Includes\DatabaseHandler::count_threads_updated_since( $since, $thread_id );

		wp_send_json_success( [ 'new_thread_count' => $count ] );
	}

	public function handle_print_student(): void {
		$this->students->handle_print_student();
	}
}
