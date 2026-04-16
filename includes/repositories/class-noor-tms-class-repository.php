<?php
/**
 * Repository for classes and subjects data.
 *
 * @package Noor_TMS\Includes\Repositories
 */

namespace Noor_TMS\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class ClassRepository
 *
 * Owns all CRUD for the mms_classes and mms_subjects tables.
 */
class ClassRepository {

	// -----------------------------------------------------------------------
	// Table name helpers
	// -----------------------------------------------------------------------

	/** @return string Fully-qualified table name including DB prefix. */
	public static function classes_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_classes';
	}

	/** @return string Fully-qualified table name including DB prefix. */
	public static function subjects_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mms_subjects';
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
		$wpdb->update( StudentRepository::students_table(), [ 'class_id' => 0 ], [ 'class_id' => $id ], [ '%d' ], [ '%d' ] );
		$wpdb->delete( self::subjects_table(), [ 'class_id' => $id ], [ '%d' ] );
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
}
