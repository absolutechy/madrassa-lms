<?php
/**
 * Front-end classes list template.
 *
 * Variables in scope:
 *   $classes  array  All classes with subject_count.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Classes', 'noor-tms' );
$active_nav     = 'classes';

$add_class_url = add_query_arg(
	array_filter(
		[
			'tms_action'     => 'new',
			'category_id'    => (int) ( $category_id ?? 0 ),
			'subcategory_id' => (int) ( $subcategory_id ?? 0 ),
		],
		static fn( $value ) => (int) $value > 0 || 'new' === $value
	),
	home_url( '/tms-classes/' )
);

$topbar_actions = '<a href="' . esc_url( $add_class_url ) . '"'
	. ' class="noor-btn noor-btn--primary">+ ' . esc_html__( 'Add New Class', 'noor-tms' ) . '</a>';

include __DIR__ . '/layout.php';

$scope_bits = [];
if ( ! empty( $category_id ) ) {
	$scope_bits[] = sprintf( __( 'Category: %s', 'noor-tms' ), $scope_category['name'] ?? (string) $category_id );
}
if ( ! empty( $subcategory_id ) ) {
	$scope_bits[] = sprintf( __( 'Sub-Category: %s', 'noor-tms' ), $scope_subcategory['name'] ?? (string) $subcategory_id );
}

$msg = sanitize_key( $_GET['msg'] ?? '' );
if ( 'class_added' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Class created successfully.', 'noor-tms' ) . '</div>';
} elseif ( 'class_updated' === $msg ) {
	echo '<div class="noor-notice noor-notice--success">' . esc_html__( 'Class updated successfully.', 'noor-tms' ) . '</div>';
}
?>

<?php if ( ! empty( $scope_bits ) ) : ?>
	<div class="noor-notice noor-notice--info">
		<?php echo esc_html( implode( ' | ', $scope_bits ) ); ?>
	</div>
<?php endif; ?>

<?php if ( empty( $classes ) ) : ?>
	<div class="noor-empty">
		<span class="noor-empty-icon">&#127979;</span>
		<p><?php esc_html_e( 'No classes yet. Create your first class to get started.', 'noor-tms' ); ?></p>
		<a href="<?php echo esc_url( $add_class_url ); ?>"
		   class="noor-btn noor-btn--primary">+ <?php esc_html_e( 'Create a Class', 'noor-tms' ); ?></a>
	</div>
<?php else : ?>

	<div class="noor-class-grid">
		<?php foreach ( $classes as $cls ) :
			$subjects = \Noor_TMS\Includes\DatabaseHandler::get_subjects_by_class( (int) $cls['id'] );
		?>
			<div class="noor-class-card" id="noor-class-card-<?php echo esc_attr( $cls['id'] ); ?>">
				<div class="noor-class-card__header">
					<h3 class="noor-class-card__name"><?php echo esc_html( $cls['name'] ); ?></h3>
					<span class="noor-class-card__meta">
						<?php echo esc_html( sprintf(
							_n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ),
							(int) $cls['subject_count']
						) ); ?>
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
					<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'edit', 'class_id' => $cls['id'] ], home_url( '/tms-classes/' ) ) ); ?>"
					   class="noor-btn noor-btn--secondary noor-btn--sm">
						<?php esc_html_e( 'Edit', 'noor-tms' ); ?>
					</a>

					<a href="<?php echo esc_url( add_query_arg( 'class_id', $cls['id'], home_url( '/tms-results/' ) ) ); ?>"
					   class="noor-btn noor-btn--secondary noor-btn--sm">
						<?php esc_html_e( 'View Results', 'noor-tms' ); ?>
					</a>

					<button type="button"
							class="noor-btn noor-btn--danger noor-btn--sm noor-delete-class"
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

<?php include __DIR__ . '/layout-close.php'; ?>
