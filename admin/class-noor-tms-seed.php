<?php
/**
 * Temporary admin page for running the academic seed once.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

defined( 'ABSPATH' ) || exit;

class Seed {
	private const NONCE_ACTION = 'noor_tms_seed_academic_data';
	private const NONCE_NAME   = 'noor_tms_seed_nonce';
	private const SCRIPT_PATH  = NOOR_TMS_PLUGIN_DIR . 'scripts/seed-academic-data.php';

	/**
	 * Render the temporary seed page.
	 */
	public function page_seed(): void {
		if ( ! noor_tms_can_manage() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$notice_type = '';
		$notice_text  = '';
		$seed_output  = '';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST[ self::NONCE_NAME ] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

			if ( ! file_exists( self::SCRIPT_PATH ) ) {
				$notice_type = 'error';
				$notice_text  = __( 'Seed script was not found.', 'noor-tms' );
			} else {
				define( 'NOOR_TMS_ALLOW_WEB_SEED', true );
				ob_start();
				require self::SCRIPT_PATH;
				$seed_output = trim( (string) ob_get_clean() );

				if ( str_contains( $seed_output, 'Seed completed successfully.' ) ) {
					$notice_type = 'success';
					$notice_text  = __( 'Seeder finished successfully.', 'noor-tms' );
				} else {
					$notice_type = 'warning';
					$notice_text  = __( 'Seeder finished with output. Review the log below.', 'noor-tms' );
				}
			}
		}

		?>
		<div class="wrap noor-tms-wrap">
			<h1><?php esc_html_e( 'Run Academic Seeder', 'noor-tms' ); ?></h1>

			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'This runs the academic seed exactly once from wp-admin. Use it only when you want to reset the sample academic data.', 'noor-tms' ); ?></p>
			</div>

			<?php if ( ! empty( $notice_text ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> inline">
					<p><?php echo esc_html( $notice_text ); ?></p>
				</div>
			<?php endif; ?>

			<div class="noor-tms-card" style="max-width:900px;">
				<h2><?php esc_html_e( 'One-time seed action', 'noor-tms' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Clicking the button will wipe the academic tables listed in the seed script and rebuild the Banin/Banaat demo data.', 'noor-tms' ); ?>
				</p>

				<form method="post">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<?php submit_button( __( 'Run Seeder Now', 'noor-tms' ), 'primary', 'noor_tms_run_seed', false ); ?>
				</form>

				<?php if ( '' !== $seed_output ) : ?>
					<h3><?php esc_html_e( 'Seeder output', 'noor-tms' ); ?></h3>
					<pre style="white-space:pre-wrap;max-height:420px;overflow:auto;background:#111;color:#eee;padding:16px;border-radius:8px;"><?php echo esc_html( $seed_output ); ?></pre>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}