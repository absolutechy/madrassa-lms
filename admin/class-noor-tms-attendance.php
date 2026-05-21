<?php
/**
 * Student attendance management – global marking, time slots, paginated history
 * with audit-trail correction flow.
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
	// Helpers
	// -----------------------------------------------------------------------

	/** Valid time slots (key → display label). */
	private static function slots(): array {
		return DatabaseHandler::get_time_slots();
	}

	/** Valid attendance statuses. */
	private static function statuses(): array {
		return [
			'present' => __( 'Present',  'noor-tms' ),
			'absent'  => __( 'Absent',   'noor-tms' ),
			'late'    => __( 'Late',     'noor-tms' ),
			'excused' => __( 'Excused',  'noor-tms' ),
		];
	}

	/** CSS badge class for a status. */
	private static function status_class( string $status ): string {
		return match ( $status ) {
			'present' => 'noor-badge--active',
			'absent'  => 'noor-badge--inactive',
			'late'    => 'noor-badge--late',
			'excused' => 'noor-badge--excused',
			default   => '',
		};
	}

	// -----------------------------------------------------------------------
	// Page router
	// -----------------------------------------------------------------------

	public function page_attendance(): void {
		$can_manage = noor_tms_can_manage();
		$can_teach  = current_user_can( 'noor_tms_teacher' );

		if ( ! $can_manage && ! $can_teach ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$tab = sanitize_key( $_GET['tab'] ?? 'mark' );

		// Teachers only see classes assigned to them.
		if ( $can_manage ) {
			$classes = DatabaseHandler::get_classes_dropdown();
		} else {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			$all       = DatabaseHandler::get_classes_dropdown();
			$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
		}

		$current_slot = DatabaseHandler::current_time_slot();
		$time_slot    = sanitize_key( $_GET['time_slot'] ?? $current_slot );
		if ( ! array_key_exists( $time_slot, self::slots() ) ) {
			$time_slot = $current_slot;
		}

		$att_date  = sanitize_text_field( $_GET['att_date'] ?? current_time( 'Y-m-d' ) );
		$class_id  = (int) ( $_GET['class_id'] ?? 0 );
		$mode      = $can_manage ? sanitize_key( $_GET['mode'] ?? 'class' ) : 'class'; // global only for managers
		$month     = (int) ( $_GET['att_month'] ?? (int) current_time( 'n' ) );
		$year      = (int) ( $_GET['att_year']  ?? (int) current_time( 'Y' ) );
		$per_page  = (int) ( $_GET['per_page']  ?? 25 );
		$per_page  = in_array( $per_page, [ 20, 25, 50, 100 ], true ) ? $per_page : 25;
		$paged     = max( 1, (int) ( $_GET['att_paged'] ?? 1 ) );

		// History filters.
		$f_date_from = sanitize_text_field( $_GET['f_date_from'] ?? '' );
		$f_date_to   = sanitize_text_field( $_GET['f_date_to']   ?? '' );
		$f_slot      = sanitize_key( $_GET['f_slot']             ?? '' );
		$f_status    = sanitize_key( $_GET['f_status']           ?? '' );
		$f_class_id  = (int) ( $_GET['f_class_id']              ?? 0 );
		$f_search    = sanitize_text_field( $_GET['f_search']    ?? '' );

		// Clamp teacher's class selection.
		if ( ! $can_manage && $class_id ) {
			if ( ! in_array( $class_id, $class_ids ?? [], true ) ) {
				$class_id = (int) ( $classes[0]['id'] ?? 0 );
			}
		}

		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php esc_html_e( 'Student Attendance', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Tabs -->
			<nav class="nav-tab-wrapper noor-tab-nav">
				<?php
			$base_url = admin_url( 'admin.php?page=noor-tms-attendance' );
			foreach ( [
				'mark'      => __( 'Mark Attendance', 'noor-tms' ),
				'history'   => __( 'History',          'noor-tms' ),
				'sessions'  => __( 'Sessions',         'noor-tms' ),
				'audit_log' => __( 'Audit Log',        'noor-tms' ),
			] as $t => $label ) :
				?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $t, $base_url ) ); ?>"
				   class="nav-tab <?php echo $tab === $t ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

		<?php
		if ( 'mark' === $tab ) {
			$this->render_mark_tab( $classes, $class_id, $att_date, $time_slot, $mode, $current_slot, $can_manage );
		} elseif ( 'history' === $tab ) {
			$this->render_history_tab(
				$classes, $paged, $per_page, $can_manage,
				$f_date_from, $f_date_to, $f_slot, $f_status, $f_class_id, $f_search
			);
		} elseif ( 'sessions' === $tab ) {
			$this->render_sessions_tab( $can_manage );
		} else {
			$this->render_audit_log_tab( $can_manage );
		}
			?>
		</div>

		<?php $this->render_correction_modal(); ?>
		<?php
	}

	// -----------------------------------------------------------------------
	// Mark Attendance Tab
	// -----------------------------------------------------------------------

	private function render_mark_tab( array $classes, int $class_id, string $att_date, string $time_slot, string $mode, string $current_slot, bool $can_manage ): void {
		$slots = self::slots();
		?>
		<div class="noor-tms-card" style="margin-top:12px;">
			<h2><?php esc_html_e( 'Mark Attendance', 'noor-tms' ); ?></h2>

			<!-- Time-slot indicator -->
			<div class="noor-slot-bar">
				<?php foreach ( $slots as $key => $label ) : ?>
				<span class="noor-slot-chip <?php echo $key === $current_slot ? 'is-current' : ''; ?> <?php echo $key === $time_slot ? 'is-selected' : ''; ?>">
					<?php if ( $key === $current_slot ) : ?><span class="noor-slot-dot"></span><?php endif; ?>
					<?php echo esc_html( $label ); ?>
				</span>
				<?php endforeach; ?>
			</div>

			<!-- Selector form -->
			<form method="get" action="" class="noor-att-loader-form">
				<input type="hidden" name="page" value="noor-tms-attendance" />
				<input type="hidden" name="tab"  value="mark" />
				<div class="noor-att-controls">
					<!-- Time slot -->
					<label>
						<strong><?php esc_html_e( 'Time Slot:', 'noor-tms' ); ?></strong><br>
						<select name="time_slot">
							<?php foreach ( $slots as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $time_slot, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>

					<!-- Date -->
					<label>
						<strong><?php esc_html_e( 'Date:', 'noor-tms' ); ?></strong><br>
						<input type="date" name="att_date" value="<?php echo esc_attr( $att_date ); ?>" />
					</label>

					<?php if ( $can_manage ) : ?>
					<!-- Mode -->
					<label>
						<strong><?php esc_html_e( 'Mode:', 'noor-tms' ); ?></strong><br>
						<select name="mode" id="noor-att-mode">
							<option value="class"  <?php selected( $mode, 'class' ); ?>><?php esc_html_e( 'By Class',         'noor-tms' ); ?></option>
							<option value="global" <?php selected( $mode, 'global' ); ?>><?php esc_html_e( 'All Students (Global)', 'noor-tms' ); ?></option>
						</select>
					</label>
					<?php endif; ?>

					<!-- Class (shown in class mode) -->
					<label id="noor-class-label" <?php echo ( 'global' === $mode ) ? 'style="display:none"' : ''; ?>>
						<strong><?php esc_html_e( 'Class:', 'noor-tms' ); ?></strong><br>
						<select name="class_id">
							<option value="0"><?php esc_html_e( '— Select Class —', 'noor-tms' ); ?></option>
							<?php foreach ( $classes as $cls ) : ?>
								<option value="<?php echo esc_attr( $cls['id'] ); ?>" <?php selected( $class_id, (int) $cls['id'] ); ?>>
									<?php echo esc_html( $cls['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>

					<?php submit_button( __( 'Load Students', 'noor-tms' ), 'secondary small', '', false ); ?>
				</div>
			</form>

			<?php
			// Load student roster.
			$students = [];
			$marked   = [];

			if ( 'global' === $mode && $can_manage ) {
				$students = DatabaseHandler::get_all_active_students();
				$marked   = DatabaseHandler::get_student_attendance_for_date( 0, $att_date, $time_slot );
			} elseif ( $class_id ) {
				$students = DatabaseHandler::get_students_by_class( $class_id );
				$marked   = DatabaseHandler::get_student_attendance_for_date( $class_id, $att_date, $time_slot );
			}
			?>

			<?php if ( ! empty( $students ) ) : ?>
			<form id="noor-student-att-form" style="margin-top:16px;">
				<?php wp_nonce_field( 'noor_tms_ajax', 'noor_tms_att_nonce' ); ?>
				<input type="hidden" name="class_id"  value="<?php echo esc_attr( $class_id ); ?>" />
				<input type="hidden" name="att_date"  value="<?php echo esc_attr( $att_date ); ?>" />
				<input type="hidden" name="time_slot" value="<?php echo esc_attr( $time_slot ); ?>" />

				<!-- Quick mark bar -->
				<div class="noor-quick-mark-bar">
					<strong><?php esc_html_e( 'Batch Mark:', 'noor-tms' ); ?></strong>
					<?php foreach ( self::statuses() as $val => $lbl ) : ?>
					<button type="button" class="button button-small noor-mark-all" data-status="<?php echo esc_attr( $val ); ?>">
						<?php printf( esc_html__( 'All %s', 'noor-tms' ), esc_html( $lbl ) ); ?>
					</button>
					<?php endforeach; ?>
				</div>

				<?php if ( 'global' === $mode ) : ?>
				<p class="description" style="margin:4px 0 10px;">
					<?php
					printf(
						esc_html__( 'Global mode: marking %d active students across all classes for %s – %s.', 'noor-tms' ),
						count( $students ),
						esc_html( self::slots()[ $time_slot ] ),
						esc_html( $att_date )
					);
					?>
				</p>
				<?php endif; ?>

				<div style="overflow-x:auto;">
					<table class="wp-list-table widefat fixed striped noor-tms-table">
						<thead>
							<tr>
								<th style="width:32px;">#</th>
								<?php if ( 'global' === $mode ) : ?>
								<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
								<?php endif; ?>
								<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
								<th style="width:160px;"><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
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
											class="noor-att-status">
										<?php foreach ( self::statuses() as $val => $lbl ) : ?>
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

				<div class="noor-form-actions" style="margin-top:12px;">
					<button type="submit" id="noor-save-student-att-btn" class="button button-primary">
						<?php esc_html_e( 'Save Attendance', 'noor-tms' ); ?>
					</button>
					<span id="noor-student-att-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
				</div>
			</form>

			<?php elseif ( 'global' !== $mode && ! $class_id ) : ?>
				<p style="margin-top:16px;"><?php esc_html_e( 'Select a class and date, then click "Load Students".', 'noor-tms' ); ?></p>
			<?php elseif ( 'class' === $mode && $class_id ) : ?>
				<p style="margin-top:16px;"><?php esc_html_e( 'No students found in this class.', 'noor-tms' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// History Tab
	// -----------------------------------------------------------------------

	private function render_history_tab(
		array  $classes,
		int    $paged,
		int    $per_page,
		bool   $can_manage,
		string $f_date_from,
		string $f_date_to,
		string $f_slot,
		string $f_status,
		int    $f_class_id,
		string $f_search
	): void {
		$filters = [
			'date_from'      => $f_date_from,
			'date_to'        => $f_date_to,
			'time_slot'      => $f_slot,
			'status'         => $f_status,
			'class_id'       => $f_class_id,
			'student_search' => $f_search,
		];
		$result  = DatabaseHandler::get_global_attendance_history( $filters, $paged, $per_page );
		$rows    = $result['rows'];
		$total   = $result['total'];
		$pages   = $result['pages'];
		$slots   = self::slots();
		?>
		<div class="noor-tms-card" style="margin-top:12px;">
			<h2><?php esc_html_e( 'Attendance History', 'noor-tms' ); ?></h2>

			<!-- Filter form -->
			<form method="get" action="" class="noor-filter-row" style="align-items:flex-end;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
				<input type="hidden" name="page" value="noor-tms-attendance" />
				<input type="hidden" name="tab"  value="history" />

				<label>
					<?php esc_html_e( 'From', 'noor-tms' ); ?><br>
					<input type="date" name="f_date_from" value="<?php echo esc_attr( $f_date_from ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'To', 'noor-tms' ); ?><br>
					<input type="date" name="f_date_to" value="<?php echo esc_attr( $f_date_to ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Time Slot', 'noor-tms' ); ?><br>
					<select name="f_slot">
						<option value=""><?php esc_html_e( '— All Slots —', 'noor-tms' ); ?></option>
						<?php foreach ( $slots as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f_slot, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Status', 'noor-tms' ); ?><br>
					<select name="f_status">
						<option value=""><?php esc_html_e( '— All —', 'noor-tms' ); ?></option>
						<?php foreach ( self::statuses() as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f_status, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( $can_manage ) : ?>
				<label>
					<?php esc_html_e( 'Class', 'noor-tms' ); ?><br>
					<select name="f_class_id">
						<option value=""><?php esc_html_e( '— All Classes —', 'noor-tms' ); ?></option>
						<?php foreach ( $classes as $cls ) : ?>
							<option value="<?php echo esc_attr( $cls['id'] ); ?>" <?php selected( $f_class_id, (int) $cls['id'] ); ?>><?php echo esc_html( $cls['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php endif; ?>
				<label>
					<?php esc_html_e( 'Student', 'noor-tms' ); ?><br>
					<input type="text" name="f_search" value="<?php echo esc_attr( $f_search ); ?>" placeholder="<?php esc_attr_e( 'Search name…', 'noor-tms' ); ?>" style="width:160px;" />
				</label>
				<label>
					<?php esc_html_e( 'Per Page', 'noor-tms' ); ?><br>
					<select name="per_page">
						<?php foreach ( [ 20, 25, 50, 100 ] as $pp ) : ?>
							<option value="<?php echo esc_attr( $pp ); ?>" <?php selected( $per_page, $pp ); ?>><?php echo esc_html( $pp ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php submit_button( __( 'Filter', 'noor-tms' ), 'secondary small', '', false ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-attendance&tab=history' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'Reset', 'noor-tms' ); ?>
				</a>
			</form>

			<!-- Summary -->
			<p class="noor-att-summary">
				<?php
				$start = ( $paged - 1 ) * $per_page + 1;
				$end   = min( $paged * $per_page, $total );
				if ( $total > 0 ) {
					printf(
						esc_html__( 'Showing %1$d–%2$d of %3$d records', 'noor-tms' ),
						$start, $end, $total
					);
				} else {
					esc_html_e( 'No records found.', 'noor-tms' );
				}
				?>
			</p>

			<?php if ( ! empty( $rows ) ) : ?>
			<div style="overflow-x:auto;">
				<table class="wp-list-table widefat fixed striped noor-tms-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Date', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Time Slot', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Marked By', 'noor-tms' ); ?></th>
							<?php if ( $can_manage ) : ?>
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
								<span class="noor-badge <?php echo esc_attr( self::status_class( $row['status'] ) ); ?>">
									<?php echo esc_html( self::statuses()[ $row['status'] ] ?? ucfirst( $row['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $row['marked_by_name'] ?? '—' ); ?></td>
							<?php if ( $can_manage ) : ?>
							<td>
								<button type="button"
										class="button button-small noor-open-correction"
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
						[ 'att_paged' => '%#%', 'per_page' => $per_page ],
						array_filter( [
							'f_date_from' => $f_date_from,
							'f_date_to'   => $f_date_to,
							'f_slot'      => $f_slot,
							'f_status'    => $f_status,
							'f_class_id'  => $f_class_id ?: '',
							'f_search'    => $f_search,
						] )
					),
					admin_url( 'admin.php?page=noor-tms-attendance&tab=history' )
				);
			?>
			<div class="tablenav" style="margin-top:12px;">
				<div class="tablenav-pages">
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
			<p><?php esc_html_e( 'No attendance records match the selected filters.', 'noor-tms' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Audit Log Tab
	// -----------------------------------------------------------------------

	private function render_audit_log_tab( bool $can_manage ): void {
		if ( ! $can_manage ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$f_search    = sanitize_text_field( $_GET['al_search']   ?? '' );
		$f_date_from = sanitize_text_field( $_GET['al_from']     ?? '' );
		$f_date_to   = sanitize_text_field( $_GET['al_to']       ?? '' );

		$entries = DatabaseHandler::get_attendance_audit_log( [
			'student_search' => $f_search,
			'date_from'      => $f_date_from,
			'date_to'        => $f_date_to,
			'limit'          => 100,
		] );

		$slots = self::slots();
		?>
		<div class="noor-tms-card" style="margin-top:12px;">
			<h2><?php esc_html_e( 'Attendance Correction Audit Log', 'noor-tms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Every manual correction to a historical attendance record is recorded here.', 'noor-tms' ); ?></p>

			<form method="get" action="" class="noor-filter-row" style="margin-bottom:16px;gap:10px;">
				<input type="hidden" name="page" value="noor-tms-attendance" />
				<input type="hidden" name="tab"  value="audit_log" />
				<input type="text"  name="al_search" value="<?php echo esc_attr( $f_search ); ?>" placeholder="<?php esc_attr_e( 'Student name…', 'noor-tms' ); ?>" style="width:160px;" />
				<input type="date"  name="al_from"   value="<?php echo esc_attr( $f_date_from ); ?>" />
				<input type="date"  name="al_to"     value="<?php echo esc_attr( $f_date_to ); ?>" />
				<?php submit_button( __( 'Filter', 'noor-tms' ), 'secondary small', '', false ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-attendance&tab=audit_log' ) ); ?>" class="button button-small"><?php esc_html_e( 'Reset', 'noor-tms' ); ?></a>
			</form>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'No corrections on record.', 'noor-tms' ); ?></p>
			<?php else : ?>
			<div style="overflow-x:auto;">
				<table class="wp-list-table widefat fixed striped noor-tms-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Date', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Slot', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Change', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Changed By', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Changed At', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $entry['student_name'] ); ?></strong></td>
							<td><?php echo esc_html( $entry['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $entry['att_date'] ); ?></td>
							<td>
								<span class="noor-slot-badge noor-slot-<?php echo esc_attr( $entry['time_slot'] ); ?>">
									<?php echo esc_html( $slots[ $entry['time_slot'] ] ?? ucfirst( $entry['time_slot'] ) ); ?>
								</span>
							</td>
							<td>
								<span class="noor-badge <?php echo esc_attr( self::status_class( $entry['old_status'] ) ); ?>" style="opacity:.7;">
									<?php echo esc_html( self::statuses()[ $entry['old_status'] ] ?? $entry['old_status'] ); ?>
								</span>
								&rarr;
								<span class="noor-badge <?php echo esc_attr( self::status_class( $entry['new_status'] ) ); ?>">
									<?php echo esc_html( self::statuses()[ $entry['new_status'] ] ?? $entry['new_status'] ); ?>
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
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Sessions Tab
	// -----------------------------------------------------------------------

	private function render_sessions_tab( bool $can_manage ): void {
		if ( ! $can_manage ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$sessions = DatabaseHandler::get_all_sessions();
		$limit    = DatabaseHandler::get_session_limit();
		$active   = count( array_filter( $sessions, fn( $s ) => (int) $s['is_active'] ) );
		?>
		<div class="noor-tms-card" style="margin-top:12px;">
			<h2><?php esc_html_e( 'Attendance Sessions', 'noor-tms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Create on-demand sessions for attendance marking. Each session label is auto-derived from the selected time.', 'noor-tms' ); ?>
			</p>

			<!-- Session limit -->
			<div class="noor-session-limit-row">
				<label for="noor-session-limit-input"><strong><?php esc_html_e( 'Active Session Limit:', 'noor-tms' ); ?></strong></label>
				<input type="number" id="noor-session-limit-input" value="<?php echo esc_attr( $limit ); ?>" min="1" max="20" style="width:70px;" />
				<button type="button" id="noor-save-session-limit" class="button button-secondary">
					<?php esc_html_e( 'Save Limit', 'noor-tms' ); ?>
				</button>
				<span id="noor-limit-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
			</div>
			<p class="description" style="margin-top:4px;">
				<?php
				printf(
					/* translators: 1: active count, 2: limit */
					esc_html__( '%1$d of %2$d active slots in use.', 'noor-tms' ),
					$active,
					$limit
				);
				?>
			</p>

			<!-- Session list -->
			<?php if ( ! empty( $sessions ) ) : ?>
			<table class="wp-list-table widefat fixed striped noor-tms-table" style="margin-top:16px;" id="noor-sessions-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Label', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Time', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Slot Key', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions as $sess ) : ?>
					<tr id="noor-sess-row-<?php echo esc_attr( $sess['id'] ); ?>">
						<td><strong><?php echo esc_html( $sess['label'] ); ?></strong></td>
						<td><?php echo esc_html( date_i18n( 'g:i A', strtotime( $sess['session_time'] ) ) ); ?></td>
						<td><code><?php echo esc_html( $sess['slot_key'] ); ?></code></td>
						<td>
							<span class="noor-sess-status noor-badge <?php echo $sess['is_active'] ? 'noor-badge--active' : 'noor-badge--inactive'; ?>">
								<?php echo $sess['is_active'] ? esc_html__( 'Active', 'noor-tms' ) : esc_html__( 'Inactive', 'noor-tms' ); ?>
							</span>
						</td>
						<td style="display:flex;gap:6px;">
							<button type="button"
									class="button button-small noor-toggle-session"
									data-id="<?php echo esc_attr( $sess['id'] ); ?>"
									data-active="<?php echo esc_attr( $sess['is_active'] ); ?>">
								<?php echo $sess['is_active'] ? esc_html__( 'Deactivate', 'noor-tms' ) : esc_html__( 'Activate', 'noor-tms' ); ?>
							</button>
							<button type="button"
									class="button button-small button-link-delete noor-delete-session"
									data-id="<?php echo esc_attr( $sess['id'] ); ?>"
									data-label="<?php echo esc_attr( $sess['label'] ); ?>">
								<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p><?php esc_html_e( 'No sessions yet. Create one below.', 'noor-tms' ); ?></p>
			<?php endif; ?>

			<!-- Create session form -->
			<div class="noor-tms-card" style="margin-top:20px;background:#f9f9f9;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Create New Session', 'noor-tms' ); ?></h3>
				<?php if ( $active >= $limit ) : ?>
				<p class="noor-notice" style="background:#fff3cd;border-left:4px solid #e6a817;padding:8px 12px;border-radius:3px;">
					<?php
					printf(
						esc_html__( 'Session limit (%d active) reached. Deactivate or delete an existing session before adding a new one.', 'noor-tms' ),
						$limit
					);
					?>
				</p>
				<?php endif; ?>
				<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
					<label>
						<strong><?php esc_html_e( 'Session Time:', 'noor-tms' ); ?></strong><br>
						<input type="time" id="noor-new-session-time" value="09:00" style="font-size:1rem;" />
					</label>
					<label>
						<strong><?php esc_html_e( 'Auto Label:', 'noor-tms' ); ?></strong><br>
						<input type="text" id="noor-new-session-label" value="Morning" style="width:160px;"
							   placeholder="<?php esc_attr_e( 'e.g. Morning', 'noor-tms' ); ?>" />
					</label>
					<button type="button" id="noor-create-session-btn" class="button button-primary">
						<?php esc_html_e( 'Add Session', 'noor-tms' ); ?>
					</button>
					<span id="noor-create-session-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
				</div>
				<p class="description" style="margin-top:8px;">
					<?php esc_html_e( 'The label is automatically derived from the time and can be edited before saving.', 'noor-tms' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Correction modal (rendered once per page)
	// -----------------------------------------------------------------------

	private function render_correction_modal(): void {
		?>
		<div id="noor-correction-modal" class="noor-modal-overlay" hidden aria-modal="true" role="dialog" aria-labelledby="noor-modal-title">
			<div class="noor-modal-box">
				<div class="noor-modal-header">
					<h3 id="noor-modal-title"><?php esc_html_e( 'Correct Attendance Record', 'noor-tms' ); ?></h3>
					<button type="button" class="noor-modal-close" aria-label="<?php esc_attr_e( 'Close', 'noor-tms' ); ?>">&times;</button>
				</div>
				<div class="noor-modal-body">
					<p>
						<strong><?php esc_html_e( 'Student:', 'noor-tms' ); ?></strong>
						<span id="noor-modal-student"></span>
					</p>
					<p>
						<strong><?php esc_html_e( 'Date / Slot:', 'noor-tms' ); ?></strong>
						<span id="noor-modal-date-slot"></span>
					</p>
					<p>
						<strong><?php esc_html_e( 'Current Status:', 'noor-tms' ); ?></strong>
						<span id="noor-modal-current-status"></span>
					</p>

					<label for="noor-modal-new-status"><strong><?php esc_html_e( 'New Status:', 'noor-tms' ); ?></strong></label><br>
					<select id="noor-modal-new-status" style="width:100%;margin:6px 0 12px;">
						<?php foreach ( self::statuses() as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select><br>

					<label for="noor-modal-reason"><strong><?php esc_html_e( 'Reason for Change:', 'noor-tms' ); ?></strong> <span style="color:red;">*</span></label><br>
					<textarea id="noor-modal-reason" rows="3" style="width:100%;margin:6px 0 4px;" placeholder="<?php esc_attr_e( 'e.g. Medical proof provided, Administrative error…', 'noor-tms' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'A reason is required and will be stored in the audit log.', 'noor-tms' ); ?></p>
				</div>
				<div class="noor-modal-footer">
					<button type="button" id="noor-modal-submit" class="button button-primary"><?php esc_html_e( 'Save Correction', 'noor-tms' ); ?></button>
					<button type="button" class="button noor-modal-close"><?php esc_html_e( 'Cancel', 'noor-tms' ); ?></button>
					<span id="noor-modal-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
				</div>
				<input type="hidden" id="noor-modal-att-id" value="" />
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// AJAX: save attendance (bulk, with time slot + global mode support)
	// -----------------------------------------------------------------------

	public function ajax_save_student_attendance(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		$can_manage = noor_tms_can_manage();
		$can_teach  = current_user_can( 'noor_tms_teacher' );

		if ( ! $can_manage && ! $can_teach ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$class_id    = (int) ( $_POST['class_id']  ?? 0 );
		$att_date    = sanitize_text_field( $_POST['att_date']   ?? current_time( 'Y-m-d' ) );
		$time_slot   = sanitize_key( $_POST['time_slot']         ?? 'morning' );
		$records_raw = (array) ( $_POST['records']               ?? [] );
		$marked_by   = get_current_user_id();

		// Validate time slot.
		$valid_slots = array_keys( DatabaseHandler::get_time_slots() );
		if ( ! in_array( $time_slot, $valid_slots, true ) ) {
			$time_slot = 'morning';
		}

		// Teachers can only mark attendance for their assigned classes.
		if ( $can_teach && ! $can_manage ) {
			if ( ! $class_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid class.', 'noor-tms' ) ] );
			}
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
				'class_id'   => (int) ( $rec['class_id']   ?? 0 ),
				'status'     => sanitize_key( $rec['status'] ?? 'present' ),
			];
		}

		$saved = DatabaseHandler::bulk_save_student_attendance( $class_id, $att_date, $records, $marked_by, $time_slot );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: records saved */
				_n( '%d record saved.', '%d records saved.', $saved, 'noor-tms' ),
				$saved
			),
		] );
	}

	// -----------------------------------------------------------------------
	// AJAX: session management
	// -----------------------------------------------------------------------

	public function ajax_create_session(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$time  = sanitize_text_field( $_POST['session_time'] ?? '' );
		$label = sanitize_text_field( $_POST['label']        ?? '' );

		if ( ! $time ) {
			wp_send_json_error( [ 'message' => __( 'Session time is required.', 'noor-tms' ) ] );
		}

		$result = DatabaseHandler::create_session( $time, $label );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		$limit  = DatabaseHandler::get_session_limit();
		$active = count( array_filter( DatabaseHandler::get_all_sessions(), fn( $s ) => (int) $s['is_active'] ) );

		wp_send_json_success( [
			'message'      => __( 'Session created.', 'noor-tms' ),
			'session'      => $result,
			'active_count' => $active,
			'limit'        => $limit,
		] );
	}

	public function ajax_delete_session(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$id = (int) ( $_POST['session_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid session.', 'noor-tms' ) ] );
		}

		if ( DatabaseHandler::delete_session( $id ) ) {
			wp_send_json_success( [ 'message' => __( 'Session deleted.', 'noor-tms' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not delete session.', 'noor-tms' ) ] );
		}
	}

	public function ajax_toggle_session(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$id     = (int) ( $_POST['session_id'] ?? 0 );
		$result = DatabaseHandler::toggle_session( $id );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success( [
			'is_active' => $result['is_active'],
			'message'   => $result['is_active']
				? __( 'Session activated.', 'noor-tms' )
				: __( 'Session deactivated.', 'noor-tms' ),
		] );
	}

	public function ajax_set_session_limit(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$limit = (int) ( $_POST['limit'] ?? 4 );
		DatabaseHandler::set_session_limit( $limit );
		wp_send_json_success( [
			'limit'   => DatabaseHandler::get_session_limit(),
			'message' => __( 'Session limit updated.', 'noor-tms' ),
		] );
	}

	// -----------------------------------------------------------------------
	// AJAX: correct a historical attendance record (with audit log)
	// -----------------------------------------------------------------------

	public function ajax_correct_attendance(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$att_id     = (int) ( $_POST['att_id']     ?? 0 );
		$new_status = sanitize_key( $_POST['new_status'] ?? '' );
		$reason     = sanitize_textarea_field( $_POST['reason'] ?? '' );
		$changed_by = get_current_user_id();

		if ( ! $att_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid attendance record.', 'noor-tms' ) ] );
		}

		if ( ! $reason ) {
			wp_send_json_error( [ 'message' => __( 'A reason for the change is required.', 'noor-tms' ) ] );
		}

		$allowed = [ 'present', 'absent', 'late', 'excused' ];
		if ( ! in_array( $new_status, $allowed, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid status.', 'noor-tms' ) ] );
		}

		$ok = DatabaseHandler::correct_attendance( $att_id, $new_status, $reason, $changed_by );

		if ( $ok ) {
			wp_send_json_success( [
				'message'    => __( 'Attendance record corrected and logged.', 'noor-tms' ),
				'new_status' => $new_status,
			] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not update the record. Please try again.', 'noor-tms' ) ] );
		}
	}
}
