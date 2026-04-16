<?php
/**
 * Front-end class add / edit form template.
 *
 * Variables in scope:
 *   $cls        array|null  Class row (null when creating).
 *   $class_id   int         0 when creating.
 *   $subjects   array       Existing subjects for this class.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$is_edit        = ! empty( $cls );
$page_title     = $is_edit ? __( 'Edit Class', 'noor-tms' ) : __( 'Add New Class', 'noor-tms' );
$active_nav     = 'classes';
$topbar_actions = '<a href="' . esc_url( home_url( '/tms-classes/' ) ) . '" class="noor-btn noor-btn--secondary">'
	. '&larr; ' . esc_html__( 'Back to Classes', 'noor-tms' ) . '</a>';

include __DIR__ . '/layout.php';
?>

<div class="noor-card">
	<h2><?php echo esc_html( $page_title ); ?></h2>

	<form method="post"
		  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		  id="noor-class-form">

		<?php wp_nonce_field( 'noor_tms_save_class', 'noor_tms_class_nonce' ); ?>
		<input type="hidden" name="action" value="noor_tms_save_class" />
		<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />

		<div class="noor-form-group" style="max-width:420px;">
			<label for="class_name"><?php esc_html_e( 'Class Name', 'noor-tms' ); ?> <span class="required">*</span></label>
			<input type="text" id="class_name" name="class_name" required
				   value="<?php echo esc_attr( $cls['name'] ?? '' ); ?>"
				   placeholder="<?php esc_attr_e( 'e.g. Hifz Class 1', 'noor-tms' ); ?>" />
		</div>

		<div class="noor-form-group">
			<label><?php esc_html_e( 'Subjects', 'noor-tms' ); ?></label>
			<p class="noor-form-description"><?php esc_html_e( 'Add all subjects taught in this class.', 'noor-tms' ); ?></p>

			<div id="noor-subjects-list" style="max-width:420px;margin-bottom:10px;">
				<?php if ( ! empty( $subjects ) ) : ?>
					<?php foreach ( $subjects as $sub ) : ?>
						<div class="noor-subject-row">
							<input type="text" name="subjects[]"
								   value="<?php echo esc_attr( $sub['subject_name'] ); ?>"
								   placeholder="<?php esc_attr_e( 'Subject name', 'noor-tms' ); ?>" />
							<button type="button" class="noor-btn noor-btn--danger noor-btn--sm noor-remove-subject">&times;</button>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="noor-subject-row">
						<input type="text" name="subjects[]"
							   placeholder="<?php esc_attr_e( 'Subject name', 'noor-tms' ); ?>" />
						<button type="button" class="noor-btn noor-btn--danger noor-btn--sm noor-remove-subject">&times;</button>
					</div>
				<?php endif; ?>
			</div>

			<button type="button" id="noor-add-subject" class="noor-btn noor-btn--secondary noor-btn--sm">
				+ <?php esc_html_e( 'Add Subject', 'noor-tms' ); ?>
			</button>
		</div>

		<div class="noor-form-actions">
			<button type="submit" class="noor-btn noor-btn--primary">
				<?php echo esc_html( $is_edit ? __( 'Update Class', 'noor-tms' ) : __( 'Create Class', 'noor-tms' ) ); ?>
			</button>
			<a href="<?php echo esc_url( home_url( '/tms-classes/' ) ); ?>" class="noor-btn noor-btn--secondary">
				<?php esc_html_e( 'Cancel', 'noor-tms' ); ?>
			</a>
		</div>
	</form>
</div>

<?php include __DIR__ . '/layout-close.php'; ?>
