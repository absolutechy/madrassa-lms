<?php
/**
 * Front-end teacher management.
 *
 * Variables in scope:
 *   $action            string  'list'|'new'|'edit'
 *   $teacher_id        int
 *   $teachers          array  teacher list rows
 *   $teacher           array|null teacher row when editing
 *   $classes           array  class dropdown rows
 *   $subjects_by_class array  subjects keyed by class_id
 *   $existing_homerooms array
 *   $existing_subjects array
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Teachers', 'noor-tms' );
$active_nav     = 'teachers';
$topbar_actions = '<a href="' . esc_url( add_query_arg( [ 'tms_action' => 'new' ], home_url( '/tms-teachers/' ) ) ) . '" class="noor-btn noor-btn--primary">+ ' . esc_html__( 'Add New Teacher', 'noor-tms' ) . '</a>';

include __DIR__ . '/layout.php';

$msg = sanitize_key( $_GET['msg'] ?? '' );
if ( 'teacher_added' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Teacher added successfully.', 'noor-tms' ) . '</div>';
} elseif ( 'teacher_updated' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Teacher updated successfully.', 'noor-tms' ) . '</div>';
}

if ( 'new' === $action || ( 'edit' === $action && $teacher_id ) ) :
	$teacher_name  = $teacher['name'] ?? '';
	$teacher_phone = $teacher['phone'] ?? '';
	$wp_user_id    = (int) ( $teacher['wp_user_id'] ?? 0 );
	$is_active     = (int) ( $teacher['is_active'] ?? 1 );
	
	// Build existing assignment rows.
	$rows_to_show = ! empty( $existing_subjects ) ? $existing_subjects : [ null ];
?>
	<div class="noor-card">
		<h2><?php echo esc_html( $teacher ? __( 'Edit Teacher', 'noor-tms' ) : __( 'Add New Teacher', 'noor-tms' ) ); ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="noor_tms_save_teacher" />
			<?php wp_nonce_field( 'noor_tms_teacher_nonce', 'noor_tms_teacher_nonce' ); ?>
			<?php if ( $teacher_id ) : ?>
				<input type="hidden" name="teacher_id" value="<?php echo esc_attr( $teacher_id ); ?>" />
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="teacher_name"><?php esc_html_e( 'Full Name', 'noor-tms' ); ?> <span class="required">*</span></label></th>
					<td>
						<input type="text" id="teacher_name" name="teacher_name" required class="regular-text"
						       value="<?php echo esc_attr( $teacher_name ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="teacher_phone"><?php esc_html_e( 'Phone', 'noor-tms' ); ?></label></th>
					<td>
						<input type="tel" id="teacher_phone" name="teacher_phone" class="regular-text"
						       placeholder="+923001234567" value="<?php echo esc_attr( $teacher_phone ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="teacher_wp_user"><?php esc_html_e( 'WordPress User', 'noor-tms' ); ?> <span class="required">*</span></label></th>
<td>
<?php if ( $teacher_id && $wp_user_id ) : 
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
					<th><label for="teacher_is_active"><?php esc_html_e( 'Status', 'noor-tms' ); ?></label></th>
					<td>
						<select id="teacher_is_active" name="is_active">
							<option value="1" <?php selected( $is_active, 1 ); ?>><?php esc_html_e( 'Active', 'noor-tms' ); ?></option>
							<option value="0" <?php selected( $is_active, 0 ); ?>><?php esc_html_e( 'Inactive', 'noor-tms' ); ?></option>
						</select>
					</td>
				</tr>

				<?php if ( ! empty( $classes ) ) : ?>
				<tr>
					<th>
						<?php esc_html_e( 'Class & Subject Assignments', 'noor-tms' ); ?>
						<p class="description" style="font-weight:normal; margin-top:5px; font-size:12px;"><?php esc_html_e( 'Assign classes (homeroom) and their respective subjects to this teacher. A class/subject assigned to another teacher cannot be selected.', 'noor-tms' ); ?></p>
					</th>
					<td>
						<?php
						$assigned_roles = \Noor_TMS\Includes\DatabaseHandler::get_assigned_class_roles();
						?>
						<div class="noor-class-subjects-container" style="display: flex; flex-direction: column; gap: 15px; background: #fafafa; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
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
							<div class="noor-assignment-group" style="padding-bottom: 10px; border-bottom: 1px solid #eee;">
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
									if ( isset( $subjects_by_class[ $c_id ] ) ) : 
										foreach ( $subjects_by_class[ $c_id ] as $sub ) :
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
				<button type="submit" class="noor-btn noor-btn--primary">
					<?php echo esc_html( $teacher ? __( 'Update Teacher', 'noor-tms' ) : __( 'Add Teacher', 'noor-tms' ) ); ?>
				</button>
				<a href="<?php echo esc_url( home_url( '/tms-teachers/' ) ); ?>" class="noor-btn noor-btn--secondary">
					<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
				</a>
			</div>
		</form>
	</div>

<?php else : ?>

	<div class="noor-card">
		<h2><?php esc_html_e( 'Teachers', 'noor-tms' ); ?></h2>

		<table class="noor-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'WP User', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Classes', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $teachers as $t ) :
					$wp_user = get_user_by( 'id', (int) $t['wp_user_id'] );
				?>
				<tr>
					<td><strong><?php echo esc_html( $t['name'] ); ?></strong></td>
					<td><?php echo esc_html( $t['phone'] ); ?></td>
					<td><?php echo $wp_user ? esc_html( $wp_user->user_login ) : '<em>' . esc_html__( 'Not linked', 'noor-tms' ) . '</em>'; ?></td>
					<td><?php echo esc_html( $t['class_count'] ); ?></td>
					<td><?php echo $t['is_active'] ? esc_html__( 'Active', 'noor-tms' ) : esc_html__( 'Inactive', 'noor-tms' ); ?></td>
					<td>
						<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'edit', 'teacher_id' => $t['id'] ], home_url( '/tms-teachers/' ) ) ); ?>" class="noor-btn noor-btn--sm noor-btn--secondary">
							<?php esc_html_e( 'Edit', 'noor-tms' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

<?php endif; ?>

<?php include __DIR__ . '/layout-close.php';


