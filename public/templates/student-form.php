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
$selected_category_id    = (int) ( $student['category_id'] ?? 0 );
$selected_subcategory_id = (int) ( $student['subcategory_id'] ?? 0 );
$selected_class_id       = (int) ( $student['class_id'] ?? 0 );

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
				<select id="category_id" name="category_id" data-selected="<?php echo esc_attr( (string) $selected_category_id ); ?>">
					<option value="0"><?php esc_html_e( 'Loading categories…', 'noor-tms' ); ?></option>
				</select>
			</div>

			<div class="noor-form-group">
				<label for="subcategory_id"><?php esc_html_e( 'Sub-Category', 'noor-tms' ); ?></label>
				<select id="subcategory_id" name="subcategory_id" data-selected="<?php echo esc_attr( (string) $selected_subcategory_id ); ?>" disabled>
					<option value="0"><?php esc_html_e( 'Select a category first', 'noor-tms' ); ?></option>
				</select>
				<p class="noor-form-description"><?php esc_html_e( 'Optional. Choose a sub-category under the selected category.', 'noor-tms' ); ?></p>
			</div>
		</div>

		<div class="noor-form-row">
			<div class="noor-form-group" id="class_group" hidden>
				<label for="class_id"><?php esc_html_e( 'Class', 'noor-tms' ); ?></label>
				<select id="class_id" name="class_id" data-selected="<?php echo esc_attr( (string) $selected_class_id ); ?>" disabled>
					<option value="0"><?php esc_html_e( 'Select a sub-category first', 'noor-tms' ); ?></option>
				</select>
				<p class="noor-form-description"><?php esc_html_e( 'Shown only for school-type categories.', 'noor-tms' ); ?></p>
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
	const classGroup = document.getElementById('class_group');
	const classSelect = document.getElementById('class_id');
	if (!category || !subcategory || !classGroup || !classSelect) return;

	const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	const ajaxNonce = '<?php echo esc_js( wp_create_nonce( 'noor_tms_ajax' ) ); ?>';

	const selectedCategory = parseInt(category.dataset.selected || '0', 10) || 0;
	const selectedSubcategory = parseInt(subcategory.dataset.selected || '0', 10) || 0;
	const selectedClass = parseInt(classSelect.dataset.selected || '0', 10) || 0;
	let categoryMap = {};
	let subcategoryMap = {};

	function post(action, data) {
		const payload = Object.assign({ action: action, nonce: ajaxNonce }, data || {});
		const body = Object.keys(payload).map(function(key) {
			return encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
		}).join('&');
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body
		}).then(response => response.json());
	}

	function renderOptions(select, rows, placeholder, valueKey, labelKey) {
		select.innerHTML = '';
		const first = document.createElement('option');
		first.value = '0';
		first.textContent = placeholder;
		select.appendChild(first);
		rows.forEach(row => {
			const option = document.createElement('option');
			option.value = String(row[valueKey]);
			option.textContent = row[labelKey];
			select.appendChild(option);
		});
	}

	async function loadCategories() {
		const response = await post('noor_tms_get_categories', {});
		const rows = (response && response.success && response.data && response.data.categories) ? response.data.categories : [];
		categoryMap = {};
		rows.forEach(row => { categoryMap[String(row.id)] = row; });
		renderOptions(category, rows, '<?php echo esc_js( __( '— Select Category —', 'noor-tms' ) ); ?>', 'id', 'name');
		category.disabled = false;
		if (selectedCategory && category.querySelector('option[value="' + selectedCategory + '"]')) {
			category.value = String(selectedCategory);
		}
	}

	async function loadSubcategories(categoryId, preferredSubcategory) {
		const response = await post('noor_tms_get_subcategories', { parent_id: categoryId });
		const rows = (response && response.success && response.data && response.data.subcategories) ? response.data.subcategories : [];
		subcategoryMap = {};
		rows.forEach(row => { subcategoryMap[String(row.id)] = row; });
		renderOptions(subcategory, rows, '<?php echo esc_js( __( '— Select Sub-Category —', 'noor-tms' ) ); ?>', 'id', 'name');
		subcategory.disabled = !rows.length;
		if (preferredSubcategory && subcategory.querySelector('option[value="' + preferredSubcategory + '"]')) {
			subcategory.value = String(preferredSubcategory);
		}
	}

	async function loadClasses(subcategoryId, preferredClass) {
		const response = await post('noor_tms_get_classes', { subcategory_id: subcategoryId });
		const rows = (response && response.success && response.data && response.data.classes) ? response.data.classes : [];
		renderOptions(classSelect, rows, '<?php echo esc_js( __( '— Select Class —', 'noor-tms' ) ); ?>', 'id', 'name');
			classSelect.disabled = !rows.length;
			classGroup.hidden = !rows.length;
		if (preferredClass && classSelect.querySelector('option[value="' + preferredClass + '"]')) {
			classSelect.value = String(preferredClass);
		}
	}

	function syncVisibility() {
		const cat = categoryMap[String(category.value)] || null;
		const sub = subcategoryMap[String(subcategory.value)] || null;
		const isSchool = cat ? !!parseInt(cat.is_school_type || '0', 10) : false;
		if (!isSchool) {
				classGroup.hidden = true;
			classSelect.value = '0';
			classSelect.disabled = true;
			return;
		}
		if (sub) {
			classGroup.hidden = false;
			classSelect.disabled = false;
		}
	}

	category.addEventListener('change', async function() {
		await loadSubcategories(category.value, 0);
		subcategory.value = '0';
		await loadClasses(0, 0);
		syncVisibility();
	});

	subcategory.addEventListener('change', async function() {
		const cat = categoryMap[String(category.value)] || null;
		if (!cat || !parseInt(cat.is_school_type || '0', 10)) {
			classGroup.hidden = true;
			classSelect.value = '0';
			return;
		}
		await loadClasses(subcategory.value, 0);
		syncVisibility();
	});

	(async function init() {
		await loadCategories();
		if (selectedCategory) {
			await loadSubcategories(selectedCategory, selectedSubcategory);
			if (selectedSubcategory) {
				await loadClasses(selectedSubcategory, selectedClass);
			}
		}
		syncVisibility();
	})();
})();
</script>

<?php include __DIR__ . '/layout-close.php'; ?>
