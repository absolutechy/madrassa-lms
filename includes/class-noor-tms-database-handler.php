<?php
/**
 * Database abstraction facade for Noor-TMS.
 *
 * All methods are static forwarders to the focused repository classes in
 * includes/repositories/. Existing call sites (admin classes, PublicController)
 * continue to work without changes.
 *
 * Custom tables managed (prefix + name):
 *   {prefix}mms_classes            → ClassRepository
 *   {prefix}mms_subjects           → ClassRepository
 *   {prefix}mms_students           → StudentRepository
 *   {prefix}mms_results            → ResultRepository
 *   {prefix}mms_teachers           → TeacherRepository
 *   {prefix}mms_class_teachers     → TeacherRepository
 *   {prefix}mms_student_attendance → AttendanceRepository
 *   {prefix}mms_teacher_attendance → AttendanceRepository
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

use Noor_TMS\Includes\Repositories\ClassRepository;
use Noor_TMS\Includes\Repositories\StudentRepository;
use Noor_TMS\Includes\Repositories\ResultRepository;
use Noor_TMS\Includes\Repositories\TeacherRepository;
use Noor_TMS\Includes\Repositories\AttendanceRepository;
use Noor_TMS\Includes\Repositories\FeeRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class DatabaseHandler
 */
class DatabaseHandler {

	private const SCHEMA_VERSION = '4.0';
	private const SCHEMA_OPTION  = 'noor_tms_db_version';

	// -----------------------------------------------------------------------
	// Schema management (not delegated — owns the DDL)
	// -----------------------------------------------------------------------

	/**
	 * Create (or upgrade) the plugin's custom tables via dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_classes = "CREATE TABLE " . self::classes_table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(255)        NOT NULL DEFAULT '',
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_name (name)
		) {$charset_collate};";

		$sql_subjects = "CREATE TABLE " . self::subjects_table() . " (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id     BIGINT(20) UNSIGNED NOT NULL,
			subject_name VARCHAR(255)        NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id)
		) {$charset_collate};";

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

		// ── Fee Management tables ─────────────────────────────────────────────
		$sql_fee_structure = "CREATE TABLE " . self::fee_structure_table() . " (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_title      VARCHAR(255)        NOT NULL DEFAULT '',
			amount         DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			fine_per_day   DECIMAL(8,2)        NOT NULL DEFAULT 0.00,
			frequency      ENUM('monthly','term','yearly') NOT NULL DEFAULT 'monthly',
			effective_from DATE                NOT NULL,
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_class_id (class_id),
			KEY idx_effective_from (effective_from)
		) {$charset_collate};";

		$sql_fee_assignment = "CREATE TABLE " . self::fee_assignment_table() . " (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_structure_id BIGINT(20) UNSIGNED NOT NULL,
			academic_year    VARCHAR(9)          NOT NULL DEFAULT '',
			assigned_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_student_year (student_id, academic_year),
			KEY idx_fee_structure_id (fee_structure_id)
		) {$charset_collate};";

		$sql_fee_invoices = "CREATE TABLE " . self::fee_invoices_table() . " (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id       BIGINT(20) UNSIGNED NOT NULL,
			fee_structure_id BIGINT(20) UNSIGNED NOT NULL,
			invoice_month    VARCHAR(7)          NOT NULL DEFAULT '',
			due_date         DATE                NOT NULL,
			amount_due       DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			discount         DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			fine             DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			status           ENUM('unpaid','partial','paid','voided') NOT NULL DEFAULT 'unpaid',
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_student_month (student_id, invoice_month),
			KEY idx_status (status),
			KEY idx_invoice_month (invoice_month),
			KEY idx_student_id (student_id)
		) {$charset_collate};";

		$sql_fee_payments = "CREATE TABLE " . self::fee_payments_table() . " (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id     BIGINT(20) UNSIGNED NOT NULL,
			paid_amount    DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			payment_date   DATE                NOT NULL,
			payment_method ENUM('cash','bank','cheque') NOT NULL DEFAULT 'cash',
			received_by    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			remarks        TEXT,
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_invoice_id (invoice_id),
			KEY idx_payment_date (payment_date)
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

		if ( version_compare( $installed, '2.0', '<' ) ) {
			$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::students_table(), 0 ); // phpcs:ignore
			if ( ! in_array( 'class_id', $cols, true ) ) {
				$wpdb->query( 'ALTER TABLE ' . self::students_table() . ' ADD COLUMN class_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id' ); // phpcs:ignore
			}
		}

		if ( version_compare( $installed, '3.0', '<' ) ) {
			$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::students_table(), 0 ); // phpcs:ignore
			if ( ! in_array( 'photo_id', $cols, true ) ) {
				$wpdb->query( 'ALTER TABLE ' . self::students_table() . ' ADD COLUMN photo_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER status' ); // phpcs:ignore
			}
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
		// Fee tables (use direct prefix to avoid autoloader dependency in uninstall.php).
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lms_fee_payments' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lms_fee_invoices' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lms_student_fee_assignment' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'lms_fee_structure' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::teacher_attendance_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::student_attendance_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::class_teachers_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::teachers_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::results_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::students_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::subjects_table() );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::classes_table() );
		// phpcs:enable
		delete_option( self::SCHEMA_OPTION );
	}

	// -----------------------------------------------------------------------
	// Table name helpers (delegated to repositories)
	// -----------------------------------------------------------------------

	// ── Fee table name helpers ────────────────────────────────────────────────
	/** @return string */
	public static function fee_structure_table(): string        { return FeeRepository::fee_structure_table(); }
	/** @return string */
	public static function fee_assignment_table(): string       { return FeeRepository::fee_assignment_table(); }
	/** @return string */
	public static function fee_invoices_table(): string         { return FeeRepository::fee_invoices_table(); }
	/** @return string */
	public static function fee_payments_table(): string         { return FeeRepository::fee_payments_table(); }

