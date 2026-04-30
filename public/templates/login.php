<?php
/**
 * Front-end login template.
 * Rendered by [noor_tms_login] shortcode.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noor-tms-login-wrap">
	<div class="noor-login-card">

		<div class="noor-login-card__logo">
			<img src="<?php echo esc_url( plugins_url( 'public/images/madrassa-logo.png', dirname( dirname( __FILE__ ) ) ) ); ?>" alt="Madrassa Logo" class="noor-logo-image">
			<div class="noor-logo-text" dir="rtl">
				<h1>جَامِعَةُ عَبْدِ اللهِ ابْنِ عَبَّاسٍ</h1>
				<p><?php esc_html_e( 'Madrasa Management System', 'noor-tms' ); ?></p>
			</div>
		</div>

		<h2><?php esc_html_e( 'Staff Login', 'noor-tms' ); ?></h2>

		<?php
		// Show error notice when WP redirects back with ?login=failed
		$login_param = sanitize_key( $_GET['login'] ?? '' );
		if ( 'failed' === $login_param ) {
			echo '<p class="noor-login-error">'
				. esc_html__( 'Invalid username or password. Please try again.', 'noor-tms' )
				. '</p>';
		}

		wp_login_form( [
			'redirect'       => home_url( '/tms-students/' ),
			'label_username' => __( 'Username', 'noor-tms' ),
			'label_password' => __( 'Password', 'noor-tms' ),
			'label_remember' => __( 'Remember me', 'noor-tms' ),
			'label_log_in'   => __( 'Sign In', 'noor-tms' ),
			'remember'       => true,
		] );
		?>

	</div>
</div>
