<?php
/**
 * Core plugin orchestrator.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Wires together the loader, admin, and public-facing functionality.
 */
final class Plugin {

	/** @var Plugin|null Singleton instance. */
	private static ?Plugin $instance = null;

	/** @var Loader Hook registration queue. */
	private Loader $loader;

	/** Private constructor – use get_instance(). */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Returns the single shared instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Boot helpers
	// -----------------------------------------------------------------------

	/**
	 * Instantiate the supporting classes.
	 */
	private function load_dependencies(): void {
		$this->loader = new Loader();
	}

	/**
	 * Register text-domain loader.
	 */
	private function set_locale(): void {
		$this->loader->add_action(
			'plugins_loaded',
			$this,
			'load_plugin_textdomain'
		);
	}

	/**
	 * Wire up all admin-side hooks.
	 */
	private function define_admin_hooks(): void {
		$admin = new \Noor_TMS\Admin\Admin();

		$this->loader->add_action( 'admin_menu',            $admin, 'register_menus' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

		// AJAX handlers (logged-in users only).
		$this->loader->add_action( 'wp_ajax_noor_tms_save_result',            $admin, 'ajax_save_result' );
		$this->loader->add_action( 'wp_ajax_noor_tms_delete_student',         $admin, 'ajax_delete_student' );
		$this->loader->add_action( 'wp_ajax_noor_tms_delete_result',          $admin, 'ajax_delete_result' );
		$this->loader->add_action( 'wp_ajax_noor_tms_delete_class',           $admin, 'ajax_delete_class' );
		$this->loader->add_action( 'wp_ajax_noor_tms_get_subjects',           $admin, 'ajax_get_subjects' );
		$this->loader->add_action( 'wp_ajax_noor_tms_get_students_for_class', $admin, 'ajax_get_students_for_class' );
		$this->loader->add_action( 'wp_ajax_noor_tms_save_report',            $admin, 'ajax_save_report' );
		$this->loader->add_action( 'wp_ajax_noor_tms_delete_teacher',            $admin, 'ajax_delete_teacher' );
		$this->loader->add_action( 'wp_ajax_noor_tms_save_student_attendance',   $admin, 'ajax_save_student_attendance' );
		$this->loader->add_action( 'wp_ajax_noor_tms_save_teacher_attendance',   $admin, 'ajax_save_teacher_attendance' );

		// Fee Management AJAX (admin-only).
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_delete_structure',  $admin, 'ajax_delete_fee_structure' );
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_void_invoice',      $admin, 'ajax_void_invoice' );
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_search_students',   $admin, 'ajax_fee_search_students' );
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_get_invoices',      $admin, 'ajax_get_student_invoices' );
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_save_payment',      $admin, 'ajax_save_fee_payment' );
		$this->loader->add_action( 'wp_ajax_noor_tms_fee_generate_invoices', $admin, 'ajax_generate_fee_invoices' );

		// WP Cron – monthly invoice generation.
		$this->loader->add_filter( 'cron_schedules',                    $this, 'add_monthly_cron_schedule' );
		$this->loader->add_action( 'noor_tms_generate_monthly_invoices', $admin, 'cron_generate_fee_invoices' );
		$this->loader->add_action( 'init',                               $this, 'schedule_monthly_cron' );
	}

	/**
	 * Add a "monthly" interval to WP Cron schedules if not already present.
	 *
	 * @param array<string, array<string, mixed>> $schedules
	 * @return array<string, array<string, mixed>>
	 */
	public function add_monthly_cron_schedule( array $schedules ): array {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'noor-tms' ),
			];
		}
		return $schedules;
	}

	/**
	 * Schedule the monthly invoice cron event if not already scheduled.
	 * Also ensures the tms-fees page exists (handles existing installs that
	 * activated the plugin before the fee module was added).
	 */
	public function schedule_monthly_cron(): void {
		if ( ! wp_next_scheduled( 'noor_tms_generate_monthly_invoices' ) ) {
			$next_month = strtotime( 'first day of next month 00:05:00' );
			wp_schedule_event( $next_month, 'monthly', 'noor_tms_generate_monthly_invoices' );
		}

		// Ensure the tms-fees WordPress page exists.
		if ( ! get_page_by_path( 'tms-fees' ) ) {
			wp_insert_post( [
				'post_title'     => 'TMS Fees',
				'post_name'      => 'tms-fees',
				'post_content'   => '[noor_tms_fees]',
				'post_status'    => 'publish',
				'post_author'    => 1,
				'post_type'      => 'page',
				'comment_status' => 'closed',
			] );
		}
	}

	// -----------------------------------------------------------------------
	// Public-facing hooks
	// -----------------------------------------------------------------------

	/**
	 * Register shortcodes, front-end assets, login-redirect filter, and form processors.
	 */
	private function define_public_hooks(): void {
		$public    = new \Noor_TMS\PublicFacing\PublicController();
		$processor = new \Noor_TMS\PublicFacing\FormProcessor();

		$this->loader->add_action( 'init',               $public, 'register_shortcodes' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );
		$this->loader->add_action( 'template_redirect',  $public, 'handle_early_requests' );
		$this->loader->add_filter( 'login_redirect',     $public, 'redirect_after_login', 10, 3 );

		// admin-post.php handlers (front-end form submissions).
		$this->loader->add_action( 'admin_post_noor_tms_save_student',  $processor, 'process_student_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_class',    $processor, 'process_class_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_settings', $processor, 'process_settings_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_teacher',  $processor, 'process_teacher_form' );
	}

	// -----------------------------------------------------------------------
	// Public methods
	// -----------------------------------------------------------------------

	/**
	 * Fire all registered hooks.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Load translated strings.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'noor-tms',
			false,
			dirname( NOOR_TMS_PLUGIN_BASE ) . '/languages/'
		);
	}
}
