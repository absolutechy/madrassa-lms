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
		$subjects = array_filter(
			array_map( 'sanitize_text_field', (array) ( $_POST['subjects'] ?? [] ) )
		);

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Class name cannot be empty.', 'noor-tms' ) );
		}

		if ( $class_id > 0 ) {
			DatabaseHandler::update_class( $class_id, $name, array_values( $subjects ) );
			$msg = 'class_updated';
		} else {
			DatabaseHandler::insert_class( $name, array_values( $subjects ) );
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
