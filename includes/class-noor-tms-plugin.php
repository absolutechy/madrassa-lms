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
	}

	// -----------------------------------------------------------------------
	// Public-facing hooks
	// -----------------------------------------------------------------------

	/**
	 * Register shortcodes, front-end assets, and login-redirect filter.
	 */
	private function define_public_hooks(): void {
		$public = new \Noor_TMS\PublicFacing\PublicController();

		$this->loader->add_action( 'init',               $public, 'register_shortcodes' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );
		$this->loader->add_action( 'template_redirect',  $public, 'handle_early_requests' );
		$this->loader->add_filter( 'login_redirect',     $public, 'redirect_after_login', 10, 3 );

		// admin-post.php handlers (front-end form submissions).
		$this->loader->add_action( 'admin_post_noor_tms_save_student',  $public, 'process_student_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_class',    $public, 'process_class_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_settings', $public, 'process_settings_form' );
		$this->loader->add_action( 'admin_post_noor_tms_save_teacher',  $public, 'process_teacher_form' );
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
