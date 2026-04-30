<?php
/**
 * Front-end template: Fees Management
 *
 * Shortcode: [noor_tms_fees]
 *
 * @package Noor_TMS\Public
 */

defined( 'ABSPATH' ) || exit;

// Variables set in PublicController::sc_fees(): $action (dashboard|invoices|payments|defaulters|structures)
$page_title = __( 'Fee Management', 'noor-tms' );
$active_nav = 'fees';

include NOOR_TMS_PLUGIN_DIR . 'public/templates/layout.php';
?>

<!-- Tabs -->
<div class="noor-tab-wrap" style="margin-bottom: 24px;">
	<a href="?tms_action=dashboard" class="noor-tab-link <?php echo 'dashboard' === $action ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Dashboard', 'noor-tms' ); ?>
	</a>
	<a href="?tms_action=invoices" class="noor-tab-link <?php echo 'invoices' === $action ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Invoices', 'noor-tms' ); ?>
	</a>
	<a href="?tms_action=payments" class="noor-tab-link <?php echo 'payments' === $action ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Record Payment', 'noor-tms' ); ?>
	</a>
	<a href="?tms_action=defaulters" class="noor-tab-link <?php echo 'defaulters' === $action ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Defaulters', 'noor-tms' ); ?>
	</a>
	<a href="?tms_action=structures" class="noor-tab-link <?php echo 'structures' === $action ? 'is-active' : ''; ?>">
		<?php esc_html_e( 'Fee Structures', 'noor-tms' ); ?>
	</a>
</div>

