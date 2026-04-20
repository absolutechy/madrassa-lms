<?php
/**
 * Database abstraction layer for Noor-TMS.
 *
 * Manages eight custom tables:
 *   - {prefix}mms_classes
 *   - {prefix}mms_subjects
 *   - {prefix}mms_students
 *   - {prefix}mms_results
 *   - {prefix}mms_teachers
 *   - {prefix}mms_class_teachers
 *   - {prefix}mms_student_attendance
 *   - {prefix}mms_teacher_attendance
 *
 * All public methods are static so callers need not hold an instance.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class DatabaseHandler
 */
class DatabaseHandler {

	/** Current schema version – bump when ALTER TABLE migrations are needed. */
	private const SCHEMA_VERSION = '4.0';
	private const SCHEMA_OPTION  = 'noor_tms_db_version';

	// -----------------------------------------------------------------------
	// Schema management
	// -----------------------------------------------------------------------

	/**
	 * Create (or upgrade) the plugin's custom tables.
	 *
	 * Uses dbDelta() — safe to re-run on existing installs.
	 * Also runs ALTER TABLE migrations for schema upgrades.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// ----------------------------------------------------------------
		// Classes table
		// ----------------------------------------------------------------
		$sql_classes = "CREATE TABLE " . self::classes_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(255)        NOT NULL DEFAULT '',
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_name (name)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Subjects table  (many subjects per class)
		// ----------------------------------------------------------------
		$sql_subjects = "CREATE TABLE " . self::subjects_table() . " (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id     BIGINT(20) UNSIGNED NOT NULL,
			subject_name VARCHAR(255)        NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Students table  (includes class_id and photo_id)
		// ----------------------------------------------------------------
		$sql_students = "CREATE TABLE " . self::students_table() . " (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id        BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			name            VARCHAR(255)        NOT NULL DEFAULT '',
			parent_phone    VARCHAR(30)         NOT NULL DEFAULT '',
			enrollment_date DATE                NOT NULL,
			status          ENUM('active','inactive','graduated') NOT NULL DEFAULT 'active',
			photo_id        BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id),
			KEY idx_status (status),
			KEY idx_enrollment_date (enrollment_date)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Results table
		// ----------------------------------------------------------------
		$sql_results = "CREATE TABLE " . self::results_table() . " (
			id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id        BIGINT(20) UNSIGNED NOT NULL,
			subject           VARCHAR(255)        NOT NULL DEFAULT '',
			marks_obtained    DECIMAL(6,2)        NOT NULL DEFAULT 0.00,
			total_marks       DECIMAL(6,2)        NOT NULL DEFAULT 100.00,
			exam_date         DATE                NOT NULL,
			notification_sent TINYINT(1)          NOT NULL DEFAULT 0,
			created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_student_id (student_id),
			KEY idx_exam_date (exam_date),
			KEY idx_notification_sent (notification_sent)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Teachers table
		// ----------------------------------------------------------------
		$sql_teachers = "CREATE TABLE " . self::teachers_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT(20) UNSIGNED NOT NULL,
			name       VARCHAR(255)        NOT NULL DEFAULT '',
			phone      VARCHAR(30)         NOT NULL DEFAULT '',
			is_active  TINYINT(1)          NOT NULL DEFAULT 1,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_wp_user_id (wp_user_id),
			KEY idx_is_active (is_active)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Class–Teacher assignments
		// role_type: 'homeroom' | 'subject'
		// subject_id is NULL for homeroom assignments
		// ----------------------------------------------------------------
		$sql_class_teachers = "CREATE TABLE " . self::class_teachers_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id   BIGINT(20) UNSIGNED NOT NULL,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			role_type  VARCHAR(20)         NOT NULL DEFAULT 'homeroom',
			subject_id BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id),
			KEY idx_teacher_id (teacher_id)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Student attendance  (one row per student per day)
		// ----------------------------------------------------------------
		$sql_student_att = "CREATE TABLE " . self::student_attendance_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id BIGINT(20) UNSIGNED NOT NULL,
			class_id   BIGINT(20) UNSIGNED NOT NULL,
			att_date   DATE                NOT NULL,
			status     VARCHAR(20)         NOT NULL DEFAULT 'present',
			marked_by  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_student_date (student_id, att_date),
			KEY idx_class_date (class_id, att_date)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Teacher attendance  (one row per teacher per day)
		// ----------------------------------------------------------------
		$sql_teacher_att = "CREATE TABLE " . self::teacher_attendance_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			teacher_id BIGINT(20) UNSIGNED NOT NULL,
			att_date   DATE                NOT NULL,
			status     VARCHAR(20)         NOT NULL DEFAULT 'present',
			notes      TEXT,
			marked_by  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_teacher_date (teacher_id, att_date),
			KEY idx_att_date (att_date)
		) {$charset_collate};";

		// ----------------------------------------------------------------
		// Fee Modules
		// ----------------------------------------------------------------
		$sql_fee_structure = "CREATE TABLE " . self::fee_structure_table() . " (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_title      VARCHAR(255)        NOT NULL,
			amount         DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			frequency      ENUM('monthly','term','yearly') NOT NULL DEFAULT 'monthly',
			effective_from DATE                NOT NULL,
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id)
		) {$charset_collate};";

		$sql_fee_assignment = "CREATE TABLE " . self::student_fee_assignment_table() . " (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_structure_id BIGINT(20) UNSIGNED NOT NULL,
			academic_year    VARCHAR(20)         NOT NULL,
			assigned_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_student_fee_year (student_id, fee_structure_id, academic_year),
			KEY idx_academic_year (academic_year)
		) {$charset_collate};";

		$sql_fee_invoices = "CREATE TABLE " . self::fee_invoices_table() . " (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_structure_id BIGINT(20) UNSIGNED NOT NULL,
			invoice_month    VARCHAR(7)          NOT NULL, -- Format YYYY-MM
			academic_year    VARCHAR(20)         NOT NULL,
			due_date         DATE                NOT NULL,
			amount_due       DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			discount         DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			fine             DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			status           ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_student_structure_month (student_id, fee_structure_id, invoice_month),
			KEY idx_status_month (status, invoice_month),
			KEY idx_academic_year (academic_year)
		) {$charset_collate};";

		$sql_fee_payments = "CREATE TABLE " . self::fee_payments_table() . " (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id     BIGINT(20) UNSIGNED NOT NULL,
			paid_amount    DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			payment_date   DATE                NOT NULL,
			payment_method ENUM('cash','bank','cheque') NOT NULL DEFAULT 'cash',
			received_by    BIGINT(20) UNSIGNED NOT NULL,
			remarks        TEXT,
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_invoice_id (invoice_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_classes );
		dbDelta( $sql_subjects );
		dbDelta( $sql_students );
		dbDelta( $sql_results );
		dbDelta( $sql_teachers );
		dbDelta( $sql_class_teachers );
		dbDelta( $sql_student_att );
		dbDelta( $sql_teacher_att );
		dbDelta( $sql_fee_structure );
		dbDelta( $sql_fee_assignment );
		dbDelta( $sql_fee_invoices );
		dbDelta( $sql_fee_payments );

		$installed = get_option( self::SCHEMA_OPTION, '1.0' );

		// v1.0 → v2.0: add class_id column to students.
		if ( version_compare( $installed, '2.0', '<' ) ) {
			$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::students_table(), 0 ); // phpcs:ignore
			if ( ! in_array( 'class_id', $cols, true ) ) {
				$wpdb->query( 'ALTER TABLE ' . self::students_table() . ' ADD COLUMN class_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id' ); // phpcs:ignore
			}
		}

		// v2.0 → v3.0: add photo_id to students; add idx_notification_sent to results.
		if ( version_compare( $installed, '3.0', '<' ) ) {
			$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::students_table(), 0 ); // phpcs:ignore
			if ( ! in_array( 'photo_id', $cols, true ) ) {
				$wpdb->query( 'ALTER TABLE ' . self::students_table() . ' ADD COLUMN photo_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER status' ); // phpcs:ignore
			}
			// Add index only if it does not exist.
			$indexes = $wpdb->get_col( 'SHOW INDEX FROM ' . self::results_table() . ' WHERE Key_name = \'idx_notification_sent\'', 0 ); // phpcs:ignore
			if ( empty( $indexes ) ) {
				$wpdb->query( 'ALTER TABLE ' . self::results_table() . ' ADD KEY idx_notification_sent (notification_sent)' ); // phpcs:ignore
			}
		}

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Drop all plugin tables. Called from uninstall.php only.
	 */
	public static function drop_tables(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::teacher_attendance_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::student_attendance_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::class_teachers_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::teachers_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::results_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::students_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::subjects_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::classes_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::fee_payments_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::fee_invoices_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::student_fee_assignment_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::fee_structure_table() );
		// phpcs:enable
		delete_option( self::SCHEMA_OPTION );
	}

	// -----------------------------------------------------------------------
	// Table name helpers
	// -----------------------------------------------------------------------

	public static function classes_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_classes';
	}

	public static function subjects_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_subjects';
	}

	public static function students_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_students';
	}

	public static function results_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_results';
	}

	public static function teachers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_teachers';
	}

	public static function class_teachers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_class_teachers';
	}

	public static function student_attendance_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_student_attendance';
	}

	public static function teacher_attendance_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_teacher_attendance';
	}

	public static function fee_structure_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_fee_structure';
	}

	public static function student_fee_assignment_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_student_fee_assignment';
	}

	public static function fee_invoices_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_fee_invoices';
	}

	public static function fee_payments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_fee_payments';
	}

	// -----------------------------------------------------------------------
	// Classes CRUD
	// -----------------------------------------------------------------------

	/**
	 * Insert a new class and optionally its subjects.
	 *
	 * @param string   $name
	 * @param string[] $subjects  Array of subject name strings.
	 * @return int|false  New class ID or false on failure.
	 */
	public static function insert_class( string $name, array $subjects = [] ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::classes_table(),
			[ 'name' => sanitize_text_field( $name ) ],
			[ '%s' ]
		);
		if ( ! $inserted ) {
			return false;
		}
		$class_id = (int) $wpdb->insert_id;
		if ( ! empty( $subjects ) ) {
			self::replace_subjects( $class_id, $subjects );
		}
		return $class_id;
	}

	/**
	 * Get all classes (with subject count).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_classes(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT c.*, COUNT(s.id) AS subject_count
			   FROM ' . self::classes_table() . ' c
			   LEFT JOIN ' . self::subjects_table() . ' s ON s.class_id = c.id
			  GROUP BY c.id
			  ORDER BY c.name ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Fees CRUD
	// -----------------------------------------------------------------------

	/**
	 * Get all fee structures.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_fee_structures(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT fs.*, c.name as class_name
			   FROM ' . self::fee_structure_table() . ' fs
			   LEFT JOIN ' . self::classes_table() . ' c ON c.id = fs.class_id
			  ORDER BY fs.id DESC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	public static function insert_fee_structure( int $class_id, string $title, float $amount, string $frequency, string $effective_from ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::fee_structure_table(),
			[
				'class_id'       => $class_id,
				'fee_title'      => sanitize_text_field( $title ),
				'amount'         => $amount,
				'frequency'      => sanitize_text_field( $frequency ),
				'effective_from' => sanitize_text_field( $effective_from ),
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%f', '%s', '%s', '%s' ]
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	public static function update_fee_structure( int $id, int $class_id, string $title, float $amount, string $frequency, string $effective_from ): bool {
		global $wpdb;
		$updated = $wpdb->update(
			self::fee_structure_table(),
			[
				'class_id'       => $class_id,
				'fee_title'      => sanitize_text_field( $title ),
				'amount'         => $amount,
				'frequency'      => sanitize_text_field( $frequency ),
				'effective_from' => sanitize_text_field( $effective_from ),
			],
			[ 'id' => $id ],
			[ '%d', '%s', '%f', '%s', '%s' ],
			[ '%d' ]
		);
		return false !== $updated;
	}

	public static function delete_fee_structure( int $id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete(
			self::fee_structure_table(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		return false !== $deleted;
	}

	public static function get_fee_structure( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::fee_structure_table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function assign_student_fee( int $student_id, int $fee_structure_id, string $academic_year ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::student_fee_assignment_table(),
			[
				'student_id'       => $student_id,
				'fee_structure_id' => $fee_structure_id,
				'academic_year'    => sanitize_text_field( $academic_year ),
				'assigned_at'      => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	public static function void_invoice( int $invoice_id ): bool {
		global $wpdb;
		$updated = $wpdb->update(
			self::fee_invoices_table(),
			[ 'status' => 'void' ],
			[ 'id' => $invoice_id ],
			[ '%s' ],
			[ '%d' ]
		);
		return false !== $updated;
	}

	/**
	 * Get fee dashboard stats
	 * 
	 * @return array<string, mixed>
	 */
	public static function get_fee_dashboard_stats(): array {
		global $wpdb;
		$stats = [
			'total_collected' => 0,
			'total_due'       => 0,
			'unpaid_count'    => 0,
			'paid_count'      => 0,
		];

		$current_month = date('Y-m');

		// Total collected this month
		$collected = $wpdb->get_var( "
			SELECT SUM(paid_amount) 
			FROM " . self::fee_payments_table() . " 
			WHERE payment_date LIKE '{$current_month}%'
		" );
		$stats['total_collected'] = (float) $collected;

		// Stats for current month invoices
		$invoices_stats = $wpdb->get_row( "
			SELECT 
				SUM(amount_due + fine - discount) as total_due,
				SUM(CASE WHEN status IN ('unpaid', 'partial') THEN 1 ELSE 0 END) as unpaid_count,
				SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count
			FROM " . self::fee_invoices_table() . "
			WHERE invoice_month = '{$current_month}' AND status != 'void'
		", ARRAY_A );

		if ( $invoices_stats ) {
			$stats['total_due']    = (float) $invoices_stats['total_due'];
			$stats['unpaid_count'] = (int) $invoices_stats['unpaid_count'];
			$stats['paid_count']   = (int) $invoices_stats['paid_count'];
		}

		return $stats;
	}

	/**
	 * Get all invoices.
	 * 
	 * @return array
	 */
	public static function get_invoices( $args = [] ): array {
		global $wpdb;
		
		$where = ["i.status != 'void'"];
		
		if ( ! empty( $args['month'] ) ) {
			$where[] = $wpdb->prepare( "i.invoice_month = %s", $args['month'] );
		}
		
		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( "i.status = %s", $args['status'] );
		}
		
		// If searching by student name
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( "s.name LIKE %s", '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_sql = implode( ' AND ', $where );

		$query = "
			SELECT 
				i.*,
				s.name AS student_name,
				c.name AS class_name,
				(i.amount_due + i.fine - i.discount) AS net_due,
				COALESCE(SUM(p.paid_amount), 0) AS total_paid
			FROM " . self::fee_invoices_table() . " i
			JOIN " . self::students_table() . " s ON i.student_id = s.id
			LEFT JOIN " . self::classes_table() . " c ON s.class_id = c.id
			LEFT JOIN " . self::fee_payments_table() . " p ON i.id = p.invoice_id
			WHERE {$where_sql}
			GROUP BY i.id
			ORDER BY i.invoice_month DESC, s.name ASC
		";

		return $wpdb->get_results( $query, ARRAY_A ) ?: [];
	}

	/**
	 * Get unpaid invoices by student ID
	 */
	public static function get_unpaid_invoices_by_student( int $student_id ): array {
		global $wpdb;
		$query = $wpdb->prepare( "
			SELECT 
				i.*,
				(i.amount_due + i.fine - i.discount) AS net_due,
				COALESCE(SUM(p.paid_amount), 0) AS total_paid
			FROM " . self::fee_invoices_table() . " i
			LEFT JOIN " . self::fee_payments_table() . " p ON i.id = p.invoice_id
			WHERE i.student_id = %d AND i.status IN ('unpaid', 'partial')
			GROUP BY i.id
			ORDER BY i.invoice_month ASC
		", $student_id );

		return $wpdb->get_results( $query, ARRAY_A ) ?: [];
	}

	/**
	 * Get list of students with unpaid or partial invoices.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_defaulters(): array {
		global $wpdb;
		
		$query = $wpdb->prepare( "
			SELECT 
				s.id AS student_id,
				s.name AS student_name,
				s.parent_phone,
				c.name AS class_name,
				i.id AS invoice_id,
				i.invoice_month,
				i.amount_due,
				i.fine,
				i.discount,
				(i.amount_due + i.fine - i.discount) AS net_due,
				COALESCE(SUM(p.paid_amount), 0) AS total_paid,
				i.status
			FROM " . self::fee_invoices_table() . " i
			JOIN " . self::students_table() . " s ON i.student_id = s.id
			LEFT JOIN " . self::classes_table() . " c ON s.class_id = c.id
			LEFT JOIN " . self::fee_payments_table() . " p ON i.id = p.invoice_id
			WHERE i.status IN ('unpaid', 'partial')
			GROUP BY i.id
			ORDER BY c.name ASC, s.name ASC, i.invoice_month ASC
		" );
		
		$results = $wpdb->get_results( $query, ARRAY_A );
		return $results ?: [];
	}

	public static function add_fee_payment( int $invoice_id, float $amount, string $date, string $method, int $received_by, string $remarks = '' ): int|false {
		global $wpdb;
		
		$wpdb->query('START TRANSACTION');

		$inserted = $wpdb->insert(
			self::fee_payments_table(),
			[
				'invoice_id'     => $invoice_id,
				'paid_amount'    => $amount,
				'payment_date'   => sanitize_text_field( $date ),
				'payment_method' => sanitize_text_field( $method ),
				'received_by'    => $received_by,
				'remarks'        => sanitize_text_field( $remarks ),
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%f', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			$wpdb->query('ROLLBACK');
			return false;
		}

		$payment_id = (int) $wpdb->insert_id;

		// Recalculate invoice status
		$invoice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::fee_invoices_table() . " WHERE id = %d FOR UPDATE", $invoice_id ), ARRAY_A );
		if ( $invoice ) {
			$total_paid = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM " . self::fee_payments_table() . " WHERE invoice_id = %d", $invoice_id ) );
			$net_due    = (float) $invoice['amount_due'] + (float) $invoice['fine'] - (float) $invoice['discount'];
			
			$new_status = 'unpaid';
			if ( $total_paid >= $net_due ) {
				$new_status = 'paid';
			} elseif ( $total_paid > 0 ) {
				$new_status = 'partial';
			}

			$wpdb->update(
				self::fee_invoices_table(),
				[ 'status' => $new_status ],
				[ 'id' => $invoice_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		$wpdb->query('COMMIT');

		return $payment_id;
	}

	/**
	 * Get a single class by ID.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_class( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::classes_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Update a class name and replace its subjects.
	 *
	 * @param int      $id
	 * @param string   $name
	 * @param string[] $subjects
	 * @return bool
	 */
	public static function update_class( int $id, string $name, array $subjects = [] ): bool {
		global $wpdb;
		$result = $wpdb->update(
			self::classes_table(),
			[ 'name' => sanitize_text_field( $name ) ],
			[ 'id'   => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		self::replace_subjects( $id, $subjects );
		return $result !== false;
	}

	/**
	 * Delete a class and its subjects. Students in this class get class_id = 0.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_class( int $id ): bool {
		global $wpdb;
		// Unlink students — don't delete them.
		$wpdb->update( self::students_table(), [ 'class_id' => 0 ], [ 'class_id' => $id ], [ '%d' ], [ '%d' ] );
		// Remove subjects.
		$wpdb->delete( self::subjects_table(), [ 'class_id' => $id ], [ '%d' ] );
		// Remove class.
		$result = $wpdb->delete( self::classes_table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Lightweight dropdown: id + name for all classes.
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function get_classes_dropdown(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT id, name FROM ' . self::classes_table() . ' ORDER BY name ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Subjects CRUD
	// -----------------------------------------------------------------------

	/**
	 * Replace all subjects for a class (delete + re-insert) inside a transaction.
	 *
	 * @param int      $class_id
	 * @param string[] $subjects  Non-empty strings only.
	 */
	public static function replace_subjects( int $class_id, array $subjects ): void {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore
		$ok = true;
		$wpdb->delete( self::subjects_table(), [ 'class_id' => $class_id ], [ '%d' ] );
		foreach ( $subjects as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' !== $name ) {
				$inserted = $wpdb->insert(
					self::subjects_table(),
					[ 'class_id' => $class_id, 'subject_name' => $name ],
					[ '%d', '%s' ]
				);
				if ( false === $inserted ) {
					$ok = false;
					break;
				}
			}
		}
		if ( $ok ) {
			$wpdb->query( 'COMMIT' ); // phpcs:ignore
		} else {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore
		}
	}

	/**
	 * Get all subjects for a class.
	 *
	 * @param int $class_id
	 * @return array<int, array{id: string, subject_name: string}>
	 */
	public static function get_subjects_by_class( int $class_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, subject_name FROM ' . self::subjects_table() . ' WHERE class_id = %d ORDER BY id ASC',
				$class_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Students CRUD
	// -----------------------------------------------------------------------

	/**
	 * Insert a new student record.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false  New row ID on success, false on failure.
	 */
	public static function insert_student( array $data ): int|false {
		global $wpdb;
		$row = [
			'class_id'        => (int) ( $data['class_id'] ?? 0 ),
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'parent_phone'    => sanitize_text_field( $data['parent_phone'] ?? '' ),
			'enrollment_date' => sanitize_text_field( $data['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
			'status'          => in_array( $data['status'] ?? 'active', [ 'active', 'inactive', 'graduated' ], true )
								? $data['status']
								: 'active',
		];
		$formats = [ '%d', '%s', '%s', '%s', '%s' ];
		if ( ! empty( $data['photo_id'] ) ) {
			$row['photo_id']  = (int) $data['photo_id'];
			$formats[]        = '%d';
		}
		$inserted = $wpdb->insert( self::students_table(), $row, $formats );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Retrieve a paginated list of students with optional search/filter.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int    $per_page  Rows per page. Default 20.
	 *     @type int    $page      1-based page number. Default 1.
	 *     @type string $search    Partial name search.
	 *     @type string $status    Filter by status.
	 *     @type int    $class_id  Filter by class (0 = all).
	 * }
	 * @return array{ rows: array, total: int }
	 */
	public static function get_students( array $args = [] ): array {
		global $wpdb;

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page']     ?? 1  ) );
		$offset   = ( $page - 1 ) * $per_page;

		$st = self::students_table();
		$cl = self::classes_table();

		$where  = '1=1';
		$params = [];

		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND st.name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'active', 'inactive', 'graduated' ], true ) ) {
			$where   .= ' AND st.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['class_id'] ) ) {
			$where   .= ' AND st.class_id = %d';
			$params[] = (int) $args['class_id'];
		}

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$st} st WHERE {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) // phpcs:ignore
			: $wpdb->get_var( $count_sql ) );

		// Paginated rows — include class name via LEFT JOIN.
		$row_sql  = "SELECT st.*, cl.name AS class_name
		               FROM {$st} st
		               LEFT JOIN {$cl} cl ON cl.id = st.class_id
		              WHERE {$where}
		              ORDER BY st.id DESC
		              LIMIT %d OFFSET %d";
		$row_args = array_merge( $params, [ $per_page, $offset ] );
		$rows     = $wpdb->get_results( $wpdb->prepare( $row_sql, ...$row_args ), ARRAY_A ); // phpcs:ignore

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Get a single student by ID (includes class_name).
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_student( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT st.*, cl.name AS class_name
				   FROM ' . self::students_table() . ' st
				   LEFT JOIN ' . self::classes_table() . ' cl ON cl.id = st.class_id
				  WHERE st.id = %d',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Update an existing student record.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public static function update_student( int $id, array $data ): bool {
		global $wpdb;
		$row = [
			'class_id'        => (int) ( $data['class_id'] ?? 0 ),
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'parent_phone'    => sanitize_text_field( $data['parent_phone'] ?? '' ),
			'enrollment_date' => sanitize_text_field( $data['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
			'status'          => in_array( $data['status'] ?? 'active', [ 'active', 'inactive', 'graduated' ], true )
								? $data['status']
								: 'active',
		];
		$formats = [ '%d', '%s', '%s', '%s', '%s' ];
		if ( array_key_exists( 'photo_id', $data ) ) {
			$row['photo_id'] = $data['photo_id'] ? (int) $data['photo_id'] : null;
			$formats[]       = $data['photo_id'] ? '%d' : null;
		}
		$result = $wpdb->update( self::students_table(), $row, [ 'id' => $id ], $formats, [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Delete a student and cascade-delete their results.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_student( int $id ): bool {
		global $wpdb;
		// Clean up WP media attachment if a photo was stored.
		$photo_id = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT photo_id FROM ' . self::students_table() . ' WHERE id = %d', $id )
		);
		if ( $photo_id ) {
			wp_delete_attachment( $photo_id, true );
		}
		$wpdb->delete( self::results_table(), [ 'student_id' => $id ], [ '%d' ] );
		$wpdb->delete( self::student_attendance_table(), [ 'student_id' => $id ], [ '%d' ] );
		$result = $wpdb->delete( self::students_table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Return active students in a specific class (id, name, class_id).
	 *
	 * @param int $class_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_students_by_class( int $class_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, class_id, parent_phone, status FROM " . self::students_table()
				. " WHERE class_id = %d AND status = 'active' ORDER BY name ASC",
				$class_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get active students as a dropdown list, optionally filtered by class.
	 *
	 * @param int $class_id  0 = all active students.
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function get_students_dropdown( int $class_id = 0 ): array {
		global $wpdb;
		if ( $class_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name FROM " . self::students_table() . " WHERE status = 'active' AND class_id = %d ORDER BY name ASC",
					$class_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT id, name FROM " . self::students_table() . " WHERE status = 'active' ORDER BY name ASC",
				ARRAY_A
			);
		}
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Results CRUD
	// -----------------------------------------------------------------------

	/**
	 * Insert a new exam result.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public static function insert_result( array $data ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::results_table(),
			[
				'student_id'     => (int)   ( $data['student_id']     ?? 0 ),
				'subject'        => sanitize_text_field( $data['subject'] ?? '' ),
				'marks_obtained' => (float) ( $data['marks_obtained'] ?? 0 ),
				'total_marks'    => (float) ( $data['total_marks']    ?? 100 ),
				'exam_date'      => sanitize_text_field( $data['exam_date'] ?? current_time( 'Y-m-d' ) ),
			],
			[ '%d', '%s', '%f', '%f', '%s' ]
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Mark a result's notification as sent.
	 *
	 * @param int $result_id
	 * @return bool
	 */
	public static function mark_notification_sent( int $result_id ): bool {
		global $wpdb;
		$result = $wpdb->update(
			self::results_table(),
			[ 'notification_sent' => 1 ],
			[ 'id' => $result_id ],
			[ '%d' ],
			[ '%d' ]
		);
		return $result !== false;
	}

	/**
	 * Get paginated results for all students in a class.
	 *
	 * @param int                  $class_id
	 * @param array<string, mixed> $args {
	 *     @type int $per_page
	 *     @type int $page
	 *     @type int $student_id  Optional: further filter by one student.
	 * }
	 * @return array{ rows: array, total: int }
	 */
	public static function get_results_by_class( int $class_id, array $args = [] ): array {
		global $wpdb;

		$per_page = max( 1, (int) ( $args['per_page'] ?? 25 ) );
		$page     = max( 1, (int) ( $args['page']     ?? 1  ) );
		$offset   = ( $page - 1 ) * $per_page;

		$r  = self::results_table();
		$st = self::students_table();

		$where  = 'st.class_id = %d';
		$params = [ $class_id ];

		if ( ! empty( $args['student_id'] ) ) {
			$where   .= ' AND r.student_id = %d';
			$params[] = (int) $args['student_id'];
		}

		$count_sql = "SELECT COUNT(*) FROM {$r} r JOIN {$st} st ON st.id = r.student_id WHERE {$where}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ); // phpcs:ignore

		$row_sql  = "SELECT r.*, st.name AS student_name, st.parent_phone
		               FROM {$r} r
		               JOIN {$st} st ON st.id = r.student_id
		              WHERE {$where}
		              ORDER BY r.exam_date DESC, r.id DESC
		              LIMIT %d OFFSET %d";
		$row_args = array_merge( $params, [ $per_page, $offset ] );
		$rows     = $wpdb->get_results( $wpdb->prepare( $row_sql, ...$row_args ), ARRAY_A ); // phpcs:ignore

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Get all results for a single student (no pagination).
	 *
	 * @param int $student_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_results_by_student( int $student_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, st.name AS student_name, st.parent_phone
				   FROM ' . self::results_table() . ' r
				   JOIN ' . self::students_table() . ' st ON st.id = r.student_id
				  WHERE r.student_id = %d
				  ORDER BY r.exam_date DESC',
				$student_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get a results summary for a class, grouped by student then by subject.
	 *
	 * Returns an associative array keyed by student_id:
	 * [
	 *   student_id => [
	 *     'name'         => string,
	 *     'phone'        => string,
	 *     'entries'      => [ ['subject'=>..,'obtained'=>..,'total'=>..], ... ],
	 *     'sum_obtained' => float,
	 *     'sum_total'    => float,
	 *   ], ...
	 * ]
	 *
	 * @param int $class_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_results_summary_by_class( int $class_id, string $exam_date = '' ): array {
		global $wpdb;

		$query = 'SELECT r.student_id,
				        st.name              AS student_name,
				        st.parent_phone,
				        r.subject,
				        SUM(r.marks_obtained) AS obtained,
				        SUM(r.total_marks)    AS total_marks,
				        MAX(r.exam_date)      AS exam_date
				   FROM ' . self::results_table() . ' r
				   JOIN ' . self::students_table() . ' st ON st.id = r.student_id
				  WHERE st.class_id = %d';

		if ( ! empty( $exam_date ) ) {
			$query .= ' AND r.exam_date = %s';
			$query .= ' GROUP BY r.student_id, r.subject ORDER BY st.name ASC, r.subject ASC';
			$sql = $wpdb->prepare( $query, $class_id, $exam_date );
		} else {
			$query .= ' GROUP BY r.student_id, r.subject ORDER BY st.name ASC, r.subject ASC';
			$sql = $wpdb->prepare( $query, $class_id );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$summary = [];
		foreach ( $rows ?: [] as $row ) {
			$sid = (int) $row['student_id'];
			if ( ! isset( $summary[ $sid ] ) ) {
				$summary[ $sid ] = [
					'name'         => $row['student_name'],
					'phone'        => $row['parent_phone'],
					'entries'      => [],
					'sum_obtained' => 0.0,
					'sum_total'    => 0.0,
				];
			}
			$summary[ $sid ]['entries'][]      = [
				'subject'  => $row['subject'],
				'obtained' => (float) $row['obtained'],
				'total'    => (float) $row['total_marks'],
			];
			$summary[ $sid ]['sum_obtained'] += (float) $row['obtained'];
			$summary[ $sid ]['sum_total']    += (float) $row['total_marks'];
			// Keep the latest exam date seen for this student.
			if ( empty( $summary[ $sid ]['exam_date'] ) || $row['exam_date'] > $summary[ $sid ]['exam_date'] ) {
				$summary[ $sid ]['exam_date'] = $row['exam_date'];
			}
		}

		return $summary;
	}

	/**
	 * Get a list of past conducted exam dates for a specific class.
	 * Returns distinct exam dates along with the number of results recorded.
	 *
	 * @param int $class_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_exam_dates_by_class( int $class_id ): array {
		global $wpdb;

		$sql = "
			SELECT r.exam_date, COUNT(r.id) AS results_count
			  FROM " . self::results_table() . " r
			  JOIN " . self::students_table() . " st ON st.id = r.student_id
			 WHERE st.class_id = %d
			 GROUP BY r.exam_date
			 ORDER BY r.exam_date DESC
		";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $class_id ), ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Delete a single result.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_result( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( self::results_table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	// -----------------------------------------------------------------------
	// Teachers CRUD
	// -----------------------------------------------------------------------

	/**
	 * Insert a new teacher record and grant the noor_tms_teacher capability
	 * to the linked WP user.
	 *
	 * @param array<string, mixed> $data  Keys: wp_user_id, name, phone, is_active.
	 * @return int|false
	 */
	public static function insert_teacher( array $data ): int|false {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::teachers_table(),
			[
				'wp_user_id' => (int) ( $data['wp_user_id'] ?? 0 ),
				'name'       => sanitize_text_field( $data['name']  ?? '' ),
				'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
				'is_active'  => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			],
			[ '%d', '%s', '%s', '%d' ]
		);
		if ( ! $inserted ) {
			return false;
		}
		$teacher_id = (int) $wpdb->insert_id;
		// Grant teacher capability to the linked WP user.
		$user = get_user_by( 'id', (int) ( $data['wp_user_id'] ?? 0 ) );
		if ( $user ) {
			$user->add_cap( 'noor_tms_teacher' );
		}
		return $teacher_id;
	}

	/**
	 * Get all teachers (with class assignment count).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_teachers(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT t.*, COUNT(ct.id) AS class_count
			   FROM ' . self::teachers_table() . ' t
			   LEFT JOIN ' . self::class_teachers_table() . ' ct ON ct.teacher_id = t.id
			  GROUP BY t.id
			  ORDER BY t.name ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get a single teacher by ID.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_teacher( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::teachers_table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get a teacher record by their WordPress user ID.
	 *
	 * @param int $wp_user_id
	 * @return array<string, mixed>|null
	 */
	public static function get_teacher_by_user( int $wp_user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::teachers_table() . ' WHERE wp_user_id = %d', $wp_user_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Update a teacher record.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public static function update_teacher( int $id, array $data ): bool {
		global $wpdb;
		$result = $wpdb->update(
			self::teachers_table(),
			[
				'name'      => sanitize_text_field( $data['name']  ?? '' ),
				'phone'     => sanitize_text_field( $data['phone'] ?? '' ),
				'is_active' => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%d' ],
			[ '%d' ]
		);
		return $result !== false;
	}

	/**
	 * Delete a teacher and remove their WP capability.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_teacher( int $id ): bool {
		global $wpdb;
		$teacher = self::get_teacher( $id );
		if ( $teacher ) {
			$user = get_user_by( 'id', (int) $teacher['wp_user_id'] );
			if ( $user ) {
				$user->remove_cap( 'noor_tms_teacher' );
			}
		}
		$wpdb->delete( self::class_teachers_table(), [ 'teacher_id' => $id ], [ '%d' ] );
		$wpdb->delete( self::teacher_attendance_table(), [ 'teacher_id' => $id ], [ '%d' ] );
		$result = $wpdb->delete( self::teachers_table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Lightweight dropdown list: id + name for active teachers.
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function get_teachers_dropdown(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT id, name FROM " . self::teachers_table() . " WHERE is_active = 1 ORDER BY name ASC",
			ARRAY_A
		);
		return $rows ?: [];
	}

	// -----------------------------------------------------------------------
	// Class–Teacher assignments
	// -----------------------------------------------------------------------

	/**
	 * Replace all class assignments for a teacher.
	 *
	 * @param int   $teacher_id
	 * @param array $assignments  Each item: [ class_id, role_type, subject_id(optional) ]
	 */
	public static function save_teacher_assignments( int $teacher_id, array $assignments ): void {
		global $wpdb;
		$wpdb->delete( self::class_teachers_table(), [ 'teacher_id' => $teacher_id ], [ '%d' ] );
		foreach ( $assignments as $a ) {
			$class_id   = (int) ( $a['class_id']   ?? 0 );
			$role_type  = sanitize_key( $a['role_type']  ?? 'subject' );
			$subject_id = ! empty( $a['subject_id'] ) ? (int) $a['subject_id'] : null;
			if ( ! $class_id ) {
				continue;
			}
			$wpdb->insert(
				self::class_teachers_table(),
				[
					'teacher_id' => $teacher_id,
					'class_id'   => $class_id,
					'role_type'  => $role_type,
					'subject_id' => $subject_id,
				],
				[ '%d', '%d', '%s', $subject_id ? '%d' : null ]
			);
		}
	}

	/**
	 * Get class IDs assigned to a teacher.
	 *
	 * @param int $teacher_id
	 * @return int[]
	 */
	public static function get_teacher_class_ids( int $teacher_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT class_id FROM ' . self::class_teachers_table() . ' WHERE teacher_id = %d',
				$teacher_id
			)
		);
		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * Get all teacher assignments for a class (with teacher name).
	 *
	 * @param int $class_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_class_teacher_assignments( int $class_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ct.*, t.name AS teacher_name, t.phone AS teacher_phone
				   FROM ' . self::class_teachers_table() . ' ct
				   JOIN ' . self::teachers_table() . ' t ON t.id = ct.teacher_id
				  WHERE ct.class_id = %d
				  ORDER BY ct.role_type ASC, t.name ASC',
				$class_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get the full assignment rows for a teacher (with class name).
	 *
	 * @param int $teacher_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_teacher_assignments( int $teacher_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ct.*, c.name AS class_name, s.subject_name
				   FROM ' . self::class_teachers_table() . ' ct
				   JOIN ' . self::classes_table() . ' c ON c.id = ct.class_id
				   LEFT JOIN ' . self::subjects_table() . ' s ON s.id = ct.subject_id
				  WHERE ct.teacher_id = %d
				  ORDER BY c.name ASC, ct.role_type ASC',
				$teacher_id
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get all assignment rows across all teachers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_teacher_assignments(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT ct.*, c.name AS class_name, s.subject_name, t.name AS teacher_name
			   FROM ' . self::class_teachers_table() . ' ct
			   JOIN ' . self::classes_table() . ' c ON c.id = ct.class_id
			   JOIN ' . self::teachers_table() . ' t ON t.id = ct.teacher_id
			   LEFT JOIN ' . self::subjects_table() . ' s ON s.id = ct.subject_id
			  ORDER BY c.name ASC, ct.role_type ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get assigned classes and subjects grouped by type to easily filter in the UI.
	 *
	 * @return array{homeroom: array<int, int[]>, subject: array<int, array<int, string>>}
	 */
	public static function get_assigned_class_roles(): array {
		$assignments = self::get_all_teacher_assignments();
		
		$assigned = [
			'homeroom' => [],
			'subject'  => [],
		];

		foreach ( $assignments as $row ) {
			$t_id = (int) $row['teacher_id'];
			$c_id = (int) $row['class_id'];
			
			if ( 'homeroom' === $row['role_type'] ) {
				$assigned['homeroom'][$c_id] = $t_id; // class_id => teacher_id
			} else {
				$s_id = (int) $row['subject_id'];
				$assigned['subject'][$c_id . '_' . $s_id] = $t_id; // class_id_subject_id => teacher_id
			}
		}

		return $assigned;
	}

	// -----------------------------------------------------------------------
	// Student Attendance
	// -----------------------------------------------------------------------

	/**
	 * Upsert (insert or update) a single student attendance record.
	 *
	 * @param int    $student_id
	 * @param int    $class_id
	 * @param string $date       Y-m-d
	 * @param string $status     present|absent|late|excused
	 * @param int    $marked_by  WP user ID of recorder.
	 * @return bool
	 */
	public static function upsert_student_attendance(
		int    $student_id,
		int    $class_id,
		string $date,
		string $status,
		int    $marked_by
	): bool {
		global $wpdb;
		$allowed = [ 'present', 'absent', 'late', 'excused' ];
		$status  = in_array( $status, $allowed, true ) ? $status : 'present';

		// Try UPDATE first, then INSERT.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::student_attendance_table() . ' WHERE student_id = %d AND att_date = %s',
				$student_id, $date
			)
		);

		if ( $existing ) {
			$r = $wpdb->update(
				self::student_attendance_table(),
				[ 'status' => $status, 'marked_by' => $marked_by ],
				[ 'id' => (int) $existing ],
				[ '%s', '%d' ],
				[ '%d' ]
			);
		} else {
			$r = $wpdb->insert(
				self::student_attendance_table(),
				[
					'student_id' => $student_id,
					'class_id'   => $class_id,
					'att_date'   => $date,
					'status'     => $status,
					'marked_by'  => $marked_by,
				],
				[ '%d', '%d', '%s', '%s', '%d' ]
			);
		}
		return $r !== false;
	}

	/**
	 * Bulk-save attendance records for all students in a class on a given date.
	 *
	 * @param int    $class_id
	 * @param string $date
	 * @param array  $records   [ student_id => status, ... ]
	 * @param int    $marked_by
	 * @return int  Number of records saved.
	 */
	public static function bulk_save_student_attendance(
		int    $class_id,
		string $date,
		array  $records,
		int    $marked_by
	): int {
		$saved = 0;
		foreach ( $records as $rec ) {
			$student_id = (int)    ( $rec['student_id'] ?? 0 );
			$status     = (string) ( $rec['status']     ?? 'present' );
			if ( $student_id && self::upsert_student_attendance( $student_id, $class_id, $date, $status, $marked_by ) ) {
				$saved++;
			}
		}
		return $saved;
	}

	/**
	 * Get attendance records for a class on a specific date.
	 *
	 * Returns array keyed by student_id → status.
	 *
	 * @param int    $class_id
	 * @param string $date  Y-m-d
	 * @return array<int, string>
	 */
	public static function get_student_attendance_for_date( int $class_id, string $date ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT student_id, status FROM ' . self::student_attendance_table() . ' WHERE class_id = %d AND att_date = %s',
				$class_id, $date
			),
			ARRAY_A
		);
		$map = [];
		foreach ( $rows ?: [] as $row ) {
			$map[ (int) $row['student_id'] ] = $row['status'];
		}
		return $map;
	}

	/**
	 * Get the attendance summary for a given month, optionally filtered by class.
	 *
	 * Returns array: [ student_id => [ name, present, absent, late, excused, total_days, pct ] ]
	 *
	 * @param int      $month    1–12
	 * @param int      $year
	 * @param int|null $class_id Optional — filter to a single class when provided.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_student_attendance_summary( int $month, int $year, ?int $class_id = null ): array {
		global $wpdb;
		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = date( 'Y-m-t', mktime( 0, 0, 0, $month, 1, $year ) );

		if ( $class_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.student_id,
							st.name AS student_name,
							c.name  AS class_name,
							SUM(a.status = 'present') AS present,
							SUM(a.status = 'absent')  AS absent,
							SUM(a.status = 'late')    AS late,
							SUM(a.status = 'excused') AS excused,
							COUNT(*) AS total_days,
							GROUP_CONCAT(CONCAT(a.att_date, ':', a.status) SEPARATOR ',') AS daily_records
					   FROM " . self::student_attendance_table() . " a
					   JOIN " . self::students_table() . " st ON st.id = a.student_id
					   JOIN " . self::classes_table() . " c  ON c.id  = a.class_id
					  WHERE a.class_id = %d AND a.att_date BETWEEN %s AND %s
					  GROUP BY a.student_id
					  ORDER BY st.name ASC",
					$class_id, $from, $to
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.student_id,
							st.name AS student_name,
							c.name  AS class_name,
							SUM(a.status = 'present') AS present,
							SUM(a.status = 'absent')  AS absent,
							SUM(a.status = 'late')    AS late,
							SUM(a.status = 'excused') AS excused,
							COUNT(*) AS total_days,
							GROUP_CONCAT(CONCAT(a.att_date, ':', a.status) SEPARATOR ',') AS daily_records
					   FROM " . self::student_attendance_table() . " a
					   JOIN " . self::students_table() . " st ON st.id = a.student_id
					   JOIN " . self::classes_table() . " c  ON c.id  = a.class_id
					  WHERE a.att_date BETWEEN %s AND %s
					  GROUP BY a.student_id
					  ORDER BY st.name ASC",
					$from, $to
				),
				ARRAY_A
			);
		}

		$summary = [];
		foreach ( $rows ?: [] as $row ) {
			$sid     = (int) $row['student_id'];
			$total   = (int) $row['total_days'];
			$present = (int) $row['present'];
			
			$daily = [];
			if ( ! empty( $row['daily_records'] ) ) {
				$records = explode( ',', $row['daily_records'] );
				foreach ( $records as $rec ) {
					list( $date, $status ) = explode( ':', $rec );
					$daily[ $date ] = $status;
				}
			}

			$summary[ $sid ] = [
				'name'       => $row['student_name'],
				'class_name' => $row['class_name'],
				'present'    => $present,
				'absent'     => (int) $row['absent'],
				'late'       => (int) $row['late'],
				'excused'    => (int) $row['excused'],
				'total_days' => $total,
				'pct'        => $total > 0 ? round( ( $present / $total ) * 100, 1 ) : 0,
				'daily'      => $daily,
			];
		}
		return $summary;
	}

	// -----------------------------------------------------------------------
	// Teacher Attendance
	// -----------------------------------------------------------------------

	/**
	 * Upsert a teacher attendance record.
	 *
	 * @param int    $teacher_id
	 * @param string $date      Y-m-d
	 * @param string $status    present|absent|late|excused
	 * @param string $notes
	 * @param int    $marked_by WP user ID.
	 * @return bool
	 */
	public static function upsert_teacher_attendance(
		int    $teacher_id,
		string $date,
		string $status,
		string $notes,
		int    $marked_by
	): bool {
		global $wpdb;
		$allowed = [ 'present', 'absent', 'late', 'excused' ];
		$status  = in_array( $status, $allowed, true ) ? $status : 'present';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::teacher_attendance_table() . ' WHERE teacher_id = %d AND att_date = %s',
				$teacher_id, $date
			)
		);

		if ( $existing ) {
			$r = $wpdb->update(
				self::teacher_attendance_table(),
				[ 'status' => $status, 'notes' => sanitize_textarea_field( $notes ), 'marked_by' => $marked_by ],
				[ 'id' => (int) $existing ],
				[ '%s', '%s', '%d' ],
				[ '%d' ]
			);
		} else {
			$r = $wpdb->insert(
				self::teacher_attendance_table(),
				[
					'teacher_id' => $teacher_id,
					'att_date'   => $date,
					'status'     => $status,
					'notes'      => sanitize_textarea_field( $notes ),
					'marked_by'  => $marked_by,
				],
				[ '%d', '%s', '%s', '%s', '%d' ]
			);
		}
		return $r !== false;
	}

	/**
	 * Bulk-save teacher attendance for a given date.
	 *
	 * @param string $date
	 * @param array  $records  [ [ teacher_id, status, notes ], ... ]
	 * @param int    $marked_by
	 * @return int
	 */
	public static function bulk_save_teacher_attendance( string $date, array $records, int $marked_by ): int {
		$saved = 0;
		foreach ( $records as $rec ) {
			$tid  = (int)    ( $rec['teacher_id'] ?? 0 );
			$stat = (string) ( $rec['status']     ?? 'present' );
			$note = (string) ( $rec['notes']      ?? '' );
			if ( $tid && self::upsert_teacher_attendance( $tid, $date, $stat, $note, $marked_by ) ) {
				$saved++;
			}
		}
		return $saved;
	}

	/**
	 * Get teacher attendance records for a given month.
	 *
	 * @param int $month  1–12
	 * @param int $year
	 * @return array<int, array<string, mixed>>  Keyed by teacher_id.
	 */
	public static function get_teacher_attendance_summary( int $month, int $year ): array {
		global $wpdb;
		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = date( 'Y-m-t', mktime( 0, 0, 0, $month, 1, $year ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.teacher_id,
						t.name AS teacher_name,
						SUM(a.status = 'present') AS present,
						SUM(a.status = 'absent')  AS absent,
						SUM(a.status = 'late')    AS late,
						SUM(a.status = 'excused') AS excused,
						COUNT(*) AS total_days
				   FROM " . self::teacher_attendance_table() . " a
				   JOIN " . self::teachers_table() . " t ON t.id = a.teacher_id
				  WHERE a.att_date BETWEEN %s AND %s
				  GROUP BY a.teacher_id
				  ORDER BY t.name ASC",
				$from, $to
			),
			ARRAY_A
		);

		$summary = [];
		foreach ( $rows ?: [] as $row ) {
			$tid     = (int) $row['teacher_id'];
			$total   = (int) $row['total_days'];
			$present = (int) $row['present'];
			$summary[ $tid ] = [
				'name'       => $row['teacher_name'],
				'present'    => $present,
				'absent'     => (int) $row['absent'],
				'late'       => (int) $row['late'],
				'excused'    => (int) $row['excused'],
				'total_days' => $total,
				'pct'        => $total > 0 ? round( ( $present / $total ) * 100, 1 ) : 0,
			];
		}
		return $summary;
	}

	/**
	 * Get raw teacher attendance rows for a date (for mark form).
	 *
	 * Returns keyed by teacher_id → status.
	 *
	 * @param string $date
	 * @return array<int, string>
	 */
	public static function get_teacher_attendance_for_date( string $date ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT teacher_id, status FROM ' . self::teacher_attendance_table() . ' WHERE att_date = %s',
				$date
			),
			ARRAY_A
		);
		$map = [];
		foreach ( $rows ?: [] as $row ) {
			$map[ (int) $row['teacher_id'] ] = $row['status'];
		}
		return $map;
	}
}

