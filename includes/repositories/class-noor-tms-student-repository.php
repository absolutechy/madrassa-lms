<?php
/**
 * Repository for student data.
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class StudentRepository
 *
 * Owns all CRUD for the mms_students table.
 */
class StudentRepository {

	// -----------------------------------------------------------------------
	// Table name helper
	// -----------------------------------------------------------------------

	/** @return string Fully-qualified table name including DB prefix. */
	public static function students_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_students';
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
			$row['photo_id'] = (int) $data['photo_id'];
			$formats[]       = '%d';
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
		$cl = ClassRepository::classes_table();

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

		$count_sql = "SELECT COUNT(*) FROM {$st} st WHERE {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) // phpcs:ignore
			: $wpdb->get_var( $count_sql ) );

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
				   LEFT JOIN ' . ClassRepository::classes_table() . ' cl ON cl.id = st.class_id
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
	 * Delete a student and cascade-delete their results and attendance.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_student( int $id ): bool {
		global $wpdb;
		$photo_id = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT photo_id FROM ' . self::students_table() . ' WHERE id = %d', $id )
		);
		if ( $photo_id ) {
			wp_delete_attachment( $photo_id, true );
		}
		$wpdb->delete( ResultRepository::results_table(), [ 'student_id' => $id ], [ '%d' ] );
		$wpdb->delete( AttendanceRepository::student_attendance_table(), [ 'student_id' => $id ], [ '%d' ] );
		$result = $wpdb->delete( self::students_table(), [ 'id' => $id ], [ '%d' ] );
		return $result !== false;
	}

	/**
	 * Return active students in a specific class.
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
}
