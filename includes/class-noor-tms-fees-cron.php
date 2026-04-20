<?php
/**
 * Cron Job for Fees Invoices.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

class FeesCron {

	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'noor_tms_generate_monthly_invoices' ) ) {
			wp_schedule_event( time(), 'daily', 'noor_tms_generate_monthly_invoices' );
		}
	}

	public static function clear_events(): void {
		wp_clear_scheduled_hook( 'noor_tms_generate_monthly_invoices' );
	}

	public static function generate_monthly_invoices(): void {
		global $wpdb;

		$today            = current_time( 'Y-m-d' );
		$current_month    = current_time( 'Y-m' );
		$academic_year    = date( 'Y' );
		$due_date         = date( 'Y-m-d', strtotime( date( 'Y-m-01' ) . ' + 9 days' ) );

		// Use single INSERT...SELECT for better performance and reliability
		$inserted = $wpdb->query( $wpdb->prepare(
			"INSERT INTO " . DatabaseHandler::fee_invoices_table() . " 
			 (student_id, fee_structure_id, invoice_month, academic_year, due_date, amount_due, status, created_at)
			 SELECT s.id, fs.id, %s, %s, %s, fs.amount, 'unpaid', NOW()
			 FROM " . DatabaseHandler::students_table() . " s
			 INNER JOIN " . DatabaseHandler::fee_structure_table() . " fs 
			   ON ( fs.class_id = 0 OR fs.class_id = s.class_id )
			 WHERE fs.frequency IN ('monthly', 'one-time') 
			   AND s.status = 'active'
			   AND NOT EXISTS (
			       SELECT 1 FROM " . DatabaseHandler::fee_invoices_table() . " 
			       WHERE student_id = s.id AND fee_structure_id = fs.id AND invoice_month = %s
			   )",
			$current_month, $academic_year, $due_date, $current_month
		) );

		if ( $inserted ) {
			error_log( "[Noor-TMS] Invoice generation complete. Inserted {$inserted} invoices." );
		} else {
			error_log( '[Noor-TMS] No invoices were created or all eligible invoices already exist.' );
		}

		self::update_late_fines( $today );
	}

	public static function update_late_fines( string $today ): void {
		global $wpdb;

		// 10 fine per day for overdue
		$fine_per_day = 10.00;

		$results = $wpdb->get_results( "SELECT id, due_date FROM " . DatabaseHandler::fee_invoices_table() . " WHERE status IN ('unpaid', 'partial') AND due_date < '$today'", ARRAY_A );

		foreach ( $results as $row ) {
			$invoice_id = (int) $row['id'];
			$due_date   = $row['due_date'];

			$days_late = max( 0, ( strtotime( $today ) - strtotime( $due_date ) ) / DAY_IN_SECONDS );
			$fine      = $days_late * $fine_per_day;

			$wpdb->update(
				DatabaseHandler::fee_invoices_table(),
				[ 'fine' => $fine ],
				[ 'id' => $invoice_id ],
				[ '%f' ],
				[ '%d' ]
			);
		}
	}
}
