<?php
/**
 * Front-end results overview – class selection grid.
 *
 * Variables in scope:
 *   $classes  array  All classes.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title = __( 'Exam Results', 'noor-tms' );
$active_nav = 'results';

include __DIR__ . '/layout.php';
?>

<?php if ( empty( $classes ) ) : ?>
	<div class="noor-empty">
		<span class="noor-empty-icon">&#128203;</span>
		<p><?php esc_html_e( 'No classes found. Create a class and add students first.', 'noor-tms' ); ?></p>
		<a href="<?php echo esc_url( add_query_arg( 'tms_action', 'new', home_url( '/tms-classes/' ) ) ); ?>"
		   class="noor-btn noor-btn--primary">+ <?php esc_html_e( 'Create a Class', 'noor-tms' ); ?></a>
	</div>
<?php else : ?>

	<p style="margin:0 0 20px;color:var(--tms-muted);font-size:14px;">
		<?php esc_html_e( 'Select a class to view and record exam results.', 'noor-tms' ); ?>
	</p>

	<div class="noor-class-grid">
		<?php foreach ( $classes as $cls ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'class_id', $cls['id'], home_url( '/tms-results/' ) ) ); ?>"
			   class="noor-class-card noor-class-card--link">
				<div class="noor-class-card__header">
					<h3 class="noor-class-card__name"><?php echo esc_html( $cls['name'] ); ?></h3>
					<span class="noor-class-card__meta">
						<?php echo esc_html( sprintf(
							_n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ),
							(int) $cls['subject_count']
						) ); ?>
					</span>
				</div>
				<span class="noor-class-card__cta">
					<?php esc_html_e( 'View Results →', 'noor-tms' ); ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>

<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
