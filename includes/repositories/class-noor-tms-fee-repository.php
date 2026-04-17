<?php
/**
 * Repository for all Fee Management data.
 *
 * Tables owned:
 *   {prefix}lms_fee_structure
 *   {prefix}lms_student_fee_assignment
 *   {prefix}lms_fee_invoices
 *   {prefix}lms_fee_payments
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class FeeRepository
 */
class FeeRepository {

	// -----------------------------------------------------------------------
	// Table name helpers
	// -----------------------------------------------------------------------

	/** @return string */
	public static function fee_structure_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lms_fee_structure';
	}

	/** @return string */
	public static function fee_assignment_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lms_student_fee_assignment';
	}

	/** @return string */
	public static function fee_invoices_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lms_fee_invoices';
	}

	/** @return string */
	public static function fee_payments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'lms_fee_payments';
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns the current academic year string (e.g. "2025-2026").
	 * Academic year starts in April.
	 */
	public static function current_academic_year(): string {
		$year  = (int) current_time( 'Y' );
		$month = (int) current_time( 'n' );
		if ( $month >= 4 ) {
			return $year . '-' . ( $year + 1 );
		}
		return ( $year - 1 ) . '-' . $year;
	}

	// -----------------------------------------------------------------------
	// Fee Structures
	// -----------------------------------------------------------------------

	/**
	 * Insert a new fee structure.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false  New ID on success, false on failure.
	 */
	public static function insert_fee_structure( array $data ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::fee_structure_table(),
			[
				'class_id'       => (int) ( $data['class_id'] ?? 0 ),
				'fee_title'      => sanitize_text_field( $data['fee_title'] ?? '' ),
				'amount'         => (float) ( $data['amount'] ?? 0 ),
				'fine_per_day'   => (float) ( $data['fine_per_day'] ?? 0 ),
				'frequency'      => in_array( $data['frequency'] ?? '', [ 'monthly', 'term', 'yearly' ], true )
					? $data['frequency']
					: 'monthly',
				'effective_from' => sanitize_text_field( $data['effective_from'] ?? current_time( 'Y-m-d' ) ),
			],
			[ '%d', '%s', '%f', '%f', '%s', '%s' ]
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing fee structure.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public static function update_fee_structure( int $id, array $data ): bool {
		global $wpdb;
		$row     = [];
		$formats = [];

		if ( isset( $data['class_id'] ) )       { $row['class_id']       = (int) $data['class_id'];                                        $formats[] = '%d'; }
		if ( isset( $data['fee_title'] ) )       { $row['fee_title']       = sanitize_text_field( $data['fee_title'] );                     $formats[] = '%s'; }
		if ( isset( $data['amount'] ) )          { $row['amount']          = (float) $data['amount'];                                       $formats[] = '%f'; }
		if ( isset( $data['fine_per_day'] ) )    { $row['fine_per_day']    = (float) $data['fine_per_day'];                                  $formats[] = '%f'; }
		if ( isset( $data['frequency'] ) )       { $row['frequency']       = sanitize_key( $data['frequency'] );                            $formats[] = '%s'; }
		if ( isset( $data['effective_from'] ) )  { $row['effective_from']  = sanitize_text_field( $data['effective_from'] );                $formats[] = '%s'; }

		if ( empty( $row ) ) {
			return false;
		}
		$result = $wpdb->update(
			self::fee_structure_table(),
			$row,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);
		return false !== $result;
	}

	/**
	 * Delete a fee structure by ID.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_fee_structure( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::fee_structure_table(), [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Get all fee structures, optionally filtered by class.
	 *
	 * @param int $class_id  0 = all classes.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fee_structures( int $class_id = 0 ): array {
		global $wpdb;
		$fs = self::fee_structure_table();
		$cl = ClassRepository::classes_table();

		if ( $class_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT fs.*, c.name AS class_name
					   FROM {$fs} fs
					   LEFT JOIN {$cl} c ON c.id = fs.class_id
					  WHERE fs.class_id = %d
					  ORDER BY fs.effective_from DESC",
					$class_id
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results(
				"SELECT fs.*, c.name AS class_name
				   FROM {$fs} fs
				   LEFT JOIN {$cl} c ON c.id = fs.class_id
				  ORDER BY c.name ASC, fs.effective_from DESC",
				ARRAY_A
			);
		}
		return $rows ?: [];
	}

	/**
	 * Get a single fee structure by ID.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_fee_structure( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::fee_structure_table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get the most recent (effective) fee structure for a class.
	 *
	 * @param int $class_id
	 * @return array<string, mixed>|null
	 */
	public static function get_active_fee_for_class( int $class_id ): ?array {
		global $wpdb;
		$today = current_time( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::fee_structure_table() .
				' WHERE class_id = %d AND effective_from <= %s ORDER BY effective_from DESC LIMIT 1',
				$class_id,
				$today
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	// -----------------------------------------------------------------------
	// Student Fee Assignments
	// -----------------------------------------------------------------------

	/**
	 * Assign (or update) a fee structure for a student in a given academic year.
	 *
	 * @param int    $student_id
	 * @param int    $fee_structure_id
	 * @param string $academic_year   e.g. "2025-2026"
	 * @return bool
	 */
	public static function upsert_student_assignment( int $student_id, int $fee_structure_id, string $academic_year ): bool {
		global $wpdb;
		$table    = self::fee_assignment_table();
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE student_id = %d AND academic_year = %s", // phpcs:ignore
				$student_id,
				$academic_year
			)
		);

		if ( $existing ) {
			return false !== $wpdb->update(
				$table,
				[ 'fee_structure_id' => $fee_structure_id ],
				[ 'student_id' => $student_id, 'academic_year' => $academic_year ],
				[ '%d' ],
				[ '%d', '%s' ]
			);
		}

		return false !== $wpdb->insert(
			$table,
			[
				'student_id'       => $student_id,
				'fee_structure_id' => $fee_structure_id,
				'academic_year'    => $academic_year,
			],
			[ '%d', '%d', '%s' ]
		);
	}

	/**
	 * Get the effective fee structure for a student:
	 * uses their specific assignment first, falls back to their class's fee structure.
	 *
	 * @param int    $student_id
	 * @param string $academic_year
	 * @return array<string, mixed>|null  Row with at minimum: id (fee_structure id), amount, fine_per_day, frequency.
	 */
	public static function get_effective_fee_for_student( int $student_id, string $academic_year ): ?array {
		global $wpdb;

		// Check specific assignment.
		$table = self::fee_assignment_table();
		$fs    = self::fee_structure_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT sfa.fee_structure_id AS id, fs.*
				   FROM {$table} sfa
				   JOIN {$fs} fs ON fs.id = sfa.fee_structure_id
				  WHERE sfa.student_id = %d AND sfa.academic_year = %s",
				$student_id,
				$academic_year
			),
			ARRAY_A
		);
		if ( $row ) {
			return $row;
		}

		// Fall back to class fee structure.
		$student = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT class_id FROM ' . StudentRepository::students_table() . ' WHERE id = %d',
				$student_id
			),
			ARRAY_A
		);
		if ( ! $student || empty( $student['class_id'] ) ) {
			return null;
		}
		return self::get_active_fee_for_class( (int) $student['class_id'] );
	}

	// -----------------------------------------------------------------------
	// Invoices
	// -----------------------------------------------------------------------

	/**
	 * Insert a new invoice.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public static function insert_invoice( array $data ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::fee_invoices_table(),
			[
				'student_id'       => (int) $data['student_id'],
				'fee_structure_id' => (int) $data['fee_structure_id'],
				'invoice_month'    => sanitize_text_field( $data['invoice_month'] ),
				'due_date'         => sanitize_text_field( $data['due_date'] ),
				'amount_due'       => (float) ( $data['amount_due'] ?? 0 ),
				'discount'         => (float) ( $data['discount'] ?? 0 ),
				'fine'             => (float) ( $data['fine'] ?? 0 ),
				'status'           => 'unpaid',
			],
			[ '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s' ]
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single invoice with student + class + fee_title data.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_invoice( int $id ): ?array {
		global $wpdb;
		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();
		$fs = self::fee_structure_table();
		$st = StudentRepository::students_table();
		$cl = ClassRepository::classes_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT fi.*, s.name AS student_name, s.class_id,
				        c.name AS class_name, fst.fee_title, fst.fine_per_day,
				        COALESCE((SELECT SUM(paid_amount) FROM {$fp} WHERE invoice_id = fi.id), 0) AS total_paid
				   FROM {$fi} fi
				   JOIN {$st} s ON s.id = fi.student_id
				   LEFT JOIN {$cl} c ON c.id = s.class_id
				   LEFT JOIN {$fs} fst ON fst.id = fi.fee_structure_id
				  WHERE fi.id = %d",
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get paginated invoices with optional filters.
	 *
	 * @param array{
	 *   per_page?: int,
	 *   page?: int,
	 *   invoice_month?: string,
	 *   class_id?: int,
	 *   status?: string,
	 *   student_id?: int
	 * } $args
	 * @return array{ rows: array, total: int }
	 */
	public static function get_invoices( array $args = [] ): array {
		global $wpdb;

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();
		$fs = self::fee_structure_table();
		$st = StudentRepository::students_table();
		$cl = ClassRepository::classes_table();

		$where  = [];
		$params = [];

		if ( ! empty( $args['invoice_month'] ) ) {
			$where[]  = 'fi.invoice_month = %s';
			$params[] = $args['invoice_month'];
		}
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[]  = 'fi.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['class_id'] ) ) {
			$where[]  = 's.class_id = %d';
			$params[] = (int) $args['class_id'];
		}
		if ( ! empty( $args['student_id'] ) ) {
			$where[]  = 'fi.student_id = %d';
			$params[] = (int) $args['student_id'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$base = "FROM {$fi} fi
		         JOIN {$st} s ON s.id = fi.student_id
		         LEFT JOIN {$cl} c ON c.id = s.class_id
		         LEFT JOIN {$fs} fst ON fst.id = fi.fee_structure_id
		         {$where_sql}";

		$count_sql = "SELECT COUNT(*) {$base}";
		$data_sql  = "SELECT fi.*,
		                     s.name AS student_name, s.class_id,
		                     c.name AS class_name, fst.fee_title, fst.fine_per_day,
		                     COALESCE((SELECT SUM(paid_amount) FROM {$fp} WHERE invoice_id = fi.id), 0) AS total_paid
		              {$base}
		              ORDER BY fi.invoice_month DESC, s.name ASC
		              LIMIT %d OFFSET %d";

		if ( $params ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
			$rows  = $wpdb->get_results(
				$wpdb->prepare( $data_sql, ...array_merge( $params, [ $per_page, $offset ] ) ),
				ARRAY_A
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
			$rows  = $wpdb->get_results(
				$wpdb->prepare( $data_sql, $per_page, $offset ),
				ARRAY_A
			);
			// phpcs:enable
		}

		return [ 'rows' => $rows ?: [], 'total' => $total ];
	}

	/**
	 * Recalculate and update invoice status based on total payments vs net due.
	 *
	 * @param int $invoice_id
	 */
	public static function recalculate_invoice_status( int $invoice_id ): void {
		global $wpdb;
		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();

		$invoice = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT amount_due, discount, fine, status FROM {$fi} WHERE id = %d", $invoice_id ), // phpcs:ignore
			ARRAY_A
		);
		if ( ! $invoice || 'voided' === $invoice['status'] ) {
			return;
		}

		$net_due = max( 0, (float) $invoice['amount_due'] + (float) $invoice['fine'] - (float) $invoice['discount'] );

		$total_paid = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COALESCE(SUM(paid_amount), 0) FROM {$fp} WHERE invoice_id = %d", $invoice_id ) // phpcs:ignore
		);

		if ( $total_paid <= 0 ) {
			$status = 'unpaid';
		} elseif ( $total_paid >= $net_due ) {
			$status = 'paid';
		} else {
			$status = 'partial';
		}

		$wpdb->update( $fi, [ 'status' => $status ], [ 'id' => $invoice_id ], [ '%s' ], [ '%d' ] ); // phpcs:ignore
	}

	/**
	 * Mark an invoice as voided (never deleted).
	 *
	 * @param int $invoice_id
	 * @return bool
	 */
	public static function void_invoice( int $invoice_id ): bool {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::fee_invoices_table(),
			[ 'status' => 'voided' ],
			[ 'id' => $invoice_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Check whether an invoice already exists for a student + month.
	 *
	 * @param int    $student_id
	 * @param string $month  YYYY-MM
	 * @return bool
	 */
	public static function has_invoice_for_month( int $student_id, string $month ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::fee_invoices_table() . ' WHERE student_id = %d AND invoice_month = %s',
				$student_id,
				$month
			)
		);
	}

	/**
	 * Get all non-voided invoices for a student (newest first).
	 *
	 * @param int $student_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_student_invoices( int $student_id ): array {
		global $wpdb;
		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();
		$fs = self::fee_structure_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT fi.*, fst.fee_title, fst.fine_per_day,
				        COALESCE((SELECT SUM(paid_amount) FROM {$fp} WHERE invoice_id = fi.id), 0) AS total_paid
				   FROM {$fi} fi
				   LEFT JOIN {$fs} fst ON fst.id = fi.fee_structure_id
				  WHERE fi.student_id = %d AND fi.status != 'voided'
				  ORDER BY fi.invoice_month DESC",
				$student_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get all defaulters (unpaid/partial invoices) for a given month.
	 *
	 * @param string $month  YYYY-MM
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_defaulters( string $month ): array {
		global $wpdb;
		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();
		$st = StudentRepository::students_table();
		$cl = ClassRepository::classes_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT fi.*,
				        s.name AS student_name, s.parent_phone,
				        c.name AS class_name,
				        COALESCE((SELECT SUM(paid_amount) FROM {$fp} WHERE invoice_id = fi.id), 0) AS total_paid
				   FROM {$fi} fi
				   JOIN {$st} s ON s.id = fi.student_id
				   LEFT JOIN {$cl} c ON c.id = s.class_id
				  WHERE fi.invoice_month = %s AND fi.status IN ('unpaid', 'partial')
				  ORDER BY c.name ASC, s.name ASC",
				$month
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Update fine amounts for all overdue unpaid/partial invoices.
	 * Called by WP Cron or manually.
	 *
	 * @param string $today  Y-m-d, defaults to current_time.
	 * @return int  Number of invoices updated.
	 */
	public static function apply_late_fines( string $today = '' ): int {
		global $wpdb;
		if ( ! $today ) {
			$today = current_time( 'Y-m-d' );
		}
		$fi = self::fee_invoices_table();
		$fs = self::fee_structure_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$invoices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT fi.id, fi.due_date, fst.fine_per_day
				   FROM {$fi} fi
				   JOIN {$fs} fst ON fst.id = fi.fee_structure_id
				  WHERE fi.status IN ('unpaid', 'partial')
				    AND fi.due_date < %s
				    AND fst.fine_per_day > 0",
				$today
			),
			ARRAY_A
		);

		$count = 0;
		foreach ( $invoices as $inv ) {
			$days = max( 0, (int) round( ( strtotime( $today ) - strtotime( $inv['due_date'] ) ) / DAY_IN_SECONDS ) );
			$fine = $days * (float) $inv['fine_per_day'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $fi, [ 'fine' => $fine ], [ 'id' => (int) $inv['id'] ], [ '%f' ], [ '%d' ] );
			self::recalculate_invoice_status( (int) $inv['id'] );
			$count++;
		}
		return $count;
	}

	/**
	 * Collection report: summarise billed vs collected, grouped by month or class.
	 *
	 * @param array{
	 *   group_by?: string,
	 *   invoice_month?: string,
	 *   class_id?: int
	 * } $args
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_collection_summary( array $args = [] ): array {
		global $wpdb;

		$group_by = in_array( $args['group_by'] ?? 'month', [ 'month', 'class' ], true )
			? $args['group_by']
			: 'month';
		$month    = sanitize_text_field( $args['invoice_month'] ?? '' );
		$class_id = (int) ( $args['class_id'] ?? 0 );

		$fi = self::fee_invoices_table();
		$fp = self::fee_payments_table();
		$st = StudentRepository::students_table();
		$cl = ClassRepository::classes_table();

		$where  = [ "fi.status != 'voided'" ];
		$params = [];

		if ( $month ) {
			$where[]  = 'fi.invoice_month = %s';
			$params[] = $month;
		}
		if ( $class_id ) {
			$where[]  = 's.class_id = %d';
			$params[] = $class_id;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		if ( 'class' === $group_by ) {
			$select_group = 'COALESCE(c.name, \'(No Class)\') AS group_label';
			$group_clause = 'GROUP BY s.class_id';
			$order_clause = 'ORDER BY group_label ASC';
		} else {
			$select_group = 'fi.invoice_month AS group_label';
			$group_clause = 'GROUP BY fi.invoice_month';
			$order_clause = 'ORDER BY fi.invoice_month DESC';
		}

		$sql = "SELECT {$select_group},
		               COUNT(fi.id)                                       AS invoice_count,
		               COALESCE(SUM(fi.amount_due + fi.fine - fi.discount), 0) AS total_billed,
		               COALESCE(SUM(IFNULL(fp_agg.total_paid, 0)), 0)    AS total_collected
		          FROM {$fi} fi
		          JOIN {$st} s ON s.id = fi.student_id
		          LEFT JOIN {$cl} c ON c.id = s.class_id
		          LEFT JOIN (
		                SELECT invoice_id, SUM(paid_amount) AS total_paid
		                  FROM {$fp}
		                 GROUP BY invoice_id
		               ) fp_agg ON fp_agg.invoice_id = fi.id
		         {$where_sql}
		         {$group_clause}
		         {$order_clause}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Payments
	// -----------------------------------------------------------------------

	/**
	 * Insert a payment entry and recalculate invoice status.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public static function insert_payment( array $data ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::fee_payments_table(),
			[
				'invoice_id'     => (int) $data['invoice_id'],
				'paid_amount'    => (float) $data['paid_amount'],
				'payment_date'   => sanitize_text_field( $data['payment_date'] ?? current_time( 'Y-m-d' ) ),
				'payment_method' => in_array( $data['payment_method'] ?? '', [ 'cash', 'bank', 'cheque' ], true )
					? $data['payment_method']
					: 'cash',
				'received_by'    => (int) ( $data['received_by'] ?? get_current_user_id() ),
				'remarks'        => sanitize_textarea_field( $data['remarks'] ?? '' ),
			],
			[ '%d', '%f', '%s', '%s', '%d', '%s' ]
		);
		if ( $inserted ) {
			self::recalculate_invoice_status( (int) $data['invoice_id'] );
			return (int) $wpdb->insert_id;
		}
		return false;
	}

	/**
	 * Get all payments for a given invoice.
	 *
	 * @param int $invoice_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_payments_for_invoice( int $invoice_id ): array {
		global $wpdb;
		$fp = self::fee_payments_table();
		$wu = $wpdb->users;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT fp.*, u.display_name AS received_by_name
				   FROM {$fp} fp
				   LEFT JOIN {$wu} u ON u.ID = fp.received_by
				  WHERE fp.invoice_id = %d
				  ORDER BY fp.payment_date ASC, fp.id ASC",
				$invoice_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Monthly Invoice Generation (Cron)
	// -----------------------------------------------------------------------

	/**
	 * Generate invoices for all active students for a given month.
	 * Skips students who already have an invoice for that month.
	 * Due date is set to the 10th of the invoice month.
	 *
	 * @param string $month          YYYY-MM
	 * @param string $academic_year  e.g. "2025-2026"
	 * @return int  Number of new invoices created.
	 */
	public static function generate_invoices_for_month( string $month, string $academic_year ): int {
		global $wpdb;

		$students = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, class_id FROM " . StudentRepository::students_table() . " WHERE status = 'active' AND class_id > 0", // phpcs:ignore
			ARRAY_A
		);

		$count    = 0;
		$due_date = $month . '-10';

		foreach ( $students as $student ) {
			$student_id = (int) $student['id'];

			if ( self::has_invoice_for_month( $student_id, $month ) ) {
				continue;
			}

			$fee = self::get_effective_fee_for_student( $student_id, $academic_year );
			if ( ! $fee || 'monthly' !== ( $fee['frequency'] ?? 'monthly' ) ) {
				continue;
			}

			$fee_structure_id = (int) $fee['id'];

			if ( self::insert_invoice( [
				'student_id'       => $student_id,
				'fee_structure_id' => $fee_structure_id,
				'invoice_month'    => $month,
				'due_date'         => $due_date,
				'amount_due'       => (float) $fee['amount'],
				'discount'         => 0,
				'fine'             => 0,
			] ) ) {
				$count++;
			}
		}

		return $count;
	}
}
