<?php
/**
 * Fee Management – admin pages and AJAX handlers.
 *
 * Screens (tabs under noor-tms-fees):
 *   structures – Fee Structure CRUD
 *   invoices   – Invoice Manager (list, void, generate)
 *   payment    – Payment Entry (search student → pick invoice → pay)
 *   defaulters – Defaulters list for a given month
 *   report     – Collection summary report
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\Repositories\FeeRepository;
use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fee
 */
class Fee {

	// -----------------------------------------------------------------------
	// Main page router
	// -----------------------------------------------------------------------

	/**
	 * Entry point for the Fees admin page.
	 * Handles form POSTs, applies late fines, then renders the correct tab.
	 */
	public function page_fee(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		// Handle POST actions before any output.
		if ( isset( $_POST['noor_fee_structure_nonce'] ) ) {
			$this->handle_save_structure();
			return;
		}

		// Refresh late fines on every page load (lightweight — only overdue invoices).
		FeeRepository::apply_late_fines();

		$tab = sanitize_key( $_GET['tab'] ?? 'structures' );
		$allowed_tabs = [ 'structures', 'invoices', 'payment', 'defaulters', 'report' ];
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'structures';
		}

		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Fee Management', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<!-- Tab navigation -->
			<nav class="noor-tab-wrap">
				<?php
				$tabs = [
					'structures' => __( 'Fee Structures', 'noor-tms' ),
					'invoices'   => __( 'Invoice Manager', 'noor-tms' ),
					'payment'    => __( 'Payment Entry', 'noor-tms' ),
					'defaulters' => __( 'Defaulters', 'noor-tms' ),
					'report'     => __( 'Collection Report', 'noor-tms' ),
				];
				foreach ( $tabs as $slug => $label ) {
					printf(
						'<a href="%s" class="noor-tab-link%s">%s</a>',
						esc_url( add_query_arg( [ 'page' => 'noor-tms-fees', 'tab' => $slug ], admin_url( 'admin.php' ) ) ),
						$slug === $tab ? ' is-active' : '',
						esc_html( $label )
					);
				}
				?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'invoices':
					$this->render_tab_invoices();
					break;
				case 'payment':
					$this->render_tab_payment();
					break;
				case 'defaulters':
					$this->render_tab_defaulters();
					break;
				case 'report':
					$this->render_tab_report();
					break;
				default:
					$this->render_tab_structures();
					break;
			}
			?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Fee Structures
	// -----------------------------------------------------------------------

	private function render_tab_structures(): void {
		$edit_id   = (int) ( $_GET['edit_id'] ?? 0 );
		$edit_item = $edit_id ? FeeRepository::get_fee_structure( $edit_id ) : null;
		$classes   = DatabaseHandler::get_classes_dropdown();
		$structures = FeeRepository::get_fee_structures();
		?>
		<!-- Structures list -->
		<table class="wp-list-table widefat fixed striped noor-tms-table" style="margin-top:16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fee Title', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Fine/Day', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Frequency', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Effective From', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $structures ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No fee structures defined yet.', 'noor-tms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $structures as $s ) : ?>
						<tr id="noor-fee-struct-row-<?php echo esc_attr( $s['id'] ); ?>">
							<td><strong><?php echo esc_html( $s['fee_title'] ); ?></strong></td>
							<td><?php echo esc_html( $s['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( number_format( (float) $s['amount'], 2 ) ); ?></td>
							<td><?php echo esc_html( (float) $s['fine_per_day'] > 0 ? number_format( (float) $s['fine_per_day'], 2 ) : '—' ); ?></td>
							<td><?php echo esc_html( ucfirst( $s['frequency'] ) ); ?></td>
							<td><?php echo esc_html( $s['effective_from'] ); ?></td>
							<td class="noor-actions">
								<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-fees', 'tab' => 'structures', 'edit_id' => $s['id'] ], admin_url( 'admin.php' ) ) ); ?>"
								   class="button button-small"><?php esc_html_e( 'Edit', 'noor-tms' ); ?></a>
								<button type="button" class="button button-small button-link-delete noor-fee-delete-structure"
								        data-id="<?php echo esc_attr( $s['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'noor-tms' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Add / Edit form -->
		<div class="noor-tms-card" style="margin-top:24px;">
			<h2><?php echo $edit_item ? esc_html__( 'Edit Fee Structure', 'noor-tms' ) : esc_html__( 'Add Fee Structure', 'noor-tms' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'noor_fee_save_structure', 'noor_fee_structure_nonce' ); ?>
				<?php if ( $edit_item ) : ?>
					<input type="hidden" name="structure_id" value="<?php echo esc_attr( $edit_item['id'] ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fee_class_id"><?php esc_html_e( 'Class', 'noor-tms' ); ?> <span class="required">*</span></label></th>
						<td>
							<select id="fee_class_id" name="class_id" required class="regular-text">
								<option value=""><?php esc_html_e( '— Select Class —', 'noor-tms' ); ?></option>
								<?php foreach ( $classes as $cls ) : ?>
									<option value="<?php echo esc_attr( $cls['id'] ); ?>"
										<?php selected( (int) ( $edit_item['class_id'] ?? 0 ), (int) $cls['id'] ); ?>>
										<?php echo esc_html( $cls['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fee_title"><?php esc_html_e( 'Fee Title', 'noor-tms' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="text" id="fee_title" name="fee_title" required class="regular-text"
							       value="<?php echo esc_attr( $edit_item['fee_title'] ?? '' ); ?>"
							       placeholder="<?php esc_attr_e( 'e.g. Monthly Tuition Fee', 'noor-tms' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fee_amount"><?php esc_html_e( 'Amount', 'noor-tms' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="number" id="fee_amount" name="amount" required min="0" step="0.01" class="regular-text"
							       value="<?php echo esc_attr( $edit_item['amount'] ?? '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fee_fine_per_day"><?php esc_html_e( 'Late Fine / Day', 'noor-tms' ); ?></label></th>
						<td>
							<input type="number" id="fee_fine_per_day" name="fine_per_day" min="0" step="0.01" class="regular-text"
							       value="<?php echo esc_attr( $edit_item['fine_per_day'] ?? '0' ); ?>" />
							<p class="description"><?php esc_html_e( 'Applied per day after due date. Set 0 to disable.', 'noor-tms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fee_frequency"><?php esc_html_e( 'Frequency', 'noor-tms' ); ?></label></th>
						<td>
							<select id="fee_frequency" name="frequency">
								<?php
								foreach ( [ 'monthly' => __( 'Monthly', 'noor-tms' ), 'term' => __( 'Per Term', 'noor-tms' ), 'yearly' => __( 'Yearly', 'noor-tms' ) ] as $val => $lbl ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $val ),
										selected( $edit_item['frequency'] ?? 'monthly', $val, false ),
										esc_html( $lbl )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fee_effective_from"><?php esc_html_e( 'Effective From', 'noor-tms' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="date" id="fee_effective_from" name="effective_from" required class="regular-text"
							       value="<?php echo esc_attr( $edit_item['effective_from'] ?? current_time( 'Y-m-d' ) ); ?>" />
						</td>
					</tr>
				</table>

				<div class="noor-form-actions">
					<?php submit_button( $edit_item ? __( 'Update Structure', 'noor-tms' ) : __( 'Add Structure', 'noor-tms' ), 'primary', 'submit', false ); ?>
					<?php if ( $edit_item ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-fees', 'tab' => 'structures' ], admin_url( 'admin.php' ) ) ); ?>"
						   class="button"><?php esc_html_e( 'Cancel', 'noor-tms' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Invoice Manager
	// -----------------------------------------------------------------------

	private function render_tab_invoices(): void {
		$month    = sanitize_text_field( $_GET['invoice_month'] ?? '' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );
		$status   = sanitize_key( $_GET['status'] ?? '' );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		$result      = FeeRepository::get_invoices( [
			'per_page'      => 25,
			'page'          => $paged,
			'invoice_month' => $month,
			'class_id'      => $class_id,
			'status'        => $status,
		] );
		$invoices    = $result['rows'];
		$total       = $result['total'];
		$total_pages = (int) ceil( $total / 25 );
		$classes     = DatabaseHandler::get_classes_dropdown();

		$current_month = current_time( 'Y-m' );
		$academic_year = FeeRepository::current_academic_year();
		?>
		<!-- Filter bar -->
		<form method="get" action="" style="margin-top:16px;">
			<input type="hidden" name="page" value="noor-tms-fees" />
			<input type="hidden" name="tab" value="invoices" />
			<p class="search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
				<input type="month" name="invoice_month" value="<?php echo esc_attr( $month ); ?>"
				       class="regular-text" style="max-width:160px;" />

				<select name="class_id">
					<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
					<?php foreach ( $classes as $cls ) : ?>
						<option value="<?php echo esc_attr( $cls['id'] ); ?>"
							<?php selected( $class_id, (int) $cls['id'] ); ?>>
							<?php echo esc_html( $cls['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
					<?php
					foreach ( [ 'unpaid' => __( 'Unpaid', 'noor-tms' ), 'partial' => __( 'Partial', 'noor-tms' ), 'paid' => __( 'Paid', 'noor-tms' ), 'voided' => __( 'Voided', 'noor-tms' ) ] as $val => $lbl ) {
						printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $lbl ) );
					}
					?>
				</select>

				<?php submit_button( __( 'Filter', 'noor-tms' ), 'secondary', '', false ); ?>
			</p>
		</form>

		<!-- Generate invoices button -->
		<div style="margin-bottom:12px;">
			<button type="button" id="noor-fee-generate-btn" class="button button-primary"
			        data-month="<?php echo esc_attr( $current_month ); ?>"
			        data-year="<?php echo esc_attr( $academic_year ); ?>">
				<?php
				/* translators: %s = current month like "2025-04" */
				printf( esc_html__( 'Generate Invoices for %s', 'noor-tms' ), esc_html( $current_month ) );
				?>
			</button>
			<span id="noor-fee-generate-feedback" class="noor-ajax-feedback" style="margin-left:12px;"></span>
		</div>

		<table class="wp-list-table widefat fixed striped noor-tms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Month', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Fee Title', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Due', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Fine', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Discount', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Net Due', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody id="noor-fee-invoices-tbody">
				<?php if ( empty( $invoices ) ) : ?>
					<tr><td colspan="12"><?php esc_html_e( 'No invoices found.', 'noor-tms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $invoices as $inv ) :
						$net_due = max( 0, (float) $inv['amount_due'] + (float) $inv['fine'] - (float) $inv['discount'] );
						$balance = max( 0, $net_due - (float) $inv['total_paid'] );
					?>
						<tr id="noor-fee-inv-row-<?php echo esc_attr( $inv['id'] ); ?>">
							<td><strong><?php echo esc_html( $inv['student_name'] ); ?></strong></td>
							<td><?php echo esc_html( $inv['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $inv['invoice_month'] ); ?></td>
							<td><?php echo esc_html( $inv['fee_title'] ?? '—' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (float) $inv['amount_due'], 2 ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (float) $inv['fine'], 2 ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (float) $inv['discount'], 2 ) ); ?></td>
							<td style="text-align:right;"><strong><?php echo esc_html( number_format( $net_due, 2 ) ); ?></strong></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (float) $inv['total_paid'], 2 ) ); ?></td>
							<td style="text-align:right;">
								<strong><?php echo esc_html( number_format( $balance, 2 ) ); ?></strong>
							</td>
							<td>
								<span class="noor-status-badge noor-fee-status-<?php echo esc_attr( $inv['status'] ); ?>">
									<?php echo esc_html( ucfirst( $inv['status'] ) ); ?>
								</span>
							</td>
							<td class="noor-actions">
								<?php if ( 'voided' !== $inv['status'] ) : ?>
									<button type="button"
									        class="button button-small button-link-delete noor-fee-void-invoice"
									        data-id="<?php echo esc_attr( $inv['id'] ); ?>">
										<?php esc_html_e( 'Void', 'noor-tms' ); ?>
									</button>
								<?php endif; ?>
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
					echo paginate_links( [
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					] );
					?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Payment Entry
	// -----------------------------------------------------------------------

	private function render_tab_payment(): void {
		$classes = DatabaseHandler::get_classes_dropdown();
		?>
		<div class="noor-tms-card" style="margin-top:16px;">
			<h2><?php esc_html_e( 'Find Student', 'noor-tms' ); ?></h2>
			<p class="description" style="margin-bottom:12px;">
				<?php esc_html_e( 'Search by student name or ID to view their outstanding invoices.', 'noor-tms' ); ?>
			</p>
			<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
				<input type="text" id="noor-fee-student-search"
				       class="regular-text" style="min-width:280px;"
				       placeholder="<?php esc_attr_e( 'Type student name…', 'noor-tms' ); ?>" />
				<button type="button" id="noor-fee-search-btn" class="button">
					<?php esc_html_e( 'Search', 'noor-tms' ); ?>
				</button>
				<span id="noor-fee-search-spinner" class="noor-ajax-feedback"></span>
			</div>
			<div id="noor-fee-student-results" style="margin-top:12px;"></div>
		</div>

		<!-- Invoice list for selected student (hidden until student selected) -->
		<div id="noor-fee-invoice-section" class="noor-tms-card" style="display:none;margin-top:16px;">
			<h2 id="noor-fee-invoice-title"><?php esc_html_e( 'Outstanding Invoices', 'noor-tms' ); ?></h2>
			<div id="noor-fee-invoice-list"></div>
		</div>

		<!-- Payment form (hidden until invoice selected) -->
		<div id="noor-fee-payment-section" class="noor-tms-card" style="display:none;margin-top:16px;">
			<h2><?php esc_html_e( 'Record Payment', 'noor-tms' ); ?></h2>
			<form id="noor-fee-payment-form">
				<input type="hidden" id="noor-fee-invoice-id" name="invoice_id" value="" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="noor-fee-amount"><?php esc_html_e( 'Amount Paid', 'noor-tms' ); ?> <span class="required">*</span></label></th>
						<td>
							<input type="number" id="noor-fee-amount" name="paid_amount"
							       min="0.01" step="0.01" required class="regular-text" />
							<p class="description" id="noor-fee-balance-note"></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="noor-fee-pay-date"><?php esc_html_e( 'Payment Date', 'noor-tms' ); ?></label></th>
						<td>
							<input type="date" id="noor-fee-pay-date" name="payment_date"
							       value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
							       class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="noor-fee-method"><?php esc_html_e( 'Payment Method', 'noor-tms' ); ?></label></th>
						<td>
							<select id="noor-fee-method" name="payment_method">
								<option value="cash"><?php esc_html_e( 'Cash', 'noor-tms' ); ?></option>
								<option value="bank"><?php esc_html_e( 'Bank Transfer', 'noor-tms' ); ?></option>
								<option value="cheque"><?php esc_html_e( 'Cheque', 'noor-tms' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="noor-fee-remarks"><?php esc_html_e( 'Remarks', 'noor-tms' ); ?></label></th>
						<td>
							<textarea id="noor-fee-remarks" name="remarks" rows="2" class="regular-text"></textarea>
						</td>
					</tr>
				</table>
				<div class="noor-form-actions">
					<button type="submit" id="noor-fee-pay-btn" class="button button-primary">
						<?php esc_html_e( 'Record Payment', 'noor-tms' ); ?>
					</button>
					<span id="noor-fee-pay-feedback" class="noor-ajax-feedback"></span>
				</div>
			</form>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Defaulters
	// -----------------------------------------------------------------------

	private function render_tab_defaulters(): void {
		$month = sanitize_text_field( $_GET['def_month'] ?? current_time( 'Y-m' ) );
		$rows  = FeeRepository::get_defaulters( $month );
		$today = current_time( 'Y-m-d' );
		?>
		<form method="get" action="" style="margin:16px 0 8px;">
			<input type="hidden" name="page" value="noor-tms-fees" />
			<input type="hidden" name="tab" value="defaulters" />
			<p class="search-box" style="display:flex;gap:8px;align-items:center;">
				<label><?php esc_html_e( 'Month:', 'noor-tms' ); ?>
					<input type="month" name="def_month" value="<?php echo esc_attr( $month ); ?>"
					       style="margin-left:6px;" />
				</label>
				<?php submit_button( __( 'View', 'noor-tms' ), 'secondary', '', false ); ?>
			</p>
		</form>

		<div class="noor-fee-summary-bar">
			<strong><?php echo esc_html( count( $rows ) ); ?></strong>
			<?php esc_html_e( 'defaulters for', 'noor-tms' ); ?>
			<strong><?php echo esc_html( $month ); ?></strong>
		</div>

		<table class="wp-list-table widefat fixed striped noor-tms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Class', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Due Date', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Days Overdue', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Net Due', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Paid', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Balance', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'noor-tms' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9" style="color:#155724;">
						<?php esc_html_e( 'No defaulters — all invoices for this month are settled!', 'noor-tms' ); ?>
					</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) :
						$net_due  = max( 0, (float) $row['amount_due'] + (float) $row['fine'] - (float) $row['discount'] );
						$balance  = max( 0, $net_due - (float) $row['total_paid'] );
						$days_overdue = max( 0, (int) round( ( strtotime( $today ) - strtotime( $row['due_date'] ) ) / DAY_IN_SECONDS ) );
					?>
						<tr>
							<td><strong><?php echo esc_html( $row['student_name'] ); ?></strong></td>
							<td><?php echo esc_html( $row['class_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $row['due_date'] ); ?></td>
							<td>
								<?php if ( $days_overdue > 0 ) : ?>
									<span class="noor-status-badge noor-fee-overdue"><?php echo esc_html( $days_overdue ); ?> days</span>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right;"><?php echo esc_html( number_format( $net_due, 2 ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (float) $row['total_paid'], 2 ) ); ?></td>
							<td style="text-align:right;"><strong><?php echo esc_html( number_format( $balance, 2 ) ); ?></strong></td>
							<td>
								<span class="noor-status-badge noor-fee-status-<?php echo esc_attr( $row['status'] ); ?>">
									<?php echo esc_html( ucfirst( $row['status'] ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $row['parent_phone'] ) ) : ?>
									<a href="https://wa.me/<?php echo esc_attr( ltrim( $row['parent_phone'], '+' ) ); ?>?text=<?php echo esc_attr( urlencode( sprintf( __( 'Dear parent, fee for %s is outstanding. Please pay PKR %s.', 'noor-tms' ), $row['student_name'], number_format( $balance, 2 ) ) ) ); ?>"
									   target="_blank" rel="noopener" class="button button-small noor-wa-btn">
										<?php esc_html_e( '&#128172; WhatsApp', 'noor-tms' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// Tab: Collection Report
	// -----------------------------------------------------------------------

	private function render_tab_report(): void {
		$group_by = sanitize_key( $_GET['group_by'] ?? 'month' );
		$month    = sanitize_text_field( $_GET['report_month'] ?? '' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );
		$classes  = DatabaseHandler::get_classes_dropdown();

		$rows = FeeRepository::get_collection_summary( [
			'group_by'      => $group_by,
			'invoice_month' => $month,
			'class_id'      => $class_id,
		] );

		$total_billed    = 0.0;
		$total_collected = 0.0;
		foreach ( $rows as $row ) {
			$total_billed    += (float) $row['total_billed'];
			$total_collected += (float) $row['total_collected'];
		}
		?>
		<form method="get" action="" style="margin:16px 0 8px;">
			<input type="hidden" name="page" value="noor-tms-fees" />
			<input type="hidden" name="tab" value="report" />
			<p class="search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
				<label><?php esc_html_e( 'Group By:', 'noor-tms' ); ?>
					<select name="group_by" style="margin-left:6px;">
						<option value="month" <?php selected( $group_by, 'month' ); ?>><?php esc_html_e( 'Month', 'noor-tms' ); ?></option>
						<option value="class" <?php selected( $group_by, 'class' ); ?>><?php esc_html_e( 'Class', 'noor-tms' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Month:', 'noor-tms' ); ?>
					<input type="month" name="report_month" value="<?php echo esc_attr( $month ); ?>"
					       style="margin-left:6px;" />
				</label>
				<select name="class_id">
					<option value=""><?php esc_html_e( 'All Classes', 'noor-tms' ); ?></option>
					<?php foreach ( $classes as $cls ) : ?>
						<option value="<?php echo esc_attr( $cls['id'] ); ?>"
							<?php selected( $class_id, (int) $cls['id'] ); ?>>
							<?php echo esc_html( $cls['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Apply', 'noor-tms' ), 'secondary', '', false ); ?>
			</p>
		</form>

		<!-- Summary cards -->
		<div class="noor-fee-report-cards">
			<div class="noor-fee-stat-card">
				<span class="noor-fee-stat-label"><?php esc_html_e( 'Total Billed', 'noor-tms' ); ?></span>
				<span class="noor-fee-stat-value"><?php echo esc_html( number_format( $total_billed, 2 ) ); ?></span>
			</div>
			<div class="noor-fee-stat-card is-success">
				<span class="noor-fee-stat-label"><?php esc_html_e( 'Total Collected', 'noor-tms' ); ?></span>
				<span class="noor-fee-stat-value"><?php echo esc_html( number_format( $total_collected, 2 ) ); ?></span>
			</div>
			<div class="noor-fee-stat-card is-danger">
				<span class="noor-fee-stat-label"><?php esc_html_e( 'Outstanding', 'noor-tms' ); ?></span>
				<span class="noor-fee-stat-value"><?php echo esc_html( number_format( max( 0, $total_billed - $total_collected ), 2 ) ); ?></span>
			</div>
			<?php if ( $total_billed > 0 ) : ?>
				<div class="noor-fee-stat-card">
					<span class="noor-fee-stat-label"><?php esc_html_e( 'Collection Rate', 'noor-tms' ); ?></span>
					<span class="noor-fee-stat-value"><?php echo esc_html( number_format( ( $total_collected / $total_billed ) * 100, 1 ) ); ?>%</span>
				</div>
			<?php endif; ?>
		</div>

		<table class="wp-list-table widefat fixed striped noor-tms-table" style="margin-top:16px;">
			<thead>
				<tr>
					<th><?php echo 'class' === $group_by ? esc_html__( 'Class', 'noor-tms' ) : esc_html__( 'Month', 'noor-tms' ); ?></th>
					<th style="text-align:center;"><?php esc_html_e( 'Invoices', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Total Billed', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Total Collected', 'noor-tms' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Outstanding', 'noor-tms' ); ?></th>
					<th style="text-align:center;"><?php esc_html_e( 'Rate', 'noor-tms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No data found.', 'noor-tms' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) :
						$billed      = (float) $row['total_billed'];
						$collected   = (float) $row['total_collected'];
						$outstanding = max( 0, $billed - $collected );
						$rate        = $billed > 0 ? round( ( $collected / $billed ) * 100, 1 ) : 0;
					?>
						<tr>
							<td><strong><?php echo esc_html( $row['group_label'] ); ?></strong></td>
							<td style="text-align:center;"><?php echo esc_html( $row['invoice_count'] ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( $billed, 2 ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( $collected, 2 ) ); ?></td>
							<td style="text-align:right;">
								<strong><?php echo esc_html( number_format( $outstanding, 2 ) ); ?></strong>
							</td>
							<td style="text-align:center;">
								<span class="noor-pct <?php echo $rate >= 75 ? 'noor-pct-pass' : 'noor-pct-fail'; ?>">
									<?php echo esc_html( $rate ); ?>%
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
					<!-- Totals row -->
					<tr style="font-weight:700;background:#f6f7f7;">
						<td><?php esc_html_e( 'TOTAL', 'noor-tms' ); ?></td>
						<td style="text-align:center;"><?php echo esc_html( array_sum( array_column( $rows, 'invoice_count' ) ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format( $total_billed, 2 ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format( $total_collected, 2 ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format( max( 0, $total_billed - $total_collected ), 2 ) ); ?></td>
						<td style="text-align:center;">
							<?php if ( $total_billed > 0 ) : ?>
								<span class="noor-pct <?php echo ( $total_collected / $total_billed ) * 100 >= 75 ? 'noor-pct-pass' : 'noor-pct-fail'; ?>">
									<?php echo esc_html( number_format( ( $total_collected / $total_billed ) * 100, 1 ) ); ?>%
								</span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	// -----------------------------------------------------------------------
	// POST handler: save/update fee structure
	// -----------------------------------------------------------------------

	private function handle_save_structure(): void {
		if ( ! check_admin_referer( 'noor_fee_save_structure', 'noor_fee_structure_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$struct_id = (int) ( $_POST['structure_id'] ?? 0 );
		$data      = [
			'class_id'       => (int) ( $_POST['class_id'] ?? 0 ),
			'fee_title'      => sanitize_text_field( $_POST['fee_title'] ?? '' ),
			'amount'         => (float) ( $_POST['amount'] ?? 0 ),
			'fine_per_day'   => (float) ( $_POST['fine_per_day'] ?? 0 ),
			'frequency'      => sanitize_key( $_POST['frequency'] ?? 'monthly' ),
			'effective_from' => sanitize_text_field( $_POST['effective_from'] ?? current_time( 'Y-m-d' ) ),
		];

		if ( empty( $data['class_id'] ) || empty( $data['fee_title'] ) || $data['amount'] <= 0 ) {
			wp_die( esc_html__( 'Class, Fee Title and Amount are required and amount must be greater than 0.', 'noor-tms' ) );
		}

		if ( $struct_id > 0 ) {
			FeeRepository::update_fee_structure( $struct_id, $data );
			$msg = 'struct_updated';
		} else {
			FeeRepository::insert_fee_structure( $data );
			$msg = 'struct_added';
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-fees', 'tab' => 'structures', 'msg' => $msg ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX: Delete fee structure
	// -----------------------------------------------------------------------

	public function ajax_delete_fee_structure(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$id = (int) ( $_POST['structure_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid structure ID.', 'noor-tms' ) ] );
		}

		if ( FeeRepository::delete_fee_structure( $id ) ) {
			wp_send_json_success( [ 'message' => __( 'Fee structure deleted.', 'noor-tms' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not delete fee structure.', 'noor-tms' ) ] );
		}
	}

	// -----------------------------------------------------------------------
	// AJAX: Void invoice
	// -----------------------------------------------------------------------

	public function ajax_void_invoice(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$id = (int) ( $_POST['invoice_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid invoice ID.', 'noor-tms' ) ] );
		}

		if ( FeeRepository::void_invoice( $id ) ) {
			wp_send_json_success( [ 'message' => __( 'Invoice voided.', 'noor-tms' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not void invoice.', 'noor-tms' ) ] );
		}
	}

	// -----------------------------------------------------------------------
	// AJAX: Search students for payment entry
	// -----------------------------------------------------------------------

	public function ajax_search_students(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$q = sanitize_text_field( $_POST['q'] ?? '' );
		if ( strlen( $q ) < 2 ) {
			wp_send_json_error( [ 'message' => __( 'Please enter at least 2 characters.', 'noor-tms' ) ] );
		}

		$result   = DatabaseHandler::get_students( [ 'search' => $q, 'per_page' => 10, 'page' => 1, 'status' => 'active' ] );
		$students = [];
		foreach ( $result['rows'] as $s ) {
			$students[] = [
				'id'         => (int) $s['id'],
				'name'       => $s['name'],
				'class_name' => $s['class_name'] ?? '',
			];
		}

		wp_send_json_success( [ 'students' => $students ] );
	}

	// -----------------------------------------------------------------------
	// AJAX: Get unpaid / partial invoices for a student
	// -----------------------------------------------------------------------

	public function ajax_get_student_invoices(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$student_id = (int) ( $_POST['student_id'] ?? 0 );
		if ( ! $student_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid student ID.', 'noor-tms' ) ] );
		}

		$rows     = FeeRepository::get_student_invoices( $student_id );
		$invoices = [];
		foreach ( $rows as $inv ) {
			$net_due   = max( 0, (float) $inv['amount_due'] + (float) $inv['fine'] - (float) $inv['discount'] );
			$balance   = max( 0, $net_due - (float) $inv['total_paid'] );
			$invoices[] = [
				'id'            => (int) $inv['id'],
				'invoice_month' => $inv['invoice_month'],
				'fee_title'     => $inv['fee_title'] ?? '',
				'net_due'       => round( $net_due, 2 ),
				'total_paid'    => round( (float) $inv['total_paid'], 2 ),
				'balance'       => round( $balance, 2 ),
				'status'        => $inv['status'],
			];
		}

		wp_send_json_success( [ 'invoices' => $invoices ] );
	}

	// -----------------------------------------------------------------------
	// AJAX: Save payment entry
	// -----------------------------------------------------------------------

	public function ajax_save_payment(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$invoice_id     = (int) ( $_POST['invoice_id'] ?? 0 );
		$paid_amount    = (float) ( $_POST['paid_amount'] ?? 0 );
		$payment_date   = sanitize_text_field( $_POST['payment_date'] ?? current_time( 'Y-m-d' ) );
		$payment_method = sanitize_key( $_POST['payment_method'] ?? 'cash' );
		$remarks        = sanitize_textarea_field( $_POST['remarks'] ?? '' );

		if ( ! $invoice_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid invoice.', 'noor-tms' ) ] );
		}
		if ( $paid_amount <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Amount must be greater than 0.', 'noor-tms' ) ] );
		}

		// Verify invoice is payable.
		$invoice = FeeRepository::get_invoice( $invoice_id );
		if ( ! $invoice ) {
			wp_send_json_error( [ 'message' => __( 'Invoice not found.', 'noor-tms' ) ] );
		}
		if ( 'voided' === $invoice['status'] ) {
			wp_send_json_error( [ 'message' => __( 'Cannot add payment to a voided invoice.', 'noor-tms' ) ] );
		}
		if ( 'paid' === $invoice['status'] ) {
			wp_send_json_error( [ 'message' => __( 'This invoice is already fully paid.', 'noor-tms' ) ] );
		}

		$payment_id = FeeRepository::insert_payment( [
			'invoice_id'     => $invoice_id,
			'paid_amount'    => $paid_amount,
			'payment_date'   => $payment_date,
			'payment_method' => $payment_method,
			'received_by'    => get_current_user_id(),
			'remarks'        => $remarks,
		] );

		if ( ! $payment_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save payment.', 'noor-tms' ) ] );
		}

		// Return updated invoice status.
		$updated = FeeRepository::get_invoice( $invoice_id );
		wp_send_json_success( [
			'message'        => __( 'Payment recorded successfully.', 'noor-tms' ),
			'new_status'     => $updated['status'] ?? 'unpaid',
			'total_paid'     => round( (float) ( $updated['total_paid'] ?? 0 ), 2 ),
		] );
	}

	// -----------------------------------------------------------------------
	// AJAX: Generate invoices for current month
	// -----------------------------------------------------------------------

	public function ajax_generate_invoices(): void {
		check_ajax_referer( 'noor_tms_ajax', 'nonce' );
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$month         = sanitize_text_field( $_POST['month'] ?? current_time( 'Y-m' ) );
		$academic_year = sanitize_text_field( $_POST['academic_year'] ?? FeeRepository::current_academic_year() );

		$count = FeeRepository::generate_invoices_for_month( $month, $academic_year );

		wp_send_json_success( [
			/* translators: %1$d = count, %2$s = month */
			'message' => sprintf( __( '%1$d invoice(s) generated for %2$s.', 'noor-tms' ), $count, $month ),
			'count'   => $count,
		] );
	}

	// -----------------------------------------------------------------------
	// WP-Cron callback: auto-generate invoices on 1st of every month
	// -----------------------------------------------------------------------

	public function cron_generate_invoices(): void {
		$month         = current_time( 'Y-m' );
		$academic_year = FeeRepository::current_academic_year();
		FeeRepository::generate_invoices_for_month( $month, $academic_year );
		FeeRepository::apply_late_fines();
	}

	// -----------------------------------------------------------------------
	// Notices helper
	// -----------------------------------------------------------------------

	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );
		$notices = [
			'struct_added'   => __( 'Fee structure added successfully.', 'noor-tms' ),
			'struct_updated' => __( 'Fee structure updated successfully.', 'noor-tms' ),
		];
		if ( isset( $notices[ $msg ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $notices[ $msg ] )
			);
		}
	}
}
