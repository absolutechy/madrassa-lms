<?php
/**
 * Classes management – list, create, edit, and AJAX delete.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Classes
 */
class Classes {

	// -----------------------------------------------------------------------
	// Page router
	// -----------------------------------------------------------------------

	public function page_classes(): void {
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$action   = sanitize_key( $_GET['action'] ?? 'list' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );

		// Handle POST (create / update).
		if ( isset( $_POST['noor_tms_class_nonce'] ) ) {
			$this->handle_save( $class_id );
			return;
		}

		if ( 'edit' === $action && $class_id ) {
			$this->page_form( $class_id );
		} elseif ( 'new' === $action ) {
			$this->page_form( 0 );
		} else {
			$this->page_list();
		}
	}

	// -----------------------------------------------------------------------
	// List view
	// -----------------------------------------------------------------------

	private function page_list(): void {
		$classes = DatabaseHandler::get_classes();
		$categories = DatabaseHandler::get_categories( [ 'parent_id' => 0, 'include_inactive' => true ] );
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Classes', 'noor-tms' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"
			   class="page-title-action"><?php esc_html_e( 'Add New Class', 'noor-tms' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<?php if ( empty( $classes ) ) : ?>
				<div class="noor-tms-card">
					<p>
						<?php
						printf(
							/* translators: %s: link to create class */
							esc_html__( 'No classes yet. %s to get started.', 'noor-tms' ),
							'<a href="' . esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create a class', 'noor-tms' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="noor-class-grid">
					<?php foreach ( $classes as $cls ) : ?>
						<?php $subjects = DatabaseHandler::get_subjects_by_class( (int) $cls['id'] ); ?>
						<div class="noor-class-card" id="noor-class-card-<?php echo esc_attr( $cls['id'] ); ?>">
							<div class="noor-class-card__header">
								<h3 class="noor-class-card__name"><?php echo esc_html( $cls['name'] ); ?></h3>
								<span class="noor-class-card__meta">
									<?php echo esc_html(
										sprintf(
											/* translators: %d: number of subjects */
											_n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ),
											(int) $cls['subject_count']
										)
									); ?>
								</span>
							</div>
							<?php if ( ! empty( $cls['category_id'] ) || ! empty( $cls['subcategory_id'] ) ) : ?>
								<p class="description">
									<?php
									$parts = [];
									if ( ! empty( $cls['category_id'] ) ) {
										$cat = DatabaseHandler::get_category( (int) $cls['category_id'] );
										if ( $cat ) {
											$parts[] = $cat['name'];
										}
									}
									if ( ! empty( $cls['subcategory_id'] ) ) {
										$sub = DatabaseHandler::get_category( (int) $cls['subcategory_id'] );
										if ( $sub ) {
											$parts[] = $sub['name'];
										}
									}
									echo esc_html( implode( ' / ', $parts ) );
									?>
								</p>
							<?php endif; ?>

							<?php if ( ! empty( $subjects ) ) : ?>
								<ul class="noor-subject-tags">
									<?php foreach ( $subjects as $sub ) : ?>
										<li class="noor-subject-tag"><?php echo esc_html( $sub['subject_name'] ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<div class="noor-class-card__actions">
								<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'edit', 'class_id' => $cls['id'] ], admin_url( 'admin.php' ) ) ); ?>"
								   class="button button-small"><?php esc_html_e( 'Edit', 'noor-tms' ); ?></a>

								<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-results', 'class_id' => $cls['id'] ], admin_url( 'admin.php' ) ) ); ?>"
								   class="button button-small"><?php esc_html_e( 'View Results', 'noor-tms' ); ?></a>

								<button type="button" class="button button-small button-link-delete noor-delete-class"
										data-id="<?php echo esc_attr( $cls['id'] ); ?>"
										data-name="<?php echo esc_attr( $cls['name'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'noor_tms_delete_class_' . $cls['id'] ) ); ?>">
									<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Create / Edit form
	// -----------------------------------------------------------------------

