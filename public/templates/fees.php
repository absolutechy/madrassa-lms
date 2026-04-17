<?php
/**
 * Front-end Fee Management portal template.
 * Rendered by [noor_tms_fees] shortcode (managers only).
 *
 * Variables in scope (set by ShortcodeHandler::sc_fees):
 *   $tab             string   Active tab slug.
 *   $classes         array    Classes dropdown data.
 *   $cur_month       string   Current YYYY-MM.
 *   --- invoices tab ---
 *   $invoices        array    Invoice rows.
 *   $inv_month       string
 *   $inv_class       int
 *   $inv_status      string
 *   $inv_total_pages int
 *   $inv_paged       int
 *   --- defaulters tab ---
 *   $def_month       string
 *   $defaulters      array
 *   --- report tab ---
 *   $group_by        string
 *   $report_month    string
 *   $report_class    int
 *   $report_rows     array
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

$page_title     = __( 'Fee Management', 'noor-tms' );
$active_nav     = 'fees';
$topbar_actions = null;

include __DIR__ . '/layout.php';

$fees_url = home_url( '/tms-fees/' );

// Tab links helper.
$tab_link = static function ( string $slug, string $label ) use ( $fees_url, $tab ): void {
	printf(
		'<a href="%s" class="noor-tab-link%s">%s</a>',
		esc_url( add_query_arg( 'tab', $slug, $fees_url ) ),
		$slug === $tab ? ' is-active' : '',
		esc_html( $label )
	);
};
?>

<!-- Tab navigation -->
<nav class="noor-tms-tabs" style="display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin-bottom:20px;">
	<?php
	$tab_link( 'invoices',   __( 'Invoices',    'noor-tms' ) );
	$tab_link( 'payment',    __( 'Payment',      'noor-tms' ) );
	$tab_link( 'defaulters', __( 'Defaulters',   'noor-tms' ) );
	$tab_link( 'report',     __( 'Report',        'noor-tms' ) );
	?>
</nav>

<?php
/* =========================================================================
   TAB: INVOICES
   ========================================================================= */
if ( 'invoices' === $tab ) :
	$academic_year = \Noor_TMS\Includes\Repositories\FeeRepository::current_academic_year();
