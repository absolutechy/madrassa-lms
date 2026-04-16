<?php
/**
 * Front-end student list template.
 * Rendered by [noor_tms_students] shortcode (list view).
 *
 * Variables in scope (set by PublicController::sc_students):
 *   $students    array   Rows fetched from DB.
 *   $total       int     Total matching rows.
 *   $total_pages int
 *   $paged       int
 *   $search      string
 *   $status      string
 *   $class_id    int
 *   $classes     array   Dropdown data.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Students', 'noor-tms' );
$active_nav     = 'students';
$topbar_actions = '<a href="' . esc_url( add_query_arg( 'tms_action', 'add', home_url( '/tms-students/' ) ) ) . '"'
	. ' class="noor-btn noor-btn--primary">+ ' . esc_html__( 'Add Student', 'noor-tms' ) . '</a>';

include __DIR__ . '/layout.php';

// Notices
$msg = sanitize_key( $_GET['msg'] ?? '' );
if ( 'added' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Student added successfully.', 'noor-tms' ) . '</div>';
} elseif ( 'updated' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Student updated successfully.', 'noor-tms' ) . '</div>';
}
?>

<!-- Filter bar -->
<form method="get" action="<?php echo esc_url( home_url( '/tms-students/' ) ); ?>">
	<div class="noor-filter-row">
		<input type="search" name="s"
			   value="<?php echo esc_attr( $search ); ?>"
			   placeholder="<?php esc_attr_e( 'Search by name…', 'noor-tms' ); ?>" />

		<select name="class_id">
			<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
			<?php foreach ( $classes as $cls ) : ?>
				<option value="<?php echo esc_attr( $cls['id'] ); ?>"
					<?php selected( $class_id, (int) $cls['id'] ); ?>>
					<?php echo esc_html( $cls['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="status_filter">
			<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
			<?php
			foreach ( [
				'active'    => __( 'Active',    'noor-tms' ),
				'inactive'  => __( 'Inactive',  'noor-tms' ),
				'graduated' => __( 'Graduated', 'noor-tms' ),
			] as $val => $lbl ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $val ),
					selected( $status, $val, false ),
					esc_html( $lbl )
				);
			}
			?>
		</select>

		<button type="submit" class="noor-btn noor-btn--secondary">
			<?php esc_html_e( 'Filter', 'noor-tms' ); ?>
		</button>
	</div>
</form>

<?php if ( empty( $students ) ) : ?>
	<div class="noor-empty">
		<span class="noor-empty-icon">&#128101;</span>
		<p><?php esc_html_e( 'No students found.', 'noor-tms' ); ?></p>
		<a href="<?php echo esc_url( add_query_arg( 'tms_action', 'add', home_url( '/tms-students/' ) ) ); ?>"
		   class="noor-btn noor-btn--primary">+ <?php esc_html_e( 'Add the first student', 'noor-tms' ); ?></a>
	</div>
<?php else : ?>

	<div class="noor-table-wrap">
		<table class="noor-table">
			<thead>
				<tr>
					<th style="width:48px;"></th>
					<th><?php esc_html_e( 'Name', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( "Parent's WhatsApp", 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Enrolled', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $students as $student ) : ?>
					<tr>
						<td>
							<?php if ( ! empty( $student['photo_id'] ) ) : ?>
								<?php echo wp_get_attachment_image( (int) $student['photo_id'], [ 40, 40 ], false, [ 'style' => 'border-radius:50%;object-fit:cover;width:40px;height:40px;' ] ); ?>
							<?php else : ?>
								<span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:var(--tms-surface,#f0f0f0);font-size:18px;">&#128100;</span>
							<?php endif; ?>
						</td>
						<td>
							<strong>
							<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'edit', 'student_id' => $student['id'] ], home_url( '/tms-students/' ) ) ); ?>"
								   style="color:var(--tms-primary);text-decoration:none;">
									<?php echo esc_html( $student['name'] ); ?>
								</a>
							</strong>
						</td>
						<td>
							<?php if ( ! empty( $student['class_name'] ) ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'class_id', $student['class_id'], home_url( '/tms-students/' ) ) ); ?>"
								   style="color:var(--tms-muted);text-decoration:none;">
									<?php echo esc_html( $student['class_name'] ); ?>
								</a>
							<?php else : ?>
								<span style="color:var(--tms-muted)">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $student['parent_phone'] ); ?></td>
						<td><?php echo esc_html( $student['enrollment_date'] ); ?></td>
						<td>
							<?php
							$badge_class = match ( $student['status'] ) {
								'active'    => 'noor-badge--active',
								'inactive'  => 'noor-badge--inactive',
								'graduated' => 'noor-badge--graduated',
								default     => '',
							};
							?>
							<span class="noor-badge <?php echo esc_attr( $badge_class ); ?>">
								<?php echo esc_html( ucfirst( $student['status'] ) ); ?>
							</span>
						</td>
						<td class="noor-actions">
						<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'edit', 'student_id' => $student['id'] ], home_url( '/tms-students/' ) ) ); ?>"
							   class="noor-btn noor-btn--secondary noor-btn--sm">
								<?php esc_html_e( 'Edit', 'noor-tms' ); ?>
							</a>

							<?php if ( ! empty( $student['class_id'] ) ) : ?>
								<a href="<?php echo esc_url( add_query_arg( [ 'class_id' => $student['class_id'], 'student_id' => $student['id'] ], home_url( '/tms-results/' ) ) ); ?>"
								   class="noor-btn noor-btn--secondary noor-btn--sm">
									<?php esc_html_e( 'Results', 'noor-tms' ); ?>
								</a>
							<?php endif; ?>

							<button type="button"
									class="noor-btn noor-btn--danger noor-btn--sm noor-delete-student"
								data-id="<?php echo esc_attr( $student['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="noor-pagination">
			<?php
			echo paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $paged,
				'type'      => 'list',
			] );
			?>
		</div>
	<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
