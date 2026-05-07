<?php
/**
 * Front-end attendance template (global marking, time slots, paginated history, audit trail).
 * Rendered by [noor_tms_attendance] shortcode.
 *
 * Variables in scope (set by PublicController::sc_attendance):
 *   $classes         array   Classes visible to the current user.
 *   $is_manager      bool
 *   $is_teacher      bool
 *   $att_date        string  Y-m-d
 *   $class_id        int
 *   $tab             string  'mark' | 'history' | 'audit_log'
 *   $time_slot       string  Current selected slot key.
 *   $current_slot    string  Auto-detected slot based on server time.
 *   $mode            string  'class' | 'global'
 *   $students        array   Loaded for mark tab.
 *   $marked          array   student_id => status for the loaded date+slot.
 *   $history_result  array   { rows, total, pages }
 *   $paged           int
 *   $per_page        int
 *   $f_date_from, $f_date_to, $f_slot, $f_status, $f_class_id, $f_search  string
 *   $audit_entries   array
 *   $month           int
 *   $year            int
 *
 * @package Noor_TMS
 */

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Attendance', 'noor-tms' );
$active_nav     = 'attendance';
$topbar_actions = null;

include __DIR__ . '/layout.php';

$slots       = DatabaseHandler::get_time_slots();
$att_base    = home_url( '/tms-attendance/' );
$statuses    = [
	'present' => __( 'Present',  'noor-tms' ),
	'absent'  => __( 'Absent',   'noor-tms' ),
	'late'    => __( 'Late',     'noor-tms' ),
	'excused' => __( 'Excused',  'noor-tms' ),
];
$status_cls  = fn( string $s ): string => match ( $s ) {
	'present' => 'noor-badge--active',
	'absent'  => 'noor-badge--inactive',
	'late'    => 'noor-badge--late',
	'excused' => 'noor-badge--excused',
	default   => '',
};
?>

<!-- ================================================================
     Tab Navigation
     ================================================================ -->
<div class="noor-tab-wrap" style="margin-bottom:16px;">
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'mark', $att_base ) ); ?>"
	   class="noor-tab-link <?php echo 'mark' === $tab ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Mark Attendance', 'noor-tms' ); ?>
	</a>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $att_base ) ); ?>"
	   class="noor-tab-link <?php echo 'history' === $tab ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'History', 'noor-tms' ); ?>
	</a>
	<?php if ( $is_manager ) : ?>
	<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit_log', $att_base ) ); ?>"
	   class="noor-tab-link <?php echo 'audit_log' === $tab ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Audit Log', 'noor-tms' ); ?>
	</a>
	<?php endif; ?>
</div>

<?php if ( 'mark' === $tab ) : ?>

<!-- ================================================================
     Mark Attendance Tab
     ================================================================ -->

<!-- Time slot indicator -->
<div class="noor-slot-bar" style="margin-bottom:14px;">
	<?php foreach ( $slots as $key => $label ) : ?>
	<span class="noor-slot-chip <?php echo $key === $current_slot ? 'is-current' : ''; ?> <?php echo $key === $time_slot ? 'is-selected' : ''; ?>">
		<?php if ( $key === $current_slot ) : ?><span class="noor-slot-dot"></span><?php endif; ?>
		<?php echo esc_html( $label ); ?>
	</span>
	<?php endforeach; ?>
</div>

