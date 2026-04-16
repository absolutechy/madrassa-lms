<?php
/**
 * Custom role & capability management.
 *
 * Creates a 'noor_tms_manager' role so non-admin staff can log in from the
 * front-end portal and perform all TMS operations without WP admin access.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Roles
 */
class Roles {

	/**
	 * Register custom roles and grant caps to administrators.
	 * Called on plugin activation.
	 */
	public static function add(): void {
		// TMS Manager — full front-end portal access.
		add_role(
			'noor_tms_manager',
			__( 'TMS Manager', 'noor-tms' ),
			[
				'read'            => true,
				'noor_tms_manage' => true,
			]
		);

		// TMS Teacher — limited access: own classes, attendance marking only.
		add_role(
			'noor_tms_teacher',
			__( 'TMS Teacher', 'noor-tms' ),
			[
				'read'             => true,
				'noor_tms_teacher' => true,
			]
		);

		// Grant manager cap to existing administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'noor_tms_manage' );
		}
	}

	/**
	 * Remove custom roles and their caps from administrators.
	 * Called on plugin deactivation.
	 */
	public static function remove(): void {
		remove_role( 'noor_tms_manager' );
		remove_role( 'noor_tms_teacher' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'noor_tms_manage' );
		}
	}
}
