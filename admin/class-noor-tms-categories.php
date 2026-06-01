<?php
/**
 * Categories management – list, create, edit, and delete.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Categories
 */
class Categories {

	// -----------------------------------------------------------------------
	// Page router
	// -----------------------------------------------------------------------

	public function page_categories(): void {
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$action      = sanitize_key( $_GET['action'] ?? 'list' );
		$category_id = (int) ( $_GET['category_id'] ?? 0 );

		if ( 'delete' === $action && $category_id ) {
			$this->handle_delete( $category_id );
			return;
		}

		// Handle POST (create / update).
		if ( isset( $_POST['noor_tms_category_nonce'] ) ) {
			$this->handle_save( $category_id );
			return;
		}

		if ( 'edit' === $action && $category_id ) {
			$this->page_form( $category_id );
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
		$scope = $this->get_account_type_scope();
		$filter = $scope ? $scope : sanitize_key( $_GET['account_type'] ?? '' );
		if ( $filter && ! in_array( $filter, [ 'banin', 'banaat' ], true ) ) {
			$filter = '';
		}

		$args = [];
		if ( $filter ) {
			$args['account_type'] = $filter;
		}
		$args['include_inactive'] = true;

		$categories = DatabaseHandler::get_categories( $args );
		$parents    = [];
		foreach ( $categories as $cat ) {
			if ( (int) $cat['parent_id'] === 0 ) {
				$parents[ (int) $cat['id'] ] = $cat['name'];
			}
		}
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'noor-tms' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-categories', 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>"
			   class="page-title-action"><?php esc_html_e( 'Add New Category', 'noor-tms' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<?php if ( ! $scope ) : ?>
				<form method="get" style="margin:12px 0;">
					<input type="hidden" name="page" value="noor-tms-categories" />
					<select name="account_type">
						<option value=""><?php esc_html_e( 'All Types', 'noor-tms' ); ?></option>
						<option value="banin" <?php selected( $filter, 'banin' ); ?>><?php esc_html_e( 'Banin', 'noor-tms' ); ?></option>
						<option value="banaat" <?php selected( $filter, 'banaat' ); ?>><?php esc_html_e( 'Banaat', 'noor-tms' ); ?></option>
					</select>
					<button class="button"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
				</form>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped noor-tms-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Type', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'School', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Parent', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Max Marks', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Pass Marks', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Order', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $categories ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No categories found.', 'noor-tms' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $categories as $cat ) : ?>
							<?php
							$parent_id = (int) $cat['parent_id'];
							$parent_name = $parent_id ? ( $parents[ $parent_id ] ?? '' ) : '';
							$type_label = ( 'banaat' === $cat['account_type'] ) ? __( 'Banaat', 'noor-tms' ) : __( 'Banin', 'noor-tms' );
							$school_label = ! empty( $cat['is_school_type'] ) ? __( 'School', 'noor-tms' ) : __( 'Course', 'noor-tms' );
							$status_label = ! empty( $cat['is_active'] ) ? __( 'Active', 'noor-tms' ) : __( 'Inactive', 'noor-tms' );
							$edit_url = add_query_arg( [
								'page' => 'noor-tms-categories',
								'action' => 'edit',
								'category_id' => (int) $cat['id'],
							], admin_url( 'admin.php' ) );
							$delete_url = wp_nonce_url(
								add_query_arg( [
									'page' => 'noor-tms-categories',
									'action' => 'delete',
									'category_id' => (int) $cat['id'],
								], admin_url( 'admin.php' ) ),
								'noor_tms_delete_category_' . (int) $cat['id']
							);
							?>
							<tr>
								<td>
									<?php if ( $parent_id ) : ?>
										<span class="description">—</span>
									<?php endif; ?>
									<?php echo esc_html( $cat['name'] ); ?>
								</td>
								<td><?php echo esc_html( $type_label ); ?></td>
								<td><?php echo esc_html( $school_label ); ?></td>
								<td><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo esc_html( $parent_name ?: '—' ); ?></td>
								<td><?php echo esc_html( (string) ( $cat['max_marks'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $cat['pass_marks'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $cat['sort_order'] ?? 0 ) ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>">
										<?php esc_html_e( 'Edit', 'noor-tms' ); ?>
									</a>
									<a class="button button-small" href="<?php echo esc_url( $delete_url ); ?>"
									   onclick="return confirm('<?php esc_attr_e( 'Delete this category?', 'noor-tms' ); ?>');">
										<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Form view
	// -----------------------------------------------------------------------

	private function page_form( int $category_id ): void {
		$category = $category_id ? DatabaseHandler::get_category( $category_id ) : null;
		if ( $category_id && ! $category ) {
			wp_die( esc_html__( 'Category not found.', 'noor-tms' ) );
		}

		$scope = $this->get_account_type_scope();
		$account_type = $scope ? $scope : ( $category['account_type'] ?? 'banin' );

		$parent_args = [ 'parent_id' => 0, 'include_inactive' => true ];
		if ( $scope ) {
			$parent_args['account_type'] = $account_type;
		}
		$parents = DatabaseHandler::get_categories( $parent_args );
		if ( $category_id ) {
			$parents = array_values( array_filter( $parents, fn( $p ) => (int) $p['id'] !== $category_id ) );
		}

		$title = $category ? __( 'Edit Category', 'noor-tms' ) : __( 'Add New Category', 'noor-tms' );
		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<hr class="wp-header-end">

			<div class="noor-tms-card">
				<form method="post" action="">
					<?php wp_nonce_field( 'noor_tms_save_category', 'noor_tms_category_nonce' ); ?>
					<?php if ( $category_id ) : ?>
						<input type="hidden" name="category_id" value="<?php echo esc_attr( $category_id ); ?>" />
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="name"><?php esc_html_e( 'Name', 'noor-tms' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input type="text" id="name" name="name" required
									value="<?php echo esc_attr( $category['name'] ?? '' ); ?>"
									class="regular-text" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="account_type"><?php esc_html_e( 'Type', 'noor-tms' ); ?></label>
							</th>
							<td>
								<?php if ( $scope ) : ?>
									<input type="hidden" name="account_type" value="<?php echo esc_attr( $account_type ); ?>" />
									<?php echo esc_html( 'banaat' === $account_type ? __( 'Banaat', 'noor-tms' ) : __( 'Banin', 'noor-tms' ) ); ?>
								<?php else : ?>
									<select id="account_type" name="account_type">
										<option value="banin" <?php selected( $account_type, 'banin' ); ?>><?php esc_html_e( 'Banin', 'noor-tms' ); ?></option>
										<option value="banaat" <?php selected( $account_type, 'banaat' ); ?>><?php esc_html_e( 'Banaat', 'noor-tms' ); ?></option>
									</select>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="is_school_type"><?php esc_html_e( 'School Type', 'noor-tms' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="is_school_type" name="is_school_type" value="1" <?php checked( ! empty( $category['is_school_type'] ) ); ?> />
									<?php esc_html_e( 'Treat this as a school-type category', 'noor-tms' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="is_active"><?php esc_html_e( 'Active', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="is_active" name="is_active">
									<option value="1" <?php selected( (int) ( $category['is_active'] ?? 1 ), 1 ); ?>><?php esc_html_e( 'Yes', 'noor-tms' ); ?></option>
									<option value="0" <?php selected( (int) ( $category['is_active'] ?? 1 ), 0 ); ?>><?php esc_html_e( 'No', 'noor-tms' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="parent_id"><?php esc_html_e( 'Parent Category', 'noor-tms' ); ?></label>
							</th>
							<td>
								<select id="parent_id" name="parent_id" class="regular-text">
									<option value="0"><?php esc_html_e( '— None (Top Level) —', 'noor-tms' ); ?></option>
									<?php foreach ( $parents as $parent ) : ?>
										<option value="<?php echo esc_attr( $parent['id'] ); ?>"
											data-account="<?php echo esc_attr( $parent['account_type'] ); ?>"
											<?php selected( (int) ( $category['parent_id'] ?? 0 ), (int) $parent['id'] ); ?>>
											<?php echo esc_html( $parent['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="max_marks"><?php esc_html_e( 'Max Marks', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="number" step="0.01" min="0" id="max_marks" name="max_marks" class="small-text"
									value="<?php echo esc_attr( (string) ( $category['max_marks'] ?? '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="pass_marks"><?php esc_html_e( 'Pass Marks', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="number" step="0.01" min="0" id="pass_marks" name="pass_marks" class="small-text"
									value="<?php echo esc_attr( (string) ( $category['pass_marks'] ?? '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="sort_order"><?php esc_html_e( 'Sort Order', 'noor-tms' ); ?></label>
							</th>
							<td>
								<input type="number" id="sort_order" name="sort_order" class="small-text"
									value="<?php echo esc_attr( $category['sort_order'] ?? 0 ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="description"><?php esc_html_e( 'Description', 'noor-tms' ); ?></label>
							</th>
							<td>
								<textarea id="description" name="description" class="large-text" rows="4"><?php echo esc_textarea( $category['description'] ?? '' ); ?></textarea>
							</td>
						</tr>
					</table>

					<div class="noor-form-actions">
						<?php submit_button( $category ? __( 'Update Category', 'noor-tms' ) : __( 'Add Category', 'noor-tms' ), 'primary', 'submit', false ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-categories' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
						</a>
					</div>
				</form>
				<?php if ( ! $scope ) : ?>
					<script>
					(function() {
						const accountType = document.getElementById('account_type');
						const parent = document.getElementById('parent_id');
						const schoolType = document.getElementById('is_school_type');
						if (!accountType || !parent) return;
						const options = Array.from(parent.options);
						function syncParents() {
							const type = accountType.value;
							options.forEach(option => {
								if (!option.value) {
									option.hidden = false;
									return;
								}
								const match = option.dataset.account === type;
								option.hidden = !match;
							});
							if (parent.selectedOptions[0] && parent.selectedOptions[0].hidden) {
								parent.value = '0';
							}
							if (schoolType && parent.value !== '0') {
								schoolType.checked = false;
								schoolType.disabled = true;
							} else if (schoolType) {
								schoolType.disabled = false;
							}
						}
						accountType.addEventListener('change', syncParents);
						parent.addEventListener('change', syncParents);
						syncParents();
					})();
					</script>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Actions
	// -----------------------------------------------------------------------

	private function handle_save( int $category_id ): void {
		if ( ! check_admin_referer( 'noor_tms_save_category', 'noor_tms_category_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$data = [
			'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
			'account_type'   => sanitize_key( $_POST['account_type'] ?? '' ),
			'parent_id'      => (int) ( $_POST['parent_id'] ?? 0 ),
			'is_school_type' => ! empty( $_POST['is_school_type'] ) ? 1 : 0,
			'is_active'      => (int) ( $_POST['is_active'] ?? 1 ),
			'max_marks'      => (float) ( $_POST['max_marks'] ?? 0 ),
			'pass_marks'     => (float) ( $_POST['pass_marks'] ?? 0 ),
			'sort_order'     => (int) ( $_POST['sort_order'] ?? 0 ),
			'description'    => sanitize_textarea_field( $_POST['description'] ?? '' ),
		];

		if ( $category_id > 0 ) {
			$ok = DatabaseHandler::update_category( $category_id, $data );
			$msg = $ok ? 'category_updated' : 'category_error';
		} else {
			$inserted = DatabaseHandler::insert_category( $data );
			$msg = $inserted ? 'category_added' : 'category_error';
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-categories', 'msg' => $msg ], admin_url( 'admin.php' ) ) );
		exit;
	}

	private function handle_delete( int $category_id ): void {
		$nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'noor_tms_delete_category_' . $category_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$deleted = DatabaseHandler::delete_category( $category_id );
		$msg = $deleted ? 'category_deleted' : 'category_delete_failed';

		wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-categories', 'msg' => $msg ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		$map = [
			'category_added'         => __( 'Category created successfully.', 'noor-tms' ),
			'category_updated'       => __( 'Category updated successfully.', 'noor-tms' ),
			'category_deleted'       => __( 'Category deleted successfully.', 'noor-tms' ),
			'category_delete_failed' => __( 'Category could not be deleted (remove sub-categories first).', 'noor-tms' ),
			'category_error'         => __( 'Unable to save category. Please check the fields.', 'noor-tms' ),
		];
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( 'category_error' === $msg || 'category_delete_failed' === $msg ? 'error' : 'success' ) . ' is-dismissible"><p>'
				. esc_html( $map[ $msg ] )
				. '</p></div>';
		}
	}

	private function get_account_type_scope(): ?string {
		$can_banin  = current_user_can( 'manage_banin' );
		$can_banaat = current_user_can( 'manage_banaat' );
		if ( $can_banin && ! $can_banaat ) {
			return 'banin';
		}
		if ( $can_banaat && ! $can_banin ) {
			return 'banaat';
		}
		return null;
	}
}
