<?php
/**
 * Noor-TMS academic data seeder.
 *
 * Usage:
 *   php scripts/seed-academic-data.php --force
 *
 * This script removes the current academic / results / attendance / fee data
 * from Noor-TMS tables and seeds the requested Banin / Banaat structure.
 *
 * Run from the plugin directory.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$argv = $_SERVER['argv'] ?? [];
if ( ! in_array( '--force', $argv, true ) ) {
	echo "Usage: php scripts/seed-academic-data.php --force\n";
	echo "Warning: this will delete existing Noor-TMS academic data before seeding new records.\n";
	exit( 1 );
}

$plugin_dir = dirname( __DIR__ );
$wp_root    = dirname( $plugin_dir, 3 );
require_once $wp_root . '/wp-load.php';

if ( ! class_exists( '\Noor_TMS\Includes\DatabaseHandler' ) ) {
	require_once $plugin_dir . '/includes/class-noor-tms-database-handler.php';
}

global $wpdb;

/**
 * Delete all rows from a table and reset its auto-increment counter.
 */
function noor_tms_seed_clear_table( string $table_name ): void {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . $table_name ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( 'ALTER TABLE ' . $table_name . ' AUTO_INCREMENT = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Seed a category and return its ID.
 *
 * @param array<string, mixed> $data
 */
function noor_tms_seed_category( array $data ): int {
	$category_id = \Noor_TMS\Includes\DatabaseHandler::insert_category( $data );
	if ( ! $category_id ) {
		throw new RuntimeException( 'Failed to insert category: ' . (string) ( $data['name'] ?? '' ) );
	}
	return (int) $category_id;
}

/**
 * Seed a class and return its ID.
 *
 * @param array<string, mixed> $data
 */
function noor_tms_seed_class( string $name, array $data ): int {
	$class_id = \Noor_TMS\Includes\DatabaseHandler::insert_class( $name, [], $data );
	if ( ! $class_id ) {
		throw new RuntimeException( 'Failed to insert class: ' . $name );
	}
	return (int) $class_id;
}

/**
 * Seed a student and return its ID.
 *
 * @param array<string, mixed> $data
 */
function noor_tms_seed_student( array $data ): int {
	$student_id = \Noor_TMS\Includes\DatabaseHandler::insert_student( $data );
	if ( ! $student_id ) {
		throw new RuntimeException( 'Failed to insert student: ' . (string) ( $data['name'] ?? '' ) );
	}
	return (int) $student_id;
}

/**
 * Seed demo students for a class.
 *
 * @param string[] $student_names
 */
function noor_tms_seed_students_for_class( array $student_names, int $class_id, int $category_id, int $subcategory_id, string $account_type ): void {
	$gender = 'banaat' === $account_type ? 'female' : 'male';
	foreach ( $student_names as $index => $student_name ) {
		noor_tms_seed_student(
			[
				'class_id'        => $class_id,
				'name'            => $student_name,
				'parent_phone'    => '+92300' . str_pad( (string) ( $class_id * 10 + $index + 1 ), 7, '0', STR_PAD_LEFT ),
				'gender'          => $gender,
				'account_type'    => $account_type,
				'category_id'     => $category_id,
				'subcategory_id'  => $subcategory_id,
				'enrollment_date' => current_time( 'Y-m-d' ),
				'status'          => 'active',
			]
		);
	}
}

/**
 * Pick normal demo names for seeded students.
 *
 * @return string[]
 */
function noor_tms_demo_student_names( string $account_type, int $count = 2 ): array {
	$boys = [
		'Muhammad Ahmed',
		'Abdullah Khan',
		'Omar Farooq',
		'Bilal Hussain',
		'Usman Ali',
		'Zain Siddiqui',
		'Haris Javed',
		'Ibrahim Noor',
		'Saad Rehman',
		'Yusuf Khan',
	];

	$girls = [
		'Ayesha Noor',
		'Fatima Zahra',
		'Maryam Ali',
		'Khadija Noor',
		'Zainab Khan',
		'Hira Ahmed',
		'Areeba Hassan',
		'Isra Javed',
		'Sana Rehman',
		'Hafsa Umar',
	];

	$pool = 'banaat' === $account_type ? $girls : $boys;
	$names = [];
	for ( $i = 0; $i < $count; $i++ ) {
		$names[] = $pool[ $i % count( $pool ) ];
	}

	return $names;
}

$tables_to_clear = [
	\Noor_TMS\Includes\DatabaseHandler::results_table(),
	\Noor_TMS\Includes\DatabaseHandler::student_attendance_table(),
	\Noor_TMS\Includes\DatabaseHandler::teacher_attendance_table(),
	\Noor_TMS\Includes\DatabaseHandler::class_teachers_table(),
	\Noor_TMS\Includes\DatabaseHandler::students_table(),
	\Noor_TMS\Includes\DatabaseHandler::subjects_table(),
	\Noor_TMS\Includes\DatabaseHandler::classes_table(),
	\Noor_TMS\Includes\DatabaseHandler::categories_table(),
	\Noor_TMS\Includes\DatabaseHandler::fee_payments_table(),
	\Noor_TMS\Includes\DatabaseHandler::fee_invoices_table(),
	\Noor_TMS\Includes\DatabaseHandler::student_fee_assignment_table(),
	\Noor_TMS\Includes\DatabaseHandler::fee_structure_table(),
];

$seed_plan = [
	'banin' => [
		[
			'name'         => 'School',
			'is_school_type'=> 1,
			'sort_order'   => 1,
			'children'     => [
				[
					'name'      => 'Baqaida School Nizam',
					'sort_order'=> 1,
					'subjects'   => [ 'Quran', 'Urdu', 'English', 'Mathematics', 'Islamiat' ],
					'classes'   => [
						'Nursery',
						'KG',
						'Class 1',
						'Class 2',
						'Class 3',
						'Class 4',
						'Class 5',
						'Class 6',
						'Class 7',
						'Class 8',
					],
				],
				[
					'name'      => '9th & 10th',
					'sort_order'=> 2,
					'subjects'   => [ 'Quran', 'Urdu', 'English', 'Mathematics', 'Islamiat', 'Science' ],
					'classes'   => [ 'Class 9', 'Class 10' ],
				],
			],
		],
		[
			'name'          => 'Quran',
			'is_school_type'=> 0,
			'sort_order'    => 2,
			'children'      => [
				[ 'name' => 'Dawr',           'sort_order' => 1, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Dawr' ] ],
				[ 'name' => 'Hifz',           'sort_order' => 2, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Hifz' ] ],
				[ 'name' => 'Nazra',          'sort_order' => 3, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Nazra' ] ],
				[ 'name' => 'Noorani Qaida',  'sort_order' => 4, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Noorani Qaida' ] ],
			],
		],
		[
			'name'          => 'Kutub',
			'is_school_type'=> 1,
			'sort_order'    => 3,
			'children'      => [
				[
					'name'      => 'Dars-e-Nizami',
					'sort_order'=> 1,
					'subjects'   => [ 'Nahw', 'Sarf', 'Fiqh', 'Hadith', 'Tafseer', 'Aqeedah' ],
					'classes'   => [
						'Ola (Yr 1)',
						'Saniya (Yr 2)',
						'Thalitha (Yr 3)',
						'Rabia (Yr 4)',
						'Khamisa (Yr 5)',
						'Sadisa (Yr 6)',
						'Sabia (Yr 7)',
						'Thamaniya (Yr 8)',
					],
				],
				[
					'name'      => 'Nisab Tajweed lil Huffaz',
					'sort_order'=> 2,
					'subjects'   => [ 'Tajweed', 'Hifz Revision' ],
					'classes'   => [ 'Nisab Tajweed lil Huffaz' ],
				],
				[
					'name'      => 'Huffaz Education',
					'sort_order'=> 3,
					'subjects'   => [ 'Quran', 'Tajweed', 'Arabic', 'Adab' ],
					'classes'   => [
						'Nursery',
						'KG',
						'Class 1',
						'Class 2',
						'Class 3',
						'Class 4',
						'Class 5',
						'Class 6',
						'Class 7',
						'Class 8',
						'Class 9',
						'Class 10',
					],
				],
			],
		],
	],
	'banaat' => [
		[
			'name'          => 'School',
			'is_school_type'=> 1,
			'sort_order'    => 1,
			'children'      => [
				[
					'name'      => 'Baqaida School Nizam',
					'sort_order'=> 1,
					'subjects'   => [ 'Quran', 'Urdu', 'English', 'Mathematics', 'Islamiat' ],
					'classes'   => [
						'Nursery',
						'KG',
						'Class 1',
						'Class 2',
						'Class 3',
						'Class 4',
						'Class 5',
						'Class 6',
						'Class 7',
						'Class 8',
					],
				],
				[
					'name'      => 'Coaching Nizam',
					'sort_order'=> 2,
					'subjects'   => [ 'English', 'Mathematics', 'Computer' ],
					'classes'   => [],
				],
				[
					'name'      => '9th & 10th',
					'sort_order'=> 3,
					'subjects'   => [ 'Quran', 'Urdu', 'English', 'Mathematics', 'Islamiat', 'Science' ],
					'classes'   => [ 'Class 9', 'Class 10' ],
				],
			],
		],
		[
			'name'          => 'Quran',
			'is_school_type'=> 0,
			'sort_order'    => 2,
			'children'      => [
				[ 'name' => 'Hifz',              'sort_order' => 1, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Hifz' ] ],
				[ 'name' => 'Nazra',             'sort_order' => 2, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Nazra' ] ],
				[ 'name' => 'Noorani Qaida',     'sort_order' => 3, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Noorani Qaida' ] ],
				[ 'name' => 'Tajweed (older women)', 'sort_order' => 4, 'max_marks' => 100, 'pass_marks' => 50, 'subjects' => [ 'Tajweed' ] ],
			],
		],
		[
			'name'          => 'Kutub',
			'is_school_type'=> 1,
			'sort_order'    => 3,
			'children'      => [
				[
					'name'      => 'Do-sala Dirasat',
					'sort_order'=> 1,
					'subjects'   => [ 'Arabic', 'Fiqh', 'Tafseer' ],
					'classes'   => [ 'Year 1', 'Year 2' ],
				],
				[
					'name'      => 'Dars-e-Nizami',
					'sort_order'=> 2,
					'subjects'   => [ 'Nahw', 'Sarf', 'Fiqh', 'Hadith', 'Tafseer', 'Aqeedah' ],
					'classes'   => [
						'Ola (Yr 1)',
						'Saniya (Yr 2)',
						'Thalitha (Yr 3)',
						'Rabia (Yr 4)',
						'Khamisa (Yr 5)',
						'Sadisa (Yr 6)',
						'Sabia (Yr 7)',
						'Thamaniya (Yr 8)',
					],
				],
				[
					'name'      => 'Tarjuma Quran — Urdu & Pashto',
					'sort_order'=> 3,
					'subjects'   => [ 'Tarjuma Quran — Urdu & Pashto' ],
					'classes'   => [ 'Tarjuma Quran — Urdu & Pashto' ],
				],
				[
					'name'      => 'Tajweed',
					'sort_order'=> 4,
					'subjects'   => [ 'Tajweed' ],
					'classes'   => [ 'Tajweed' ],
				],
				[
					'name'      => 'Muallimaat Course',
					'sort_order'=> 5,
					'subjects'   => [ 'Muallimaat Course' ],
					'classes'   => [ 'Muallimaat Course' ],
				],
			],
		],
	],
];

try {
	echo "Resetting Noor-TMS academic data...\n";
	foreach ( $tables_to_clear as $table_name ) {
		noor_tms_seed_clear_table( $table_name );
	}

	echo "Seeding Banin categories...\n";
	foreach ( $seed_plan as $account_type => $root_categories ) {
		foreach ( $root_categories as $root_category ) {
			$root_id = noor_tms_seed_category(
				[
					'name'           => $root_category['name'],
					'account_type'   => $account_type,
					'parent_id'      => 0,
					'is_school_type' => ! empty( $root_category['is_school_type'] ),
					'is_active'      => 1,
					'sort_order'     => (int) ( $root_category['sort_order'] ?? 0 ),
				]
			);

			foreach ( $root_category['children'] as $child ) {
				$child_data = [
					'name'           => $child['name'],
					'account_type'   => $account_type,
					'parent_id'      => $root_id,
					'is_active'      => 1,
					'sort_order'     => (int) ( $child['sort_order'] ?? 0 ),
				];

				if ( isset( $child['max_marks'] ) ) {
					$child_data['max_marks'] = (float) $child['max_marks'];
				}
				if ( isset( $child['pass_marks'] ) ) {
					$child_data['pass_marks'] = (float) $child['pass_marks'];
				}
				if ( isset( $child['is_school_type'] ) ) {
					$child_data['is_school_type'] = (bool) $child['is_school_type'];
				}

				$child_id = noor_tms_seed_category( $child_data );

				if ( ! empty( $child['classes'] ) ) {
					$class_names = $child['classes'];
				} elseif ( empty( $root_category['is_school_type'] ) ) {
					$class_names = [ $child['name'] ];
				} else {
					continue;
				}

				$subjects = $child['subjects'] ?? [ $child['name'] ];
				foreach ( $class_names as $index => $class_name ) {
					$class_id = noor_tms_seed_class(
						$class_name,
						[
							'account_type'   => $account_type,
							'category_id'    => $root_id,
							'subcategory_id' => $child_id,
						],
					);
					if ( ! empty( $subjects ) ) {
						\Noor_TMS\Includes\DatabaseHandler::replace_subjects( $class_id, $subjects );
					}
					noor_tms_seed_students_for_class(
						noor_tms_demo_student_names( $account_type, 2 ),
						$class_id,
						$root_id,
						$child_id,
						$account_type
					);
				}
			}
		}
	}

	echo "Seed completed successfully.\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, 'Seed failed: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
