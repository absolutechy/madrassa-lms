<?php
/**
 * Repository for student and teacher attendance data.
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class AttendanceRepository
 *
 * Owns all CRUD for mms_student_attendance and mms_teacher_attendance tables.
 */
class AttendanceRepository {

	// -----------------------------------------------------------------------
	// Table name helpers
	// -----------------------------------------------------------------------

	/** @return string Fully-qualified table name including DB prefix. */
	public static function student_attendance_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_student_attendance';
	}

	/** @return string Fully-qualified table name including DB prefix. */
	public static function teacher_attendance_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_teacher_attendance';
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
	 * @param array  $records   [ [ student_id => int, status => string ], ... ]
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
	 * @param int      $month    1–12
	 * @param int      $year
	 * @param int|null $class_id Optional class filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_student_attendance_summary( int $month, int $year, ?int $class_id = null ): array {
		global $wpdb;
		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = date( 'Y-m-t', mktime( 0, 0, 0, $month, 1, $year ) );

		$st = StudentRepository::students_table();
		$cl = ClassRepository::classes_table();
		$at = self::student_attendance_table();

		$select = "SELECT a.student_id,
				st.name AS student_name,
				c.name  AS class_name,
				SUM(a.status = 'present') AS present,
				SUM(a.status = 'absent')  AS absent,
				SUM(a.status = 'late')    AS late,
				SUM(a.status = 'excused') AS excused,
				COUNT(*) AS total_days,
				GROUP_CONCAT(CONCAT(a.att_date, ':', a.status) SEPARATOR ',') AS daily_records
		   FROM {$at} a
		   JOIN {$st} st ON st.id = a.student_id
		   JOIN {$cl} c  ON c.id  = a.class_id";

		if ( $class_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$select . ' WHERE a.class_id = %d AND a.att_date BETWEEN %s AND %s GROUP BY a.student_id ORDER BY st.name ASC',
					$class_id, $from, $to
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$select . ' WHERE a.att_date BETWEEN %s AND %s GROUP BY a.student_id ORDER BY st.name ASC',
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
				foreach ( explode( ',', $row['daily_records'] ) as $rec ) {
					[ $att_date, $att_status ] = explode( ':', $rec );
					$daily[ $att_date ] = $att_status;
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
				   JOIN " . TeacherRepository::teachers_table() . " t ON t.id = a.teacher_id
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
	 * Get raw teacher attendance rows for a date (keyed by teacher_id → status).
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
