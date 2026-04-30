<?php
/**
 * Front-end student add / edit form template.
 *
 * Variables in scope:
 *   $student    array|null  Student row (null when adding).
 *   $student_id int         0 when adding.
 *   $classes    array       Dropdown options.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$is_edit        = ! empty( $student );
$page_title     = $is_edit ? __( 'Edit Student', 'noor-tms' ) : __( 'Add New Student', 'noor-tms' );
$active_nav     = 'students';
$topbar_actions = '<a href="' . esc_url( home_url( '/tms-students/' ) ) . '" class="noor-btn noor-btn--secondary">'
	. '&larr; ' . esc_html__( 'Back to Students', 'noor-tms' ) . '</a>';
$print_url      = '';

if ( $is_edit && $student_id > 0 ) {
	$print_url = wp_nonce_url(
		add_query_arg(
			[
				'action'     => 'noor_tms_print_student',
				'student_id' => $student_id,
				'month'      => (int) current_time( 'n' ),
				'year'       => (int) current_time( 'Y' ),
			],
			admin_url( 'admin-post.php' )
		),
		'noor_tms_print_student_' . $student_id
	);
}

include __DIR__ . '/layout.php';
?>

<div class="noor-card">
	<h2><?php echo esc_html( $page_title ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'noor_tms_save_student', 'noor_tms_student_nonce' ); ?>
		<input type="hidden" name="action" value="noor_tms_save_student" />
		<input type="hidden" name="student_id" value="<?php echo esc_attr( $student_id ); ?>" />

		<div class="noor-form-row">
			<div class="noor-form-group">
				<label for="name"><?php esc_html_e( 'Full Name', 'noor-tms' ); ?> <span class="required">*</span></label>
				<input type="text" id="name" name="name" required
					   value="<?php echo esc_attr( $student['name'] ?? '' ); ?>"
					   placeholder="<?php esc_attr_e( 'Student full name', 'noor-tms' ); ?>" />
			</div>

			<div class="noor-form-group">
				<label for="parent_phone"><?php esc_html_e( "Parent's WhatsApp", 'noor-tms' ); ?> <span class="required">*</span></label>
				<input type="tel" id="parent_phone" name="parent_phone" required
					   value="<?php echo esc_attr( $student['parent_phone'] ?? '' ); ?>"
					   placeholder="+923001234567"
					   pattern="^\+[1-9]\d{7,14}$" />
				<p class="noor-form-description"><?php esc_html_e( 'International format, e.g. +923001234567', 'noor-tms' ); ?></p>
			</div>
		</div>

		<div class="noor-form-row">
			<div class="noor-form-group">
				<label for="class_id"><?php esc_html_e( 'Class', 'noor-tms' ); ?></label>
				<?php if ( empty( $classes ) ) : ?>
					<p class="noor-form-description">
						<?php
						printf(
							/* translators: %s: link to create class */
							esc_html__( 'No classes found. %s first.', 'noor-tms' ),
							'<a href="' . esc_url( add_query_arg( 'tms_action', 'new', home_url( '/tms-classes/' ) ) ) . '">'
							. esc_html__( 'Create a class', 'noor-tms' ) . '</a>'
						);
						?>
					</p>
				<?php else : ?>
					<select id="class_id" name="class_id">
						<option value="0"><?php esc_html_e( '— No Class —', 'noor-tms' ); ?></option>
						<?php foreach ( $classes as $cls ) : ?>
							<option value="<?php echo esc_attr( $cls['id'] ); ?>"
								<?php selected( (int) ( $student['class_id'] ?? 0 ), (int) $cls['id'] ); ?>>
								<?php echo esc_html( $cls['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>

			<div class="noor-form-group">
				<label for="enrollment_date"><?php esc_html_e( 'Enrollment Date', 'noor-tms' ); ?></label>
				<input type="date" id="enrollment_date" name="enrollment_date"
					   value="<?php echo esc_attr( $student['enrollment_date'] ?? current_time( 'Y-m-d' ) ); ?>" />
			</div>
		</div>

		<div class="noor-form-group" style="max-width:260px;">
			<label for="status"><?php esc_html_e( 'Status', 'noor-tms' ); ?></label>
			<select id="status" name="status">
				<?php
				foreach ( [
					'active'    => __( 'Active',    'noor-tms' ),
					'inactive'  => __( 'Inactive',  'noor-tms' ),
					'graduated' => __( 'Graduated', 'noor-tms' ),
				] as $val => $lbl ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $val ),
						selected( $student['status'] ?? 'active', $val, false ),
						esc_html( $lbl )
					);
				}
				?>
			</select>
		</div>

		<div class="noor-form-group">
			<label for="student_photo"><?php esc_html_e( 'Student Photo', 'noor-tms' ); ?></label>
			<?php if ( ! empty( $student['photo_id'] ) ) : ?>
				<div style="margin-bottom:8px;">
					<?php echo wp_get_attachment_image( (int) $student['photo_id'], [ 80, 80 ], false, [ 'style' => 'border-radius:4px;object-fit:cover;' ] ); ?>
				</div>
				<label>
					<input type="checkbox" name="remove_photo" value="1" />
					<?php esc_html_e( 'Remove current photo', 'noor-tms' ); ?>
				</label><br />
			<?php endif; ?>
			<input type="file" id="student_photo" name="student_photo" accept="image/jpeg,image/png,image/webp" />
			<p class="noor-form-description"><?php esc_html_e( 'Optional. JPEG, PNG or WebP. Max 2 MB.', 'noor-tms' ); ?></p>
		</div>

		<div class="noor-form-actions">
			<button type="submit" class="noor-btn noor-btn--primary">
				<?php echo esc_html( $is_edit ? __( 'Update Student', 'noor-tms' ) : __( 'Add Student', 'noor-tms' ) ); ?>
			</button>
			<?php if ( $print_url ) : ?>
				<a href="<?php echo esc_url( $print_url ); ?>" class="noor-btn noor-btn--secondary" target="_blank" rel="noopener">
					<?php esc_html_e( 'Print PDF', 'noor-tms' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/tms-students/' ) ); ?>" class="noor-btn noor-btn--secondary">
				<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
			</a>
		</div>
	</form>
</div>

<?php include __DIR__ . '/layout-close.php'; ?>
