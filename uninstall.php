<?php
/**
 * Noor-TMS – Uninstall script.
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Drops all custom tables and removes all plugin options.
 *
 * NOTE: This file is executed DIRECTLY by WordPress, never via the autoloader,
 * so we manually include the DatabaseHandler class.
 */

// Security gate – only WordPress may call this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Autoload is not yet available; require the handler directly.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-noor-tms-database-handler.php';

// Drop tables.
\Noor_TMS\Includes\DatabaseHandler::drop_tables();

// Remove all plugin options.
$options = [
    'noor_tms_options',
    'noor_tms_activated_at',
    'noor_tms_db_version',
];

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'noor_tms_generate_monthly_invoices' );

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear any remaining transients.
delete_transient( 'noor_tms_last_mock_wa' );
