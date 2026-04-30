<?php
/**
 * Admin Fee Management.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

class Fees {

	/**
	 * Render the main Fee Management page.
	 */
	public static function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'structures';

		echo '<div class="wrap">';
		echo '<h1>Fee Management</h1>';
		self::render_tabs( $active_tab );

		switch ( $active_tab ) {
			case 'structures':
				self::render_structures_tab();
				break;
			case 'invoices':
				self::render_invoices_tab();
				break;
			case 'payments':
				self::render_payments_tab();
				break;
			case 'defaulters':
				self::render_defaulters_tab();
				break;
			case 'diagnostics':
				self::render_diagnostics_tab();
				break;
			case 'reports':
				self::render_reports_tab();
				break;
			default:
				self::render_structures_tab();
				break;
		}
		echo '</div>';
	}

	/**
	 * Navigation tabs.
	 */
	private static function render_tabs( string $active_tab ): void {
		$tabs = [
			'structures'  => 'Fee Structure',
			'invoices'    => 'Invoice Manager',
			'payments'    => 'Payment Entry',
			'defaulters'  => 'Defaulters List',
			'diagnostics' => 'Diagnostics',
			'reports'     => 'Collection Report',
		];

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $id => $name ) {
			$active_class = ( $active_tab === $id ) ? ' nav-tab-active' : '';
			$url          = admin_url( 'admin.php?page=noor-tms-fees&tab=' . $id );
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( $url ), esc_attr( $active_class ), esc_html( $name ) );
		}
		echo '</nav>';
	}

	private static function render_structures_tab(): void {
		echo '<h2>Fee Structures</h2>';
		
		if ( isset( $_POST['submit_structure'] ) && check_admin_referer( 'noor_tms_save_structure' ) ) {
			$class_id = (int) $_POST['class_id'];
			$title    = sanitize_text_field( $_POST['fee_title'] );
			$amount   = (float) $_POST['amount'];
			$freq     = sanitize_text_field( $_POST['frequency'] );
			$from     = sanitize_text_field( $_POST['effective_from'] );
			// type="month" returns 'YYYY-MM' — normalize to full DATE 'YYYY-MM-01'.
			if ( preg_match( '/^\d{4}-\d{2}$/', $from ) ) {
				$from .= '-01';
			}
			
			if ( DatabaseHandler::insert_fee_structure( $class_id, $title, $amount, $freq, $from ) ) {
				echo '<div class="notice notice-success inline"><p>Fee Structure created successfully.</p></div>';
			} else {
				echo '<div class="notice notice-error inline"><p>Error creating fee structure.</p></div>';
			}
		}

		if ( isset( $_POST['submit_assignment'] ) && check_admin_referer( 'noor_tms_save_assignment' ) ) {
			$student_id = (int) $_POST['student_id'];
			$struct_id  = (int) $_POST['fee_structure_id'];
			$year       = sanitize_text_field( $_POST['academic_year'] );
			
			if ( DatabaseHandler::assign_student_fee( $student_id, $struct_id, $year ) ) {
				echo '<div class="notice notice-success inline"><p>Fee Structure assigned to student successfully.</p></div>';
			} else {
				echo '<div class="notice notice-error inline"><p>Error assigning fee structure or student already assigned for this year.</p></div>';
			}
		}

		echo '<div style="display:flex;gap:40px;">';
		
		// 1. Create Structure Form
		echo '<div style="flex:1;">';
		echo '<h3>Create Fee Structure</h3>';
		echo '<form method="post">';
		wp_nonce_field( 'noor_tms_save_structure' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label>Class</label></th><td><select name="class_id" required><option value="0">-- All Classes --</option>';
		foreach ( DatabaseHandler::get_classes() as $cls ) {
			echo '<option value="' . esc_attr( $cls['id'] ) . '">' . esc_html( $cls['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label>Fee Title</label></th><td><input type="text" class="regular-text" name="fee_title" required placeholder="e.g. Monthly Tuition"></td></tr>';
		echo '<tr><th scope="row"><label>Amount</label></th><td><input type="number" class="regular-text" name="amount" step="0.01" required></td></tr>';
		echo '<tr><th scope="row"><label>Frequency</label></th><td><select name="frequency" required><option value="monthly">Monthly</option><option value="yearly">Yearly</option><option value="one-time">One-Time</option></select></td></tr>';
		echo '<tr><th scope="row"><label>Effective From</label></th><td><input type="month" class="regular-text" name="effective_from" required value="' . current_time('Y-m') . '"></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><input type="submit" name="submit_structure" class="button button-primary" value="Save Fee Structure"></p>';
		echo '</form>';
		echo '</div>';

		// Fetch existing structures for dropdown
		global $wpdb;
		$structures = $wpdb->get_results( "SELECT fs.*, c.name as class_name FROM " . DatabaseHandler::fee_structure_table() . " fs LEFT JOIN " . DatabaseHandler::classes_table() . " c ON fs.class_id = c.id", ARRAY_A );

		echo '</div>';

		// 3. List active structures
		echo '<h3>Existing Structures</h3>';
		echo '<p>Since fee structures are auto-mapped directly through class relations to create invoices dynamically, there is no manual "student assignment" step required anymore. Generating invoices captures active enrolled students accurately.</p>';
		if ( $structures ) {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr><th>ID</th><th>Class</th><th>Title</th><th>Amount</th><th>Frequency</th><th>Effective From</th></tr></thead><tbody>';
			foreach ( $structures as $fs ) {
				echo '<tr>';
				echo '<td>' . esc_html( $fs['id'] ) . '</td>';
				echo '<td>' . esc_html( $fs['class_name'] ) . '</td>';
				echo '<td>' . esc_html( $fs['fee_title'] ) . '</td>';
				echo '<td>' . esc_html( $fs['amount'] ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $fs['frequency'] ) ) . '</td>';
				echo '<td>' . esc_html( $fs['effective_from'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>No fee structures found.</p>';
		}
	}

	private static function render_invoices_tab(): void {
		echo '<h2>Invoice Manager</h2>';
		echo '<p>View and manage auto-generated fee invoices. When you generate invoices, the system automatically creates bills for ALL active students whose class matches the fee structure class.</p>';
		
		if ( isset( $_POST['generate_invoices'] ) && check_admin_referer( 'noor_tms_generate_invoices' ) ) {
			$before_count = count( DatabaseHandler::get_invoices() );
			\Noor_TMS\Includes\FeesCron::generate_monthly_invoices();
			$after_count = count( DatabaseHandler::get_invoices() );
			$created = $after_count - $before_count;
			
			echo '<div class="notice notice-success inline"><p>';
			echo "Monthly invoices generation complete. New invoices created: <strong>{$created}</strong>. ";
			echo "This automatically assigned fees to all eligible active students based on class matching.";
			echo '</p></div>';
		}

		echo '<form method="post" style="margin-bottom: 20px;">';
		wp_nonce_field( 'noor_tms_generate_invoices' );
		echo '<input type="submit" name="generate_invoices" class="button button-primary" value="Generate Current Month Invoices Now">';
		echo ' <span class="description">Tip: This triggers automatic assignment of fees to all active students in classes with matching fee structures. Note: This manually triggers the invoice generation cron job for the current month. Only missing invoices will be created, ignoring existing ones.</span>';
		echo '</form>';

		$invoices = DatabaseHandler::get_invoices();
		if ( empty( $invoices ) ) {
			echo '<div class="notice notice-info inline"><p>No invoices found.</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>#</th>';
		echo '<th>Month</th>';
		echo '<th>Student</th>';
		echo '<th>Total Billed</th>';
		echo '<th>Balance Due</th>';
		echo '<th>Status</th>';
		echo '</tr></thead><tbody>';

		foreach ( $invoices as $invoice ) {
			$balance = (float) $invoice['net_due'] - (float) $invoice['total_paid'];
			echo '<tr>';
			echo '<td>#' . esc_html( $invoice['id'] ) . '</td>';
			echo '<td>' . esc_html( gmdate( 'F Y', strtotime( $invoice['invoice_month'] . '-01' ) ) ) . '</td>';
			echo '<td>' . esc_html( $invoice['student_name'] ) . '<br><small>' . esc_html( $invoice['class_name'] ) . '</small></td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $invoice['net_due'], 2 ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $balance, 2 ) ) . '</td>';
			
			if ( 'paid' === $invoice['status'] ) {
				echo '<td><span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:bold;">Paid</span></td>';
			} elseif ( 'partial' === $invoice['status'] ) {
				echo '<td><span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:bold;">Partial</span></td>';
			} else {
				echo '<td><span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:bold;">Unpaid</span></td>';
			}
			
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_payments_tab(): void {
		echo '<h2>Payment Entry</h2>';
		echo '<p>For recording new payments, please use the frontend <strong>Record Payment</strong> screen inside the Fees portal. Payments processing has been fully integrated on the front-end to streamline cashier responsibilities.</p>';
		echo '<p><a href="' . esc_url( home_url( '/tms-fees/?tms_action=payments' ) ) . '" class="button button-primary" target="_blank">Open Payment Screen</a></p>';
	}

	private static function render_defaulters_tab(): void {
		echo '<h2>Defaulters List</h2>';
		echo '<p>List of students with unpaid or partial invoices.</p>';
		
		$defaulters = DatabaseHandler::get_defaulters();
		
		if ( empty( $defaulters ) ) {
			echo '<div class="notice notice-success inline"><p>Great news! There are no defaulters found.</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>Student</th>';
		echo '<th>Class</th>';
		echo '<th>Contact</th>';
		echo '<th>Invoice Month</th>';
		echo '<th>Amount Due</th>';
		echo '<th>Status</th>';
		echo '</tr></thead><tbody>';

		foreach ( $defaulters as $defaulter ) {
			$balance = (float) $defaulter['amount_due'] + (float) $defaulter['fine'] - (float) $defaulter['discount'] - (float) $defaulter['total_paid'];
			echo '<tr>';
			echo '<td>' . esc_html( $defaulter['student_name'] ) . '</td>';
			echo '<td>' . esc_html( $defaulter['class_name'] ) . '</td>';
			echo '<td>' . esc_html( $defaulter['parent_phone'] ?: '--' ) . '</td>';
			echo '<td>' . esc_html( gmdate( 'F Y', strtotime( $defaulter['invoice_month'] . '-01' ) ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $balance, 2 ) ) . '</td>';
			
			if ( 'partial' === $defaulter['status'] ) {
				echo '<td><span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:bold;">Partial</span></td>';
			} else {
				echo '<td><span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:bold;">Unpaid</span></td>';
			}
			
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private static function render_reports_tab(): void {
		echo '<h2>Collection Report</h2>';
		echo '<p>Total collected vs total due report. (Implementation pending frontend UI integration)</p>';
	}

	private static function render_diagnostics_tab(): void {
		global $wpdb;
		
		echo '<h2>Fee Assignment Diagnostics</h2>';
		echo '<p>This shows exactly how the automatic assignment system works by displaying which students match each fee structure.</p>';
		
		// Get all fee structures
		$structures = $wpdb->get_results( "SELECT fs.*, c.name as class_name FROM " . DatabaseHandler::fee_structure_table() . " fs LEFT JOIN " . DatabaseHandler::classes_table() . " c ON fs.class_id = c.id ORDER BY fs.id", ARRAY_A );
		
		if ( empty( $structures ) ) {
			echo '<div class="notice notice-warning inline"><p>No fee structures exist yet. Create one to see the assignment matching.</p></div>';
			return;
		}
		
		foreach ( $structures as $fs ) {
			echo '<div style="margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; background: #fafbfc;">';
			echo '<h3 style="margin-top: 0;">Structure ID ' . esc_html( $fs['id'] ) . ': ' . esc_html( $fs['fee_title'] ) . '</h3>';
			echo '<p><strong>Class:</strong> ' . esc_html( $fs['class_name'] ?: 'All Classes' ) . ' (ID: ' . esc_html( $fs['class_id'] ) . ')</p>';
			echo '<p><strong>Amount:</strong> ' . esc_html( number_format_i18n( (float) $fs['amount'], 2 ) ) . ' | <strong>Frequency:</strong> ' . esc_html( ucfirst( $fs['frequency'] ) ) . '</p>';
			
			// Show which students will get this fee
			$students_table = DatabaseHandler::students_table();
			$classes_table = DatabaseHandler::classes_table();
			$fs_class_id = (int) $fs['class_id'];
			
			$query = "
				SELECT s.id, s.name, s.class_id, c.name as class_name, s.status
				FROM {$students_table} s
				LEFT JOIN {$classes_table} c ON s.class_id = c.id
				WHERE s.status = 'active' AND ({$fs_class_id} = 0 OR s.class_id = {$fs_class_id})
				ORDER BY s.name ASC
			";
			
			$students = $wpdb->get_results( $query, ARRAY_A );
			
			if ( empty( $students ) ) {
				echo '<p style="color: #666; font-style: italic;">No active students match this structure.</p>';
			} else {
				echo '<p style="margin: 10px 0;"><strong>Eligible Students (' . count( $students ) . '):</strong></p>';
				echo '<ul style="margin: 5px 0; padding-left: 20px;">';
				foreach ( $students as $student ) {
					echo '<li>';
					echo esc_html( $student['name'] ) . ' (ID: ' . esc_html( $student['id'] ) . ', Class: ' . esc_html( $student['class_name'] ?: 'N/A' ) . ')';
					
					// Check if invoice already exists for this month
					$current_month = current_time( 'Y-m' );
					$invoice_table = DatabaseHandler::fee_invoices_table();
					$invoice_exists = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM {$invoice_table} WHERE student_id = %d AND fee_structure_id = %d AND invoice_month = %s",
						$student['id'], $fs['id'], $current_month
					) );
					
					if ( $invoice_exists ) {
						echo ' <span style="color: green; font-weight: bold;">✓ Invoice exists for ' . esc_html( $current_month ) . '</span>';
					} else {
						echo ' <span style="color: orange;">⚠ No invoice for ' . esc_html( $current_month ) . ' yet</span>';
					}
					
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		
		echo '<div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 3px;">';
		echo '<strong>How it works:</strong>';
		echo '<ul>';
		echo '<li>When you click "Generate Current Month Invoices", the system performs this automatic matching for all structures at once.</li>';
		echo '<li>An invoice is created for each eligible student and fee structure pair.</li>';
		echo '<li>Students are "assigned" implicitly through class matching, not through manual database entries.</li>';
		echo '<li>If a student changes classes, they automatically get different fees on the next generation cycle.</li>';
		echo '</ul>';
		echo '</div>';
	}
}
