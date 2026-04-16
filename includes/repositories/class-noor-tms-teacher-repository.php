<?php
/**
 * Repository for teacher data and class-teacher assignments.
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class TeacherRepository
 *
 * Owns all CRUD for mms_teachers and mms_class_teachers tables.
 */
class TeacherRepository {

	// -----------------------------------------------------------------------
	// Table name helpers
	// -----------------------------------------------------------------------

	/** @return string Fully-qualified table name including DB prefix. */
	public static function teachers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_teachers';
	}

	/** @return string Fully-qualified table name including DB prefix. */
	public static function class_teachers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_class_teachers';
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
		$user       = get_user_by( 'id', (int) ( $data['wp_user_id'] ?? 0 ) );
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
		$wpdb->delete( AttendanceRepository::teacher_attendance_table(), [ 'teacher_id' => $id ], [ '%d' ] );
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
				   JOIN ' . ClassRepository::classes_table() . ' c ON c.id = ct.class_id
				   LEFT JOIN ' . ClassRepository::subjects_table() . ' s ON s.id = ct.subject_id
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
			   JOIN ' . ClassRepository::classes_table() . ' c ON c.id = ct.class_id
			   JOIN ' . self::teachers_table() . ' t ON t.id = ct.teacher_id
			   LEFT JOIN ' . ClassRepository::subjects_table() . ' s ON s.id = ct.subject_id
			  ORDER BY c.name ASC, ct.role_type ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Get assigned classes and subjects grouped by role type.
	 *
	 * @return array{homeroom: array<int, int>, subject: array<string, int>}
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
				$assigned['homeroom'][ $c_id ] = $t_id;
			} else {
				$s_id                                    = (int) $row['subject_id'];
				$assigned['subject'][ $c_id . '_' . $s_id ] = $t_id;
			}
		}

		return $assigned;
	}
}
