<?php
/**
 * Front-end portal controller.
 *
 * Thin orchestrator: registers shortcodes (delegated to ShortcodeHandler),
 * enqueues front-end assets, provides the auth guard via template_redirect,
 * and filters login_redirect for TMS users.
 *
 * admin-post.php form submissions are handled by FormProcessor.
 *
 * Shortcode → WordPress Page mapping (created on activation):
 *   [noor_tms_login]      → slug: tms-login
 *   [noor_tms_students]   → slug: tms-students
 *   [noor_tms_classes]    → slug: tms-classes
 *   [noor_tms_results]    → slug: tms-results
 *   [noor_tms_settings]   → slug: tms-settings
 *   [noor_tms_attendance] → slug: tms-attendance
 *   [noor_tms_teachers]   → slug: tms-teachers
 *
 * @package Noor_TMS\PublicFacing
 */

namespace Noor_TMS\PublicFacing;

use Noor_TMS\Includes\TeacherUserService;

defined( 'ABSPATH' ) || exit;

/**
 * Class PublicController
 */
class PublicController {

	/** @var ShortcodeHandler */
	private ShortcodeHandler $shortcodes;

	public function __construct() {
		$this->shortcodes = new ShortcodeHandler();
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	/**
	 * Register all shortcodes and the WP-user-fields hook.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'noor_tms_login',      [ $this->shortcodes, 'sc_login' ] );
		add_shortcode( 'noor_tms_students',   [ $this->shortcodes, 'sc_students' ] );
		add_shortcode( 'noor_tms_classes',    [ $this->shortcodes, 'sc_classes' ] );
		add_shortcode( 'noor_tms_results',    [ $this->shortcodes, 'sc_results' ] );
		add_shortcode( 'noor_tms_teachers',   [ $this->shortcodes, 'sc_teachers' ] );
		add_shortcode( 'noor_tms_settings',   [ $this->shortcodes, 'sc_settings' ] );
		add_shortcode( 'noor_tms_attendance', [ $this->shortcodes, 'sc_attendance' ] );

		add_action( 'noor_tms_teacher_handle_wp_user_fields', [ $this, 'handle_wp_user_fields' ], 10, 0 );
	}

	/**
	 * Enqueue front-end CSS & JS only on pages that carry a TMS shortcode.
	 */
	public function enqueue_assets(): void {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$tms_shortcodes = [
			'noor_tms_login', 'noor_tms_students',
			'noor_tms_classes', 'noor_tms_results',
			'noor_tms_teachers',
			'noor_tms_settings', 'noor_tms_attendance',
		];

		$found = false;
		foreach ( $tms_shortcodes as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return;
		}

		wp_enqueue_style(
			'noor-tms-public',
			NOOR_TMS_PLUGIN_URL . 'public/css/noor-tms-public.css',
			[],
			NOOR_TMS_VERSION
		);

		wp_enqueue_script(
			'noor-tms-public',
			NOOR_TMS_PLUGIN_URL . 'public/js/noor-tms-public.js',
			[ 'jquery' ],
			NOOR_TMS_VERSION,
			true
		);

		wp_localize_script( 'noor-tms-public', 'noorTMS', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'noor_tms_ajax' ),
			'i18n'    => [
				'saving'             => __( 'Saving…',                                    'noor-tms' ),
				'deleting'           => __( 'Deleting…',                                  'noor-tms' ),
				'error'              => __( 'An error occurred. Please try again.',        'noor-tms' ),
				'confirmDelete'      => __( 'Are you sure you want to delete this?',       'noor-tms' ),
				'saveReport'         => __( 'Save All Results',                            'noor-tms' ),
				'subjectPlaceholder' => __( 'Subject name',                                'noor-tms' ),
			],
		] );
	}

	// -----------------------------------------------------------------------
	// Early request handler (template_redirect)
	// -----------------------------------------------------------------------

	/**
	 * Runs before output — sets cache-busting headers on TMS pages and
	 * enforces the auth guard for protected portal pages.
	 */
	public function handle_early_requests(): void {
		if ( ! is_page() ) {
			return;
		}

		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$all_tms = [
			'noor_tms_login',
			'noor_tms_students', 'noor_tms_classes',
			'noor_tms_results',  'noor_tms_teachers',
			'noor_tms_settings',
			'noor_tms_attendance',
		];

		$is_tms = false;
		foreach ( $all_tms as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$is_tms = true;
				break;
			}
		}

		if ( ! $is_tms ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );

		$protected = [
			'noor_tms_students', 'noor_tms_classes',
			'noor_tms_results',  'noor_tms_teachers',
			'noor_tms_settings',
			'noor_tms_attendance',
		];
		$is_protected = false;
		foreach ( $protected as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$is_protected = true;
				break;
			}
		}

		$can_access = current_user_can( 'noor_tms_manage' ) || current_user_can( 'noor_tms_teacher' );
		if ( $is_protected && ( ! is_user_logged_in() || ! $can_access ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}
	}

	/**
	 * After a successful login, redirect TMS managers/teachers to the portal
	 * instead of wp-admin.
	 *
	 * @param string             $redirect_to
	 * @param string             $requested
	 * @param \WP_User|\WP_Error $user
	 */
	public function redirect_after_login( string $redirect_to, string $requested, $user ): string {
		if ( $user instanceof \WP_User ) {
			if ( $user->has_cap( 'noor_tms_manage' ) && ! $user->has_cap( 'manage_options' ) ) {
				return home_url( '/tms-students/' );
			}
			if ( $user->has_cap( 'noor_tms_teacher' ) ) {
				return home_url( '/tms-attendance/' );
			}
		}
		return $redirect_to;
	}

	// -----------------------------------------------------------------------
	// WP user creation hook (fired by both admin Teachers and FormProcessor)
	// -----------------------------------------------------------------------

	/**
	 * Optionally create a new WordPress user when saving a teacher.
	 * Fires on the `noor_tms_teacher_handle_wp_user_fields` action.
	 * Delegates to TeacherUserService — single canonical implementation.
	 */
	public function handle_wp_user_fields(): void {
		TeacherUserService::handle_wp_user_fields();
	}

	// -----------------------------------------------------------------------
	// Legacy stub
	// -----------------------------------------------------------------------

	/**
	 * @deprecated Auth is handled by handle_early_requests() on template_redirect.
	 *             Kept only to prevent fatals if called externally.
	 */
	private function require_auth(): void {
		_deprecated_function( __METHOD__, '1.0.8', 'PublicController::handle_early_requests' );
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}
	}
}