<div class="noor-card">
	<?php if ( 'dashboard' === $action ) : 
		$stats = \Noor_TMS\Includes\DatabaseHandler::get_fee_dashboard_stats();
	?>
		<h2 style="margin-top:0;"><?php esc_html_e( 'Overview (Current Month)', 'noor-tms' ); ?></h2>
		
		<div class="noor-class-grid" style="margin-bottom: 24px;">
			<div class="noor-class-card" style="box-shadow: none; background: #fafbfd;">
				<div style="font-size: 13px; color: var(--tms-muted);"><?php esc_html_e( 'Total Collected', 'noor-tms' ); ?></div>
				<h3 style="font-size: 24px; color: var(--tms-pass); margin: 8px 0 0;"><?php echo esc_html( number_format_i18n( $stats['total_collected'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow: none; background: #fafbfd;">
				<div style="font-size: 13px; color: var(--tms-muted);"><?php esc_html_e( 'Total Invoiced Due', 'noor-tms' ); ?></div>
				<h3 style="font-size: 24px; color: var(--tms-text); margin: 8px 0 0;"><?php echo esc_html( number_format_i18n( $stats['total_due'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow: none; background: #fafbfd;">
				<div style="font-size: 13px; color: var(--tms-muted);"><?php esc_html_e( 'Paid Invoices', 'noor-tms' ); ?></div>
				<h3 style="font-size: 24px; color: var(--tms-primary); margin: 8px 0 0;"><?php echo esc_html( $stats['paid_count'] ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow: none; background: #fafbfd;">
				<div style="font-size: 13px; color: var(--tms-muted);"><?php esc_html_e( 'Defaulters Found', 'noor-tms' ); ?></div>
				<h3 style="font-size: 24px; color: var(--tms-fail); margin: 8px 0 0;"><?php echo esc_html( $stats['unpaid_count'] ); ?></h3>
			</div>
		</div>

	<?php elseif ( 'invoices' === $action ) :
		$inv_student_id = (int) ( $_GET['student_id'] ?? 0 );

		if ( $inv_student_id > 0 ) :
			// ── Detail view: all invoices for one student ────────────────────
			$inv_student     = \Noor_TMS\Includes\DatabaseHandler::get_student( $inv_student_id );
			$inv_detail_rows = \Noor_TMS\Includes\DatabaseHandler::get_invoices( [ 'student_id' => $inv_student_id ] );
			$inv_summary     = \Noor_TMS\Includes\DatabaseHandler::get_student_fee_summary( $inv_student_id );
			$inv_back_url    = add_query_arg( 'tms_action', 'invoices', home_url( '/tms-fees/' ) );
		?>

		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
			<div>
				<a href="<?php echo esc_url( $inv_back_url ); ?>" class="noor-btn noor-btn--secondary noor-btn--sm" style="margin-bottom:8px;">
					&larr; <?php esc_html_e( 'Back to Invoices', 'noor-tms' ); ?>
				</a>
				<h2 style="margin:0; font-size:20px;"><?php echo esc_html( $inv_student['name'] ?? __( 'Student', 'noor-tms' ) ); ?></h2>
				<span style="color:var(--tms-muted); font-size:13px;"><?php echo esc_html( $inv_student['class_name'] ?? '' ); ?></span>
			</div>
			<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'payments', 'student_id' => $inv_student_id ], home_url( '/tms-fees/' ) ) ); ?>"
			   class="noor-btn noor-btn--success noor-btn--sm">
				<?php esc_html_e( 'Record Payment', 'noor-tms' ); ?>
			</a>
		</div>

		<!-- Summary cards -->
		<div class="noor-class-grid" style="margin-bottom:24px;">
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Total Billed', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-text); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $inv_summary['total_due'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Total Paid', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-pass); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $inv_summary['total_paid'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-fail); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $inv_summary['balance'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-primary); margin:6px 0 0;">
					<?php
					printf(
						/* translators: 1: paid 2: total */
						esc_html__( '%1$d / %2$d paid', 'noor-tms' ),
						(int) $inv_summary['paid_count'],
						(int) $inv_summary['invoice_count']
					);
					?>
				</h3>
			</div>
		</div>

		<?php if ( empty( $inv_detail_rows ) ) : ?>
			<div class="noor-notice noor-notice--warning"><?php esc_html_e( 'No invoices found for this student.', 'noor-tms' ); ?></div>
		<?php else : ?>
			<div class="noor-table-wrap">
				<table class="noor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Month', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Amount', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Fine', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Discount', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Net Due', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $inv_detail_rows as $inv_row ) :
							$inv_net     = (float) $inv_row['net_due'];
							$inv_paid    = (float) $inv_row['total_paid'];
							$inv_balance = max( 0.0, $inv_net - $inv_paid );
						?>
						<tr>
							<td style="font-weight:500;"><?php echo esc_html( gmdate( 'F Y', strtotime( $inv_row['invoice_month'] . '-01' ) ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (float) $inv_row['amount_due'], 2 ) ); ?></td>
							<td style="text-align:right; color:var(--tms-fail);"><?php echo esc_html( number_format_i18n( (float) $inv_row['fine'], 2 ) ); ?></td>
							<td style="text-align:right; color:var(--tms-pass);"><?php echo esc_html( number_format_i18n( (float) $inv_row['discount'], 2 ) ); ?></td>
							<td style="text-align:right; font-weight:500;"><?php echo esc_html( number_format_i18n( $inv_net, 2 ) ); ?></td>
							<td style="text-align:right; color:var(--tms-pass);"><?php echo esc_html( number_format_i18n( $inv_paid, 2 ) ); ?></td>
							<td style="text-align:right; font-weight:600; color:<?php echo $inv_balance > 0 ? 'var(--tms-fail)' : 'var(--tms-pass)'; ?>;">
								<?php echo esc_html( number_format_i18n( $inv_balance, 2 ) ); ?>
							</td>
							<td style="text-align:center;">
								<?php if ( 'paid' === $inv_row['status'] ) : ?>
									<span class="noor-pct-pass"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></span>
								<?php elseif ( 'partial' === $inv_row['status'] ) : ?>
									<span class="noor-badge" style="background:#fffbeb; color:#92400e;"><?php esc_html_e( 'Partial', 'noor-tms' ); ?></span>
								<?php else : ?>
									<span class="noor-pct-fail"><?php esc_html_e( 'Unpaid', 'noor-tms' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php else :
			// ── List view: one row per student ──────────────────────────────
			$inv_search      = sanitize_text_field( $_GET['noor_search'] ?? '' );
			$inv_paged       = max( 1, (int) ( $_GET['tms_page'] ?? 1 ) );
			$inv_result      = \Noor_TMS\Includes\DatabaseHandler::get_invoices_by_student( [
				'per_page' => 15,
				'page'     => $inv_paged,
				'search'   => $inv_search,
			] );
			$inv_rows        = $inv_result['rows'];
			$inv_total       = $inv_result['total'];
			$inv_total_pages = $inv_result['total_pages'];
		?>

		<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
			<div>
				<h2 style="margin-top:0;"><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></h2>
				<p style="color:var(--tms-muted); margin-bottom:0;"><?php esc_html_e( 'One row per student — click a student to see their full invoice history.', 'noor-tms' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="noor_tms_generate_frontend_invoices">
				<?php wp_nonce_field( 'noor_tms_trigger_invoices' ); ?>
				<button type="submit" class="noor-btn noor-btn--primary"><?php esc_html_e( 'Generate Missing Invoices', 'noor-tms' ); ?></button>
			</form>
		</div>

		<?php if ( ! empty( $_GET['invoices_generated'] ) ) : ?>
			<div class="noor-notice noor-notice--success" style="margin-bottom:20px;">
				<?php esc_html_e( 'Invoices generated for all eligible students across all missing months.', 'noor-tms' ); ?>
			</div>
		<?php endif; ?>

		<!-- Search -->
		<div class="noor-filter-row" style="margin-bottom:16px;">
			<input type="search" id="noor_inv_search" value="<?php echo esc_attr( $inv_search ); ?>"
			       placeholder="<?php esc_attr_e( 'Search by student name…', 'noor-tms' ); ?>" />
			<button type="button" class="noor-btn noor-btn--secondary"
			        onclick="noorApplyFeeFilter('invoices','noor_inv_search');">
				<?php esc_html_e( 'Search', 'noor-tms' ); ?>
			</button>
		</div>

		<?php if ( empty( $inv_rows ) ) : ?>
			<div class="noor-notice noor-notice--warning"><?php esc_html_e( 'No invoices found.', 'noor-tms' ); ?></div>
		<?php else : ?>
			<div class="noor-table-wrap">
				<table class="noor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Total Billed', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Total Paid', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Overview', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $inv_rows as $inv_row ) :
							$inv_detail_link = add_query_arg( [ 'tms_action' => 'invoices', 'student_id' => $inv_row['student_id'] ], home_url( '/tms-fees/' ) );
							$inv_row_balance = (float) $inv_row['total_billed'] - (float) $inv_row['total_paid'];
						?>
						<tr style="cursor:pointer;" onclick="window.location='<?php echo esc_url( $inv_detail_link ); ?>'">
							<td>
								<a href="<?php echo esc_url( $inv_detail_link ); ?>"
								   style="font-weight:600; color:var(--tms-primary); text-decoration:none;">
									<?php echo esc_html( $inv_row['student_name'] ); ?>
								</a>
							</td>
							<td style="color:var(--tms-muted);"><?php echo esc_html( $inv_row['class_name'] ?: '—' ); ?></td>
							<td style="text-align:center;">
								<span style="font-size:13px; color:var(--tms-muted);">
									<?php
									printf(
										/* translators: 1: paid count 2: total count */
										esc_html__( '%1$d paid / %2$d total', 'noor-tms' ),
										(int) $inv_row['paid_count'],
										(int) $inv_row['invoice_count']
									);
									?>
								</span>
								<?php if ( (int) $inv_row['unpaid_count'] > 0 ) : ?>
									<span class="noor-pct-fail" style="margin-left:4px; font-size:11px;">
										<?php echo esc_html( $inv_row['unpaid_count'] ); ?> unpaid
									</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right; font-weight:500;"><?php echo esc_html( number_format_i18n( (float) $inv_row['total_billed'], 2 ) ); ?></td>
							<td style="text-align:right; color:var(--tms-pass);"><?php echo esc_html( number_format_i18n( (float) $inv_row['total_paid'], 2 ) ); ?></td>
							<td style="text-align:right; font-weight:600; color:<?php echo $inv_row_balance > 0 ? 'var(--tms-fail)' : 'var(--tms-pass)'; ?>;">
								<?php echo esc_html( number_format_i18n( max( 0.0, $inv_row_balance ), 2 ) ); ?>
							</td>
							<td style="text-align:center;">
								<?php if ( (int) $inv_row['unpaid_count'] === 0 && (int) $inv_row['partial_count'] === 0 ) : ?>
									<span class="noor-pct-pass"><?php esc_html_e( 'All Paid', 'noor-tms' ); ?></span>
								<?php elseif ( (int) $inv_row['partial_count'] > 0 ) : ?>
									<span class="noor-badge" style="background:#fffbeb; color:#92400e;"><?php esc_html_e( 'Partial', 'noor-tms' ); ?></span>
								<?php else : ?>
									<span class="noor-pct-fail"><?php esc_html_e( 'Unpaid', 'noor-tms' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<?php if ( $inv_total_pages > 1 ) : ?>
				<div class="noor-pagination-wrap">
					<div class="noor-pagination-info">
						<?php
						$inv_start = ( $inv_paged - 1 ) * 15 + 1;
						$inv_end   = min( $inv_paged * 15, $inv_total );
						printf(
							esc_html__( 'Showing %1$d–%2$d of %3$d students', 'noor-tms' ),
							intval( $inv_start ), intval( $inv_end ), intval( $inv_total )
						);
						?>
					</div>
					<div class="noor-pagination">
						<?php
						echo paginate_links( [
							'base'      => add_query_arg( [ 'tms_page' => '%#%', 'noor_search' => $inv_search ?: false ], home_url( '/tms-fees/?tms_action=invoices' ) ),
							'format'    => '',
							'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'noor-tms' ),
							'next_text' => esc_html__( 'Next', 'noor-tms' ) . ' &raquo;',
							'total'     => $inv_total_pages,
							'current'   => $inv_paged,
							'type'      => 'plain',
						] );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php endif; // empty rows ?>
		<?php endif; // list vs detail ?>

	<?php elseif ( 'payments' === $action ) : 
		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$unpaid_invoices = [];
		if ( $student_id > 0 ) {
			$unpaid_invoices = \Noor_TMS\Includes\DatabaseHandler::get_unpaid_invoices_by_student( $student_id );
		}
	?>
		<h2 style="margin-top:0;"><?php esc_html_e( 'Record Payment', 'noor-tms' ); ?></h2>
		
		<?php if ( ! empty( $_GET['success'] ) ) : ?>
			<div class="noor-notice noor-notice--success">
				<?php esc_html_e( 'Payment recorded successfully.', 'noor-tms' ); ?>
			</div>
		<?php endif; ?>

		<form method="get" action="" class="noor-filter-row" style="background: #fafbfd; padding: 16px; border-radius: 8px; border: 1px solid var(--tms-border); margin-bottom: 24px;">
			<input type="hidden" name="tms_action" value="payments">
			<div style="flex-grow: 1; max-width: 300px;">
				<label for="student_id" style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;"><?php esc_html_e( 'Select Student', 'noor-tms' ); ?></label>
				<select name="student_id" id="student_id" style="width: 100%; border: 1.5px solid var(--tms-border); border-radius: 8px; padding: 9px 14px;">
					<option value="0"><?php esc_html_e( '&mdash; Select a Student &mdash;', 'noor-tms' ); ?></option>
					<?php 
					$dropdown_students = \Noor_TMS\Includes\DatabaseHandler::get_students_dropdown();
					foreach ( $dropdown_students as $st ) : 
					?>
						<option value="<?php echo esc_attr( $st['id'] ); ?>" <?php selected( $student_id, $st['id'] ); ?>>
							<?php echo esc_html( $st['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div style="padding-top: 24px;">
				<button type="submit" class="noor-btn noor-btn--primary">
					<?php esc_html_e( 'Load Invoices', 'noor-tms' ); ?>
				</button>
			</div>
		</form>

		<?php if ( $student_id > 0 ) : ?>
			<?php if ( empty( $unpaid_invoices ) ) : ?>
				<div class="noor-notice noor-notice--success" style="background: #e0f2fe; color: #0369a1; border-color: #0369a1;">
					<?php esc_html_e( 'This student has no unpaid invoices. All dues are cleared.', 'noor-tms' ); ?>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="noor_tms_record_payment">
					<input type="hidden" name="student_id" value="<?php echo esc_attr( $student_id ); ?>">
					<?php wp_nonce_field( 'noor_tms_record_payment', 'noor_tms_payment_nonce' ); ?>

					<div class="noor-form-row">
						<div class="noor-form-group">
							<label for="invoice_id"><?php esc_html_e( 'Select Invoice', 'noor-tms' ); ?></label>
							<select name="invoice_id" id="invoice_id" required>
								<option value=""><?php esc_html_e( '&mdash; Select an Invoice to Pay &mdash;', 'noor-tms' ); ?></option>
								<?php foreach ( $unpaid_invoices as $inv ) : 
									$bal = (float) $inv['net_due'] - (float) $inv['total_paid'];
									$lbl = gmdate( 'F Y', strtotime( $inv['invoice_month'] . '-01' ) ) . ' — Balance: ' . number_format_i18n( $bal, 2 );
								?>
									<option value="<?php echo esc_attr( $inv['id'] ); ?>">
										<?php echo esc_html( $lbl ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="noor-form-group">
							<label for="paid_amount"><?php esc_html_e( 'Payment Amount', 'noor-tms' ); ?></label>
							<input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" required>
						</div>

						<div class="noor-form-group">
							<label for="payment_method"><?php esc_html_e( 'Payment Method', 'noor-tms' ); ?></label>
							<select name="payment_method" id="payment_method" required>
								<option value="cash"><?php esc_html_e( 'Cash', 'noor-tms' ); ?></option>
								<option value="bank"><?php esc_html_e( 'Bank Transfer', 'noor-tms' ); ?></option>
								<option value="cheque"><?php esc_html_e( 'Cheque', 'noor-tms' ); ?></option>
							</select>
						</div>

						<div class="noor-form-group">
							<label for="payment_date"><?php esc_html_e( 'Date', 'noor-tms' ); ?></label>
							<input type="date" name="payment_date" id="payment_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
						</div>
					</div>

					<div class="noor-form-group">
						<label for="remarks"><?php esc_html_e( 'Remarks / Notes', 'noor-tms' ); ?></label>
						<textarea name="remarks" id="remarks" rows="2"></textarea>
					</div>

					<div class="noor-form-actions" style="margin-top: 0; border: none; padding-top: 0;">
						<button type="submit" class="noor-btn noor-btn--success">
							<?php esc_html_e( 'Save Payment', 'noor-tms' ); ?>
						</button>
					</div>
				</form>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( 'defaulters' === $action ) :
		$def_student_id = (int) ( $_GET['student_id'] ?? 0 );

		if ( $def_student_id > 0 ) :
			// ── Detail view: unpaid invoices for one student ─────────────────
			$def_student      = \Noor_TMS\Includes\DatabaseHandler::get_student( $def_student_id );
			$def_detail_rows  = \Noor_TMS\Includes\DatabaseHandler::get_unpaid_invoices_by_student( $def_student_id );
			$def_summary      = \Noor_TMS\Includes\DatabaseHandler::get_student_fee_summary( $def_student_id );
			$def_back_url     = add_query_arg( 'tms_action', 'defaulters', home_url( '/tms-fees/' ) );
			$pay_url_base     = add_query_arg( [ 'tms_action' => 'payments', 'student_id' => $def_student_id ], home_url( '/tms-fees/' ) );
		?>

		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
			<div>
				<a href="<?php echo esc_url( $def_back_url ); ?>" class="noor-btn noor-btn--secondary noor-btn--sm" style="margin-bottom:8px;">
					&larr; <?php esc_html_e( 'Back to Defaulters', 'noor-tms' ); ?>
				</a>
				<h2 style="margin:0; font-size:20px;"><?php echo esc_html( $def_student['name'] ?? __( 'Student', 'noor-tms' ) ); ?></h2>
				<span style="color:var(--tms-muted); font-size:13px;">
					<?php echo esc_html( $def_student['class_name'] ?? '' ); ?>
					<?php if ( ! empty( $def_student['parent_phone'] ) ) : ?>
						&mdash; <?php echo esc_html( $def_student['parent_phone'] ); ?>
					<?php endif; ?>
				</span>
			</div>
			<a href="<?php echo esc_url( $pay_url_base ); ?>" class="noor-btn noor-btn--success noor-btn--sm">
				<?php esc_html_e( 'Record Payment', 'noor-tms' ); ?>
			</a>
		</div>

		<!-- Outstanding summary -->
		<div class="noor-class-grid" style="margin-bottom:24px;">
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Total Outstanding', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-fail); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $def_summary['balance'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Unpaid Months', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-fail); margin:6px 0 0;"><?php echo esc_html( $def_summary['unpaid_count'] + $def_summary['partial_count'] ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Total Paid So Far', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-pass); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $def_summary['total_paid'], 2 ) ); ?></h3>
			</div>
			<div class="noor-class-card" style="box-shadow:none; background:#fafbfd;">
				<div style="font-size:12px; color:var(--tms-muted);"><?php esc_html_e( 'Total Billed', 'noor-tms' ); ?></div>
				<h3 style="font-size:20px; color:var(--tms-text); margin:6px 0 0;"><?php echo esc_html( number_format_i18n( $def_summary['total_due'], 2 ) ); ?></h3>
			</div>
		</div>

		<?php if ( empty( $def_detail_rows ) ) : ?>
			<div class="noor-notice noor-notice--success"><?php esc_html_e( 'All dues cleared — no outstanding invoices.', 'noor-tms' ); ?></div>
		<?php else : ?>
			<div class="noor-table-wrap">
				<table class="noor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Month', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Net Due', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Action', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $def_detail_rows as $def_row ) :
							$def_net     = (float) $def_row['net_due'];
							$def_paid    = (float) $def_row['total_paid'];
							$def_balance = max( 0.0, $def_net - $def_paid );
						?>
						<tr>
							<td style="font-weight:500;"><?php echo esc_html( gmdate( 'F Y', strtotime( $def_row['invoice_month'] . '-01' ) ) ); ?></td>
							<td style="text-align:right; font-weight:500;"><?php echo esc_html( number_format_i18n( $def_net, 2 ) ); ?></td>
							<td style="text-align:right; color:var(--tms-pass);"><?php echo esc_html( number_format_i18n( $def_paid, 2 ) ); ?></td>
							<td style="text-align:right; font-weight:600; color:var(--tms-fail);"><?php echo esc_html( number_format_i18n( $def_balance, 2 ) ); ?></td>
							<td style="text-align:center;">
								<?php if ( 'partial' === $def_row['status'] ) : ?>
									<span class="noor-badge" style="background:#fffbeb; color:#92400e;"><?php esc_html_e( 'Partial', 'noor-tms' ); ?></span>
								<?php else : ?>
									<span class="noor-pct-fail"><?php esc_html_e( 'Unpaid', 'noor-tms' ); ?></span>
								<?php endif; ?>
							</td>
							<td style="text-align:center;">
								<a href="<?php echo esc_url( $pay_url_base ); ?>" class="noor-btn noor-btn--primary noor-btn--sm">
									<?php esc_html_e( 'Pay', 'noor-tms' ); ?>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php else :
			// ── List view: one row per student ──────────────────────────────
			$def_search      = sanitize_text_field( $_GET['noor_search'] ?? '' );
			$def_paged       = max( 1, (int) ( $_GET['tms_page'] ?? 1 ) );
			$def_result      = \Noor_TMS\Includes\DatabaseHandler::get_defaulters_by_student( [
				'per_page' => 15,
				'page'     => $def_paged,
				'search'   => $def_search,
			] );
			$def_rows        = $def_result['rows'];
			$def_total       = $def_result['total'];
			$def_total_pages = $def_result['total_pages'];
		?>

		<h2 style="margin-top:0;"><?php esc_html_e( 'Defaulters List', 'noor-tms' ); ?></h2>
		<p style="color:var(--tms-muted); margin-bottom:20px;"><?php esc_html_e( 'One row per student — click a student to see their outstanding invoice breakdown.', 'noor-tms' ); ?></p>

		<!-- Search -->
		<div class="noor-filter-row" style="margin-bottom:16px;">
			<input type="search" id="noor_def_search" value="<?php echo esc_attr( $def_search ); ?>"
			       placeholder="<?php esc_attr_e( 'Search by student name…', 'noor-tms' ); ?>" />
			<button type="button" class="noor-btn noor-btn--secondary"
			        onclick="noorApplyFeeFilter('defaulters','noor_def_search');">
				<?php esc_html_e( 'Search', 'noor-tms' ); ?>
			</button>
		</div>

		<?php if ( empty( $def_rows ) ) : ?>
			<div class="noor-notice noor-notice--success"><?php esc_html_e( 'Great news! No defaulters found.', 'noor-tms' ); ?></div>
		<?php else : ?>
			<div class="noor-table-wrap">
				<table class="noor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Contact', 'noor-tms' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Unpaid Months', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Total Outstanding', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Since', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $def_rows as $def_row ) :
							$def_detail_link = add_query_arg( [ 'tms_action' => 'defaulters', 'student_id' => $def_row['student_id'] ], home_url( '/tms-fees/' ) );
							$def_row_balance = (float) $def_row['total_due'] - (float) $def_row['total_paid'];
						?>
						<tr style="cursor:pointer;" onclick="window.location='<?php echo esc_url( $def_detail_link ); ?>'">
							<td>
								<a href="<?php echo esc_url( $def_detail_link ); ?>"
								   style="font-weight:600; color:var(--tms-fail); text-decoration:none;">
									<?php echo esc_html( $def_row['student_name'] ); ?>
								</a>
							</td>
							<td style="color:var(--tms-muted);"><?php echo esc_html( $def_row['class_name'] ?: '—' ); ?></td>
							<td style="color:var(--tms-muted);"><?php echo esc_html( $def_row['parent_phone'] ?: '—' ); ?></td>
							<td style="text-align:center;">
								<span class="noor-pct-fail" style="font-size:13px;">
									<?php echo esc_html( $def_row['unpaid_months'] ); ?>
								</span>
							</td>
							<td style="text-align:right; font-weight:600; color:var(--tms-fail);">
								<?php echo esc_html( number_format_i18n( max( 0.0, $def_row_balance ), 2 ) ); ?>
							</td>
							<td style="color:var(--tms-muted); font-size:13px;">
								<?php echo esc_html( gmdate( 'M Y', strtotime( $def_row['oldest_unpaid_month'] . '-01' ) ) ); ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<?php if ( $def_total_pages > 1 ) : ?>
				<div class="noor-pagination-wrap">
					<div class="noor-pagination-info">
						<?php
						$def_start = ( $def_paged - 1 ) * 15 + 1;
						$def_end   = min( $def_paged * 15, $def_total );
						printf(
							esc_html__( 'Showing %1$d–%2$d of %3$d students', 'noor-tms' ),
							intval( $def_start ), intval( $def_end ), intval( $def_total )
						);
						?>
					</div>
					<div class="noor-pagination">
						<?php
						echo paginate_links( [
							'base'      => add_query_arg( [ 'tms_page' => '%#%', 'noor_search' => $def_search ?: false ], home_url( '/tms-fees/?tms_action=defaulters' ) ),
							'format'    => '',
							'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'noor-tms' ),
							'next_text' => esc_html__( 'Next', 'noor-tms' ) . ' &raquo;',
							'total'     => $def_total_pages,
							'current'   => $def_paged,
							'type'      => 'plain',
						] );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php endif; // empty rows ?>
		<?php endif; // list vs detail ?>

	<?php elseif ( 'structures' === $action ) : ?>
			<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
				<div>
					<h2 style="margin-top:0;"><?php esc_html_e( 'Fee Structures', 'noor-tms' ); ?></h2>
					<p style="color: var(--tms-muted); margin-bottom: 0;"><?php esc_html_e( 'Overview of all defined fees. The system dynamically maps these structures during invoice generation.', 'noor-tms' ); ?></p>
				</div>
				
				<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
					<input type="hidden" name="action" value="noor_tms_generate_frontend_invoices">
					<?php wp_nonce_field( 'noor_tms_trigger_invoices' ); ?>
					<button type="submit" class="noor-btn" style="background-color: var(--tms-link); color: #c36;"><?php esc_html_e( 'Generate Missing Invoices Now', 'noor-tms' ); ?></button>
				</form>
			</div>

			<?php if ( ! empty( $_GET['invoices_generated'] ) ) : ?>
				<div class="noor-notice noor-notice--success" style="margin-bottom: 20px;">
					<?php esc_html_e( 'Invoices successfully generated and assigned to all eligible active students.', 'noor-tms' ); ?>
				</div>
			<?php endif; ?>

		<?php if ( ! empty( $_GET['added'] ) ) : ?>
			<div class="noor-notice noor-notice--success">
				<?php esc_html_e( 'Fee structure updated successfully.', 'noor-tms' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
			<div class="noor-notice noor-notice--success" style="border-color: #dc3545; background: #fef2f2; color: #991b1b;">
				<?php esc_html_e( 'Fee structure deleted successfully.', 'noor-tms' ); ?>
			</div>
		<?php endif; ?>

		<!-- Add / Edit Fee Structure Form -->
		<?php 
		$editing_id = (int) ( $_GET['edit_structure'] ?? 0 );
		$edit_data = null;
		if ( $editing_id > 0 ) {
			$edit_data = \Noor_TMS\Includes\DatabaseHandler::get_fee_structure( $editing_id );
		}
		?>
		<div style="background: #fafbfd; padding: 20px; border-radius: 8px; border: 1px solid var(--tms-border); margin-bottom: 30px;">
			<h3 style="margin: 0 0 16px; font-size: 15px; color: var(--tms-text);">
				<?php echo $edit_data ? esc_html__( 'Edit Fee Structure', 'noor-tms' ) : esc_html__( 'Add New Fee Structure', 'noor-tms' ); ?>
			</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="noor_tms_create_fee_structure">
				<input type="hidden" name="structure_id" value="<?php echo esc_attr( $editing_id ); ?>">
				<?php wp_nonce_field( 'noor_tms_create_fee_structure', 'noor_tms_create_fee_nonce' ); ?>
				
				<div class="noor-form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
					<div class="noor-form-group">
						<label for="fee_title"><?php esc_html_e( 'Fee Title', 'noor-tms' ); ?> <span class="required">*</span></label>
						<input type="text" name="fee_title" id="fee_title" required placeholder="e.g. Monthly Tuition" value="<?php echo esc_attr( $edit_data['fee_title'] ?? '' ); ?>">
					</div>
					<div class="noor-form-group">
						<label for="class_id"><?php esc_html_e( 'Apply to Class', 'noor-tms' ); ?></label>
						<select name="class_id" id="class_id">
							<option value="0"><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
							<?php 
							$dropdown_classes = \Noor_TMS\Includes\DatabaseHandler::get_classes_dropdown();
							$current_class = $edit_data['class_id'] ?? 0;
							foreach ( $dropdown_classes as $cls ) {
								echo '<option value="' . esc_attr( $cls['id'] ) . '" ' . selected( $current_class, $cls['id'], false ) . '>' . esc_html( $cls['name'] ) . '</option>';
							}
							?>
						</select>
					</div>
					<div class="noor-form-group">
						<label for="fee_amount"><?php esc_html_e( 'Amount', 'noor-tms' ); ?> <span class="required">*</span></label>
						<input type="number" step="0.01" min="0" name="fee_amount" id="fee_amount" required value="<?php echo esc_attr( $edit_data['amount'] ?? '' ); ?>">
					</div>
					<div class="noor-form-group">
						<label for="fee_frequency"><?php esc_html_e( 'Frequency', 'noor-tms' ); ?></label>
						<select name="fee_frequency" id="fee_frequency">
							<?php $freq = $edit_data['frequency'] ?? 'monthly'; ?>
							<option value="monthly" <?php selected( $freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'noor-tms' ); ?></option>
							<option value="yearly" <?php selected( $freq, 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'noor-tms' ); ?></option>
							<option value="one-time" <?php selected( $freq, 'one-time' ); ?>><?php esc_html_e( 'One-Time', 'noor-tms' ); ?></option>
						</select>
					</div>
					<div class="noor-form-group">
						<label for="effective_from"><?php esc_html_e( 'Effective From', 'noor-tms' ); ?> <span class="required">*</span></label>
						<input type="month" name="effective_from" id="effective_from" required value="<?php echo esc_attr( $edit_data['effective_from'] ?? current_time( 'Y-m' ) ); ?>">
					</div>
				</div>
				<div class="noor-form-actions" style="margin-top: 4px; padding-top: 0; border: none;">
					<button type="submit" class="noor-btn noor-btn--success">
						<?php echo $edit_data ? esc_html__( 'Update Fee Structure', 'noor-tms' ) : esc_html__( 'Create Fee Structure', 'noor-tms' ); ?>
					</button>
					<?php if ( $edit_data ) : ?>
						<a href="?tms_action=structures" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'Cancel Editing', 'noor-tms' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<?php
		$structures = \Noor_TMS\Includes\DatabaseHandler::get_fee_structures();
		if ( empty( $structures ) ) :
			?>
			<div class="noor-notice noor-notice--warning">
				<?php esc_html_e( 'No fee structures found.', 'noor-tms' ); ?>
			</div>
		<?php else : ?>
			<div class="noor-table-wrap">
				<table class="noor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Title', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
							<th style="text-align:right;"><?php esc_html_e( 'Amount', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'noor-tms' ); ?></th>
							<th style="text-align:right; width:120px;"><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $structures as $structure ) : 
							$delete_url = wp_nonce_url( admin_url('admin-post.php?action=noor_tms_delete_fee_structure&structure_id=' . $structure['id']), 'delete_fee_structure_' . $structure['id'] );
						?>
							<tr>
								<td>#<?php echo esc_attr( $structure['id'] ); ?></td>
								<td><div style="font-weight: 600; color: var(--tms-text);"><?php echo esc_html( $structure['fee_title'] ); ?></div></td>
								<td style="color: var(--tms-muted);"><?php echo esc_html( $structure['class_name'] ?: 'All Classes' ); ?></td>
								<td style="text-align:right; font-weight: 500; color: var(--tms-text);">
									<?php echo esc_html( number_format_i18n( (float) $structure['amount'], 2 ) ); ?>
								</td>
								<td style="color: var(--tms-muted); text-transform: capitalize;"><?php echo esc_html( $structure['frequency'] ); ?></td>
								<td class="noor-actions" style="text-align:right;">
									<a href="?tms_action=structures&edit_structure=<?php echo esc_attr( $structure['id'] ); ?>" class="noor-btn noor-btn--secondary noor-btn--sm">Edit</a>
									<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this fee structure?', 'noor-tms' ); ?>');" class="noor-btn noor-btn--danger noor-btn--sm">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>

<script>
function noorApplyFeeFilter( action, inputId ) {
	var val = document.getElementById( inputId );
	var url = '<?php echo esc_js( home_url( '/tms-fees/' ) ); ?>?tms_action=' + action;
	if ( val && val.value.trim() ) {
		url += '&noor_search=' + encodeURIComponent( val.value.trim() );
	}
	window.location.href = url;
}
document.addEventListener( 'DOMContentLoaded', function () {
	[ 'noor_inv_search', 'noor_def_search' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.addEventListener( 'keypress', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					var action = id === 'noor_inv_search' ? 'invoices' : 'defaulters';
					noorApplyFeeFilter( action, id );
				}
			} );
		}
	} );
} );
</script>

<?php
include NOOR_TMS_PLUGIN_DIR . 'public/templates/layout-close.php';