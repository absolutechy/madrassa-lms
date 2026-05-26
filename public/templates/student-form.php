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
				<label for="category_id"><?php esc_html_e( 'Category', 'noor-tms' ); ?></label>
				<?php if ( empty( $parent_categories ) ) : ?>
					<p class="noor-form-description"><?php esc_html_e( 'No categories found yet.', 'noor-tms' ); ?></p>
				<?php else : ?>
					<select id="category_id" name="category_id">
						<option value="0"><?php esc_html_e( '— Select Category —', 'noor-tms' ); ?></option>
						<?php foreach ( $parent_categories as $cat ) : ?>
							<?php
							$label = $cat['name'];
							if ( $has_mixed_category_types ) {
								$type_label = ( 'banaat' === $cat['account_type'] ) ? __( 'Banaat', 'noor-tms' ) : __( 'Banin', 'noor-tms' );
								$label = $type_label . ' - ' . $label;
							}
							?>
							<option value="<?php echo esc_attr( $cat['id'] ); ?>" <?php selected( (int) ( $student['category_id'] ?? 0 ), (int) $cat['id'] ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>

			<div class="noor-form-group">
				<label for="subcategory_id"><?php esc_html_e( 'Sub-Category', 'noor-tms' ); ?></label>
				<select id="subcategory_id" name="subcategory_id">
					<option value="0"><?php esc_html_e( '— Select Sub-Category —', 'noor-tms' ); ?></option>
					<?php foreach ( $subcategories as $subcat ) : ?>
						<option value="<?php echo esc_attr( $subcat['id'] ); ?>"
							data-parent="<?php echo esc_attr( $subcat['parent_id'] ); ?>"
							<?php selected( (int) ( $student['subcategory_id'] ?? 0 ), (int) $subcat['id'] ); ?>>
							<?php echo esc_html( $subcat['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="noor-form-description"><?php esc_html_e( 'Optional. Choose a sub-category under the selected category.', 'noor-tms' ); ?></p>
			</div>
		</div>

		<div class="noor-form-row">
			<div class="noor-form-group">
				<label for="enrollment_date"><?php esc_html_e( 'Enrollment Date', 'noor-tms' ); ?></label>
				<input type="date" id="enrollment_date" name="enrollment_date"
					   value="<?php echo esc_attr( $student['enrollment_date'] ?? current_time( 'Y-m-d' ) ); ?>" />
			</div>
		</div>

		<div class="noor-form-group" style="max-width:260px;">
			<label for="gender"><?php esc_html_e( 'Gender', 'noor-tms' ); ?></label>
			<?php
			$gender_scope = null;
			if ( current_user_can( 'manage_banaat' ) && ! current_user_can( 'manage_banin' ) ) {
				$gender_scope = 'female';
			} elseif ( current_user_can( 'manage_banin' ) && ! current_user_can( 'manage_banaat' ) ) {
				$gender_scope = 'male';
			}
			if ( $gender_scope ) :
				$label = 'female' === $gender_scope ? __( 'Female', 'noor-tms' ) : __( 'Male', 'noor-tms' );
				?>
				<input type="hidden" name="gender" value="<?php echo esc_attr( $gender_scope ); ?>" />
				<div class="noor-form-description" style="margin-top:6px;">
					<?php echo esc_html( $label ); ?>
				</div>
			<?php else : ?>
				<select id="gender" name="gender">
					<?php
					foreach ( [ 'male' => __( 'Male', 'noor-tms' ), 'female' => __( 'Female', 'noor-tms' ) ] as $val => $lbl ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $val ),
							selected( $student['gender'] ?? 'male', $val, false ),
							esc_html( $lbl )
						);
					}
					?>
				</select>
			<?php endif; ?>
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

<script>
(function() {
	const category = document.getElementById('category_id');
	const subcategory = document.getElementById('subcategory_id');
	if (!category || !subcategory) return;
	const options = Array.from(subcategory.options);
	function syncSubcategories() {
		const parentId = category.value;
		let hasMatch = false;
		options.forEach(option => {
			if (!option.value) {
				option.hidden = false;
				return;
			}
			const match = option.dataset.parent === parentId;
			option.hidden = !match;
			if (match) hasMatch = true;
		});
		if (!parentId || !hasMatch) {
			if (subcategory.value && subcategory.selectedOptions[0] && subcategory.selectedOptions[0].hidden) {
				subcategory.value = '0';
			}
			subcategory.disabled = true;
			return;
		}
		subcategory.disabled = false;
		if (subcategory.selectedOptions[0] && subcategory.selectedOptions[0].hidden) {
			subcategory.value = '0';
		}
	}
	category.addEventListener('change', syncSubcategories);
	syncSubcategories();
})();
</script>

<?php include __DIR__ . '/layout-close.php'; ?>
