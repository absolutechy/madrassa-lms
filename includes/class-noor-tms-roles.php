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
		self::sync_caps();
	}

	/**
	 * Create/sync roles and capabilities for existing installs too.
	 *
	 * WordPress stores roles in the database. If new roles are added after the
	 * plugin is already active, activation does not run again on the live site.
	 * Calling this on normal plugin load makes the roles appear in wp-admin's
	 * user role dropdown without manual DB edits.
	 */
	public static function sync_caps(): void {
		// TMS Manager — legacy full front-end portal access.
		add_role(
			'noor_tms_manager',
			__( 'TMS Manager', 'noor-tms' ),
			[
				'read'            => true,
				'noor_tms_manage' => true,
				'manage_banin'    => true,
				'manage_banaat'   => true,
			]
		);

		// Banin manager — manages male/Banin academic data only.
		add_role(
			'manager_banin',
			__( 'Banin Manager', 'noor-tms' ),
			[
				'read'         => true,
				'manage_banin' => true,
			]
		);

		// Banaat manager — manages female/Banaat academic data only.
		add_role(
			'manager_banaat',
			__( 'Banaat Manager', 'noor-tms' ),
			[
				'read'          => true,
				'manage_banaat' => true,
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

		// add_role() is a no-op if the role already exists, so keep caps synced.
		$legacy_manager = get_role( 'noor_tms_manager' );
		if ( $legacy_manager ) {
			$legacy_manager->add_cap( 'read' );
			$legacy_manager->add_cap( 'noor_tms_manage' );
			$legacy_manager->add_cap( 'manage_banin' );
			$legacy_manager->add_cap( 'manage_banaat' );
		}

		$banin_manager = get_role( 'manager_banin' );
		if ( $banin_manager ) {
			$banin_manager->add_cap( 'read' );
			$banin_manager->add_cap( 'manage_banin' );
			$banin_manager->remove_cap( 'manage_banaat' );
			$banin_manager->remove_cap( 'noor_tms_manage' );
		}

		$banaat_manager = get_role( 'manager_banaat' );
		if ( $banaat_manager ) {
			$banaat_manager->add_cap( 'read' );
			$banaat_manager->add_cap( 'manage_banaat' );
			$banaat_manager->remove_cap( 'manage_banin' );
			$banaat_manager->remove_cap( 'noor_tms_manage' );
		}

		$teacher = get_role( 'noor_tms_teacher' );
		if ( $teacher ) {
			$teacher->add_cap( 'read' );
			$teacher->add_cap( 'noor_tms_teacher' );
		}

		// Grant manager caps to existing administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'noor_tms_manage' );
			$admin_role->add_cap( 'manage_banin' );
			$admin_role->add_cap( 'manage_banaat' );
		}
	}

	/**
	 * Remove custom roles and their caps from administrators.
	 * Called on plugin deactivation.
	 */
	public static function remove(): void {
		remove_role( 'noor_tms_manager' );
		remove_role( 'manager_banin' );
		remove_role( 'manager_banaat' );
		remove_role( 'noor_tms_teacher' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( 'noor_tms_manage' );
			$admin_role->remove_cap( 'manage_banin' );
			$admin_role->remove_cap( 'manage_banaat' );
		}
	}
}
