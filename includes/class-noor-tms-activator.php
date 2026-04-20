<?php
/**
 * Fired during plugin activation.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 *
 * Creates custom database tables and stores the schema version.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		DatabaseHandler::create_tables();
		// Create the custom TMS role so front-end staff can log in.
		Roles::add();
		// Create default pages with shortcodes.
		self::create_default_pages();

		// Schedule cron events
		FeesCron::schedule_events();

		// Store activation timestamp for admin notices / onboarding.
		if ( ! get_option( 'noor_tms_activated_at' ) ) {
			add_option( 'noor_tms_activated_at', current_time( 'mysql' ) );
		}
		flush_rewrite_rules();
	}

	/**
	 * Create default frontend pages with shortcodes.
	 */
	private static function create_default_pages(): void {
		$pages = [
			'tms-login'      => [
				'title'   => 'TMS Login',
				'content' => '[noor_tms_login]',
			],
			'tms-students'   => [
				'title'   => 'TMS Students',
				'content' => '[noor_tms_students]',
			],
			'tms-classes'    => [
				'title'   => 'TMS Classes',
				'content' => '[noor_tms_classes]',
			],
			'tms-results'    => [
				'title'   => 'TMS Results',
				'content' => '[noor_tms_results]',
			],
			'tms-teachers'   => [
				'title'   => 'TMS Teachers',
				'content' => '[noor_tms_teachers]',
			],
			'tms-settings'   => [
				'title'   => 'TMS Settings',
				'content' => '[noor_tms_settings]',
			],
			'tms-attendance' => [
				'title'   => 'TMS Attendance',
				'content' => '[noor_tms_attendance]',
			],
			'tms-fees'       => [
				'title'   => 'TMS Fees',
				'content' => '[noor_tms_fees]',
			],
		];

		foreach ( $pages as $slug => $page ) {
			$page_check = get_page_by_path( $slug );
			if ( ! isset( $page_check->ID ) ) {
				$page_id = wp_insert_post(
					[
						'post_title'     => $page['title'],
						'post_name'      => $slug,
						'post_content'   => $page['content'],
						'post_status'    => 'publish',
						'post_author'    => 1,
						'post_type'      => 'page',
						'comment_status' => 'closed',
					]
				);
			}
		}
	}
}