	private function page_form( int $class_id ): void {
		$cls      = $class_id ? DatabaseHandler::get_class( $class_id ) : null;
		$subjects = $class_id ? DatabaseHandler::get_subjects_by_class( $class_id ) : [];
		$categories = DatabaseHandler::get_categories( [ 'parent_id' => 0, 'include_inactive' => true ] );
		$selected_category_id = (int) ( $cls['category_id'] ?? (int) ( $_GET['category_id'] ?? 0 ) );
		$selected_subcategory_id = (int) ( $cls['subcategory_id'] ?? (int) ( $_GET['subcategory_id'] ?? 0 ) );
		$title    = $cls ? __( 'Edit Class', 'noor-tms' ) : __( 'Add New Class', 'noor-tms' );
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<hr class="wp-header-end">

			<div class="noor-tms-card">
				<form method="post" action="" id="noor-class-form">
					<?php wp_nonce_field( 'noor_tms_save_class', 'noor_tms_class_nonce' ); ?>
					<?php if ( $class_id ) : ?>
						<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="class_name"><?php esc_html_e( 'Class Name', 'noor-tms' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="class_name" name="class_name" required
									value="<?php echo esc_attr( $cls['name'] ?? '' ); ?>"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'e.g. Class Hifz 1', 'noor-tms' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="category_id"><?php esc_html_e( 'Category', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="category_id" name="category_id" class="regular-text" data-selected="<?php echo esc_attr( (string) $selected_category_id ); ?>">
									<option value="0"><?php esc_html_e( '— Select Category —', 'noor-tms' ); ?></option>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( (int) $cat['id'] ); ?>" <?php selected( $selected_category_id, (int) $cat['id'] ); ?>>
											<?php echo esc_html( $cat['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="subcategory_id"><?php esc_html_e( 'Sub-Category', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="subcategory_id" name="subcategory_id" class="regular-text" data-selected="<?php echo esc_attr( (string) $selected_subcategory_id ); ?>" disabled>
									<option value="0"><?php esc_html_e( 'Select a category first', 'noor-tms' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Subjects', 'noor-tms' ); ?></label>
								<p class="description"><?php esc_html_e( 'Add all subjects taught in this class.', 'noor-tms' ); ?></p>
							</th>
							<td>
								<div id="noor-subjects-list">
									<?php if ( ! empty( $subjects ) ) : ?>
										<?php foreach ( $subjects as $sub ) : ?>
											<div class="noor-subject-row">
												<input type="text" name="subjects[]"
													value="<?php echo esc_attr( $sub['subject_name'] ); ?>"
													class="regular-text noor-subject-input"
													placeholder="<?php esc_attr_e( 'Subject name', 'noor-tms' ); ?>" />
												<button type="button" class="button button-small noor-remove-subject"
														title="<?php esc_attr_e( 'Remove subject', 'noor-tms' ); ?>">
													&times;
												</button>
											</div>
										<?php endforeach; ?>
									<?php else : ?>
										<div class="noor-subject-row">
											<input type="text" name="subjects[]"
												class="regular-text noor-subject-input"
												placeholder="<?php esc_attr_e( 'Subject name', 'noor-tms' ); ?>" />
											<button type="button" class="button button-small noor-remove-subject"
													title="<?php esc_attr_e( 'Remove subject', 'noor-tms' ); ?>">
												&times;
											</button>
										</div>
									<?php endif; ?>
								</div>

								<button type="button" id="noor-add-subject" class="button button-secondary" style="margin-top:8px;">
									+ <?php esc_html_e( 'Add Subject', 'noor-tms' ); ?>
								</button>
							</td>
						</tr>
					</table>

					<div class="noor-form-actions">
						<?php submit_button(
							$cls ? __( 'Update Class', 'noor-tms' ) : __( 'Create Class', 'noor-tms' ),
							'primary', 'submit', false
						); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-classes' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
						</a>
					</div>
				</form>
				<script>
				(function() {
					const category = document.getElementById('category_id');
					const subcategory = document.getElementById('subcategory_id');
					if (!category || !subcategory) return;

					const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
					const ajaxNonce = '<?php echo esc_js( wp_create_nonce( 'noor_tms_ajax' ) ); ?>';

					const selectedCategory = parseInt(category.dataset.selected || '0', 10) || 0;
					const selectedSubcategory = parseInt(subcategory.dataset.selected || '0', 10) || 0;

					function post(action, data) {
						const payload = Object.assign({ action: action, nonce: ajaxNonce }, data || {});
						const body = Object.keys(payload).map(function(key) {
							return encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
						}).join('&');
						return fetch(ajaxUrl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
							body: body
						}).then(function(response) { return response.json(); });
					}

					async function loadSubcategories(parentId, preferred) {
						const response = await post('noor_tms_get_subcategories', { parent_id: parentId });
						const rows = (response && response.success && response.data && response.data.subcategories) ? response.data.subcategories : [];
						subcategory.innerHTML = '';
						const first = document.createElement('option');
						first.value = '0';
						first.textContent = '<?php echo esc_js( __( '— Select Sub-Category —', 'noor-tms' ) ); ?>';
						subcategory.appendChild(first);
						rows.forEach(function(row) {
							const option = document.createElement('option');
							option.value = String(row.id);
							option.textContent = row.name;
							subcategory.appendChild(option);
						});
						subcategory.disabled = !rows.length;
						if (preferred && subcategory.querySelector('option[value="' + preferred + '"]')) {
							subcategory.value = String(preferred);
						}
					}

					category.addEventListener('change', function() {
						loadSubcategories(category.value, 0);
					});

					(async function init() {
						if (selectedCategory) {
							await loadSubcategories(selectedCategory, selectedSubcategory);
						}
					})();
				})();
				</script>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Form handler
	// -----------------------------------------------------------------------

	private function handle_save( int $class_id ): void {
		if ( ! check_admin_referer( 'noor_tms_save_class', 'noor_tms_class_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$name     = sanitize_text_field( $_POST['class_name'] ?? '' );
		$category_id = (int) ( $_POST['category_id'] ?? 0 );
		$subcategory_id = (int) ( $_POST['subcategory_id'] ?? 0 );
		$subjects = array_filter(
			array_map( 'sanitize_text_field', (array) ( $_POST['subjects'] ?? [] ) )
		);

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Class name cannot be empty.', 'noor-tms' ) );
		}

		if ( $class_id > 0 ) {
			DatabaseHandler::update_class( $class_id, $name, array_values( $subjects ), [
				'category_id'    => $category_id,
				'subcategory_id' => $subcategory_id,
			] );
			$msg = 'class_updated';
		} else {
			DatabaseHandler::insert_class( $name, array_values( $subjects ), [
				'category_id'    => $category_id,
				'subcategory_id' => $subcategory_id,
			] );
			$msg = 'class_added';
		}

		wp_safe_redirect(
			add_query_arg( [ 'page' => 'noor-tms-classes', 'msg' => $msg ], admin_url( 'admin.php' ) )
		);
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	/**
	 * AJAX: delete a class.
	 */
	public function ajax_delete_class(): void {
		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [], 403 );
		}

		if ( ! $class_id || ! DatabaseHandler::delete_class( $class_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete class.', 'noor-tms' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Class deleted.', 'noor-tms' ) ] );
	}

	/**
	 * AJAX: return subjects for a given class (used in Results form).
	 */
	public function ajax_get_subjects(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [], 403 );
		}

		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$subjects = $class_id ? DatabaseHandler::get_subjects_by_class( $class_id ) : [];

		wp_send_json_success( [ 'subjects' => $subjects ] );
	}

	/**
	 * AJAX: return active students for a given class (used in Results form).
	 */
	public function ajax_get_students_for_class(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [], 403 );
		}

		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$students = DatabaseHandler::get_students_dropdown( $class_id );

		wp_send_json_success( [ 'students' => $students ] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		$map = [
			'class_added'   => __( 'Class created successfully.', 'noor-tms' ),
			'class_updated' => __( 'Class updated successfully.', 'noor-tms' ),
		];
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html( $map[ $msg ] )
				. '</p></div>';
		}
	}
}
