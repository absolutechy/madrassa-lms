<?php
/**
 * Shared app shell – sidebar nav.
 *
 * Include at the TOP of every authenticated template before outputting content.
 * Close the shell with layout-close.php at the bottom.
 *
 * Required variables in scope (set by calling template):
 *   $page_title   string  Shown in the top-bar.
 *   $active_nav   string  One of: students | classes | results | settings
 *   $topbar_actions string|null  Optional HTML for add/action buttons in top-bar.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$is_manager   = current_user_can( 'noor_tms_manage' );

$nav_items = [];

if ( $is_manager ) {
	$nav_items['students'] = [
		'icon'  => '&#128101;',
		'label' => __( 'Students',  'noor-tms' ),
		'url'   => home_url( '/tms-students/' ),
	];
}

$nav_items['attendance'] = [
	'icon'  => '&#128203;',
	'label' => __( 'Attendance', 'noor-tms' ),
	'url'   => home_url( '/tms-attendance/' ),
];

$nav_items['classes'] = [
	'icon'  => '&#127979;',
	'label' => __( 'Classes',   'noor-tms' ),
	'url'   => home_url( '/tms-classes/' ),
];

$nav_items['results'] = [
	'icon'  => '&#128203;',
	'label' => __( 'Results',   'noor-tms' ),
	'url'   => home_url( '/tms-results/' ),
];

if ( $is_manager ) {
	$nav_items['teachers'] = [
		'icon'  => '&#128104;',
		'label' => __( 'Teachers',  'noor-tms' ),
		'url'   => home_url( '/tms-teachers/' ),
	];
	$nav_items['fees'] = [
		'icon'  => '&#128176;',
		'label' => __( 'Fees',      'noor-tms' ),
		'url'   => home_url( '/tms-fees/' ),
	];
	/*
	$nav_items['settings'] = [
		'icon'  => '&#9881;',
		'label' => __( 'Settings',  'noor-tms' ),
		'url'   => home_url( '/tms-settings/' ),
	];
	*/
}
?>
<div class="noor-tms-app">

	<!-- ================================================================
	     Sidebar Navigation
	     ================================================================ -->
	<aside class="noor-tms-sidebar">
		<div class="noor-tms-sidebar__brand">
			<img src="<?php echo esc_url( plugins_url( 'public/images/madrassa-logo.png', dirname( dirname( __FILE__ ) ) ) ); ?>" alt="Madrassa Logo" class="noor-sidebar-logo">
			<h2 dir="rtl">جَامِعَةُ عَبْدِ اللهِ ابْنِ عَبَّاسٍ</h2>
			<span><?php esc_html_e( 'Madrasa Portal', 'noor-tms' ); ?></span>
		</div>

		<ul class="noor-tms-nav">
			<?php foreach ( $nav_items as $key => $item ) : ?>
				<li>
					<a href="<?php echo esc_url( $item['url'] ); ?>"
					   class="<?php echo ( isset( $active_nav ) && $active_nav === $key ) ? 'is-active' : ''; ?>">
						<span class="nav-icon" aria-hidden="true"><?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="noor-tms-sidebar__footer">
			<?php echo esc_html( $current_user->display_name ); ?><br>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/tms-login/' ) ) ); ?>">
				<?php esc_html_e( 'Sign out', 'noor-tms' ); ?>
			</a>
		</div>
	</aside>

	<!-- ================================================================
	     Main Area
	     ================================================================ -->
	<div class="noor-tms-main">

		<div class="noor-tms-topbar">
			<h1><?php echo esc_html( $page_title ?? '' ); ?></h1>
			<?php if ( ! empty( $topbar_actions ) ) : ?>
				<div class="noor-tms-topbar__actions">
					<?php echo $topbar_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="noor-tms-content">