<!-- Selector form -->
<form method="get" action="<?php echo esc_url( $att_base ); ?>" class="noor-filter-row noor-att-loader-form" style="margin-bottom:16px;flex-wrap:wrap;gap:10px;">
	<input type="hidden" name="tab" value="mark" />

	<label>
		<?php esc_html_e( 'Time Slot', 'noor-tms' ); ?><br>
		<select name="time_slot" class="noor-select">
			<?php foreach ( $slots as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $time_slot, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>
		<?php esc_html_e( 'Date', 'noor-tms' ); ?><br>
		<input type="date" name="att_date" value="<?php echo esc_attr( $att_date ); ?>" class="noor-input" />
	</label>

	<?php if ( $is_manager ) : ?>
	<label>
		<?php esc_html_e( 'Mode', 'noor-tms' ); ?><br>
		<select name="mode" id="noor-att-mode" class="noor-select">
			<option value="class"  <?php selected( $mode, 'class' ); ?>><?php esc_html_e( 'By Class', 'noor-tms' ); ?></option>
			<option value="global" <?php selected( $mode, 'global' ); ?>><?php esc_html_e( 'All Students (Global)', 'noor-tms' ); ?></option>
		</select>
	</label>
	<?php endif; ?>

	<label id="noor-class-label" <?php echo ( 'global' === $mode ) ? 'style="display:none"' : ''; ?>>
		<?php esc_html_e( 'Class', 'noor-tms' ); ?><br>
		<select name="class_id" class="noor-select">
			<option value="0"><?php esc_html_e( '— Select Class —', 'noor-tms' ); ?></option>
			<?php foreach ( $classes as $cls ) : ?>
				<option value="<?php echo esc_attr( $cls['id'] ); ?>" <?php selected( $class_id, (int) $cls['id'] ); ?>>
					<?php echo esc_html( $cls['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>

	<div style="display:flex;align-items:flex-end;">
		<button type="submit" class="noor-btn noor-btn--secondary">
			<?php esc_html_e( 'Load Students', 'noor-tms' ); ?>
		</button>
	</div>
</form>

<?php if ( ! empty( $students ) ) : ?>
<form id="noor-pub-att-form">
	<?php wp_nonce_field( 'noor_tms_ajax', 'noor_tms_att_nonce' ); ?>
	<input type="hidden" name="class_id"  value="<?php echo esc_attr( $class_id ); ?>" />
	<input type="hidden" name="att_date"  value="<?php echo esc_attr( $att_date ); ?>" />
	<input type="hidden" name="time_slot" value="<?php echo esc_attr( $time_slot ); ?>" />

	<!-- Batch mark bar -->
	<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
		<strong><?php esc_html_e( 'Batch Mark:', 'noor-tms' ); ?></strong>
		<?php foreach ( $statuses as $val => $lbl ) : ?>
		<button type="button" class="noor-btn noor-btn--secondary noor-btn--small noor-mark-all" data-status="<?php echo esc_attr( $val ); ?>">
			<?php printf( esc_html__( 'All %s', 'noor-tms' ), esc_html( $lbl ) ); ?>
		</button>
		<?php endforeach; ?>
	</div>

	<?php if ( 'global' === $mode ) : ?>
	<p class="noor-notice noor-notice--info">
		<?php
		printf(
			esc_html__( 'Global mode: marking %d active students across all classes for %s – %s.', 'noor-tms' ),
			count( $students ),
			esc_html( $slots[ $time_slot ] ?? $time_slot ),
			esc_html( $att_date )
		);
		?>
	</p>
	<?php endif; ?>

	<div class="noor-table-wrap">
		<table class="noor-table noor-attendance-table">
			<thead>
				<tr>
					<th>#</th>
					<?php if ( 'global' === $mode ) : ?>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<?php endif; ?>
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
					<?php if ( 'global' === $mode ) : ?>
					<td><?php echo esc_html( $student['class_name'] ?? '—' ); ?></td>
					<?php endif; ?>
					<td>
						<strong><?php echo esc_html( $student['name'] ); ?></strong>
						<input type="hidden" name="records[<?php echo esc_attr( $student['id'] ); ?>][student_id]" value="<?php echo esc_attr( $student['id'] ); ?>" />
						<?php if ( 'global' === $mode ) : ?>
						<input type="hidden" name="records[<?php echo esc_attr( $student['id'] ); ?>][class_id]" value="<?php echo esc_attr( $student['class_id'] ?? 0 ); ?>" />
						<?php endif; ?>
					</td>
					<td>
						<select name="records[<?php echo esc_attr( $student['id'] ); ?>][status]"
								class="noor-att-status noor-select">
							<?php foreach ( $statuses as $val => $lbl ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $status, $val ); ?>>
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

<?php elseif ( 'global' !== $mode && ! $class_id ) : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'Select a class and date, then click "Load Students".', 'noor-tms' ); ?>
	</p>
<?php elseif ( 'class' === $mode && $class_id ) : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'No students found in this class.', 'noor-tms' ); ?>
	</p>
<?php endif; ?>

<?php elseif ( 'history' === $tab ) : ?>

<!-- ================================================================
     History Tab
     ================================================================ -->

<!-- Filter bar -->
<form method="get" action="<?php echo esc_url( $att_base ); ?>" class="noor-filter-row" style="margin-bottom:16px;flex-wrap:wrap;gap:10px;">
	<input type="hidden" name="tab" value="history" />

	<label>
		<?php esc_html_e( 'From', 'noor-tms' ); ?><br>
		<input type="date" name="f_date_from" value="<?php echo esc_attr( $f_date_from ); ?>" class="noor-input" />
	</label>
	<label>
		<?php esc_html_e( 'To', 'noor-tms' ); ?><br>
		<input type="date" name="f_date_to" value="<?php echo esc_attr( $f_date_to ); ?>" class="noor-input" />
	</label>
	<label>
		<?php esc_html_e( 'Time Slot', 'noor-tms' ); ?><br>
		<select name="f_slot" class="noor-select">
			<option value=""><?php esc_html_e( '— All Slots —', 'noor-tms' ); ?></option>
			<?php foreach ( $slots as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f_slot, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<?php esc_html_e( 'Status', 'noor-tms' ); ?><br>
		<select name="f_status" class="noor-select">
			<option value=""><?php esc_html_e( '— All —', 'noor-tms' ); ?></option>
			<?php foreach ( $statuses as $val => $lbl ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f_status, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php if ( $is_manager ) : ?>
	<label>
		<?php esc_html_e( 'Class', 'noor-tms' ); ?><br>
		<select name="f_class_id" class="noor-select">
			<option value=""><?php esc_html_e( '— All Classes —', 'noor-tms' ); ?></option>
			<?php foreach ( $classes as $cls ) : ?>
				<option value="<?php echo esc_attr( $cls['id'] ); ?>" <?php selected( $f_class_id, (int) $cls['id'] ); ?>><?php echo esc_html( $cls['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php endif; ?>
	<label>
		<?php esc_html_e( 'Student', 'noor-tms' ); ?><br>
		<input type="text" name="f_search" value="<?php echo esc_attr( $f_search ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'noor-tms' ); ?>" class="noor-input" style="width:140px;" />
	</label>
	<label>
		<?php esc_html_e( 'Per Page', 'noor-tms' ); ?><br>
		<select name="per_page" class="noor-select">
			<?php foreach ( [ 20, 25, 50 ] as $pp ) : ?>
				<option value="<?php echo esc_attr( $pp ); ?>" <?php selected( $per_page, $pp ); ?>><?php echo esc_html( $pp ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<div style="display:flex;align-items:flex-end;gap:6px;">
		<button type="submit" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $att_base ) ); ?>" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'Reset', 'noor-tms' ); ?></a>
	</div>
</form>

<?php
$rows  = $history_result['rows'];
$total = $history_result['total'];
$pages = $history_result['pages'];

if ( $total > 0 ) {
	$start = ( $paged - 1 ) * $per_page + 1;
	$end   = min( $paged * $per_page, $total );
	printf(
		'<p class="noor-att-summary">' . esc_html__( 'Showing %1$d–%2$d of %3$d records', 'noor-tms' ) . '</p>',
		$start, $end, $total
	);
}
?>

<?php if ( ! empty( $rows ) ) : ?>
<div class="noor-table-wrap">
	<table class="noor-table noor-attendance-history-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Time Slot', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Marked By', 'noor-tms' ); ?></th>
				<?php if ( $is_manager ) : ?>
				<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr id="noor-att-row-<?php echo esc_attr( $row['id'] ); ?>">
				<td><strong><?php echo esc_html( $row['student_name'] ); ?></strong></td>
				<td><?php echo esc_html( $row['class_name'] ?? '—' ); ?></td>
				<td><?php echo esc_html( $row['att_date'] ); ?></td>
				<td>
					<span class="noor-slot-badge noor-slot-<?php echo esc_attr( $row['time_slot'] ); ?>">
						<?php echo esc_html( $slots[ $row['time_slot'] ] ?? ucfirst( $row['time_slot'] ) ); ?>
					</span>
				</td>
				<td>
					<span class="noor-badge <?php echo esc_attr( $status_cls( $row['status'] ) ); ?>">
						<?php echo esc_html( $statuses[ $row['status'] ] ?? ucfirst( $row['status'] ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $row['marked_by_name'] ?? '—' ); ?></td>
				<?php if ( $is_manager ) : ?>
				<td>
					<button type="button"
							class="noor-btn noor-btn--secondary noor-btn--small noor-open-correction"
							data-id="<?php echo esc_attr( $row['id'] ); ?>"
							data-student="<?php echo esc_attr( $row['student_name'] ); ?>"
							data-date="<?php echo esc_attr( $row['att_date'] ); ?>"
							data-slot="<?php echo esc_attr( $slots[ $row['time_slot'] ] ?? $row['time_slot'] ); ?>"
							data-status="<?php echo esc_attr( $row['status'] ); ?>">
						<?php esc_html_e( 'Correct', 'noor-tms' ); ?>
					</button>
				</td>
				<?php endif; ?>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<!-- Pagination -->
<?php if ( $pages > 1 ) :
	$pagination_base = add_query_arg(
		array_merge(
			[ 'tab' => 'history', 'att_paged' => '%#%', 'per_page' => $per_page ],
			array_filter( [
				'f_date_from' => $f_date_from,
				'f_date_to'   => $f_date_to,
				'f_slot'      => $f_slot,
				'f_status'    => $f_status,
				'f_class_id'  => $f_class_id ?: '',
				'f_search'    => $f_search,
			] )
		),
		$att_base
	);
?>
<div class="noor-pagination-wrap" style="margin-top:16px;">
	<div class="noor-pagination">
		<?php
		echo paginate_links( [
			'base'      => $pagination_base,
			'format'    => '',
			'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'noor-tms' ),
			'next_text' => esc_html__( 'Next', 'noor-tms' ) . ' &raquo;',
			'total'     => $pages,
			'current'   => $paged,
			'type'      => 'plain',
		] );
		?>
	</div>
</div>
<?php endif; ?>

<?php else : ?>
	<p class="noor-notice noor-notice--info">
		<?php esc_html_e( 'No attendance records match the selected filters.', 'noor-tms' ); ?>
	</p>
<?php endif; ?>

<?php elseif ( 'audit_log' === $tab && $is_manager ) : ?>

<!-- ================================================================
     Audit Log Tab (managers only)
     ================================================================ -->
<p class="noor-notice noor-notice--info" style="margin-bottom:16px;">
	<?php esc_html_e( 'Every manual correction to attendance data is recorded here for full accountability.', 'noor-tms' ); ?>
</p>

<?php if ( empty( $audit_entries ) ) : ?>
	<p><?php esc_html_e( 'No corrections on record yet.', 'noor-tms' ); ?></p>
<?php else : ?>
<div class="noor-table-wrap">
	<table class="noor-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Slot', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Change', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Changed By', 'noor-tms' ); ?></th>
				<th><?php esc_html_e( 'Changed At', 'noor-tms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $audit_entries as $entry ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $entry['student_name'] ); ?></strong></td>
				<td><?php echo esc_html( $entry['att_date'] ); ?></td>
				<td>
					<span class="noor-slot-badge noor-slot-<?php echo esc_attr( $entry['time_slot'] ); ?>">
						<?php echo esc_html( $slots[ $entry['time_slot'] ] ?? ucfirst( $entry['time_slot'] ) ); ?>
					</span>
				</td>
				<td>
					<span class="noor-badge <?php echo esc_attr( $status_cls( $entry['old_status'] ) ); ?>" style="opacity:.7;">
						<?php echo esc_html( $statuses[ $entry['old_status'] ] ?? $entry['old_status'] ); ?>
					</span>
					&rarr;
					<span class="noor-badge <?php echo esc_attr( $status_cls( $entry['new_status'] ) ); ?>">
						<?php echo esc_html( $statuses[ $entry['new_status'] ] ?? $entry['new_status'] ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $entry['reason'] ); ?></td>
				<td><?php echo esc_html( $entry['changed_by_name'] ?? '—' ); ?></td>
				<td><?php echo esc_html( $entry['changed_at'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php endif; // end tab check ?>

<!-- ================================================================
     Correction Modal (rendered once, shown via JS for managers)
     ================================================================ -->
<?php if ( $is_manager ) : ?>
<div id="noor-correction-modal" class="noor-modal-overlay" hidden aria-modal="true" role="dialog">
	<div class="noor-modal-box">
		<div class="noor-modal-header">
			<h3><?php esc_html_e( 'Correct Attendance Record', 'noor-tms' ); ?></h3>
			<button type="button" class="noor-modal-close" aria-label="<?php esc_attr_e( 'Close', 'noor-tms' ); ?>">&times;</button>
		</div>
		<div class="noor-modal-body">
			<p><strong><?php esc_html_e( 'Student:', 'noor-tms' ); ?></strong> <span id="noor-modal-student"></span></p>
			<p><strong><?php esc_html_e( 'Date / Slot:', 'noor-tms' ); ?></strong> <span id="noor-modal-date-slot"></span></p>
			<p><strong><?php esc_html_e( 'Current Status:', 'noor-tms' ); ?></strong> <span id="noor-modal-current-status"></span></p>

			<label for="noor-modal-new-status"><strong><?php esc_html_e( 'New Status:', 'noor-tms' ); ?></strong></label><br>
			<select id="noor-modal-new-status" class="noor-select" style="width:100%;margin:6px 0 14px;">
				<?php foreach ( $statuses as $val => $lbl ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="noor-modal-reason"><strong><?php esc_html_e( 'Reason for Change:', 'noor-tms' ); ?></strong> <span style="color:var(--tms-danger,#dc3545);">*</span></label><br>
			<textarea id="noor-modal-reason" class="noor-textarea" rows="3" style="width:100%;margin:6px 0 4px;"
					  placeholder="<?php esc_attr_e( 'e.g. Medical proof provided, Administrative error…', 'noor-tms' ); ?>"></textarea>
			<p class="noor-hint"><?php esc_html_e( 'A reason is required and will be stored in the audit log.', 'noor-tms' ); ?></p>
		</div>
		<div class="noor-modal-footer">
			<button type="button" id="noor-modal-submit" class="noor-btn noor-btn--primary">
				<?php esc_html_e( 'Save Correction', 'noor-tms' ); ?>
			</button>
			<button type="button" class="noor-btn noor-btn--secondary noor-modal-close">
				<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
			</button>
			<span id="noor-modal-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
		</div>
		<input type="hidden" id="noor-modal-att-id" value="" />
	</div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
