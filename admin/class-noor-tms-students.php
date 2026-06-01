<?php
/**
 * Student CRUD – admin pages and AJAX handlers.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Students
 */
class Students {

	// -----------------------------------------------------------------------
	// Pages
	// -----------------------------------------------------------------------

	/**
	 * Students list page.
	 */
	public function page_list(): void {
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$search   = sanitize_text_field( $_GET['noor_search'] ?? ($_GET['s'] ?? '') );
		$status   = sanitize_key( $_GET['status_filter'] ?? '' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		$result  = DatabaseHandler::get_students( [
			'per_page' => 20,
			'page'     => $paged,
			'search'   => $search,
			'status'   => $status,
			'class_id' => $class_id,
		] );
		$students    = $result['rows'];
		$total       = $result['total'];
		$total_pages = (int) ceil( $total / 20 );

		$classes = DatabaseHandler::get_classes_dropdown();
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Students', 'noor-tms' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-add-student' ) ); ?>"
			   class="page-title-action"><?php esc_html_e( 'Add New', 'noor-tms' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<!-- Search & filter bar -->
			<div class="search-box">
				<?php if ( is_admin() ) : ?>
					<input type="hidden" id="noor_page" value="noor-tms" />
				<?php endif; ?>
				<p>
					<input type="search" id="noor_search_input" value="<?php echo esc_attr( $search ); ?>"
						   placeholder="<?php esc_attr_e( 'Search by name…', 'noor-tms' ); ?>" class="noor-search-input" />

					<select id="noor_class_id">
						<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
						<?php foreach ( $classes as $cls ) : ?>
							<option value="<?php echo esc_attr( $cls['id'] ); ?>"
								<?php selected( $class_id, (int) $cls['id'] ); ?>>
								<?php echo esc_html( $cls['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select id="noor_status_filter">
						<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
						<?php
						foreach ( [ 'active' => __( 'Active', 'noor-tms' ), 'inactive' => __( 'Inactive', 'noor-tms' ), 'graduated' => __( 'Graduated', 'noor-tms' ) ] as $val => $lbl ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $val ),
								selected( $status, $val, false ),
								esc_html( $lbl )
							);
						}
						?>
					</select>
					<button type="button" class="button secondary" onclick="applyNoorFilter();"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
				</p>
			</div>

			<script>
            function applyNoorFilter() {
				const params = [];

				// Keep target page when in admin.
                const pageInput = document.getElementById('noor_page');
				if (pageInput && pageInput.value) {
					params.push('page=' + encodeURIComponent(pageInput.value));
                }
                
                const search = document.getElementById('noor_search_input').value.trim();
                const classId = document.getElementById('noor_class_id').value;
                const status = document.getElementById('noor_status_filter').value;
                
				if (search) params.push('noor_search=' + encodeURIComponent(search));
				if (classId) params.push('class_id=' + encodeURIComponent(classId));
				if (status) params.push('status_filter=' + encodeURIComponent(status));
                
				const query = params.length ? ('?' + params.join('&')) : '';
				window.location.href = window.location.pathname + query;
            }

            // Also attach Enter key on search input
            document.getElementById('noor_search_input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyNoorFilter();
                }
            });
			</script>

			<table class="wp-list-table widefat fixed striped noor-tms-table">
				<thead>
					<tr>					<th style="width:44px;"></th>						<th><?php esc_html_e( 'ID', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Name', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( "Parent's WhatsApp", 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Enrollment Date', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $students ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'No students found.', 'noor-tms' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $students as $student ) : ?>
							<tr>							<td>
								<?php if ( ! empty( $student['photo_id'] ) ) : ?>
									<?php echo wp_get_attachment_image( (int) $student['photo_id'], [ 36, 36 ], false, [ 'style' => 'border-radius:50%;object-fit:cover;width:36px;height:36px;' ] ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-admin-users" style="font-size:28px;color:#ccc;"></span>
								<?php endif; ?>
							</td>								<td><?php echo esc_html( $student['id'] ); ?></td>
								<td>
									<strong>
										<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-add-student', 'student_id' => $student['id'] ], admin_url( 'admin.php' ) ) ); ?>">
											<?php echo esc_html( $student['name'] ); ?>
										</a>
									</strong>
								</td>
								<td>
									<?php if ( ! empty( $student['class_name'] ) ) : ?>
										<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms', 'class_id' => $student['class_id'] ], admin_url( 'admin.php' ) ) ); ?>">
											<?php echo esc_html( $student['class_name'] ); ?>
										</a>
									<?php else : ?>
										<span class="description"><?php esc_html_e( '—', 'noor-tms' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $student['parent_phone'] ); ?></td>
								<td><?php echo esc_html( $student['enrollment_date'] ); ?></td>
								<td>
									<span class="noor-status-badge noor-status-<?php echo esc_attr( $student['status'] ); ?>">
										<?php echo esc_html( ucfirst( $student['status'] ) ); ?>
									</span>
								</td>
								<td class="noor-actions">
									<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-add-student', 'student_id' => $student['id'] ], admin_url( 'admin.php' ) ) ); ?>"
									   class="button button-small"><?php esc_html_e( 'Edit', 'noor-tms' ); ?></a>

									<?php if ( ! empty( $student['class_id'] ) ) : ?>
										<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-results', 'class_id' => $student['class_id'], 'student_id' => $student['id'] ], admin_url( 'admin.php' ) ) ); ?>"
										   class="button button-small"><?php esc_html_e( 'Results', 'noor-tms' ); ?></a>
									<?php endif; ?>

									<a href="<?php echo esc_url( $this->get_print_student_url( (int) $student['id'] ) ); ?>"
									   class="button button-small"
									   target="_blank"
									   rel="noopener"><?php esc_html_e( 'Print PDF', 'noor-tms' ); ?></a>
									<a href="<php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'noor_tms_delete_student', 'student_id' => $student['id'] ], admin_url( 'admin-post.php' ) ), 'noor_tms_delete_student_' . (int) $student['id'] ) ) ); ?>"
									   class="button button-small"
									   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this student?', 'noor-tms' ); ?>');">
										<?php esc_html_e( 'Delete', 'noor-tms' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( [
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $paged,
						] );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add / Edit student form page.
	 */
	public function page_form(): void {
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$student    = $student_id ? DatabaseHandler::get_student( $student_id ) : null;
		if ( $student_id && ! $student ) {
			wp_die( esc_html__( 'Student not found.', 'noor-tms' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['noor_tms_student_nonce'] ) ) {
			$this->handle_student_save( $student_id );
			return;
		}

		$all_categories = DatabaseHandler::get_categories();
		$parent_categories = array_values( array_filter( $all_categories, fn( $c ) => (int) $c['parent_id'] === 0 ) );
		$subcategories = array_values( array_filter( $all_categories, fn( $c ) => (int) $c['parent_id'] > 0 ) );
		$has_mixed_category_types = count( array_unique( array_map( fn( $c ) => (string) $c['account_type'], $parent_categories ) ) ) > 1;
		$title   = $student ? __( 'Edit Student', 'noor-tms' ) : __( 'Add New Student', 'noor-tms' );
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<hr class="wp-header-end">

			<div class="noor-tms-card">
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'noor_tms_save_student', 'noor_tms_student_nonce' ); ?>
					<?php if ( $student_id ) : ?>
						<input type="hidden" name="student_id" value="<?php echo esc_attr( $student_id ); ?>" />
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="name"><?php esc_html_e( 'Full Name', 'noor-tms' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="name" name="name" required
									value="<?php echo esc_attr( $student['name'] ?? '' ); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="parent_phone"><?php esc_html_e( "Parent's WhatsApp Number", 'noor-tms' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="tel" id="parent_phone" name="parent_phone" required
									value="<?php echo esc_attr( $student['parent_phone'] ?? '' ); ?>"
									class="regular-text"
									placeholder="+923001234567"
									pattern="^\+[1-9]\d{7,14}$" />
								<p class="description"><?php esc_html_e( 'International format. E.g. +923001234567', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="category_id"><?php esc_html_e( 'Category', 'noor-tms' ); ?></label>
							</th>
							<td>
								<?php if ( empty( $parent_categories ) ) : ?>
									<p class="description"><?php esc_html_e( 'No categories found. Add categories first.', 'noor-tms' ); ?></p>
								<?php else : ?>
									<select id="category_id" name="category_id" class="regular-text">
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
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="subcategory_id"><?php esc_html_e( 'Sub-Category', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="subcategory_id" name="subcategory_id" class="regular-text">
									<option value="0"><?php esc_html_e( '— Select Sub-Category —', 'noor-tms' ); ?></option>
									<?php foreach ( $subcategories as $subcat ) : ?>
										<option value="<?php echo esc_attr( $subcat['id'] ); ?>"
											data-parent="<?php echo esc_attr( $subcat['parent_id'] ); ?>"
											<?php selected( (int) ( $student['subcategory_id'] ?? 0 ), (int) $subcat['id'] ); ?>>
											<?php echo esc_html( $subcat['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Optional. Choose a sub-category under the selected category.', 'noor-tms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="enrollment_date"><?php esc_html_e( 'Enrollment Date', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="date" id="enrollment_date" name="enrollment_date"
									value="<?php echo esc_attr( $student['enrollment_date'] ?? current_time( 'Y-m-d' ) ); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="gender"><?php esc_html_e( 'Gender', 'noor-tms' ); ?></label>
							</th>
							<td>
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
									<span class="description" style="display:inline-block;padding-top:4px;">
										<?php echo esc_html( $label ); ?>
									</span>
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
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="status"><?php esc_html_e( 'Status', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="status" name="status">
									<?php
									foreach ( [ 'active' => __( 'Active', 'noor-tms' ), 'inactive' => __( 'Inactive', 'noor-tms' ), 'graduated' => __( 'Graduated', 'noor-tms' ) ] as $val => $lbl ) {
										printf(
											'<option value="%s"%s>%s</option>',
											esc_attr( $val ),
											selected( $student['status'] ?? 'active', $val, false ),
											esc_html( $lbl )
										);
									}
									?>
								</select>
							</td>
						</tr>
					<tr>
						<th scope="row">
							<label for="student_photo"><?php esc_html_e( 'Student Photo', 'noor-tms' ); ?></label>
						</th>
						<td>
							<?php if ( ! empty( $student['photo_id'] ) ) : ?>
								<div style="margin-bottom:8px;">
									<?php echo wp_get_attachment_image( (int) $student['photo_id'], [ 80, 80 ], false, [ 'style' => 'border-radius:4px;object-fit:cover;' ] ); ?>
								</div>
								<label>
									<input type="checkbox" name="remove_photo" value="1" />
									<?php esc_html_e( 'Remove current photo', 'noor-tms' ); ?>
								</label><br />
							<?php endif; ?>
							<input type="file" id="student_photo" name="student_photo"
								   accept="image/jpeg,image/png,image/webp" />
							<p class="description"><?php esc_html_e( 'Optional. JPEG, PNG or WebP. Max 2 MB.', 'noor-tms' ); ?></p>
						</td>
					</tr>
					<div class="noor-form-actions">
						<?php submit_button( $student ? __( 'Update Student', 'noor-tms' ) : __( 'Add Student', 'noor-tms' ), 'primary', 'submit', false ); ?>
						<?php if ( $student_id > 0 ) : ?>
							<a href="<?php echo esc_url( $this->get_print_student_url( $student_id ) ); ?>"
							   class="button"
							   target="_blank"
							   rel="noopener"><?php esc_html_e( 'Print PDF', 'noor-tms' ); ?></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
						</a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Form handlers
	// -----------------------------------------------------------------------

	private function handle_student_save( int $student_id ): void {
		if ( ! check_admin_referer( 'noor_tms_save_student', 'noor_tms_student_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$data = [
			'class_id'        => (int) ( $_POST['class_id'] ?? 0 ),
			'name'            => sanitize_text_field( $_POST['name']            ?? '' ),
			'parent_phone'    => sanitize_text_field( $_POST['parent_phone']    ?? '' ),
			'category_id'     => (int) ( $_POST['category_id']     ?? 0 ),
			'subcategory_id'  => (int) ( $_POST['subcategory_id']  ?? 0 ),
			'enrollment_date' => sanitize_text_field( $_POST['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
			'gender'          => sanitize_key( $_POST['gender']          ?? 'male' ),
			'status'          => sanitize_key( $_POST['status'] ?? 'active' ),
		];

		if ( empty( $data['name'] ) ) {
			wp_die( esc_html__( 'Student name cannot be empty.', 'noor-tms' ) );
		}

		if ( ! empty( $data['parent_phone'] ) && ! preg_match( '/^\+[1-9]\d{7,14}$/', $data['parent_phone'] ) ) {
			wp_die( esc_html__( 'Invalid phone number. Use international format, e.g. +923001234567', 'noor-tms' ) );
		}

		// Handle photo upload.
		if ( ! empty( $_FILES['student_photo']['name'] ) ) {
			$finfo     = new \finfo( FILEINFO_MIME_TYPE );
			$real_mime = $finfo->file( $_FILES['student_photo']['tmp_name'] );
			if ( ! in_array( $real_mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
				wp_die( esc_html__( 'Invalid file type. Only JPEG, PNG and WebP are allowed.', 'noor-tms' ) );
			}
			if ( $_FILES['student_photo']['size'] > 2 * MB_IN_BYTES ) {
				wp_die( esc_html__( 'Photo must be under 2 MB.', 'noor-tms' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$photo_id = media_handle_upload( 'student_photo', 0 );
			if ( ! is_wp_error( $photo_id ) ) {
				$data['photo_id'] = $photo_id;
			}
		} elseif ( $student_id > 0 && ! empty( $_POST['remove_photo'] ) ) {
			$data['photo_id'] = null; // DatabaseHandler::update_student handles deletion.
		}

		if ( $student_id > 0 ) {
			DatabaseHandler::update_student( $student_id, $data );
			$redirect_msg = 'updated';
		} else {
			DatabaseHandler::insert_student( $data );
			$redirect_msg = 'added';
		}

		wp_safe_redirect(
			add_query_arg( [ 'page' => 'noor-tms', 'msg' => $redirect_msg ], admin_url( 'admin.php' ) )
		);
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------------

	public function ajax_delete_student(): void {
		$student_id = (int) ( $_POST['student_id'] ?? 0 );

		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		if ( ! noor_tms_can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		if ( ! $student_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid student ID.', 'noor-tms' ) ] );
		}

		$deleted = DatabaseHandler::delete_student( $student_id );
		if ( $deleted ) {
			wp_send_json_success( [ 'message' => __( 'Student deleted.', 'noor-tms' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not delete student.', 'noor-tms' ) ] );
		}
	}

	public function handle_print_student(): void {
		$is_manager = noor_tms_can_manage();
		$is_teacher = current_user_can( 'noor_tms_teacher' );

		if ( ! $is_manager && ! $is_teacher ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		if ( $student_id <= 0 ) {
			wp_die( esc_html__( 'Invalid student ID.', 'noor-tms' ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'noor_tms_print_student_' . $student_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$month = (int) ( $_GET['month'] ?? current_time( 'n' ) );
		$year  = (int) ( $_GET['year'] ?? current_time( 'Y' ) );

		$month = max( 1, min( 12, $month ) );
		$year  = max( 2000, min( 2100, $year ) );

		$student = DatabaseHandler::get_student( $student_id );
		if ( ! $student ) {
			wp_die( esc_html__( 'Student not found.', 'noor-tms' ) );
		}

		if ( ! $is_manager ) {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];

			if ( empty( $student['class_id'] ) || ! in_array( (int) $student['class_id'], $class_ids, true ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
			}
		}

		$results = DatabaseHandler::get_results_by_student( $student_id );

		$attendance_map = DatabaseHandler::get_student_attendance_summary(
			$month,
			$year,
			! empty( $student['class_id'] ) ? (int) $student['class_id'] : null
		);
		$attendance  = $attendance_map[ $student_id ] ?? null;
		$fee_summary = DatabaseHandler::get_student_fee_summary( $student_id );

		$this->render_print_student_report( $student, $results, $attendance, $fee_summary, $month, $year );
		exit;
	}

	/**
	 * Render a print-friendly report page for a student.
	 *
	 * @param array<string, mixed>      $student
	 * @param array<int, array<string, mixed>> $results
	 * @param array<string, mixed>|null $attendance
	 * @param array<string, int|float>  $fee_summary
	 * @param int                       $month
	 * @param int                       $year
	 */
	private function render_print_student_report( array $student, array $results, ?array $attendance, array $fee_summary, int $month, int $year ): void {
		$student_id    = (int) ( $student['id'] ?? 0 );
		$current_year  = (int) current_time( 'Y' );
		$year_start    = max( 2000, $current_year - 5 );
		$year_end      = $current_year + 1;
		$month_name    = wp_date( 'F', mktime( 0, 0, 0, $month, 1, $year ) );
		$generated_on  = current_time( 'mysql' );
		$student_photo = ! empty( $student['photo_id'] ) ? wp_get_attachment_image_url( (int) $student['photo_id'], 'medium' ) : '';

		$total_obtained = 0.0;
		$total_marks    = 0.0;

		foreach ( $results as $result_row ) {
			$total_obtained += (float) ( $result_row['marks_obtained'] ?? 0 );
			$total_marks    += (float) ( $result_row['total_marks'] ?? 0 );
		}

		$overall_pct = $total_marks > 0 ? ( $total_obtained / $total_marks ) * 100 : 0;

		nocache_headers();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( sprintf( __( 'Student Report - %s', 'noor-tms' ), (string) ( $student['name'] ?? '' ) ) ); ?></title>
			<style>
				:root {
					--noor-bg: #f3f6fb;
					--noor-paper: #ffffff;
					--noor-text: #1f2937;
					--noor-muted: #6b7280;
					--noor-border: #dbe3ef;
					--noor-primary: #0f766e;
				}

				* { box-sizing: border-box; }

				body {
					margin: 0;
					padding: 24px;
						<select id="category_id" name="category_id" class="regular-text" data-selected="<?php echo esc_attr( (string) ( $student['category_id'] ?? 0 ) ); ?>">
							<option value="0"><?php esc_html_e( 'Loading categories…', 'noor-tms' ); ?></option>
						</select>
					background: #f9fbff;
					border-radius: 12px;
					flex-wrap: wrap;
				}

				.report-filters {
					display: flex;
						<select id="subcategory_id" name="subcategory_id" class="regular-text" data-selected="<?php echo esc_attr( (string) ( $student['subcategory_id'] ?? 0 ) ); ?>" disabled>
							<option value="0"><?php esc_html_e( 'Select a category first', 'noor-tms' ); ?></option>

				.report-filters select,
				.report-filters button,
				.report-actions button,
				<tr>
					<th scope="row">
						<label for="class_id"><?php esc_html_e( 'Class', 'noor-tms' ); ?></label>
					</th>
					<td>
						<select id="class_id" name="class_id" class="regular-text" data-selected="<?php echo esc_attr( (string) ( $student['class_id'] ?? 0 ) ); ?>" disabled>
							<option value="0"><?php esc_html_e( 'Select a sub-category first', 'noor-tms' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Shown only for school-type categories.', 'noor-tms' ); ?></p>
					</td>
				</tr>
				.report-actions a {
					height: 34px;
					border-radius: 8px;
					border: 1px solid #b8c7dd;
					padding: 0 12px;
					background: #fff;
					color: #1f2937;
					font-size: 13px;
					text-decoration: none;
					cursor: pointer;
				}

				.report-filters button,
				.report-actions .button-primary {
					background: var(--noor-primary);
					border-color: var(--noor-primary);
					color: #fff;
				}

				.report-actions {
					display: flex;
					gap: 8px;
				}

				.report-paper {
					background: var(--noor-paper);
					border: 1px solid var(--noor-border);
					border-radius: 14px;
					padding: 22px;
					box-shadow: 0 20px 45px rgba(17, 24, 39, 0.08);
				}

				.report-header {
					display: flex;
					justify-content: space-between;
					gap: 16px;
					align-items: flex-start;
					padding-bottom: 14px;
					border-bottom: 2px solid #edf2fb;
				}

				.report-title {
					margin: 0;
					font-size: 28px;
					line-height: 1.2;
				}

				.report-meta {
					font-size: 13px;
					color: var(--noor-muted);
					text-align: right;
				}

				.section {
					margin-top: 18px;
				}

				.section h2 {
					margin: 0 0 10px;
					font-size: 17px;
				}

				.profile-layout {
					display: grid;
					grid-template-columns: 96px minmax(0, 1fr);
					gap: 14px;
				}

				.profile-photo {
					width: 96px;
					height: 96px;
					border-radius: 10px;
					object-fit: cover;
					border: 1px solid var(--noor-border);
				}

				.profile-grid,
				.summary-grid {
					display: grid;
					gap: 8px;
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}

				.field {
					border: 1px solid var(--noor-border);
					border-radius: 10px;
					padding: 10px 12px;
					background: #fbfdff;
				}

				.field small {
					display: block;
					font-size: 11px;
					color: var(--noor-muted);
					text-transform: uppercase;
					letter-spacing: 0.04em;
				}

				table {
					width: 100%;
					border-collapse: collapse;
					font-size: 13px;
				}

				th,
				td {
					border: 1px solid var(--noor-border);
					padding: 8px 10px;
					text-align: left;
				}

				th {
					background: #f1f7ff;
					font-weight: 600;
				}

				.alert {
					margin: 0;
					padding: 11px 12px;
					background: #fff8e8;
					border: 1px solid #f7e4b2;
					border-radius: 9px;
					color: #7a5b00;
				}

				@media (max-width: 760px) {
					body { padding: 14px; }
					.report-paper { padding: 14px; }
					.profile-layout { grid-template-columns: 1fr; }
					.profile-grid,
					.summary-grid { grid-template-columns: 1fr; }
					.report-meta { text-align: left; }
				}

				@media print {
					body { padding: 0; background: #fff; }
					.no-print { display: none !important; }
					.report-paper {
						border: 0;
						box-shadow: none;
						border-radius: 0;
						padding: 0;
					}
					@page {
						size: A4;
						margin: 12mm;
					}
				}
			</style>
		</head>
		<body>
			<div class="report-shell">
				<div class="report-toolbar no-print">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="report-filters">
						<input type="hidden" name="action" value="noor_tms_print_student" />
						<input type="hidden" name="student_id" value="<?php echo esc_attr( $student_id ); ?>" />
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'noor_tms_print_student_' . $student_id ) ); ?>" />

						<label for="noor-print-month"><?php esc_html_e( 'Month', 'noor-tms' ); ?></label>
						<select id="noor-print-month" name="month">
							<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
								<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>>
									<?php echo esc_html( wp_date( 'F', mktime( 0, 0, 0, $m, 1, max( 2000, $year ) ) ) ); ?>
								</option>
							<?php endfor; ?>
						</select>

						<label for="noor-print-year"><?php esc_html_e( 'Year', 'noor-tms' ); ?></label>
						<select id="noor-print-year" name="year">
							<?php for ( $y = $year_start; $y <= $year_end; $y++ ) : ?>
								<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>>
									<?php echo esc_html( $y ); ?>
								</option>
							<?php endfor; ?>
						</select>

						<button type="submit"><?php esc_html_e( 'Update', 'noor-tms' ); ?></button>
					</form>
					<script>
					(function() {
						const category = document.getElementById('category_id');
						const subcategory = document.getElementById('subcategory_id');
						const classGroup = document.getElementById('class_id');
						if (!category || !subcategory || !classGroup || !window.noorTMS) return;

						const selectedCategory = parseInt(category.dataset.selected || '0', 10) || 0;
						const selectedSubcategory = parseInt(subcategory.dataset.selected || '0', 10) || 0;
						const selectedClass = parseInt(classGroup.dataset.selected || '0', 10) || 0;
						let categoryMap = {};
						let subcategoryMap = {};

						function post(action, data) {
							const payload = Object.assign({ action: action, nonce: noorTMS.nonce }, data || {});
							const body = Object.keys(payload).map(function(key) {
								return encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
							}).join('&');
							return fetch(noorTMS.ajaxUrl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
								body: body
							}).then(response => response.json());
						}

						function renderOptions(select, rows, placeholder) {
							select.innerHTML = '';
							const first = document.createElement('option');
							first.value = '0';
							first.textContent = placeholder;
							select.appendChild(first);
							rows.forEach(row => {
								const option = document.createElement('option');
								option.value = String(row.id);
								option.textContent = row.name;
								select.appendChild(option);
							});
						}

						async function loadCategories() {
							const response = await post('noor_tms_get_categories', {});
							const rows = (response && response.success && response.data && response.data.categories) ? response.data.categories : [];
							categoryMap = {};
							rows.forEach(row => { categoryMap[String(row.id)] = row; });
							renderOptions(category, rows, '<?php echo esc_js( __( '— Select Category —', 'noor-tms' ) ); ?>');
							category.disabled = false;
							if (selectedCategory && category.querySelector('option[value="' + selectedCategory + '"]')) {
								category.value = String(selectedCategory);
							}
						}

						async function loadSubcategories(parentId, preferredSubcategory) {
							const response = await post('noor_tms_get_subcategories', { parent_id: parentId });
							const rows = (response && response.success && response.data && response.data.subcategories) ? response.data.subcategories : [];
							subcategoryMap = {};
							rows.forEach(row => { subcategoryMap[String(row.id)] = row; });
							renderOptions(subcategory, rows, '<?php echo esc_js( __( '— Select Sub-Category —', 'noor-tms' ) ); ?>');
							subcategory.disabled = !rows.length;
							if (preferredSubcategory && subcategory.querySelector('option[value="' + preferredSubcategory + '"]')) {
								subcategory.value = String(preferredSubcategory);
							}
						}

						async function loadClasses(subcategoryId, preferredClass) {
							const response = await post('noor_tms_get_classes', { subcategory_id: subcategoryId });
							const rows = (response && response.success && response.data && response.data.classes) ? response.data.classes : [];
							renderOptions(classGroup, rows, '<?php echo esc_js( __( '— Select Class —', 'noor-tms' ) ); ?>');
							classGroup.disabled = !rows.length;
							if (preferredClass && classGroup.querySelector('option[value="' + preferredClass + '"]')) {
								classGroup.value = String(preferredClass);
							}
						}

						function syncVisibility() {
							const cat = categoryMap[String(category.value)] || null;
							const sub = subcategoryMap[String(subcategory.value)] || null;
							const isSchool = cat ? !!parseInt(cat.is_school_type || '0', 10) : false;
							if (!isSchool) {
								classGroup.value = '0';
								classGroup.disabled = true;
								return;
							}
							if (sub) {
								classGroup.disabled = false;
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
								classGroup.value = '0';
								classGroup.disabled = true;
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

					<div class="report-actions">
						<button type="button" class="button-primary" onclick="window.print();"><?php esc_html_e( 'Print', 'noor-tms' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms' ) ); ?>"><?php esc_html_e( 'Close', 'noor-tms' ); ?></a>
					</div>
				</div>

				<div class="report-paper">
					<header class="report-header">
						<div>
							<h1 class="report-title"><?php esc_html_e( 'Student Report', 'noor-tms' ); ?></h1>
							<p style="margin:4px 0 0;color:#6b7280;">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: month name, 2: year */
										__( 'Attendance period: %1$s %2$d', 'noor-tms' ),
										$month_name,
										$year
									)
								);
								?>
							</p>
						</div>
						<div class="report-meta">
							<div><?php echo esc_html( sprintf( __( 'Generated: %s', 'noor-tms' ), $generated_on ) ); ?></div>
							<div><?php echo esc_html( sprintf( __( 'Student ID: %d', 'noor-tms' ), $student_id ) ); ?></div>
						</div>
					</header>

					<section class="section">
						<h2><?php esc_html_e( 'Student Profile', 'noor-tms' ); ?></h2>
						<div class="profile-layout">
							<div>
								<?php if ( $student_photo ) : ?>
									<img src="<?php echo esc_url( $student_photo ); ?>" alt="" class="profile-photo" />
								<?php endif; ?>
							</div>
							<div class="profile-grid">
								<div class="field">
									<small><?php esc_html_e( 'Full Name', 'noor-tms' ); ?></small>
									<?php echo esc_html( (string) ( $student['name'] ?? '' ) ); ?>
								</div>
								<div class="field">
									<small><?php esc_html_e( 'Class', 'noor-tms' ); ?></small>
									<?php echo esc_html( (string) ( $student['class_name'] ?? __( 'Unassigned', 'noor-tms' ) ) ); ?>
								</div>
								<div class="field">
									<small><?php esc_html_e( "Parent's WhatsApp", 'noor-tms' ); ?></small>
									<?php echo esc_html( (string) ( $student['parent_phone'] ?? '-' ) ); ?>
								</div>
								<div class="field">
									<small><?php esc_html_e( 'Enrollment Date', 'noor-tms' ); ?></small>
									<?php echo esc_html( (string) ( $student['enrollment_date'] ?? '-' ) ); ?>
								</div>
								<div class="field">
									<small><?php esc_html_e( 'Status', 'noor-tms' ); ?></small>
									<?php echo esc_html( ucfirst( (string) ( $student['status'] ?? 'active' ) ) ); ?>
								</div>
							</div>
						</div>
					</section>

					<section class="section">
						<h2><?php esc_html_e( 'Exam Results', 'noor-tms' ); ?></h2>
						<?php if ( empty( $results ) ) : ?>
							<p class="alert"><?php esc_html_e( 'No exam results found for this student.', 'noor-tms' ); ?></p>
						<?php else : ?>
							<table>
								<thead>
									<tr>
										<th><?php esc_html_e( 'Subject', 'noor-tms' ); ?></th>
										<th><?php esc_html_e( 'Marks', 'noor-tms' ); ?></th>
										<th><?php esc_html_e( 'Percentage', 'noor-tms' ); ?></th>
										<th><?php esc_html_e( 'Exam Date', 'noor-tms' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $results as $result_row ) : ?>
										<?php
										$obtained   = (float) ( $result_row['marks_obtained'] ?? 0 );
										$total      = (float) ( $result_row['total_marks'] ?? 0 );
										$result_pct = $total > 0 ? ( $obtained / $total ) * 100 : 0;
										?>
										<tr>
											<td><?php echo esc_html( (string) ( $result_row['subject'] ?? '' ) ); ?></td>
											<td><?php echo esc_html( sprintf( '%s / %s', number_format_i18n( $obtained, 2 ), number_format_i18n( $total, 2 ) ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( $result_pct, 2 ) . '%' ); ?></td>
											<td><?php echo esc_html( (string) ( $result_row['exam_date'] ?? '-' ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<div style="margin-top:10px;" class="summary-grid">
								<div class="field">
									<small><?php esc_html_e( 'Total Obtained', 'noor-tms' ); ?></small>
									<?php echo esc_html( number_format_i18n( $total_obtained, 2 ) ); ?>
								</div>
								<div class="field">
									<small><?php esc_html_e( 'Overall Percentage', 'noor-tms' ); ?></small>
									<?php echo esc_html( number_format_i18n( $overall_pct, 2 ) . '%' ); ?>
								</div>
							</div>
						<?php endif; ?>
					</section>

					<section class="section">
						<h2><?php esc_html_e( 'Attendance Summary', 'noor-tms' ); ?></h2>
						<?php if ( empty( $attendance ) ) : ?>
							<p class="alert"><?php esc_html_e( 'No attendance records found for the selected month.', 'noor-tms' ); ?></p>
						<?php else : ?>
							<div class="summary-grid">
								<div class="field"><small><?php esc_html_e( 'Present', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $attendance['present'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Absent', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $attendance['absent'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Late', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $attendance['late'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Excused', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $attendance['excused'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Total Days', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $attendance['total_days'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Attendance %', 'noor-tms' ); ?></small><?php echo esc_html( number_format_i18n( (float) ( $attendance['pct'] ?? 0 ), 2 ) . '%' ); ?></div>
							</div>
						<?php endif; ?>
					</section>

					<section class="section">
						<h2><?php esc_html_e( 'Fee Summary', 'noor-tms' ); ?></h2>
						<?php if ( (int) ( $fee_summary['invoice_count'] ?? 0 ) === 0 ) : ?>
							<p class="alert"><?php esc_html_e( 'No fee records found for this student.', 'noor-tms' ); ?></p>
						<?php else : ?>
							<div class="summary-grid">
								<div class="field"><small><?php esc_html_e( 'Total Due', 'noor-tms' ); ?></small><?php echo esc_html( number_format_i18n( (float) ( $fee_summary['total_due'] ?? 0 ), 2 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Total Paid', 'noor-tms' ); ?></small><?php echo esc_html( number_format_i18n( (float) ( $fee_summary['total_paid'] ?? 0 ), 2 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Balance', 'noor-tms' ); ?></small><?php echo esc_html( number_format_i18n( (float) ( $fee_summary['balance'] ?? 0 ), 2 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $fee_summary['invoice_count'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Paid Invoices', 'noor-tms' ); ?></small><?php echo esc_html( (int) ( $fee_summary['paid_count'] ?? 0 ) ); ?></div>
								<div class="field"><small><?php esc_html_e( 'Partial/Unpaid', 'noor-tms' ); ?></small><?php echo esc_html( ( (int) ( $fee_summary['partial_count'] ?? 0 ) + (int) ( $fee_summary['unpaid_count'] ?? 0 ) ) ); ?></div>
							</div>
						<?php endif; ?>
					</section>
				</div>
			</div>
		</body>
		</html>
		<?php
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function get_print_student_url( int $student_id, ?int $month = null, ?int $year = null ): string {
		$month = (int) ( $month ?: current_time( 'n' ) );
		$year  = (int) ( $year  ?: current_time( 'Y' ) );

		$month = max( 1, min( 12, $month ) );
		$year  = max( 2000, min( 2100, $year ) );

		$url = add_query_arg(
			[
				'action'     => 'noor_tms_print_student',
				'student_id' => $student_id,
				'month'      => $month,
				'year'       => $year,
			],
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'noor_tms_print_student_' . $student_id );
	}

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		if ( 'added' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Student added successfully.', 'noor-tms' ) . '</p></div>';
		} elseif ( 'updated' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Student updated successfully.', 'noor-tms' ) . '</p></div>';
		}
	}
}
