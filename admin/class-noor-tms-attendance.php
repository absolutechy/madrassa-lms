<?php
/**
 * Student attendance management – marking & monthly history.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Attendance
 */
class Attendance {

	// -----------------------------------------------------------------------
	// Page router
	// -----------------------------------------------------------------------

	public function page_attendance(): void {
		$can_manage = current_user_can( 'noor_tms_manage' );
		$can_teach  = current_user_can( 'noor_tms_teacher' );

		if ( ! $can_manage && ! $can_teach ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$tab = sanitize_key( $_GET['tab'] ?? 'mark' );

		// Teachers only see classes assigned to them.
		$class_ids = [];
		if ( $can_manage ) {
			$classes = DatabaseHandler::get_classes_dropdown();
		} else {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			$all       = DatabaseHandler::get_classes_dropdown();
			$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
		}

		$att_date = sanitize_text_field( $_GET['att_date'] ?? current_time( 'Y-m-d' ) );
		$class_id = (int) ( $_GET['class_id']              ?? ( $classes[0]['id'] ?? 0 ) );
		$month    = (int) ( $_GET['att_month']             ?? (int) current_time( 'n' ) );
		$year     = (int) ( $_GET['att_year']              ?? (int) current_time( 'Y' ) );

		// Clamp teacher's class_id to their assigned classes only.
		if ( ! $can_manage ) {
			if ( $class_id && ! in_array( $class_id, $class_ids, true ) ) {
				$class_id = (int) ( $classes[0]['id'] ?? 0 ); // URL-tamper guard.
			}
			if ( 'history' === $tab && ! $class_id && $class_ids ) {
				$class_id = (int) ( $classes[0]['id'] ?? 0 ); // No "all classes" for teachers.
			}
		}
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php esc_html_e( 'Student Attendance', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Tabs -->
			<nav class="nav-tab-wrapper noor-tab-nav">
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-attendance', 'tab' => 'mark' ], admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'mark' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Mark Attendance', 'noor-tms' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-attendance', 'tab' => 'history' ], admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'history' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'History', 'noor-tms' ); ?>
				</a>
			</nav>

			<?php if ( 'mark' === $tab ) : ?>

			<!-- ================================================================
			     Mark Attendance
			     ================================================================ -->
			<div class="noor-tms-card" style="margin-top:12px;">
				<h2><?php esc_html_e( 'Mark Student Attendance', 'noor-tms' ); ?></h2>

				<!-- Filters -->
				<form method="get" action="">
					<input type="hidden" name="page" value="noor-tms-attendance" />
					<input type="hidden" name="tab"  value="mark" />
					<p style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
						<span>
							<label for="att_class_id"><strong><?php esc_html_e( 'Class:', 'noor-tms' ); ?></strong></label>
							<select id="att_class_id" name="class_id">
								<option value="0"><?php esc_html_e( '— Select Class —', 'noor-tms' ); ?></option>
								<?php foreach ( $classes as $cls ) : ?>
									<option value="<?php echo esc_attr( $cls['id'] ); ?>"
										<?php selected( $class_id, (int) $cls['id'] ); ?>>
										<?php echo esc_html( $cls['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</span>
						<span>
							<label for="att_date_picker"><strong><?php esc_html_e( 'Date:', 'noor-tms' ); ?></strong></label>
							<input type="date" id="att_date_picker" name="att_date"
								   value="<?php echo esc_attr( $att_date ); ?>" />
						</span>
						<?php submit_button( __( 'Load', 'noor-tms' ), 'secondary small', '', false ); ?>
					</p>
				</form>

				<?php if ( $class_id ) :
					$students = DatabaseHandler::get_students_by_class( $class_id );
					$marked   = DatabaseHandler::get_student_attendance_for_date( $class_id, $att_date );
					// $marked is keyed student_id => status.
				?>
				<?php if ( empty( $students ) ) : ?>
					<p><?php esc_html_e( 'No students in this class.', 'noor-tms' ); ?></p>
				<?php else : ?>
				<form id="noor-student-att-form">
					<?php wp_nonce_field( 'noor_tms_ajax', 'noor_tms_att_nonce' ); ?>
					<input type="hidden" name="class_id"  value="<?php echo esc_attr( $class_id ); ?>" />
					<input type="hidden" name="att_date"  value="<?php echo esc_attr( $att_date ); ?>" />

					<div style="margin-bottom:8px;">
						<strong><?php esc_html_e( 'Quick mark:', 'noor-tms' ); ?></strong>
						<button type="button" class="button button-small noor-mark-all" data-status="present"><?php esc_html_e( 'All Present', 'noor-tms' ); ?></button>
						<button type="button" class="button button-small noor-mark-all" data-status="absent"><?php esc_html_e( 'All Absent', 'noor-tms' ); ?></button>
					</div>

					<table class="wp-list-table widefat fixed striped noor-tms-table">
						<thead>
							<tr>
								<th style="width:30px;">#</th>
								<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $students as $i => $student ) :
								$status = $marked[ (int) $student['id'] ] ?? 'present';
							?>
							<tr>
								<td><?php echo esc_html( $i + 1 ); ?></td>
								<td>
									<strong><?php echo esc_html( $student['name'] ); ?></strong>
									<input type="hidden"
										   name="records[<?php echo esc_attr( $student['id'] ); ?>][student_id]"
										   value="<?php echo esc_attr( $student['id'] ); ?>" />
								</td>
								<td>
									<select name="records[<?php echo esc_attr( $student['id'] ); ?>][status]"
											class="noor-att-status">
										<?php foreach ( [ 'present' => __( 'Present', 'noor-tms' ), 'absent' => __( 'Absent', 'noor-tms' ), 'late' => __( 'Late', 'noor-tms' ), 'excused' => __( 'Excused', 'noor-tms' ) ] as $val => $lbl ) : ?>
											<option value="<?php echo esc_attr( $val ); ?>"
												<?php selected( $status, $val ); ?>>
												<?php echo esc_html( $lbl ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="noor-form-actions" style="margin-top:12px;">
						<button type="submit" id="noor-save-student-att-btn" class="button button-primary">
							<?php esc_html_e( 'Save Attendance', 'noor-tms' ); ?>
						</button>
						<span id="noor-student-att-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
					</div>
				</form>
				<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Please select a class and date, then click Load.', 'noor-tms' ); ?></p>
				<?php endif; ?>
			</div>

			<?php else : ?>

			<!-- ================================================================
			     History Tab
			     ================================================================ -->
			<div class="noor-tms-card" style="margin-top:12px;">
				<h2><?php esc_html_e( 'Attendance History', 'noor-tms' ); ?></h2>

				<!-- Month filter -->
				<form method="get" action="">
					<input type="hidden" name="page" value="noor-tms-attendance" />
					<input type="hidden" name="tab"  value="history" />
					<p style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
						<span>
							<label for="hist_class_id"><?php esc_html_e( 'Class:', 'noor-tms' ); ?></label>
							<select id="hist_class_id" name="class_id">
								<option value="0"><?php esc_html_e( '— All Classes —', 'noor-tms' ); ?></option>
								<?php foreach ( $classes as $cls ) : ?>
									<option value="<?php echo esc_attr( $cls['id'] ); ?>"
										<?php selected( $class_id, (int) $cls['id'] ); ?>>
										<?php echo esc_html( $cls['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</span>
						<span>
							<label for="hist_month"><?php esc_html_e( 'Month:', 'noor-tms' ); ?></label>
							<select id="hist_month" name="att_month">
								<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>>
										<?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?>
									</option>
								<?php endfor; ?>
							</select>
							<input type="number" name="att_year" value="<?php echo esc_attr( $year ); ?>"
								   min="2020" max="2099" style="width:80px;" />
						</span>
						<?php submit_button( __( 'View', 'noor-tms' ), 'secondary small', '', false ); ?>
					</p>
				</form>

				<?php
				$summary     = DatabaseHandler::get_student_attendance_summary( $month, $year, $class_id ?: null );
				$month_label = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
				$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
				?>
				<h3><?php echo esc_html( $month_label ); ?></h3>

				<?php if ( empty( $summary ) ) : ?>
					<p><?php esc_html_e( 'No attendance records for the selected period.', 'noor-tms' ); ?></p>
				<?php else : ?>
				<div style="overflow-x: auto;">
					<table class="wp-list-table widefat fixed striped noor-tms-table" style="min-width: max-content;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
								<?php for ( $d = 1; $d <= $days_in_month; $d++ ) : ?>
									<th title="<?php echo esc_attr( sprintf( '%04d-%02d-%02d', $year, $month, $d ) ); ?>" style="text-align: center; padding: 5px 2px; min-width: 30px;">
										<?php echo esc_html( $d ); ?>
									</th>
								<?php endfor; ?>
								<th><?php esc_html_e( 'P/A/L/E', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( '%', 'noor-tms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $summary as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['name'] ); ?></strong></td>
								<td><?php echo esc_html( $row['class_name'] ?? '' ); ?></td>
								
								<?php for ( $d = 1; $d <= $days_in_month; $d++ ) :
									$date_key = sprintf( '%04d-%02d-%02d', $year, $month, $d );
									$status = $row['daily'][ $date_key ] ?? '';
									$label  = '-';
									$color  = '';
									if ( 'present' === $status ) {
										$label = 'P';
										$color = 'green';
									} elseif ( 'absent' === $status ) {
										$label = 'A';
										$color = 'red';
									} elseif ( 'late' === $status ) {
										$label = 'L';
										$color = 'orange';
									} elseif ( 'excused' === $status ) {
										$label = 'E';
										$color = 'gray';
									}
								?>
									<td style="text-align: center; color: <?php echo esc_attr( $color ); ?>; font-weight: bold;">
										<?php echo esc_html( $label ); ?>
									</td>
								<?php endfor; ?>
								<td style="white-space: nowrap;">
									<span style="color: green;" title="Present"><?php echo esc_html( $row['present'] ); ?></span> /
									<span style="color: red;" title="Absent"><?php echo esc_html( $row['absent'] ); ?></span> /
									<span style="color: orange;" title="Late"><?php echo esc_html( $row['late'] ); ?></span> /
									<span style="color: gray;" title="Excused"><?php echo esc_html( $row['excused'] ); ?></span>
								</td>
								<td>
									<span class="noor-pct noor-pct-<?php echo $row['pct'] >= 75 ? 'pass' : 'fail'; ?>" style="font-weight:bold; color:<?php echo $row['pct'] >= 75 ? 'green' : 'red'; ?>;">
										<?php echo esc_html( $row['pct'] . '%' ); ?>
									</span>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>

			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	/**
	 * AJAX: bulk-save student attendance for a date and class.
	 * Allowed for managers AND teachers (teachers validated against class assignments).
	 */
	public function ajax_save_student_attendance(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		$can_manage = current_user_can( 'noor_tms_manage' );
		$can_teach  = current_user_can( 'noor_tms_teacher' );

		if ( ! $can_manage && ! $can_teach ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$class_id   = (int) sanitize_text_field( $_POST['class_id']  ?? 0 );
		$att_date   = sanitize_text_field( $_POST['att_date']        ?? current_time( 'Y-m-d' ) );
		$records_raw = (array) ( $_POST['records']                   ?? [] );
		$marked_by  = get_current_user_id();

		if ( ! $class_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid class.', 'noor-tms' ) ] );
		}

		// Teachers can only mark attendance for their assigned classes.
		if ( $can_teach && ! $can_manage ) {
			$teacher   = DatabaseHandler::get_teacher_by_user( $marked_by );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			if ( ! in_array( $class_id, $class_ids, true ) ) {
				wp_send_json_error( [ 'message' => __( 'You are not assigned to this class.', 'noor-tms' ) ], 403 );
			}
		}

		$records = [];
		foreach ( $records_raw as $sid_key => $rec ) {
			$records[] = [
				'student_id' => (int) ( $rec['student_id'] ?? $sid_key ),
				'status'     => sanitize_key( $rec['status'] ?? 'present' ),
			];
		}

		$saved = DatabaseHandler::bulk_save_student_attendance( $class_id, $att_date, $records, $marked_by );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: records saved */
				_n( '%d record saved.', '%d records saved.', $saved, 'noor-tms' ),
				$saved
			),
		] );
	}
}
