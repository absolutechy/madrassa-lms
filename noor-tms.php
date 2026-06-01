<?php
/**
 * Plugin Name:       Noor-TMS – Madrasa Management System
 * Plugin URI:        https://github.com/your-org/noor-tms
 * Description:       A scalable, OOP-based Madrasa Management System for student administration and automated WhatsApp parent communication.
 * Version:           1.0.8
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://yoursite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       noor-tms
 * Domain Path:       /languages
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;  
}

// ---------------------------------------------------------------------------
// Plugin Constants
// ---------------------------------------------------------------------------
define( 'NOOR_TMS_VERSION',     '1.0.8' );
define( 'NOOR_TMS_PLUGIN_FILE', __FILE__ );
define( 'NOOR_TMS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NOOR_TMS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'NOOR_TMS_PLUGIN_BASE', plugin_basename( __FILE__ ) );

require_once NOOR_TMS_PLUGIN_DIR . 'includes/noor-tms-font.php';

// ---------------------------------------------------------------------------
// Autoloader  (PSR-4 style for the Noor_TMS namespace)
// ---------------------------------------------------------------------------
spl_autoload_register( function ( string $class ): void {
	$prefix    = 'Noor_TMS\\';
	$base_dir  = NOOR_TMS_PLUGIN_DIR;

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );

	// Map sub-namespaces to directories.
	// Noor_TMS\Admin\... → admin/class-noor-tms-*.php
	// Noor_TMS\Includes\... → includes/class-noor-tms-*.php
	$parts     = explode( '\\', $relative );
	$sub_ns    = array_shift( $parts );          // 'Admin' | 'Includes'
	$class_name = end( $parts );                 // e.g. 'Students'

	$dir = match ( strtolower( $sub_ns ) ) {
		'admin'        => $base_dir . 'admin/',
		'includes'     => $base_dir . 'includes/',
		'publicfacing' => $base_dir . 'public/',
		default        => null,
	};

	if ( null === $dir ) {
		return;
	}

	// Convert PascalCase class name to WordPress filename convention.
	// e.g. 'DatabaseHandler' → 'class-noor-tms-database-handler.php'
	$slug = 'class-noor-tms-' . strtolower(
		preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name )
	) . '.php';

	$file = $dir . $slug;
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ---------------------------------------------------------------------------
// Activation / Deactivation hooks
// ---------------------------------------------------------------------------
register_activation_hook(
	__FILE__,
	[ 'Noor_TMS\\Includes\\Activator', 'activate' ]
);

register_deactivation_hook(
	__FILE__,
	[ 'Noor_TMS\\Includes\\Deactivator', 'deactivate' ]
);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
/**
 * Returns the single instance of the core plugin class.
 *
 * @return \Noor_TMS\Includes\Plugin
 */
function noor_tms(): \Noor_TMS\Includes\Plugin {
	return \Noor_TMS\Includes\Plugin::get_instance();
}

if ( ! function_exists( 'noor_tms_can_manage' ) ) {
	/**
	 * Check whether the current user can manage TMS data.
	 */
	function noor_tms_can_manage(): bool {
		return current_user_can( 'noor_tms_manage' )
			|| current_user_can( 'manage_banin' )
			|| current_user_can( 'manage_banaat' );
	}
}

noor_tms()->run();
