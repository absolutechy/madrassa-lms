<?php
/**
 * Front-end attendance template.
 * Rendered by [noor_tms_attendance] shortcode.
 *
 * Variables in scope (set by PublicController::sc_attendance):
 *   $classes     array   Classes visible to the current user.
 *   $is_manager  bool
 *   $att_date    string  Y-m-d
 *   $class_id    int
 *   $tab         string  'mark' | 'history'
 *   $students    array   Empty or loaded when class + date supplied.
 *   $marked      array   student_id => status for the loaded date.
 *   $summary     array   Monthly summary rows (history tab).
 *   $month       int
 *   $year        int
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Attendance', 'noor-tms' );
$active_nav     = 'attendance';
$topbar_actions = null;

include __DIR__ . '/layout.php';
?>

<!-- ================================================================
     Tab Navigation
     ================================================================ -->
<div class="noor-tab-wrap" style="margin-bottom:16px;">
	<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'mark', 'class_id' => $class_id, 'att_date' => $att_date ], home_url( '/tms-attendance/' ) ) ); ?>"
	   class="noor-tab-link <?php echo 'mark' === $tab ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Mark Attendance', 'noor-tms' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'history', 'class_id' => $class_id ], home_url( '/tms-attendance/' ) ) ); ?>"
	   class="noor-tab-link <?php echo 'history' === $tab ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'History', 'noor-tms' ); ?>
	</a>
</div>

<?php if ( 'mark' === $tab ) : ?>

<!-- ================================================================
     Mark Attendance Tab
     ================================================================ -->
<form method="get" action="<?php echo esc_url( home_url( '/tms-attendance/' ) ); ?>" class="noor-filter-row" style="margin-bottom:16px;">
	<input type="hidden" name="tab" value="mark" />

	<select name="class_id">
		<option value="0"><?php esc_html_e( '— Select Class —', 'noor-tms' ); ?></option>
		<?php foreach ( $classes as $cls ) : ?>
			<option value="<?php echo esc_attr( $cls['id'] ); ?>"
				<?php selected( $class_id, (int) $cls['id'] ); ?>>
				<?php echo esc_html( $cls['name'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<input type="date" name="att_date" value="<?php echo esc_attr( $att_date ); ?>" />

	<button type="submit" class="noor-btn noor-btn--secondary">
		<?php esc_html_e( 'Load', 'noor-tms' ); ?>
	</button>
</form>

<?php if ( $class_id && ! empty( $students ) ) : ?>
<form id="noor-pub-att-form">
	<?php wp_nonce_field( 'noor_tms_ajax', 'noor_tms_att_nonce' ); ?>
	<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />
	<input type="hidden" name="att_date" value="<?php echo esc_attr( $att_date ); ?>" />

	<!-- Quick mark -->
	<div style="margin-bottom:8px;">
		<strong><?php esc_html_e( 'Quick mark:', 'noor-tms' ); ?></strong>
		<button type="button" class="noor-btn noor-btn--secondary noor-btn--small noor-mark-all" data-status="present">
			<?php esc_html_e( 'All Present', 'noor-tms' ); ?>
		</button>
		<button type="button" class="noor-btn noor-btn--secondary noor-btn--small noor-mark-all" data-status="absent">
			<?php esc_html_e( 'All Absent', 'noor-tms' ); ?>
		</button>
	</div>

	<div class="noor-table-wrap">
		<table class="noor-table noor-attendance-table">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $students as $i => $student ) :
					$status = $marked[ (int) $student['id'] ] ?? 'present';
				?>
				<tr class="noor-att-row noor-att-<?php echo esc_attr( $status ); ?>">
					<td><?php echo esc_html( $i + 1 ); ?></td>
					<td>
						<strong><?php echo esc_html( $student['name'] ); ?></strong>
						<input type="hidden"
							   name="records[<?php echo esc_attr( $student['id'] ); ?>][student_id]"
							   value="<?php echo esc_attr( $student['id'] ); ?>" />
					</td>
					<td>
						<select name="records[<?php echo esc_attr( $student['id'] ); ?>][status]"
								class="noor-att-status noor-select">
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
	</div>

	<div class="noor-form-actions" style="margin-top:16px;">
		<button type="submit" id="noor-save-pub-att-btn" class="noor-btn noor-btn--primary">
			<?php esc_html_e( 'Save Attendance', 'noor-tms' ); ?>
		</button>
		<span id="noor-pub-att-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
	</div>
</form>

<?php elseif ( $class_id ) : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'No students found in this class.', 'noor-tms' ); ?>
	</p>
<?php else : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'Select a class and date above to mark attendance.', 'noor-tms' ); ?>
	</p>
<?php endif; ?>

<?php else : ?>

<!-- ================================================================
     History Tab
     ================================================================ -->
<form method="get" action="<?php echo esc_url( home_url( '/tms-attendance/' ) ); ?>" class="noor-filter-row" style="margin-bottom:16px;">
	<input type="hidden" name="tab" value="history" />

	<?php if ( $is_manager ) : ?>
	<select name="class_id">
		<option value="0"><?php esc_html_e( '— All Classes —', 'noor-tms' ); ?></option>
		<?php foreach ( $classes as $cls ) : ?>
			<option value="<?php echo esc_attr( $cls['id'] ); ?>"
				<?php selected( $class_id, (int) $cls['id'] ); ?>>
				<?php echo esc_html( $cls['name'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php else : ?>
	<select name="class_id">
		<option value="0"><?php esc_html_e( '— All My Classes —', 'noor-tms' ); ?></option>
		<?php foreach ( $classes as $cls ) : ?>
			<option value="<?php echo esc_attr( $cls['id'] ); ?>"
				<?php selected( $class_id, (int) $cls['id'] ); ?>>
				<?php echo esc_html( $cls['name'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php endif; ?>

	<select name="att_month">
		<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
			<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>>
				<?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?>
			</option>
		<?php endfor; ?>
	</select>
	<input type="number" name="att_year" value="<?php echo esc_attr( $year ); ?>"
		   min="2020" max="2099" style="width:80px;" />

	<button type="submit" class="noor-btn noor-btn--secondary">
		<?php esc_html_e( 'View', 'noor-tms' ); ?>
	</button>
</form>

<?php if ( empty( $summary ) ) : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'No attendance records for the selected period.', 'noor-tms' ); ?>
	</p>
<?php else :
	$month_label = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
	$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
?>
	<h3><?php echo esc_html( $month_label ); ?></h3>

	<div class="noor-table-wrap" style="overflow-x: auto;">
		<table class="noor-table noor-attendance-history-table" style="min-width: max-content;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<?php for ( $d = 1; $d <= $days_in_month; $d++ ) : ?>
						<th title="<?php echo esc_attr( sprintf( '%04d-%02d-%02d', $year, $month, $d ) ); ?>" style="text-align: center; padding: 0.5rem 0.2rem; min-width: 30px;">
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
						$label  = '';
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
						} else {
							$label = '-';
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
						<span class="noor-pct noor-pct-<?php echo $row['pct'] >= 75 ? 'pass' : 'fail'; ?>">
							<strong><?php echo esc_html( $row['pct'] . '%' ); ?></strong>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<?php endif; // end tab check ?>

<?php include __DIR__ . '/layout-close.php'; ?>
