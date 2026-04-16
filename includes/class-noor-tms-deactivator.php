<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 *
 * Tables and data are intentionally preserved on deactivation.
 * Full cleanup happens only on uninstall (uninstall.php).
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		Roles::remove();
		flush_rewrite_rules();
	}
}