?>
	<div class="noor-filter-row" style="margin-bottom:16px;">
		<form method="get" action="<?php echo esc_url( $fees_url ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
			<input type="hidden" name="tab" value="invoices" />
			<input type="month" name="invoice_month"
			       value="<?php echo esc_attr( $inv_month ?? $cur_month ); ?>"
			       style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;" />

			<select name="class_id" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
				<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
				<?php foreach ( $classes as $cls ) : ?>
					<option value="<?php echo esc_attr( $cls['id'] ); ?>"
						<?php selected( (int) ( $inv_class ?? 0 ), (int) $cls['id'] ); ?>>
						<?php echo esc_html( $cls['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="status" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
				<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
				<?php
				foreach ( [ 'unpaid' => __( 'Unpaid', 'noor-tms' ), 'partial' => __( 'Partial', 'noor-tms' ), 'paid' => __( 'Paid', 'noor-tms' ), 'voided' => __( 'Voided', 'noor-tms' ) ] as $val => $lbl ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $inv_status ?? '', $val, false ), esc_html( $lbl ) );
				}
				?>
			</select>

			<button type="submit" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
		</form>

		<div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
			<button type="button" id="noor-fee-generate-btn" class="noor-btn noor-btn--primary"
			        data-month="<?php echo esc_attr( $cur_month ); ?>"
			        data-year="<?php echo esc_attr( $academic_year ); ?>">
				&#128196; <?php printf( esc_html__( 'Generate %s Invoices', 'noor-tms' ), esc_html( $cur_month ) ); ?>
			</button>
			<span id="noor-fee-generate-feedback" class="noor-notice" style="display:none;"></span>
		</div>
	</div>

	<?php if ( empty( $invoices ?? [] ) ) : ?>
		<p class="noor-empty-msg"><?php esc_html_e( 'No invoices found for the selected filters.', 'noor-tms' ); ?></p>
	<?php else : ?>
		<div class="noor-table-wrap">
			<table class="noor-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Month', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Net Due', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $invoices as $inv ) :
						$net_due = max( 0, (float) $inv['amount_due'] + (float) $inv['fine'] - (float) $inv['discount'] );
						$balance = max( 0, $net_due - (float) $inv['total_paid'] );
					?>
						<tr id="noor-fee-inv-row-<?php echo esc_attr( $inv['id'] ); ?>">
							<td><?php echo esc_html( $inv['student_name'] ); ?></td>
							<td><?php echo esc_html( $inv['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $inv['invoice_month'] ); ?></td>
							<td class="noor-col-num"><?php echo esc_html( number_format( $net_due, 2 ) ); ?></td>
							<td class="noor-col-num"><?php echo esc_html( number_format( (float) $inv['total_paid'], 2 ) ); ?></td>
							<td class="noor-col-num"><strong><?php echo esc_html( number_format( $balance, 2 ) ); ?></strong></td>
							<td>
								<span class="noor-badge noor-fee-status-<?php echo esc_attr( $inv['status'] ); ?>">
									<?php echo esc_html( ucfirst( $inv['status'] ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! in_array( $inv['status'], [ 'voided', 'paid' ], true ) ) : ?>
									<button type="button"
									        class="noor-btn noor-btn--danger noor-btn--sm noor-fee-void-invoice"
									        data-id="<?php echo esc_attr( $inv['id'] ); ?>">
										<?php esc_html_e( 'Void', 'noor-tms' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( ( $inv_total_pages ?? 1 ) > 1 ) : ?>
			<div class="noor-pagination">
				<?php
				echo paginate_links( [
					'base'      => add_query_arg( 'paged', '%#%', add_query_arg( 'tab', 'invoices', $fees_url ) ),
					'format'    => '',
					'prev_text' => '&laquo; ' . esc_html__( 'Prev', 'noor-tms' ),
					'next_text' => esc_html__( 'Next', 'noor-tms' ) . ' &raquo;',
					'total'     => $inv_total_pages ?? 1,
					'current'   => $inv_paged ?? 1,
				] );
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

<?php
/* =========================================================================
   TAB: PAYMENT ENTRY
   ========================================================================= */
elseif ( 'payment' === $tab ) :
?>
	<!-- Step 1: Search student -->
	<div class="noor-card" style="margin-bottom:16px;">
		<h3 style="margin-top:0;"><?php esc_html_e( 'Search Student', 'noor-tms' ); ?></h3>
		<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="text" id="noor-fee-student-search" class="noor-input" style="min-width:260px;"
			       placeholder="<?php esc_attr_e( 'Type student name…', 'noor-tms' ); ?>" />
			<button type="button" id="noor-fee-search-btn" class="noor-btn noor-btn--primary">
				&#128270; <?php esc_html_e( 'Search', 'noor-tms' ); ?>
			</button>
			<span id="noor-fee-search-spinner"></span>
		</div>
		<div id="noor-fee-student-results" style="margin-top:12px;"></div>
	</div>

	<!-- Step 2: Invoice list (populated via AJAX) -->
	<div id="noor-fee-invoice-section" class="noor-card" style="display:none;margin-bottom:16px;">
		<h3 id="noor-fee-invoice-title" style="margin-top:0;"><?php esc_html_e( 'Outstanding Invoices', 'noor-tms' ); ?></h3>
		<div id="noor-fee-invoice-list"></div>
	</div>

	<!-- Step 3: Payment form (populated when invoice selected) -->
	<div id="noor-fee-payment-section" class="noor-card" style="display:none;">
		<h3 style="margin-top:0;"><?php esc_html_e( 'Record Payment', 'noor-tms' ); ?></h3>
		<form id="noor-fee-payment-form">
			<input type="hidden" id="noor-fee-invoice-id" name="invoice_id" value="" />

			<div class="noor-form-row">
				<label for="noor-fee-amount"><?php esc_html_e( 'Amount Paid', 'noor-tms' ); ?> <span class="noor-required">*</span></label>
				<input type="number" id="noor-fee-amount" name="paid_amount"
				       min="0.01" step="0.01" required class="noor-input" style="max-width:160px;" />
				<small id="noor-fee-balance-note" style="color:#64748b;margin-left:8px;"></small>
			</div>

			<div class="noor-form-row">
				<label for="noor-fee-pay-date"><?php esc_html_e( 'Payment Date', 'noor-tms' ); ?></label>
				<input type="date" id="noor-fee-pay-date" name="payment_date"
				       value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
				       class="noor-input" style="max-width:180px;" />
			</div>

			<div class="noor-form-row">
				<label for="noor-fee-method"><?php esc_html_e( 'Payment Method', 'noor-tms' ); ?></label>
				<select id="noor-fee-method" name="payment_method" class="noor-input" style="max-width:180px;">
					<option value="cash"><?php esc_html_e( 'Cash', 'noor-tms' ); ?></option>
					<option value="bank"><?php esc_html_e( 'Bank Transfer', 'noor-tms' ); ?></option>
					<option value="cheque"><?php esc_html_e( 'Cheque', 'noor-tms' ); ?></option>
				</select>
			</div>

			<div class="noor-form-row">
				<label for="noor-fee-remarks"><?php esc_html_e( 'Remarks', 'noor-tms' ); ?></label>
				<textarea id="noor-fee-remarks" name="remarks" rows="2"
				          class="noor-input" style="max-width:400px;"></textarea>
			</div>

			<div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
				<button type="submit" id="noor-fee-pay-btn" class="noor-btn noor-btn--primary">
					&#10003; <?php esc_html_e( 'Record Payment', 'noor-tms' ); ?>
				</button>
				<span id="noor-fee-pay-feedback"></span>
			</div>
		</form>
	</div>

<?php
/* =========================================================================
   TAB: DEFAULTERS
   ========================================================================= */
elseif ( 'defaulters' === $tab ) :
	$today = current_time( 'Y-m-d' );
?>
	<form method="get" action="<?php echo esc_url( $fees_url ); ?>" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
		<input type="hidden" name="tab" value="defaulters" />
		<label><?php esc_html_e( 'Month:', 'noor-tms' ); ?>
			<input type="month" name="def_month" value="<?php echo esc_attr( $def_month ?? $cur_month ); ?>"
			       style="margin-left:6px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;" />
		</label>
		<button type="submit" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'View', 'noor-tms' ); ?></button>
	</form>

	<div style="padding:8px 12px;background:#eff6ff;border-left:4px solid #3b82f6;border-radius:0 4px 4px 0;margin-bottom:14px;font-size:0.9rem;">
		<strong><?php echo esc_html( count( $defaulters ?? [] ) ); ?></strong>
		<?php esc_html_e( 'defaulters for', 'noor-tms' ); ?>
		<strong><?php echo esc_html( $def_month ?? $cur_month ); ?></strong>
	</div>

	<?php if ( empty( $defaulters ?? [] ) ) : ?>
		<p class="noor-empty-msg" style="color:#166534;">
			&#10003; <?php esc_html_e( 'All invoices for this month are settled!', 'noor-tms' ); ?>
		</p>
	<?php else : ?>
		<div class="noor-table-wrap">
			<table class="noor-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Overdue', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $defaulters as $row ) :
						$net_due      = max( 0, (float) $row['amount_due'] + (float) $row['fine'] - (float) $row['discount'] );
						$balance      = max( 0, $net_due - (float) $row['total_paid'] );
						$days_overdue = max( 0, (int) round( ( strtotime( $today ) - strtotime( $row['due_date'] ) ) / DAY_IN_SECONDS ) );
					?>
						<tr>
							<td><?php echo esc_html( $row['student_name'] ); ?></td>
							<td><?php echo esc_html( $row['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $row['due_date'] ); ?></td>
							<td>
								<?php if ( $days_overdue > 0 ) : ?>
									<span class="noor-badge noor-fee-status-unpaid"><?php echo esc_html( $days_overdue ); ?> days</span>
								<?php else : ?>
									<span style="color:#94a3b8;">—</span>
								<?php endif; ?>
							</td>
							<td class="noor-col-num"><strong><?php echo esc_html( number_format( $balance, 2 ) ); ?></strong></td>
							<td>
								<span class="noor-badge noor-fee-status-<?php echo esc_attr( $row['status'] ); ?>">
									<?php echo esc_html( ucfirst( $row['status'] ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $row['parent_phone'] ) ) : ?>
									<a href="https://wa.me/<?php echo esc_attr( ltrim( $row['parent_phone'], '+' ) ); ?>?text=<?php echo esc_attr( urlencode( sprintf( __( 'Dear parent, the fee for %s (Balance: %s) is outstanding for %s. Please pay at your earliest convenience.', 'noor-tms' ), $row['student_name'], number_format( $balance, 2 ), $def_month ?? $cur_month ) ) ); ?>"
									   target="_blank" rel="noopener"
									   class="noor-btn noor-btn--whatsapp noor-btn--sm">
										&#128172; <?php esc_html_e( 'WhatsApp', 'noor-tms' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

<?php
/* =========================================================================
   TAB: COLLECTION REPORT
   ========================================================================= */
elseif ( 'report' === $tab ) :
	$total_billed    = 0.0;
	$total_collected = 0.0;
	foreach ( $report_rows ?? [] as $r ) {
		$total_billed    += (float) $r['total_billed'];
		$total_collected += (float) $r['total_collected'];
	}
	$total_outstanding = max( 0, $total_billed - $total_collected );
?>
	<!-- Filters -->
	<form method="get" action="<?php echo esc_url( $fees_url ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px;">
		<input type="hidden" name="tab" value="report" />
		<label><?php esc_html_e( 'Group By:', 'noor-tms' ); ?>
			<select name="group_by" style="margin-left:6px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
				<option value="month" <?php selected( $group_by ?? 'month', 'month' ); ?>><?php esc_html_e( 'Month', 'noor-tms' ); ?></option>
				<option value="class" <?php selected( $group_by ?? 'month', 'class' ); ?>><?php esc_html_e( 'Class', 'noor-tms' ); ?></option>
			</select>
		</label>
		<input type="month" name="report_month" value="<?php echo esc_attr( $report_month ?? '' ); ?>"
		       style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;" />
		<select name="class_id" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;">
			<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
			<?php foreach ( $classes as $cls ) : ?>
				<option value="<?php echo esc_attr( $cls['id'] ); ?>"
					<?php selected( (int) ( $report_class ?? 0 ), (int) $cls['id'] ); ?>>
					<?php echo esc_html( $cls['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="noor-btn noor-btn--secondary"><?php esc_html_e( 'Apply', 'noor-tms' ); ?></button>
	</form>

	<!-- Stat cards -->
	<div style="display:flex;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
		<?php
		$stats = [
			[ 'label' => __( 'Total Billed',    'noor-tms' ), 'value' => number_format( $total_billed, 2 ),    'color' => '#1e40af', 'bg' => '#eff6ff' ],
			[ 'label' => __( 'Total Collected', 'noor-tms' ), 'value' => number_format( $total_collected, 2 ), 'color' => '#166534', 'bg' => '#f0fdf4' ],
			[ 'label' => __( 'Outstanding',     'noor-tms' ), 'value' => number_format( $total_outstanding, 2 ),'color' => '#991b1b', 'bg' => '#fff7f7' ],
		];
		foreach ( $stats as $stat ) :
		?>
			<div style="background:<?php echo esc_attr( $stat['bg'] ); ?>;border:1px solid <?php echo esc_attr( $stat['color'] ); ?>33;border-radius:8px;padding:14px 20px;min-width:150px;">
				<div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#64748b;"><?php echo esc_html( $stat['label'] ); ?></div>
				<div style="font-size:1.4rem;font-weight:700;color:<?php echo esc_attr( $stat['color'] ); ?>;margin-top:4px;"><?php echo esc_html( $stat['value'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $report_rows ?? [] ) ) : ?>
		<p class="noor-empty-msg"><?php esc_html_e( 'No data found.', 'noor-tms' ); ?></p>
	<?php else : ?>
		<div class="noor-table-wrap">
			<table class="noor-table">
				<thead>
					<tr>
						<th><?php echo 'class' === ( $group_by ?? 'month' ) ? esc_html__( 'Class', 'noor-tms' ) : esc_html__( 'Month', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Billed', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Collected', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Outstanding', 'noor-tms' ); ?></th>
						<th class="noor-col-num"><?php esc_html_e( 'Rate', 'noor-tms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $report_rows as $r ) :
						$billed      = (float) $r['total_billed'];
						$collected   = (float) $r['total_collected'];
						$outstanding = max( 0, $billed - $collected );
						$rate        = $billed > 0 ? round( ( $collected / $billed ) * 100, 1 ) : 0;
					?>
						<tr>
							<td><strong><?php echo esc_html( $r['group_label'] ); ?></strong></td>
							<td class="noor-col-num"><?php echo esc_html( $r['invoice_count'] ); ?></td>
							<td class="noor-col-num"><?php echo esc_html( number_format( $billed, 2 ) ); ?></td>
							<td class="noor-col-num"><?php echo esc_html( number_format( $collected, 2 ) ); ?></td>
							<td class="noor-col-num"><strong><?php echo esc_html( number_format( $outstanding, 2 ) ); ?></strong></td>
							<td class="noor-col-num">
								<span class="noor-badge <?php echo $rate >= 75 ? 'noor-badge--success' : 'noor-badge--danger'; ?>">
									<?php echo esc_html( $rate ); ?>%
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
					<!-- Totals row -->
					<tr style="font-weight:700;background:#f8fafc;">
						<td><?php esc_html_e( 'TOTAL', 'noor-tms' ); ?></td>
						<td class="noor-col-num"><?php echo esc_html( array_sum( array_column( $report_rows, 'invoice_count' ) ) ); ?></td>
						<td class="noor-col-num"><?php echo esc_html( number_format( $total_billed, 2 ) ); ?></td>
						<td class="noor-col-num"><?php echo esc_html( number_format( $total_collected, 2 ) ); ?></td>
						<td class="noor-col-num"><?php echo esc_html( number_format( $total_outstanding, 2 ) ); ?></td>
						<td class="noor-col-num">
							<?php if ( $total_billed > 0 ) : ?>
								<?php $total_rate = round( ( $total_collected / $total_billed ) * 100, 1 ); ?>
								<span class="noor-badge <?php echo $total_rate >= 75 ? 'noor-badge--success' : 'noor-badge--danger'; ?>">
									<?php echo esc_html( $total_rate ); ?>%
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
