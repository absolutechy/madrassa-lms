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
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
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
                const url = new URL(window.location.href);
                
                // Clear existing parameters
                url.searchParams.delete('noor_search');
                url.searchParams.delete('s');
                url.searchParams.delete('class_id');
                url.searchParams.delete('status_filter');
                url.searchParams.delete('paged');
                
                // Add page param if in admin
                const pageInput = document.getElementById('noor_page');
                if (pageInput) {
                    url.searchParams.set('page', pageInput.value);
                }
                
                const search = document.getElementById('noor_search_input').value.trim();
                const classId = document.getElementById('noor_class_id').value;
                const status = document.getElementById('noor_status_filter').value;
                
                if (search) url.searchParams.set('noor_search', search);
                if (classId) url.searchParams.set('class_id', classId);
                if (status) url.searchParams.set('status_filter', status);
                
                window.location.href = url.toString();
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

				<button type="button" class="button button-small button-link-delete noor-delete-student"
						data-id="<?php echo esc_attr( $student['id'] ); ?>">
					<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
				</button>
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
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$student    = $student_id ? DatabaseHandler::get_student( $student_id ) : null;

		// Handle form submission.
		if ( isset( $_POST['noor_tms_student_nonce'] ) ) {
			$this->handle_student_save( $student_id );
			return;
		}

		$classes = DatabaseHandler::get_classes_dropdown();
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
								<label for="class_id"><?php esc_html_e( 'Class', 'noor-tms' ); ?></label>
							</th>
							<td>
								<?php if ( empty( $classes ) ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %s: link to create class */
											esc_html__( 'No classes found. %s first.', 'noor-tms' ),
											'<a href="' . esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create a class', 'noor-tms' ) . '</a>'
										);
										?>
									</p>
								<?php else : ?>
									<select id="class_id" name="class_id" class="regular-text">
										<option value="0"><?php esc_html_e( '— No Class —', 'noor-tms' ); ?></option>
										<?php foreach ( $classes as $cls ) : ?>
											<option value="<?php echo esc_attr( $cls['id'] ); ?>"
												<?php selected( (int) ( $student['class_id'] ?? 0 ), (int) $cls['id'] ); ?>>
												<?php echo esc_html( $cls['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
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
			'enrollment_date' => sanitize_text_field( $_POST['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
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

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
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

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		if ( 'added' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Student added successfully.', 'noor-tms' ) . '</p></div>';
		} elseif ( 'updated' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Student updated successfully.', 'noor-tms' ) . '</p></div>';
		}
	}
}