	/** @return string Fully-qualified table name. */
	public static function classes_table(): string              { return ClassRepository::classes_table(); }
	/** @return string Fully-qualified table name. */
	public static function subjects_table(): string             { return ClassRepository::subjects_table(); }
	/** @return string Fully-qualified table name. */
	public static function students_table(): string             { return StudentRepository::students_table(); }
	/** @return string Fully-qualified table name. */
	public static function results_table(): string              { return ResultRepository::results_table(); }
	/** @return string Fully-qualified table name. */
	public static function teachers_table(): string             { return TeacherRepository::teachers_table(); }
	/** @return string Fully-qualified table name. */
	public static function class_teachers_table(): string       { return TeacherRepository::class_teachers_table(); }
	/** @return string Fully-qualified table name. */
	public static function student_attendance_table(): string   { return AttendanceRepository::student_attendance_table(); }
	/** @return string Fully-qualified table name. */
	public static function teacher_attendance_table(): string   { return AttendanceRepository::teacher_attendance_table(); }

	// -----------------------------------------------------------------------
	// Classes (delegated to ClassRepository)
	// -----------------------------------------------------------------------

	public static function insert_class( string $name, array $subjects = [] ): int|false {
		return ClassRepository::insert_class( $name, $subjects );
	}

	public static function get_classes(): array {
		return ClassRepository::get_classes();
	}

	public static function get_class( int $id ): ?array {
		return ClassRepository::get_class( $id );
	}

	public static function update_class( int $id, string $name, array $subjects = [] ): bool {
		return ClassRepository::update_class( $id, $name, $subjects );
	}

	public static function delete_class( int $id ): bool {
		return ClassRepository::delete_class( $id );
	}

	public static function get_classes_dropdown(): array {
		return ClassRepository::get_classes_dropdown();
	}

	public static function replace_subjects( int $class_id, array $subjects ): void {
		ClassRepository::replace_subjects( $class_id, $subjects );
	}

	public static function get_subjects_by_class( int $class_id ): array {
		return ClassRepository::get_subjects_by_class( $class_id );
	}

	// -----------------------------------------------------------------------
	// Students (delegated to StudentRepository)
	// -----------------------------------------------------------------------

	public static function insert_student( array $data ): int|false {
		return StudentRepository::insert_student( $data );
	}

	public static function get_students( array $args = [] ): array {
		return StudentRepository::get_students( $args );
	}

	public static function get_student( int $id ): ?array {
		return StudentRepository::get_student( $id );
	}

	public static function update_student( int $id, array $data ): bool {
		return StudentRepository::update_student( $id, $data );
	}

	public static function delete_student( int $id ): bool {
		return StudentRepository::delete_student( $id );
	}

	public static function get_students_by_class( int $class_id ): array {
		return StudentRepository::get_students_by_class( $class_id );
	}

	public static function get_students_dropdown( int $class_id = 0 ): array {
		return StudentRepository::get_students_dropdown( $class_id );
	}

	// -----------------------------------------------------------------------
	// Results (delegated to ResultRepository)
	// -----------------------------------------------------------------------

	public static function insert_result( array $data ): int|false {
		return ResultRepository::insert_result( $data );
	}

	public static function mark_notification_sent( int $result_id ): bool {
		return ResultRepository::mark_notification_sent( $result_id );
	}

	public static function get_results_by_class( int $class_id, array $args = [] ): array {
		return ResultRepository::get_results_by_class( $class_id, $args );
	}

	public static function get_results_by_student( int $student_id ): array {
		return ResultRepository::get_results_by_student( $student_id );
	}

	public static function get_results_summary_by_class( int $class_id, string $exam_date = '' ): array {
		return ResultRepository::get_results_summary_by_class( $class_id, $exam_date );
	}

