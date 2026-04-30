<?php
/**
 * Public homepage template.
 *
 * Variables in scope:
 *   $opts    array   Portal settings.
 *   $classes array   Public class cards.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$madrassa_name = (string) ( $opts['madrassa_name'] ?? get_bloginfo( 'name' ) );
$tagline       = (string) ( $opts['madrassa_tagline'] ?? '' );
$about_text    = (string) ( $opts['madrassa_about'] ?? '' );

$cta_apply_label   = (string) ( $opts['cta_apply_label'] ?? __( 'Apply Admission', 'noor-tms' ) );
$cta_apply_url     = (string) ( $opts['cta_apply_url'] ?? '' );
$cta_classes_label = (string) ( $opts['cta_classes_label'] ?? __( 'View Classes', 'noor-tms' ) );
$cta_classes_url   = (string) ( $opts['cta_classes_url'] ?? '' );
$cta_login_label   = (string) ( $opts['cta_login_label'] ?? __( 'Login Portal', 'noor-tms' ) );
$cta_support_label = (string) ( $opts['cta_support_label'] ?? __( 'Contact Support', 'noor-tms' ) );

$support_email    = (string) ( $opts['support_email'] ?? '' );
$support_phone    = (string) ( $opts['support_phone'] ?? '' );
$support_whatsapp = (string) ( $opts['support_whatsapp'] ?? '' );

if ( '' === $cta_apply_label ) {
	$cta_apply_label = __( 'Apply Admission', 'noor-tms' );
}
if ( '' === $cta_classes_label ) {
	$cta_classes_label = __( 'View Classes', 'noor-tms' );
}
if ( '' === $cta_login_label ) {
	$cta_login_label = __( 'Login Portal', 'noor-tms' );
}
if ( '' === $cta_support_label ) {
	$cta_support_label = __( 'Contact Support', 'noor-tms' );
}

if ( '' === $cta_apply_url ) {
	$cta_apply_url = '#';
}
if ( '' === $cta_classes_url ) {
	$cta_classes_url = '#noor-home-classes';
}
?>
<div class="noor-homepage">
	<section class="noor-home-hero">
		<div class="noor-home-hero__glow" aria-hidden="true"></div>
		<div class="noor-home-hero__content">
			<p class="noor-home-kicker"><?php esc_html_e( 'Madrassa Management Portal', 'noor-tms' ); ?></p>
			<h1><?php echo esc_html( $madrassa_name ); ?></h1>
			<?php if ( '' !== $tagline ) : ?>
				<p class="noor-home-tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $about_text ) : ?>
				<p class="noor-home-about"><?php echo esc_html( $about_text ); ?></p>
			<?php endif; ?>

			<div class="noor-home-cta-row">
				<a class="noor-btn noor-btn--primary" href="<?php echo esc_url( $cta_apply_url ); ?>"><?php echo esc_html( $cta_apply_label ); ?></a>
				<a class="noor-btn noor-btn--secondary" href="<?php echo esc_url( $cta_classes_url ); ?>"><?php echo esc_html( $cta_classes_label ); ?></a>
				<a class="noor-btn noor-btn--secondary" href="<?php echo esc_url( home_url( '/tms-login/' ) ); ?>"><?php echo esc_html( $cta_login_label ); ?></a>
				<button type="button" class="noor-btn noor-btn--success" data-noor-chat-open="1"><?php echo esc_html( $cta_support_label ); ?></button>
			</div>
		</div>
	</section>

	<section class="noor-home-contact-strip">
		<?php if ( '' !== $support_email ) : ?>
			<span><strong><?php esc_html_e( 'Email:', 'noor-tms' ); ?></strong> <?php echo esc_html( $support_email ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $support_phone ) : ?>
			<span><strong><?php esc_html_e( 'Phone:', 'noor-tms' ); ?></strong> <?php echo esc_html( $support_phone ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $support_whatsapp ) : ?>
			<span><strong><?php esc_html_e( 'WhatsApp:', 'noor-tms' ); ?></strong> <?php echo esc_html( $support_whatsapp ); ?></span>
		<?php endif; ?>
	</section>

	<section class="noor-home-section" id="noor-home-classes">
		<div class="noor-home-section__head">
			<h2><?php esc_html_e( 'Featured Classes', 'noor-tms' ); ?></h2>
			<p><?php esc_html_e( 'A snapshot of our current madrassa classes.', 'noor-tms' ); ?></p>
		</div>

		<?php if ( empty( $classes ) ) : ?>
			<div class="noor-empty">
				<span class="noor-empty-icon" aria-hidden="true">&#128218;</span>
				<p><?php esc_html_e( 'Class details will appear here soon.', 'noor-tms' ); ?></p>
			</div>
		<?php else : ?>
			<div class="noor-class-grid">
				<?php foreach ( $classes as $class_item ) : ?>
					<article class="noor-class-card">
						<div class="noor-class-card__header">
							<h3 class="noor-class-card__name"><?php echo esc_html( $class_item['name'] ?? '' ); ?></h3>
							<span class="noor-class-card__meta">
								<?php
								echo esc_html(
									sprintf(
										_n( '%d subject', '%d subjects', (int) ( $class_item['subject_count'] ?? 0 ), 'noor-tms' ),
										(int) ( $class_item['subject_count'] ?? 0 )
									)
								);
								?>
							</span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php include NOOR_TMS_PLUGIN_DIR . 'public/templates/chat-widget.php'; ?>
</div>
