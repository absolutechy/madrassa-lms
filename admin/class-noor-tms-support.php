<?php
/**
 * Support inbox page for admin users.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Support
 */
class Support {

	/**
	 * Render support inbox page.
	 */
	public function page_support(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$this->handle_status_action();

		$search = sanitize_text_field( wp_unslash( $_GET['noor_search'] ?? '' ) );
		$status = sanitize_key( $_GET['status_filter'] ?? '' );
		$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		$result = DatabaseHandler::get_support_requests(
			[
				'per_page' => 20,
				'page'     => $paged,
				'search'   => $search,
				'status'   => $status,
			]
		);

		$rows        = $result['rows'];
		$total       = (int) $result['total'];
		$total_pages = (int) ceil( $total / 20 );
		$statuses    = $this->statuses();
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Support Inbox', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<form method="get" action="" style="margin: 14px 0;">
				<input type="hidden" name="page" value="noor-tms-support" />
				<input type="search" name="noor_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, subject…', 'noor-tms' ); ?>" style="min-width:260px;" />
				<select name="status_filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
					<?php foreach ( $statuses as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped noor-tms-table">
				<thead>
					<tr>
						<th style="width:70px;"><?php esc_html_e( 'ID', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Requester', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Subject & Message', 'noor-tms' ); ?></th>
						<th style="width:140px;"><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th style="width:170px;"><?php esc_html_e( 'Submitted', 'noor-tms' ); ?></th>
						<th style="width:240px;"><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No support requests found.', 'noor-tms' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td>#<?php echo esc_html( $row['id'] ); ?></td>
								<td>
									<strong><?php echo esc_html( $row['requester_name'] ?: __( 'Anonymous', 'noor-tms' ) ); ?></strong><br>
									<small><?php echo esc_html( $row['requester_email'] ); ?></small><br>
									<small><?php echo esc_html( $row['requester_phone'] ); ?></small>
								</td>
								<td>
									<strong><?php echo esc_html( $row['subject'] ?: __( 'General Support Request', 'noor-tms' ) ); ?></strong>
									<p style="margin:6px 0 0; color:#50575e;"><?php echo esc_html( wp_trim_words( $row['message'], 20, '...' ) ); ?></p>
									<details style="margin-top:6px;">
										<summary><?php esc_html_e( 'View full message', 'noor-tms' ); ?></summary>
										<div style="white-space:pre-wrap; margin-top:6px;"><?php echo esc_html( $row['message'] ); ?></div>
									</details>
									<?php if ( ! empty( $row['source_url'] ) ) : ?>
										<p style="margin:8px 0 0;"><a href="<?php echo esc_url( $row['source_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Source page', 'noor-tms' ); ?></a></p>
									<?php endif; ?>
								</td>
								<td>
									<span class="noor-status-badge noor-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $this->status_label( $row['status'] ) ); ?></span>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', (string) $row['created_at'] ) ); ?></td>
								<td>
									<form method="post" action="">
										<?php wp_nonce_field( 'noor_tms_support_status', 'noor_tms_support_status_nonce' ); ?>
										<input type="hidden" name="support_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
										<select name="new_status" style="max-width:130px;">
											<?php foreach ( $statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Update', 'noor-tms' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links(
							[
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							]
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle status update form.
	 */
	private function handle_status_action(): void {
		if ( empty( $_POST['noor_tms_support_status_nonce'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'noor_tms_support_status', 'noor_tms_support_status_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$support_id = (int) ( $_POST['support_id'] ?? 0 );
		$new_status = sanitize_key( $_POST['new_status'] ?? '' );

		if ( $support_id <= 0 || ! DatabaseHandler::update_support_request_status( $support_id, $new_status ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-support', 'msg' => 'support_status_error' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		do_action( 'noor_tms_support_status_changed', $support_id, $new_status );

		wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-support', 'msg' => 'support_status_updated' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * @return array<string, string>
	 */
	private function statuses(): array {
		return [
			'open'        => __( 'Open', 'noor-tms' ),
			'in_progress' => __( 'In Progress', 'noor-tms' ),
			'resolved'    => __( 'Resolved', 'noor-tms' ),
			'closed'      => __( 'Closed', 'noor-tms' ),
		];
	}

	/**
	 * @param string $status
	 * @return string
	 */
	private function status_label( string $status ): string {
		$statuses = $this->statuses();
		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Render page notices.
	 */
	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		if ( 'support_status_updated' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Support request status updated.', 'noor-tms' ) . '</p></div>';
		}
		if ( 'support_status_error' === $msg ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Unable to update support request status.', 'noor-tms' ) . '</p></div>';
		}
	}
}
