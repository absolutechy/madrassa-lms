<?php
/**
 * Teachers management — CRUD, class assignments, and teacher attendance.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;
use Noor_TMS\Includes\TeacherUserService;

defined( 'ABSPATH' ) || exit;

/**
 * Class Teachers
 */
class Teachers {

	public function __construct() {
		// noor_tms_teacher_handle_wp_user_fields is handled by a single subscriber
		// registered in PublicController::register_shortcodes(), which delegates to
		// TeacherUserService::handle_wp_user_fields(). No duplicate registration here.
	}

	// -----------------------------------------------------------------------
	// Page router
	// -----------------------------------------------------------------------

	public function page_teachers(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$action     = sanitize_key( $_GET['action']     ?? 'list' );
		$teacher_id = (int) ( $_GET['teacher_id']       ?? 0 );

		if ( isset( $_POST['noor_tms_teacher_nonce'] ) ) {
			$this->handle_save( $teacher_id );
			return;
		}

		if ( ( 'edit' === $action && $teacher_id ) || 'new' === $action ) {
			$this->page_form( $teacher_id );
		} else {
			$this->page_list();
		}
	}

	// -----------------------------------------------------------------------
	// Teacher Attendance page
	// -----------------------------------------------------------------------

	public function page_teacher_attendance(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$att_date = sanitize_text_field( $_GET['att_date'] ?? current_time( 'Y-m-d' ) );
		$tab      = sanitize_key( $_GET['tab'] ?? 'mark' );
		$month    = (int) ( $_GET['att_month'] ?? (int) current_time( 'n' ) );
		$year     = (int) ( $_GET['att_year']  ?? (int) current_time( 'Y' ) );

		$teachers          = DatabaseHandler::get_teachers();
		$marked_today      = DatabaseHandler::get_teacher_attendance_for_date( $att_date );
		$summary           = ( 'history' === $tab ) ? DatabaseHandler::get_teacher_attendance_summary( $month, $year ) : [];
		$month_label       = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php esc_html_e( 'Teacher Attendance', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<!-- Tabs -->
			<nav class="nav-tab-wrapper noor-tab-nav">
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-teacher-attendance', 'tab' => 'mark' ], admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'mark' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Mark Attendance', 'noor-tms' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-teacher-attendance', 'tab' => 'history' ], admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'history' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'History', 'noor-tms' ); ?>
				</a>
			</nav>

			<?php if ( 'mark' === $tab ) : ?>
			<!-- ============================================================
			     Mark Attendance Tab
			     ============================================================ -->
			<div class="noor-tms-card" style="margin-top:12px;">
				<h2><?php esc_html_e( 'Mark Teacher Attendance', 'noor-tms' ); ?></h2>

				<!-- Date picker -->
				<form method="get" action="">
					<input type="hidden" name="page" value="noor-tms-teacher-attendance" />
					<input type="hidden" name="tab"  value="mark" />
					<p>
						<label for="att_date_picker"><strong><?php esc_html_e( 'Date:', 'noor-tms' ); ?></strong></label>
						<input type="date" id="att_date_picker" name="att_date"
							   value="<?php echo esc_attr( $att_date ); ?>" />
						<?php submit_button( __( 'Load', 'noor-tms' ), 'secondary small', '', false ); ?>
					</p>
				</form>

				<?php if ( empty( $teachers ) ) : ?>
					<p><?php esc_html_e( 'No teachers found. Add teachers first.', 'noor-tms' ); ?></p>
				<?php else : ?>
				<form id="noor-teacher-att-form">
					<?php wp_nonce_field( 'noor_tms_ajax', 'noor_tms_att_nonce' ); ?>
					<input type="hidden" name="att_date"  value="<?php echo esc_attr( $att_date ); ?>" />

					<table class="wp-list-table widefat fixed striped noor-tms-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Teacher', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'noor-tms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $teachers as $teacher ) :
								$current_status = $marked_today[ (int) $teacher['id'] ] ?? 'present';
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $teacher['name'] ); ?></strong>
									<input type="hidden" name="records[<?php echo esc_attr( $teacher['id'] ); ?>][teacher_id]"
										   value="<?php echo esc_attr( $teacher['id'] ); ?>" />
								</td>
								<td><?php echo esc_html( $teacher['phone'] ); ?></td>
								<td>
									<select name="records[<?php echo esc_attr( $teacher['id'] ); ?>][status]" class="noor-att-status">
										<?php foreach ( [ 'present' => __( 'Present', 'noor-tms' ), 'absent' => __( 'Absent', 'noor-tms' ), 'late' => __( 'Late', 'noor-tms' ), 'excused' => __( 'Excused', 'noor-tms' ) ] as $val => $lbl ) : ?>
											<option value="<?php echo esc_attr( $val ); ?>"
												<?php selected( $current_status, $val ); ?>>
												<?php echo esc_html( $lbl ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" name="records[<?php echo esc_attr( $teacher['id'] ); ?>][notes]"
										   class="regular-text" placeholder="<?php esc_attr_e( 'Optional note', 'noor-tms' ); ?>" />
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="noor-form-actions" style="margin-top:12px;">
						<button type="submit" id="noor-save-teacher-att-btn" class="button button-primary">
							<?php esc_html_e( 'Save Attendance', 'noor-tms' ); ?>
						</button>
						<span id="noor-teacher-att-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
					</div>
				</form>
				<?php endif; ?>
			</div>

			<?php else : ?>
			<!-- ============================================================
			     History Tab
			     ============================================================ -->
			<div class="noor-tms-card" style="margin-top:12px;">
				<h2><?php esc_html_e( 'Attendance History', 'noor-tms' ); ?></h2>

				<!-- Month filter -->
				<form method="get" action="">
					<input type="hidden" name="page" value="noor-tms-teacher-attendance" />
					<input type="hidden" name="tab"  value="history" />
					<p>
						<label for="att_month"><?php esc_html_e( 'Month:', 'noor-tms' ); ?></label>
						<select id="att_month" name="att_month">
							<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
								<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>>
									<?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<input type="number" name="att_year" value="<?php echo esc_attr( $year ); ?>"
							   min="2020" max="2099" style="width:80px;" />
						<?php submit_button( __( 'View', 'noor-tms' ), 'secondary small', '', false ); ?>
					</p>
				</form>

				<h3><?php echo esc_html( $month_label ); ?></h3>

				<?php if ( empty( $summary ) ) : ?>
					<p><?php esc_html_e( 'No attendance records for this month.', 'noor-tms' ); ?></p>
				<?php else : ?>
				<table class="wp-list-table widefat fixed striped noor-tms-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Teacher', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Present', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Absent', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Late', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Excused', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Total Days', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Attendance %', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $summary as $data ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
							<td><?php echo esc_html( $data['present'] ); ?></td>
							<td><?php echo esc_html( $data['absent'] ); ?></td>
							<td><?php echo esc_html( $data['late'] ); ?></td>
							<td><?php echo esc_html( $data['excused'] ); ?></td>
							<td><?php echo esc_html( $data['total_days'] ); ?></td>
							<td>
								<span class="noor-pct noor-pct-<?php echo $data['pct'] >= 75 ? 'pass' : 'fail'; ?>">
									<strong><?php echo esc_html( $data['pct'] . '%' ); ?></strong>
								</span>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// List view
	// -----------------------------------------------------------------------

	private function page_list(): void {
		$teachers = DatabaseHandler::get_teachers();
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Teachers', 'noor-tms' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-teachers', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"
			   class="page-title-action"><?php esc_html_e( 'Add New Teacher', 'noor-tms' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<?php if ( empty( $teachers ) ) : ?>
				<div class="noor-tms-card">
					<p><?php esc_html_e( 'No teachers yet. Add a teacher to assign them to classes.', 'noor-tms' ); ?></p>
				</div>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped noor-tms-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Linked WP User', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Classes', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $teachers as $teacher ) :
						$wp_user = get_user_by( 'id', (int) $teacher['wp_user_id'] );
					?>
					<tr id="noor-teacher-row-<?php echo esc_attr( $teacher['id'] ); ?>">
						<td><strong><?php echo esc_html( $teacher['name'] ); ?></strong></td>
						<td><?php echo esc_html( $teacher['phone'] ); ?></td>
						<td><?php echo $wp_user ? esc_html( $wp_user->user_login . ' (' . $wp_user->display_name . ')' ) : '<em>' . esc_html__( 'Not linked', 'noor-tms' ) . '</em>'; ?></td>
						<td><?php echo esc_html( $teacher['class_count'] ); ?></td>
						<td>
							<span class="noor-status-badge noor-status-<?php echo $teacher['is_active'] ? 'active' : 'inactive'; ?>">
								<?php echo $teacher['is_active'] ? esc_html__( 'Active', 'noor-tms' ) : esc_html__( 'Inactive', 'noor-tms' ); ?>
							</span>
						</td>
						<td class="noor-actions">
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-teachers', 'action' => 'edit', 'teacher_id' => $teacher['id'] ], admin_url( 'admin.php' ) ) ); ?>"
							   class="button button-small"><?php esc_html_e( 'Edit', 'noor-tms' ); ?></a>

							<button type="button" class="button button-small button-link-delete noor-delete-teacher"
									data-id="<?php echo esc_attr( $teacher['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Add / Edit form
	// -----------------------------------------------------------------------

	private function page_form( int $teacher_id ): void {
		$teacher     = $teacher_id ? DatabaseHandler::get_teacher( $teacher_id ) : null;
		$assignments = $teacher_id ? DatabaseHandler::get_teacher_assignments( $teacher_id ) : [];
		$classes     = DatabaseHandler::get_classes_dropdown();
		$subjects    = []; // keyed by class_id => subjects array
		foreach ( $classes as $cls ) {
			$subjects[ (int) $cls['id'] ] = DatabaseHandler::get_subjects_by_class( (int) $cls['id'] );
		}
		$title       = $teacher ? __( 'Edit Teacher', 'noor-tms' ) : __( 'Add New Teacher', 'noor-tms' );

		// Prepare existing assignments for display.
		$existing_homerooms = []; // array of class_ids of homeroom assignment
		$existing_subjects  = []; // [['class_id' => X, 'subject_id' => Y], ...]
		foreach ( $assignments as $a ) {
			if ( 'homeroom' === $a['role_type'] ) {
				$existing_homerooms[] = (int) $a['class_id'];
			} else {
				$existing_subjects[] = $a;
			}
		}

		// WP users for the dropdown (only subscriber/editor/author — not admins).
		$wp_users = get_users( [ 'orderby' => 'display_name', 'number' => 200 ] );
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<hr class="wp-header-end">

			<div class="noor-tms-card">
				<form method="post" action="" id="noor-teacher-form">
					<?php wp_nonce_field( 'noor_tms_save_teacher', 'noor_tms_teacher_nonce' ); ?>
					<?php if ( $teacher_id ) : ?>
						<input type="hidden" name="teacher_id" value="<?php echo esc_attr( $teacher_id ); ?>" />
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="teacher_name"><?php esc_html_e( 'Full Name', 'noor-tms' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="teacher_name" name="teacher_name" required
									   class="regular-text"
									   value="<?php echo esc_attr( $teacher['name'] ?? '' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="teacher_phone"><?php esc_html_e( 'Phone', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="tel" id="teacher_phone" name="teacher_phone"
									   class="regular-text"
									   placeholder="+923001234567"
									   value="<?php echo esc_attr( $teacher['phone'] ?? '' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
    <label for="teacher_wp_user"><?php esc_html_e( 'WordPress User', 'noor-tms' ); ?> <span class="required">*</span></label>
    <p class="description"><?php esc_html_e( 'The connected WordPress account for this teacher.', 'noor-tms' ); ?></p>
</th>
<td>
    <?php 
    $wp_user_id = (int) ( $teacher['wp_user_id'] ?? 0 );
    if ( $teacher_id && $wp_user_id ) : 
        $linked_u = get_user_by('id', $wp_user_id);
    ?>
        <p><strong><?php echo esc_html( $linked_u ? $linked_u->display_name . ' (' . $linked_u->user_login . ')' : __( 'Unknown User', 'noor-tms' ) ); ?></strong></p>
        <input type="hidden" name="wp_user_id" value="<?php echo esc_attr( $wp_user_id ); ?>" />
    <?php else : ?>
        <p>
            <label for="new_wp_user_login"><?php esc_html_e( 'Username', 'noor-tms' ); ?></label><br />
            <input type="text" id="new_wp_user_login" name="new_wp_user_login" class="regular-text" required />
        </p>
        <p>
            <label for="new_wp_user_email"><?php esc_html_e( 'Email', 'noor-tms' ); ?></label><br />
            <input type="email" id="new_wp_user_email" name="new_wp_user_email" class="regular-text" required />
        </p>
        <p>
            <label for="new_wp_user_pass"><?php esc_html_e( 'Password', 'noor-tms' ); ?></label><br />
            <input type="password" id="new_wp_user_pass" name="new_wp_user_pass" class="regular-text" />
            <span class="description"><?php esc_html_e( 'Leave blank to generate a random password.', 'noor-tms' ); ?></span>
        </p>
    <?php endif; ?>
</td>
</tr>
						<tr>
							<th scope="row">
								<label for="teacher_is_active"><?php esc_html_e( 'Status', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="teacher_is_active" name="is_active">
									<option value="1" <?php selected( (int) ( $teacher['is_active'] ?? 1 ), 1 ); ?>><?php esc_html_e( 'Active', 'noor-tms' ); ?></option>
									<option value="0" <?php selected( (int) ( $teacher['is_active'] ?? 1 ), 0 ); ?>><?php esc_html_e( 'Inactive', 'noor-tms' ); ?></option>
								</select>
							</td>
						</tr>

						<?php if ( ! empty( $classes ) ) : ?>
						<!-- ------------------------------------------------
						     Class Assignments
						     ------------------------------------------------ -->
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Class & Subject Assignments', 'noor-tms' ); ?></label>
								<p class="description"><?php esc_html_e( 'Assign classes (homeroom) and their respective subjects to this teacher. A class/subject assigned to another teacher cannot be selected.', 'noor-tms' ); ?></p>
							</th>
							<td>
								<?php
								$assigned_roles = \Noor_TMS\Includes\DatabaseHandler::get_assigned_class_roles();
								?>
								<div class="noor-class-subjects-container" style="display: flex; flex-direction: column; gap: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 600px;">
									<?php foreach ( $classes as $cls ) : 
										$c_id = (int) $cls['id'];
										
										// Check if homeroom is assigned to a DIFFERENT teacher
										$is_class_assigned_other = false;
										if ( isset( $assigned_roles['homeroom'][$c_id] ) ) {
											$assigned_t = (int) $assigned_roles['homeroom'][$c_id];
											if ( $assigned_t !== $teacher_id ) {
												$is_class_assigned_other = true;
											}
										}
										$is_homeroom_here = in_array( $c_id, $existing_homerooms, true );
									?>
									<div class="noor-assignment-group" style="padding-bottom: 10px; border-bottom: 1px solid #f0f0f1;">
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">
											<input type="checkbox" name="homeroom_class_ids[]" value="<?php echo esc_attr( $c_id ); ?>"
												<?php checked( $is_homeroom_here, true ); ?>
												<?php disabled( $is_class_assigned_other, true ); ?>>
											<?php echo esc_html( $cls['name'] ); ?>
											<?php if ( $is_class_assigned_other ) : ?>
												<span style="color: #d63638; font-weight: normal; font-size: 12px; margin-left: 8px;">(Assigned to another teacher)</span>
											<?php else : ?>
												<span style="color: #646970; font-weight: normal; font-size: 12px; margin-left: 8px;">(Homeroom)</span>
											<?php endif; ?>
										</label>

										<div class="noor-subjects-list" style="margin-left: 24px; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
											<?php 
											if ( isset( $subjects[ $c_id ] ) ) : 
												foreach ( $subjects[ $c_id ] as $sub ) :
													$s_id = (int) $sub['id'];
													
													// Check if subject is assigned to this teacher
													$is_subject_here = false;
													foreach ( $existing_subjects as $es ) {
														if ( (int) $es['class_id'] === $c_id && (int) $es['subject_id'] === $s_id ) {
															$is_subject_here = true;
															break;
														}
													}
													
													// Check if subject is assigned to a DIFFERENT teacher
													$is_subject_assigned_other = false;
													$combo_key = $c_id . '_' . $s_id;
													if ( isset( $assigned_roles['subject'][$combo_key] ) ) {
														$assigned_t = (int) $assigned_roles['subject'][$combo_key];
														if ( $assigned_t !== $teacher_id ) {
															$is_subject_assigned_other = true;
														}
													}
											?>
												<label style="display: flex; align-items: center;">
													<input type="checkbox" name="subject_assignments[<?php echo esc_attr( $c_id ); ?>][]" value="<?php echo esc_attr( $s_id ); ?>"
														<?php checked( $is_subject_here, true ); ?>
														<?php disabled( $is_subject_assigned_other, true ); ?>>
													<span style="<?php echo $is_subject_assigned_other ? 'color: #a7aaad;' : ''; ?>">
														<?php echo esc_html( $sub['subject_name'] ); ?>
													</span>
												</label>
											<?php 
												endforeach;
											else : 
											?>
												<span style="color: #8c8f94; font-size: 12px;">No subjects added for this class.</span>
											<?php endif; ?>
										</div>
									</div>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
						<?php endif; ?>
					</table>

					<div class="noor-form-actions">
						<?php submit_button(
							$teacher ? __( 'Update Teacher', 'noor-tms' ) : __( 'Add Teacher', 'noor-tms' ),
							'primary', 'submit', false
						); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-teachers' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
						</a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Form handler
	// -----------------------------------------------------------------------

	private function handle_save( int $teacher_id ): void {
		if ( ! check_admin_referer( 'noor_tms_save_teacher', 'noor_tms_teacher_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$name       = sanitize_text_field( $_POST['teacher_name']    ?? '' );
		$phone      = sanitize_text_field( $_POST['teacher_phone']   ?? '' );
		$wp_user_id = (int) ( $_POST['wp_user_id']                   ?? 0 );

		// Allow WP user creation via form fields (hookable).
		do_action( 'noor_tms_teacher_handle_wp_user_fields' );
		
		// Re-fetch wp_user_id after hook (in case it was created by the hook).
		$wp_user_id = (int) ( $_POST['wp_user_id'] ?? 0 );

		$is_active  = (int) ( $_POST['is_active']                    ?? 1 );

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Teacher name cannot be empty.', 'noor-tms' ) );
		}
		if ( ! $wp_user_id || ! get_user_by( 'id', $wp_user_id ) ) {
			wp_die( esc_html__( 'Please select a valid WordPress user.', 'noor-tms' ) );
		}
		if ( ! empty( $phone ) && ! preg_match( '/^\+[1-9]\d{7,14}$/', $phone ) ) {
			wp_die( esc_html__( 'Invalid phone format. Use international format, e.g. +923001234567', 'noor-tms' ) );
		}

		if ( $teacher_id > 0 ) {
			// Handle WP user reassignment: revoke cap from old user if changed.
			$old = DatabaseHandler::get_teacher( $teacher_id );
			if ( $old && (int) $old['wp_user_id'] !== $wp_user_id ) {
				$old_user = get_user_by( 'id', (int) $old['wp_user_id'] );
				if ( $old_user ) {
					$old_user->remove_cap( 'noor_tms_teacher' );
				}
				// Grant to new user.
				$new_user = get_user_by( 'id', $wp_user_id );
				if ( $new_user ) {
					$new_user->add_cap( 'noor_tms_teacher' );
				}
			}
			DatabaseHandler::update_teacher( $teacher_id, compact( 'name', 'phone', 'is_active' ) );
			$msg = 'teacher_updated';
		} else {
			$teacher_id = DatabaseHandler::insert_teacher( compact( 'wp_user_id', 'name', 'phone', 'is_active' ) );
			$msg        = 'teacher_added';
		}

		if ( $teacher_id ) {
			// Build class assignments array.
			$assignments = [];

			$homeroom_class_ids = (array) ( $_POST['homeroom_class_ids'] ?? [] );
			foreach ( $homeroom_class_ids as $cls_id ) {
				$cls_id = (int) $cls_id;
				if ( $cls_id ) {
					$assignments[] = [ 'class_id' => $cls_id, 'role_type' => 'homeroom', 'subject_id' => null ];
				}
			}

			$subject_assignments = (array) ( $_POST['subject_assignments'] ?? [] );
			foreach ( $subject_assignments as $c_id => $subject_ids ) {
				$c_id = (int) $c_id;
				if ( ! $c_id ) continue;
				$subject_ids = (array) $subject_ids;
				foreach ( $subject_ids as $s_id ) {
					$s_id = (int) $s_id;
					if ( $s_id ) {
						$assignments[] = [ 'class_id' => $c_id, 'role_type' => 'subject', 'subject_id' => $s_id ];
					}
				}
			}

			DatabaseHandler::save_teacher_assignments( $teacher_id, $assignments );
		}

		wp_safe_redirect(
			add_query_arg( [ 'page' => 'noor-tms-teachers', 'msg' => $msg ], admin_url( 'admin.php' ) )
		);
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	/**
	 * AJAX: delete a teacher.
	 */
	public function ajax_delete_teacher(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$teacher_id = (int) ( $_POST['teacher_id'] ?? 0 );
		if ( ! $teacher_id || ! DatabaseHandler::delete_teacher( $teacher_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete teacher.', 'noor-tms' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Teacher deleted.', 'noor-tms' ) ] );
	}

	/**
	 * AJAX: bulk-save teacher attendance.
	 */
	public function ajax_save_teacher_attendance(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$att_date   = sanitize_text_field( $_POST['att_date'] ?? current_time( 'Y-m-d' ) );
		$records_raw = (array) ( $_POST['records'] ?? [] );
		$marked_by  = get_current_user_id();

		$records = [];
		foreach ( $records_raw as $tid_key => $rec ) {
			$records[] = [
				'teacher_id' => (int) ( $rec['teacher_id'] ?? $tid_key ),
				'status'     => sanitize_key( $rec['status'] ?? 'present' ),
				'notes'      => sanitize_text_field( $rec['notes'] ?? '' ),
			];
		}

		$saved = DatabaseHandler::bulk_save_teacher_attendance( $att_date, $records, $marked_by );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: records saved */
				_n( '%d record saved.', '%d records saved.', $saved, 'noor-tms' ),
				$saved
			),
		] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Render extra fields on the teacher add/edit form.
	 *
	 * @param array<string,mixed>|null $teacher
	 */
	public function render_teacher_form_extra_fields( ?array $teacher ): void {
		$login = sanitize_text_field( $_POST['new_wp_user_login'] ?? '' );
		$email = sanitize_email( $_POST['new_wp_user_email'] ?? '' );
		?>
		<hr />
		<p class="description"><?php esc_html_e( 'Or create a new WordPress user for this teacher.', 'noor-tms' ); ?></p>

		<p>
			<label for="new_wp_user_login"><?php esc_html_e( 'Username', 'noor-tms' ); ?></label><br />
			<input type="text" id="new_wp_user_login" name="new_wp_user_login"
			       class="regular-text" value="<?php echo esc_attr( $login ); ?>" />
		</p>
		<p>
			<label for="new_wp_user_email"><?php esc_html_e( 'Email', 'noor-tms' ); ?></label><br />
			<input type="email" id="new_wp_user_email" name="new_wp_user_email"
			       class="regular-text" value="<?php echo esc_attr( $email ); ?>" />
		</p>
		<p>
			<label for="new_wp_user_pass"><?php esc_html_e( 'Password', 'noor-tms' ); ?></label><br />
			<input type="password" id="new_wp_user_pass" name="new_wp_user_pass"
			       class="regular-text" />
			<span class="description"><?php esc_html_e( 'Leave blank to generate a random password.', 'noor-tms' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Create a WP user from submitted fields (if any) and update $wp_user_id.
	 *
	 * @param int|null $wp_user_id
	 * @param int      $teacher_id
	 */
	/**
	 * Delegate WP user creation to the canonical TeacherUserService.
	 * Called by handle_save() after do_action('noor_tms_teacher_handle_wp_user_fields').
	 */
	public function handle_wp_user_fields(): void {
		TeacherUserService::handle_wp_user_fields();
	}

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		$map = [
			'teacher_added'   => __( 'Teacher added successfully.', 'noor-tms' ),
			'teacher_updated' => __( 'Teacher updated successfully.', 'noor-tms' ),
		];
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html( $map[ $msg ] ) . '</p></div>';
		}
	}
}


