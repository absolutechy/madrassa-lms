<?php
/**
 * Front-end categories page.
 *
 * Variables in scope:
 *   $categories     array  Top-level categories for the current scope.
 *   $category       array|null Selected category.
 *   $subcategories  array  Child rows for the selected category.
 *   $category_id    int    Selected category ID.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Categories', 'noor-tms' );
$active_nav     = 'categories';
$topbar_actions = '';

include __DIR__ . '/layout.php';

if ( empty( $categories ) ) :
	?>
	<div class="noor-empty">
		<p><?php esc_html_e( 'No categories are available for your account type.', 'noor-tms' ); ?></p>
	</div>
	<?php
	include __DIR__ . '/layout-close.php';
	return;
endif;
?>

<div class="noor-card">
	<div class="noor-form-row" style="align-items:flex-start;">
		<div class="noor-form-group" style="min-width:280px;max-width:360px;">
			<label for="noor-category-nav"><?php esc_html_e( 'Browse Categories', 'noor-tms' ); ?></label>
			<select id="noor-category-nav">
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( (int) $cat['id'] ); ?>" <?php selected( (int) $category_id, (int) $cat['id'] ); ?>>
						<?php echo esc_html( $cat['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="noor-form-group" style="flex:1;">
			<?php if ( ! empty( $category ) ) : ?>
				<h2 style="margin-top:0;">
					<?php echo esc_html( $category['name'] ); ?>
					<span class="noor-badge <?php echo ! empty( $category['is_school_type'] ) ? 'noor-badge--active' : 'noor-badge--inactive'; ?>">
						<?php echo ! empty( $category['is_school_type'] ) ? esc_html__( 'School Type', 'noor-tms' ) : esc_html__( 'Simple Type', 'noor-tms' ); ?>
					</span>
				</h2>
				<p class="noor-form-description"><?php echo esc_html( sprintf( __( 'Account type: %s', 'noor-tms' ), ( 'banaat' === $category['account_type'] ? __( 'Banaat', 'noor-tms' ) : __( 'Banin', 'noor-tms' ) ) ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( empty( $category ) ) : ?>
		<div class="noor-empty">
			<p><?php esc_html_e( 'Select a category to view its sub-categories.', 'noor-tms' ); ?></p>
		</div>
	<?php elseif ( ! empty( $category['is_school_type'] ) ) : ?>
		<div class="noor-class-grid">
			<?php foreach ( $subcategories as $subcat ) : ?>
				<?php
				$subcat_classes = 
					\Noor_TMS\Includes\DatabaseHandler::get_classes_by_context( [ 'subcategory_id' => (int) $subcat['id'] ] );
				$subcat_url = add_query_arg(
					[
						'category_id'    => (int) $category_id,
						'subcategory_id' => (int) $subcat['id'],
					],
					home_url( '/tms-classes/' )
				);
				?>
				<a class="noor-class-card" href="<?php echo esc_url( $subcat_url ); ?>" style="text-decoration:none;display:block;">
					<div class="noor-class-card__header">
						<h3 class="noor-class-card__name"><?php echo esc_html( $subcat['name'] ); ?></h3>
						<span class="noor-class-card__meta">
							<?php
							echo esc_html(
								sprintf(
								/* translators: %d: number of classes */
								_n( '%d class', '%d classes', count( $subcat_classes ), 'noor-tms' ),
								count( $subcat_classes )
								)
							);
							?>
						</span>
					</div>
					<?php if ( empty( $subcat_classes ) ) : ?>
						<p class="noor-form-description"><?php esc_html_e( 'No classes have been assigned to this sub-category yet.', 'noor-tms' ); ?></p>
					<?php else : ?>
						<ul class="noor-subject-tags">
							<?php foreach ( $subcat_classes as $cls ) : ?>
								<li class="noor-subject-tag">
									<?php echo esc_html( $cls['name'] ); ?>
									<span class="description"><?php echo esc_html( sprintf( _n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ), (int) $cls['subject_count'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="noor-class-grid">
			<?php foreach ( $subcategories as $subcat ) : ?>
				<form class="noor-class-card noor-category-card-form" data-category-id="<?php echo esc_attr( (int) $subcat['id'] ); ?>">
					<div class="noor-class-card__header">
						<h3 class="noor-class-card__name"><?php echo esc_html( $subcat['name'] ); ?></h3>
						<span class="noor-class-card__meta"><?php esc_html_e( 'Simple course card', 'noor-tms' ); ?></span>
					</div>
					<input type="hidden" name="category_id" value="<?php echo esc_attr( (int) $subcat['id'] ); ?>" />
					<div class="noor-form-row">
						<div class="noor-form-group">
							<label><?php esc_html_e( 'Name', 'noor-tms' ); ?></label>
							<input type="text" name="name" value="<?php echo esc_attr( $subcat['name'] ); ?>" />
						</div>
						<div class="noor-form-group">
							<label><?php esc_html_e( 'Max Marks', 'noor-tms' ); ?></label>
							<input type="number" step="0.01" min="0" name="max_marks" value="<?php echo esc_attr( (string) ( $subcat['max_marks'] ?? 0 ) ); ?>" />
						</div>
						<div class="noor-form-group">
							<label><?php esc_html_e( 'Pass Marks', 'noor-tms' ); ?></label>
							<input type="number" step="0.01" min="0" name="pass_marks" value="<?php echo esc_attr( (string) ( $subcat['pass_marks'] ?? 0 ) ); ?>" />
						</div>
						<div class="noor-form-group">
							<label><?php esc_html_e( 'Active', 'noor-tms' ); ?></label>
							<select name="is_active">
								<option value="1" <?php selected( (int) ( $subcat['is_active'] ?? 1 ), 1 ); ?>><?php esc_html_e( 'Yes', 'noor-tms' ); ?></option>
								<option value="0" <?php selected( (int) ( $subcat['is_active'] ?? 1 ), 0 ); ?>><?php esc_html_e( 'No', 'noor-tms' ); ?></option>
							</select>
						</div>
					</div>
					<div class="noor-class-card__actions">
						<button type="submit" class="noor-btn noor-btn--primary noor-btn--sm"><?php esc_html_e( 'Save', 'noor-tms' ); ?></button>
					</div>
					<div class="noor-form-description noor-category-feedback" aria-live="polite"></div>
				</form>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<script>
(function() {
	const nav = document.getElementById('noor-category-nav');
	if (nav) {
		nav.addEventListener('change', function() {
			window.location.href = window.location.pathname + '?category_id=' + encodeURIComponent(nav.value);
		});
	}

	document.querySelectorAll('.noor-category-card-form').forEach(function(form) {
		form.addEventListener('submit', async function(event) {
			event.preventDefault();
			const feedback = form.querySelector('.noor-category-feedback');
			const data = jQuery(form).serialize() + '&action=noor_tms_save_category_card&nonce=' + encodeURIComponent(noorTMS.nonce);
			feedback.textContent = '<?php echo esc_js( __( 'Saving…', 'noor-tms' ) ); ?>';
			try {
				const response = await fetch(noorTMS.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: data
				});
				const json = await response.json();
				if (json && json.success) {
					feedback.textContent = json.data && json.data.message ? json.data.message : '<?php echo esc_js( __( 'Saved.', 'noor-tms' ) ); ?>';
				} else {
					feedback.textContent = (json && json.data && json.data.message) ? json.data.message : '<?php echo esc_js( __( 'Unable to save.', 'noor-tms' ) ); ?>';
				}
			} catch (error) {
				feedback.textContent = '<?php echo esc_js( __( 'Unable to save.', 'noor-tms' ) ); ?>';
			}
		});
	});
})();
</script>

<?php include __DIR__ . '/layout-close.php'; ?>
