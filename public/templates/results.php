<?php
/**
 * Front-end results overview – category, sub-category, and class drilldown.
 *
 * Variables in scope:
 *   $categories_by_scope array  Root categories grouped by account type.
 *   $category            array|null Selected category.
 *   $subcategory         array|null Selected sub-category.
 *   $subcategories       array  Sub-category rows for the selected category.
 *   $classes             array  Class rows for the selected sub-category.
 *   $category_id         int    Selected category ID.
 *   $subcategory_id      int    Selected sub-category ID.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title = __( 'Exam Results', 'noor-tms' );
$active_nav = 'results';
$topbar_actions = '';

if ( ! empty( $subcategory ) ) {
	$page_title = esc_html( $subcategory['name'] ) . ' — ' . __( 'Exam Results', 'noor-tms' );
	$topbar_actions = '<a href="' . esc_url( add_query_arg( [ 'category_id' => (int) ( $category['id'] ?? 0 ) ], home_url( '/tms-results/' ) ) ) . '" class="noor-btn noor-btn--secondary">';
	$topbar_actions .= '&larr; ' . esc_html__( 'Back to Sub-Categories', 'noor-tms' ) . '</a>';
} elseif ( ! empty( $category ) ) {
	$page_title = esc_html( $category['name'] ) . ' — ' . __( 'Exam Results', 'noor-tms' );
	$topbar_actions = '<a href="' . esc_url( home_url( '/tms-results/' ) ) . '" class="noor-btn noor-btn--secondary">';
	$topbar_actions .= '&larr; ' . esc_html__( 'All Categories', 'noor-tms' ) . '</a>';
}

include __DIR__ . '/layout.php';
?>

<?php if ( ! empty( $subcategory ) ) : ?>
	<div class="noor-card">
		<div class="noor-form-row" style="align-items:flex-start;justify-content:space-between;gap:16px;">
			<div>
				<h2 style="margin-top:0;margin-bottom:6px;">
					<?php echo esc_html( $subcategory['name'] ); ?>
				</h2>
				<p class="noor-form-description" style="margin-bottom:0;">
					<?php esc_html_e( 'Select a course or class to open the result entry screen.', 'noor-tms' ); ?>
				</p>
			</div>
		</div>

		<?php if ( empty( $classes ) ) : ?>
			<div class="noor-empty">
				<p><?php esc_html_e( 'No classes are available in this sub-category yet.', 'noor-tms' ); ?></p>
			</div>
		<?php else : ?>
			<div class="noor-class-grid">
				<?php foreach ( $classes as $cls ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'class_id' => (int) $cls['id'], 'category_id' => (int) ( $category['id'] ?? 0 ), 'subcategory_id' => (int) $subcategory['id'] ], home_url( '/tms-results/' ) ) ); ?>"
					   class="noor-class-card noor-class-card--link">
						<div class="noor-class-card__header">
							<h3 class="noor-class-card__name"><?php echo esc_html( $cls['name'] ); ?></h3>
							<span class="noor-class-card__meta">
								<?php echo esc_html( sprintf( _n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ), (int) $cls['subject_count'] ) ); ?>
							</span>
						</div>
						<span class="noor-class-card__cta"><?php esc_html_e( 'Open Results →', 'noor-tms' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

<?php elseif ( ! empty( $category ) ) : ?>
	<div class="noor-card">
		<div class="noor-form-row" style="align-items:flex-start;justify-content:space-between;gap:16px;">
			<div>
				<h2 style="margin-top:0;margin-bottom:6px;">
					<?php echo esc_html( $category['name'] ); ?>
				</h2>
				<p class="noor-form-description" style="margin-bottom:0;">
					<?php echo esc_html( ! empty( $category['is_school_type'] ) ? __( 'Choose a sub-category to continue to the available classes.', 'noor-tms' ) : __( 'Choose a course to open the result entry form.', 'noor-tms' ) ); ?>
				</p>
			</div>
		</div>

		<?php if ( empty( $subcategories ) ) : ?>
			<div class="noor-empty">
				<p><?php esc_html_e( 'No sub-categories are available for this category.', 'noor-tms' ); ?></p>
			</div>
		<?php else : ?>
			<div class="noor-class-grid">
				<?php foreach ( $subcategories as $item ) : ?>
					<?php $subcat = $item['subcategory']; ?>
					<?php
					$subcat_url = add_query_arg( [ 'category_id' => (int) $category['id'], 'subcategory_id' => (int) $subcat['id'] ], home_url( '/tms-results/' ) );
					if ( empty( $category['is_school_type'] ) && ! empty( $item['classes'][0]['id'] ) ) {
						$subcat_url = add_query_arg(
							[
								'class_id'       => (int) $item['classes'][0]['id'],
								'category_id'    => (int) $category['id'],
								'subcategory_id' => (int) $subcat['id'],
							],
							home_url( '/tms-results/' )
						);
					}
					?>
					<a href="<?php echo esc_url( $subcat_url ); ?>"
					   class="noor-class-card noor-class-card--link">
						<div class="noor-class-card__header">
							<h3 class="noor-class-card__name"><?php echo esc_html( $subcat['name'] ); ?></h3>
							<span class="noor-class-card__meta">
								<?php echo esc_html( sprintf( _n( '%d class', '%d classes', count( $item['classes'] ), 'noor-tms' ), count( $item['classes'] ) ) ); ?>
							</span>
						</div>
						<span class="noor-class-card__cta">
							<?php echo esc_html( ! empty( $category['is_school_type'] ) ? __( 'View Classes →', 'noor-tms' ) : __( 'Open Results →', 'noor-tms' ) ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

<?php else : ?>
	<?php if ( empty( $categories_by_scope ) ) : ?>
		<div class="noor-empty">
			<span class="noor-empty-icon">&#128203;</span>
			<p><?php esc_html_e( 'No categories are available for your account type.', 'noor-tms' ); ?></p>
		</div>
	<?php else : ?>
		<p style="margin:0 0 20px;color:var(--tms-muted);font-size:14px;">
			<?php esc_html_e( 'Select a category to continue to its sub-categories and classes.', 'noor-tms' ); ?>
		</p>

		<?php foreach ( $categories_by_scope as $scope => $items ) : ?>
			<div class="noor-card" style="margin-bottom:20px;">
				<h2 style="margin-top:0;">
					<?php echo esc_html( 'banin' === $scope ? __( 'Banin Categories', 'noor-tms' ) : ( 'banaat' === $scope ? __( 'Banaat Categories', 'noor-tms' ) : __( 'Categories', 'noor-tms' ) ) ); ?>
				</h2>
				<div class="noor-class-grid">
					<?php foreach ( $items as $item ) : ?>
						<?php $cat = $item['category']; ?>
						<a href="<?php echo esc_url( add_query_arg( 'category_id', (int) $cat['id'], home_url( '/tms-results/' ) ) ); ?>"
						   class="noor-class-card noor-class-card--link">
							<div class="noor-class-card__header">
								<h3 class="noor-class-card__name"><?php echo esc_html( $cat['name'] ); ?></h3>
								<span class="noor-class-card__meta">
									<?php echo esc_html( sprintf( _n( '%d sub-category', '%d sub-categories', count( $item['subcategories'] ), 'noor-tms' ), count( $item['subcategories'] ) ) ); ?>
								</span>
							</div>
							<span class="noor-class-card__cta"><?php esc_html_e( 'Open Category →', 'noor-tms' ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
