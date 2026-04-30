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

		$today         = current_time( 'Y-m-d' );
		$current_month = current_time( 'Y-m' ); // e.g. '2026-04'
		$academic_year = date( 'Y' );

		$inv = DatabaseHandler::fee_invoices_table();
		$stu = DatabaseHandler::students_table();
		$fs  = DatabaseHandler::fee_structure_table();

		/*
		 * Repair any existing fee structures where effective_from was stored as a
		 * partial date ('YYYY-MM' or '0000-00-00') due to the HTML month input bug.
		 * Safe to run every time — only touches rows that need fixing.
		 */
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"UPDATE {$fs}
			    SET effective_from = CONCAT( LEFT(effective_from, 7), '-01' )
			  WHERE DAY(effective_from) = 0
			     OR effective_from = '0000-00-00'"
		);

		/*
		 * Find the earliest effective_from across all monthly/one-time structures.
		 * We fetch raw DATE strings from PHP and take the first 7 chars ('YYYY-MM')
		 * so the result is reliable regardless of MySQL mode or zero-day storage
		 * ('2026-02', '2026-02-00', '2026-02-01' all give '2026-02').
		 */
		$raw_dates = $wpdb->get_col( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT effective_from FROM {$fs} WHERE frequency IN ('monthly','one-time')"
		);

		$start_month = $current_month;
		foreach ( $raw_dates as $raw ) {
			$eff = substr( (string) $raw, 0, 7 ); // 'YYYY-MM'
			// Skip obviously invalid/zero dates.
			if ( $eff >= '2000-01' && $eff < $start_month ) {
				$start_month = $eff;
			}
		}

		$total_inserted = 0;
		$month          = $start_month;

		while ( $month <= $current_month ) {
			$due_date = date( 'Y-m-d', strtotime( $month . '-01 +9 days' ) );

			/*
			 * LEFT(fees.effective_from, 7) gives 'YYYY-MM' for any storage format
			 * ('2026-02', '2026-02-00', '2026-02-01') and supports simple string <=
			 * comparison — no YEAR() / MONTH() / DATE_FORMAT() needed.
			 */
			$inserted = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$inv}
				 (student_id, fee_structure_id, invoice_month, academic_year, due_date, amount_due, status, created_at)
				 SELECT s.id, fees.id, %s, %s, %s, fees.amount, 'unpaid', NOW()
				   FROM {$stu} s
				  INNER JOIN {$fs} fees
				     ON ( fees.class_id = 0 OR fees.class_id = s.class_id )
				  WHERE fees.frequency IN ('monthly','one-time')
				    AND s.status = 'active'
				    AND LEFT(fees.effective_from, 7) <= %s
				    AND NOT EXISTS (
				        SELECT 1
				          FROM {$inv}
				         WHERE student_id      = s.id
				           AND fee_structure_id = fees.id
				           AND invoice_month    = %s
				    )",
				$month, $academic_year, $due_date, $month, $month
			) );

			$total_inserted += (int) $inserted;

			// Advance to next calendar month (handles Dec → Jan roll-over).
			$month = date( 'Y-m', strtotime( $month . '-01 +1 month' ) );
		}

		if ( $total_inserted > 0 ) {
			error_log( "[Noor-TMS] Invoice backfill complete. Inserted {$total_inserted} invoices across all missing months." );
		} else {
			error_log( '[Noor-TMS] No new invoices created — all eligible months already have invoices.' );
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