	public static function get_exam_dates_by_class( int $class_id ): array {
		return ResultRepository::get_exam_dates_by_class( $class_id );
	}

	public static function get_result_class_id( int $result_id ): ?int {
		return ResultRepository::get_result_class_id( $result_id );
	}

	public static function delete_result( int $id ): bool {
		return ResultRepository::delete_result( $id );
	}

	// -----------------------------------------------------------------------
	// Teachers (delegated to TeacherRepository)
	// -----------------------------------------------------------------------

	public static function insert_teacher( array $data ): int|false {
		return TeacherRepository::insert_teacher( $data );
	}

	public static function get_teachers(): array {
		return TeacherRepository::get_teachers();
	}

	public static function get_teacher( int $id ): ?array {
		return TeacherRepository::get_teacher( $id );
	}

	public static function get_teacher_by_user( int $wp_user_id ): ?array {
		return TeacherRepository::get_teacher_by_user( $wp_user_id );
	}

	public static function update_teacher( int $id, array $data ): bool {
		return TeacherRepository::update_teacher( $id, $data );
	}

	public static function delete_teacher( int $id ): bool {
		return TeacherRepository::delete_teacher( $id );
	}

	public static function get_teachers_dropdown(): array {
		return TeacherRepository::get_teachers_dropdown();
	}

	public static function save_teacher_assignments( int $teacher_id, array $assignments ): void {
		TeacherRepository::save_teacher_assignments( $teacher_id, $assignments );
	}

	public static function get_teacher_class_ids( int $teacher_id ): array {
		return TeacherRepository::get_teacher_class_ids( $teacher_id );
	}

	public static function get_class_teacher_assignments( int $class_id ): array {
		return TeacherRepository::get_class_teacher_assignments( $class_id );
	}

	public static function get_teacher_assignments( int $teacher_id ): array {
		return TeacherRepository::get_teacher_assignments( $teacher_id );
	}

	public static function get_all_teacher_assignments(): array {
		return TeacherRepository::get_all_teacher_assignments();
	}

	public static function get_assigned_class_roles(): array {
		return TeacherRepository::get_assigned_class_roles();
	}

	// -----------------------------------------------------------------------
	// Attendance (delegated to AttendanceRepository)
	// -----------------------------------------------------------------------

	public static function upsert_student_attendance( int $student_id, int $class_id, string $date, string $status, int $marked_by ): bool {
		return AttendanceRepository::upsert_student_attendance( $student_id, $class_id, $date, $status, $marked_by );
	}

	public static function bulk_save_student_attendance( int $class_id, string $date, array $records, int $marked_by ): int {
		return AttendanceRepository::bulk_save_student_attendance( $class_id, $date, $records, $marked_by );
	}

	public static function get_student_attendance_for_date( int $class_id, string $date ): array {
		return AttendanceRepository::get_student_attendance_for_date( $class_id, $date );
	}

	public static function get_student_attendance_summary( int $month, int $year, ?int $class_id = null ): array {
		return AttendanceRepository::get_student_attendance_summary( $month, $year, $class_id );
	}

	public static function upsert_teacher_attendance( int $teacher_id, string $date, string $status, string $notes, int $marked_by ): bool {
		return AttendanceRepository::upsert_teacher_attendance( $teacher_id, $date, $status, $notes, $marked_by );
	}

	public static function bulk_save_teacher_attendance( string $date, array $records, int $marked_by ): int {
		return AttendanceRepository::bulk_save_teacher_attendance( $date, $records, $marked_by );
	}

	public static function get_teacher_attendance_summary( int $month, int $year ): array {
		return AttendanceRepository::get_teacher_attendance_summary( $month, $year );
	}

	public static function get_teacher_attendance_for_date( string $date ): array {
		return AttendanceRepository::get_teacher_attendance_for_date( $date );
	}

	// -----------------------------------------------------------------------
	// Fee Management (delegated to FeeRepository)
	// -----------------------------------------------------------------------

	public static function insert_fee_structure( array $data ): int|false {
		return FeeRepository::insert_fee_structure( $data );
	}

	public static function update_fee_structure( int $id, array $data ): bool {
		return FeeRepository::update_fee_structure( $id, $data );
	}

	public static function delete_fee_structure( int $id ): bool {
		return FeeRepository::delete_fee_structure( $id );
	}

	public static function get_fee_structures( int $class_id = 0 ): array {
		return FeeRepository::get_fee_structures( $class_id );
	}

	public static function get_fee_structure( int $id ): ?array {
		return FeeRepository::get_fee_structure( $id );
	}

	public static function generate_invoices_for_month( string $month, string $academic_year ): int {
		return FeeRepository::generate_invoices_for_month( $month, $academic_year );
	}
}
