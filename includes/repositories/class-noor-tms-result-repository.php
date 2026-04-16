<?php
/**
 * Repository for exam result data.
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class ResultRepository
 *
 * Owns all CRUD for the mms_results table.
 */
class ResultRepository {

	// -----------------------------------------------------------------------
	// Table name helper
	// -----------------------------------------------------------------------

	/** @return string Fully-qualified table name including DB prefix. */
	public static function results_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_results';
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
		$st = StudentRepository::students_table();

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
				   JOIN ' . StudentRepository::students_table() . ' st ON st.id = r.student_id
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
	 * @param int    $class_id
	 * @param string $exam_date  Optional Y-m-d filter.
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
				   JOIN ' . StudentRepository::students_table() . ' st ON st.id = r.student_id
				  WHERE st.class_id = %d';

		if ( ! empty( $exam_date ) ) {
			$query .= ' AND r.exam_date = %s';
			$query .= ' GROUP BY r.student_id, r.subject ORDER BY st.name ASC, r.subject ASC';
			$sql    = $wpdb->prepare( $query, $class_id, $exam_date );
		} else {
			$query .= ' GROUP BY r.student_id, r.subject ORDER BY st.name ASC, r.subject ASC';
			$sql    = $wpdb->prepare( $query, $class_id );
		}

		$rows    = $wpdb->get_results( $sql, ARRAY_A );
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
			$summary[ $sid ]['entries'][]     = [
				'subject'  => $row['subject'],
				'obtained' => (float) $row['obtained'],
				'total'    => (float) $row['total_marks'],
			];
			$summary[ $sid ]['sum_obtained'] += (float) $row['obtained'];
			$summary[ $sid ]['sum_total']    += (float) $row['total_marks'];
			if ( empty( $summary[ $sid ]['exam_date'] ) || $row['exam_date'] > $summary[ $sid ]['exam_date'] ) {
				$summary[ $sid ]['exam_date'] = $row['exam_date'];
			}
		}

		return $summary;
	}

	/**
	 * Get distinct exam dates with result counts for a class.
	 *
	 * @param int $class_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_exam_dates_by_class( int $class_id ): array {
		global $wpdb;

		$sql = "
			SELECT r.exam_date, COUNT(r.id) AS results_count
			  FROM " . self::results_table() . " r
			  JOIN " . StudentRepository::students_table() . " st ON st.id = r.student_id
			 WHERE st.class_id = %d
			 GROUP BY r.exam_date
			 ORDER BY r.exam_date DESC
		";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $class_id ), ARRAY_A );
		return $results ?: [];
	}

	/**
	 * Get the class_id of the student linked to a result.
	 * Returns null when the result does not exist.
	 *
	 * @param int $result_id
	 * @return int|null
	 */
	public static function get_result_class_id( int $result_id ): ?int {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT st.class_id
				   FROM ' . self::results_table() . ' r
				   JOIN ' . StudentRepository::students_table() . ' st ON st.id = r.student_id
				  WHERE r.id = %d',
				$result_id
			),
			OBJECT
		);
		return $row ? (int) $row->class_id : null;
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
}
