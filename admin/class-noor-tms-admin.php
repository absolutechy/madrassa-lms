<?php
/**
 * Admin façade – menu registration, asset enqueueing, and AJAX dispatch.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

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

	public function __construct() {
		$this->students   = new Students();
		$this->results    = new Results();
		$this->settings   = new Settings();
		$this->classes    = new Classes();
		$this->teachers   = new Teachers();
		$this->attendance = new Attendance();
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
}
