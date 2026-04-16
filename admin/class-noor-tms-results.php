<?php
/**
 * Exam Results management – class-based navigation, add result form, AJAX handlers.
 *
 * URL structure:
 *   ?page=noor-tms-results               → Class overview cards
 *   ?page=noor-tms-results&class_id=X    → Results for class X (+ Add Result form)
 *   ?page=noor-tms-results&class_id=X&student_id=Y  → Filtered to one student
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Results
 */
class Results {

	// -----------------------------------------------------------------------
	// Router
	// -----------------------------------------------------------------------

	public function page_results(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$class_id = (int) ( $_GET['class_id'] ?? 0 );

		if ( $class_id ) {
			$this->page_class_results( $class_id );
		} else {
			$this->page_class_overview();
		}
	}

	// -----------------------------------------------------------------------
	// Class overview (landing page)
	// -----------------------------------------------------------------------

	private function page_class_overview(): void {
		$classes = DatabaseHandler::get_classes();
		?>
		<div class="wrap noor-tms-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Exam Results', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( empty( $classes ) ) : ?>
				<div class="noor-tms-card">
					<p>
						<?php
						printf(
							/* translators: %s: link to create class */
							esc_html__( 'No classes found. %s first, then add students to it.', 'noor-tms' ),
							'<a href="' . esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'new' ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create a class', 'noor-tms' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Select a class to view and add exam results.', 'noor-tms' ); ?>
				</p>
				<div class="noor-class-grid">
					<?php foreach ( $classes as $cls ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'noor-tms-results', 'class_id' => $cls['id'] ], admin_url( 'admin.php' ) ) ); ?>"
						   class="noor-class-card noor-class-card--link">
							<div class="noor-class-card__header">
								<h3 class="noor-class-card__name"><?php echo esc_html( $cls['name'] ); ?></h3>
								<span class="noor-class-card__meta">
									<?php echo esc_html(
										sprintf(
											/* translators: %d: number of subjects */
											_n( '%d subject', '%d subjects', (int) $cls['subject_count'], 'noor-tms' ),
											(int) $cls['subject_count']
										)
									); ?>
								</span>
							</div>
							<span class="noor-class-card__cta"><?php esc_html_e( 'View Results →', 'noor-tms' ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Class results view
	// -----------------------------------------------------------------------

	private function page_class_results( int $class_id ): void {
		$class = DatabaseHandler::get_class( $class_id );
		if ( ! $class ) {
			wp_die( esc_html__( 'Class not found.', 'noor-tms' ) );
		}

		$exam_date = sanitize_text_field( $_GET['exam_date'] ?? '' );
		$subjects  = DatabaseHandler::get_subjects_by_class( $class_id );
		$students  = DatabaseHandler::get_students_dropdown( $class_id );
		$exam_dates = DatabaseHandler::get_exam_dates_by_class( $class_id );
		$summary   = $exam_date ? DatabaseHandler::get_results_summary_by_class( $class_id, $exam_date ) : [];

		$opts       = Settings::get_options();
		$is_ctc     = ( $opts['gateway_provider'] ?? 'click_to_chat' ) === 'click_to_chat';
		$student_id = 0; // no pre-selection; populated from JS after marks are added
		?>
		<div class="wrap noor-tms-wrap">
			<!-- Breadcrumb -->
			<p class="noor-breadcrumb">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=noor-tms-results' ) ); ?>">
					&larr; <?php esc_html_e( 'All Classes', 'noor-tms' ); ?>
				</a>
			</p>

			<h1><?php echo esc_html( $class['name'] ); ?> &ndash; <?php esc_html_e( 'Exam Results', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<!-- ============================================================
			     Add Exam Results Form  (one mark per subject in one go)
			     ============================================================ -->
			<div class="noor-tms-card">
				<h2><?php esc_html_e( 'Add Exam Results', 'noor-tms' ); ?></h2>

				<?php if ( empty( $students ) ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<?php
							printf(
								/* translators: %s: link to add student page */
								esc_html__( 'No active students in this class. %s to this class first.', 'noor-tms' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=noor-tms-add-student' ) ) . '">' . esc_html__( 'Add a student', 'noor-tms' ) . '</a>'
							);
							?>
						</p>
					</div>
				<?php elseif ( empty( $subjects ) ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<?php
							printf(
								/* translators: %s: link to edit class */
								esc_html__( 'No subjects defined for this class. %s to add subjects first.', 'noor-tms' ),
								'<a href="' . esc_url( add_query_arg( [ 'page' => 'noor-tms-classes', 'action' => 'edit', 'class_id' => $class_id ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit class', 'noor-tms' ) . '</a>'
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<form id="noor-tms-result-form" method="post" novalidate>
						<?php wp_nonce_field( 'noor_tms_save_result_ajax', 'noor_tms_result_nonce' ); ?>
						<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />

						<!-- Student + Date selectors -->
						<table class="form-table" role="presentation" style="margin-bottom:0;">
							<tr>
								<th scope="row" style="width:160px;">
									<label for="result_student_id"><?php esc_html_e( 'Student', 'noor-tms' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<select id="result_student_id" name="student_id" required class="regular-text">
										<option value=""><?php esc_html_e( '— Select Student —', 'noor-tms' ); ?></option>
										<?php foreach ( $students as $s ) : ?>
											<option value="<?php echo esc_attr( $s['id'] ); ?>"
												<?php selected( $student_id, (int) $s['id'] ); ?>>
												<?php echo esc_html( $s['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="result_exam_date"><?php esc_html_e( 'Exam Date', 'noor-tms' ); ?></label>
								</th>
								<td>
									<input type="date" id="result_exam_date" name="exam_date"
										value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
										class="regular-text" />
								</td>
							</tr>
						</table>

						<!-- Per-subject marks table -->
						<h3 style="margin:16px 0 8px;"><?php esc_html_e( 'Subject Marks', 'noor-tms' ); ?></h3>
						<table class="wp-list-table widefat fixed noor-marks-entry-table" style="max-width:600px;">
							<thead>
								<tr>
									<th style="width:50%;"><?php esc_html_e( 'Subject', 'noor-tms' ); ?></th>
									<th><?php esc_html_e( 'Marks Obtained', 'noor-tms' ); ?></th>
									<th><?php esc_html_e( 'Out of', 'noor-tms' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $subjects as $i => $sub ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $sub['subject_name'] ); ?></strong>
											<input type="hidden"
												name="subjects[<?php echo esc_attr( $i ); ?>][subject]"
												value="<?php echo esc_attr( $sub['subject_name'] ); ?>" />
										</td>
										<td>
											<input type="number"
												name="subjects[<?php echo esc_attr( $i ); ?>][obtained]"
												min="0" max="9999" step="0.5"
												class="small-text noor-obtained-input"
												placeholder="<?php esc_attr_e( '0', 'noor-tms' ); ?>" />
										</td>
										<td>
											<input type="number"
												name="subjects[<?php echo esc_attr( $i ); ?>][total]"
												min="1" max="9999" step="0.5"
												class="small-text"
												value="100" />
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="noor-form-actions" style="margin-top:16px;">
							<button type="submit" id="noor-save-result-btn" class="button button-primary">
								<?php esc_html_e( 'Save All Results', 'noor-tms' ); ?>
							</button>
							<span id="noor-result-feedback" class="noor-ajax-feedback" aria-live="polite"></span>
							<?php if ( $is_ctc ) : ?>
							<a id="noor-wa-report-btn" href="#" target="_blank" rel="noopener"
							   class="button noor-wa-btn" style="display:none;margin-left:8px;">
								&#128172; <?php esc_html_e( 'Send WhatsApp Report', 'noor-tms' ); ?>
							</a>
							<?php endif; ?>
						</div>
					</form>
				<?php endif; ?>
			</div>

			<!-- ============================================================
			     Student Report Summary
			     ============================================================ -->
			<?php if ( ! empty( $summary ) ) :
				// Collect all unique subject names for column headers.
				$all_subjects = [];
				foreach ( $summary as $s_data ) {
					foreach ( $s_data['entries'] as $entry ) {
						$all_subjects[ $entry['subject'] ] = true;
					}
				}
				$all_subjects = array_keys( $all_subjects );
				sort( $all_subjects );
				$col_count = count( $all_subjects ) + 4 + ( $is_ctc ? 1 : 0 );
			?>
			<div class="noor-tms-card" style="overflow-x:auto;">
				<h2 style="margin-bottom:12px;"><?php esc_html_e( 'Student Report Summary', 'noor-tms' ); ?></h2>
				<table class="wp-list-table widefat striped noor-tms-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Student', 'noor-tms' ); ?></th>
							<?php foreach ( $all_subjects as $subj ) : ?>
								<th><?php echo esc_html( $subj ); ?></th>
							<?php endforeach; ?>
							<th><?php esc_html_e( 'Total', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( '%', 'noor-tms' ); ?></th>
							<th><?php esc_html_e( 'Result', 'noor-tms' ); ?></th>
							<?php if ( $is_ctc ) : ?>
								<th><?php esc_html_e( 'WhatsApp', 'noor-tms' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $summary as $s_data ) :
							$by_subj = [];
							foreach ( $s_data['entries'] as $entry ) {
								$by_subj[ $entry['subject'] ] = $entry;
							}
							$overall_pct    = $s_data['sum_total'] > 0
								? round( ( $s_data['sum_obtained'] / $s_data['sum_total'] ) * 100, 1 )
								: 0;
							$pass           = $overall_pct >= 50;
							$exam_date_disp = $s_data['exam_date'] ?? '';

							// Build WA report URL for this student.
							$wa_report_url = '';
							if ( $is_ctc && ! empty( $s_data['phone'] ) ) {
								$wa_report_url = WhatsApp::generate_report_url(
									[ 'name' => $s_data['name'], 'parent_phone' => $s_data['phone'] ],
									$s_data['entries'],
									$exam_date_disp
								);
							}
						?>
						<tr>
							<td><strong><?php echo esc_html( $s_data['name'] ); ?></strong></td>
							<?php foreach ( $all_subjects as $subj ) :
								if ( isset( $by_subj[ $subj ] ) ) :
									$e   = $by_subj[ $subj ];
									$pct = $e['total'] > 0 ? round( ( $e['obtained'] / $e['total'] ) * 100, 1 ) : 0;
								?>
								<td>
									<?php echo esc_html( $e['obtained'] . ' / ' . $e['total'] ); ?>
									<small class="noor-pct noor-pct-<?php echo $pct >= 50 ? 'pass' : 'fail'; ?>">
										<?php echo esc_html( $pct . '%' ); ?>
									</small>
								</td>
								<?php else : ?>
								<td><span class="description">&mdash;</span></td>
								<?php endif; ?>
							<?php endforeach; ?>
							<td><strong><?php echo esc_html( $s_data['sum_obtained'] . ' / ' . $s_data['sum_total'] ); ?></strong></td>
							<td>
								<span class="noor-pct noor-pct-<?php echo $pass ? 'pass' : 'fail'; ?>">
									<strong><?php echo esc_html( $overall_pct . '%' ); ?></strong>
								</span>
							</td>
							<td>
								<span class="noor-pct noor-pct-<?php echo $pass ? 'pass' : 'fail'; ?>">
									<?php echo $pass ? esc_html__( 'Pass', 'noor-tms' ) : esc_html__( 'Fail', 'noor-tms' ); ?>
								</span>
							</td>
							<?php if ( $is_ctc ) : ?>
							<td>
								<?php if ( $wa_report_url ) : ?>
									<a href="<?php echo esc_url( $wa_report_url ); ?>" target="_blank" rel="noopener"
									   class="button button-small noor-wa-btn">
										&#128172; <?php esc_html_e( 'Send Report', 'noor-tms' ); ?>
									</a>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'No phone', 'noor-tms' ); ?></span>
								<?php endif; ?>
							</td>
							<?php endif; ?>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php else : ?>
			<div class="noor-tms-card">
				<p><?php esc_html_e( 'No results recorded for this class yet. Use the form above to add exam marks.', 'noor-tms' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------------

	/**
	 * Handle AJAX result save.
	 */
	public function ajax_save_result(): void {
		check_ajax_referer( 'noor_tms_save_result_ajax', 'noor_tms_result_nonce' );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );
		if ( ! $is_manager && ! $is_teacher ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$student_id     = (int)   ( $_POST['student_id']     ?? 0 );
		$class_id       = (int)   ( $_POST['class_id']       ?? 0 );
		$subject        = sanitize_text_field( $_POST['subject']        ?? '' );
		$marks_obtained = (float) ( $_POST['marks_obtained'] ?? 0 );
		$total_marks    = (float) ( $_POST['total_marks']    ?? 100 );
		$exam_date      = sanitize_text_field( $_POST['exam_date']      ?? current_time( 'Y-m-d' ) );

		if ( $is_teacher && ! $is_manager ) {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			if ( ! in_array( $class_id, $class_ids, true ) ) {
				wp_send_json_error( [ 'message' => __( 'You are not assigned to this class.', 'noor-tms' ) ], 403 );
			}
		}

		if ( ! $student_id || empty( $subject ) ) {
			wp_send_json_error( [ 'message' => __( 'Student and subject are required.', 'noor-tms' ) ] );
		}
		if ( $marks_obtained < 0 || $total_marks <= 0 || $marks_obtained > $total_marks ) {
			wp_send_json_error( [ 'message' => __( 'Invalid marks values.', 'noor-tms' ) ] );
		}

		$student = DatabaseHandler::get_student( $student_id );
		if ( ! $student ) {
			wp_send_json_error( [ 'message' => __( 'Student not found.', 'noor-tms' ) ] );
		}
		if ( $is_teacher && ! $is_manager && (int) $student['class_id'] !== $class_id ) {
			wp_send_json_error( [ 'message' => __( 'Student does not belong to the selected class.', 'noor-tms' ) ], 403 );
		}

		$result_id = DatabaseHandler::insert_result( compact(
			'student_id', 'subject', 'marks_obtained', 'total_marks', 'exam_date'
		) );

		if ( ! $result_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save result.', 'noor-tms' ) ] );
		}

		$pct = $total_marks > 0 ? round( ( $marks_obtained / $total_marks ) * 100, 1 ) : 0;

		// Build WhatsApp CtC URL.
		$opts   = Settings::get_options();
		$is_ctc = ( $opts['gateway_provider'] ?? 'click_to_chat' ) === 'click_to_chat';
		$wa_url = '';
		if ( $is_ctc && ! empty( $student['parent_phone'] ) ) {
			$wa_url = WhatsApp::generate_click_to_chat_url(
				$student,
				compact( 'subject', 'marks_obtained', 'total_marks', 'exam_date' )
			);
		}

		// Dispatch API notification for non-CtC providers.
		if ( ! $is_ctc ) {
			WhatsApp::send_notification( $student, compact( 'subject', 'marks_obtained', 'total_marks', 'exam_date' ) );
			DatabaseHandler::mark_notification_sent( $result_id );
		}

		wp_send_json_success( [
			'message' => __( 'Result saved successfully.', 'noor-tms' ),
			'wa_url'  => $wa_url,
			'is_ctc'  => $is_ctc,
			'result'  => [
				'id'             => $result_id,
				'student_name'   => $student['name'],
				'subject'        => $subject,
				'marks_obtained' => $marks_obtained,
				'total_marks'    => $total_marks,
				'pct'            => $pct,
				'exam_date'      => $exam_date,
				'parent_phone'   => $student['parent_phone'] ?? '',
			],
		] );
	}

	/**
	 * Handle AJAX bulk report save (all subjects for one student in one form submit).
	 *
	 * Expects POST fields:
	 *   noor_tms_result_nonce, class_id, student_id, exam_date
	 *   subjects[0][subject], subjects[0][obtained], subjects[0][total]
	 *   subjects[1][subject], subjects[1][obtained], subjects[1][total]  …
	 */
	public function ajax_save_report(): void {
		check_ajax_referer( 'noor_tms_save_result_ajax', 'noor_tms_result_nonce' );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );
		if ( ! $is_manager && ! $is_teacher ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		$student_id   = (int) ( $_POST['student_id'] ?? 0 );
		$class_id     = (int) ( $_POST['class_id']   ?? 0 );
		$exam_date    = sanitize_text_field( $_POST['exam_date'] ?? current_time( 'Y-m-d' ) );
		$subjects_raw = (array) ( $_POST['subjects']  ?? [] );

		if ( $is_teacher && ! $is_manager ) {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			if ( ! in_array( $class_id, $class_ids, true ) ) {
				wp_send_json_error( [ 'message' => __( 'You are not assigned to this class.', 'noor-tms' ) ], 403 );
			}
		}

		if ( ! $student_id ) {
			wp_send_json_error( [ 'message' => __( 'Please select a student.', 'noor-tms' ) ] );
		}

		$student = DatabaseHandler::get_student( $student_id );
		if ( ! $student ) {
			wp_send_json_error( [ 'message' => __( 'Student not found.', 'noor-tms' ) ] );
		}
		if ( $is_teacher && ! $is_manager && (int) $student['class_id'] !== $class_id ) {
			wp_send_json_error( [ 'message' => __( 'Student does not belong to the selected class.', 'noor-tms' ) ], 403 );
		}

		$saved = [];

		foreach ( $subjects_raw as $row ) {
			$subject  = sanitize_text_field( $row['subject']  ?? '' );
			$obtained = isset( $row['obtained'] ) && $row['obtained'] !== '' ? (float) $row['obtained'] : null;
			$total    = isset( $row['total'] )    && $row['total']    !== '' ? (float) $row['total']    : 100.0;

			// Skip rows where obtained is blank (admin left that subject empty).
			if ( empty( $subject ) || null === $obtained ) {
				continue;
			}

			if ( $obtained < 0 || $total <= 0 ) {
				continue;
			}

			$result_id = DatabaseHandler::insert_result( [
				'student_id'     => $student_id,
				'subject'        => $subject,
				'marks_obtained' => $obtained,
				'total_marks'    => $total,
				'exam_date'      => $exam_date,
			] );

			if ( $result_id ) {
				$pct     = $total > 0 ? round( ( $obtained / $total ) * 100, 1 ) : 0;
				$saved[] = [
					'id'       => $result_id,
					'subject'  => $subject,
					'obtained' => $obtained,
					'total'    => $total,
					'pct'      => $pct,
				];
			}
		}

		if ( empty( $saved ) ) {
			wp_send_json_error( [ 'message' => __( 'No valid marks entered. Please fill in at least one subject.', 'noor-tms' ) ] );
		}

		// Build WhatsApp click-to-chat URL for the full report.
		$opts   = Settings::get_options();
		$is_ctc = ( $opts['gateway_provider'] ?? 'click_to_chat' ) === 'click_to_chat';
		$wa_url = '';
		if ( $is_ctc && ! empty( $student['parent_phone'] ) ) {
			$wa_url = WhatsApp::generate_report_url( $student, $saved, $exam_date );
		}

		// Dispatch API notification for non-CtC providers.
		if ( ! $is_ctc && ! empty( $saved ) ) {
			WhatsApp::send_notification( $student, [
				'subject'        => implode( ', ', array_column( $saved, 'subject' ) ),
				'marks_obtained' => array_sum( array_column( $saved, 'obtained' ) ),
				'total_marks'    => array_sum( array_column( $saved, 'total' ) ),
				'exam_date'      => $exam_date,
			] );
			foreach ( $saved as $entry ) {
				DatabaseHandler::mark_notification_sent( $entry['id'] );
			}
		}

		wp_send_json_success( [
			'message'      => sprintf(
				/* translators: %d: number of subjects saved */
				_n( '%d result saved.', '%d results saved.', count( $saved ), 'noor-tms' ),
				count( $saved )
			),
			'saved'        => $saved,
			'student_name' => $student['name'],
			'exam_date'    => $exam_date,
			'wa_url'       => $wa_url,
			'is_ctc'       => $is_ctc,
		] );
	}

	/**
	 * Handle AJAX result deletion.
	 */
	public function ajax_delete_result(): void {
		$result_id = (int) ( $_POST['result_id'] ?? 0 );

		check_ajax_referer( 'noor_tms_ajax', 'nonce' );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );
		if ( ! $is_manager && ! $is_teacher ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
		}

		if ( $result_id && $is_teacher && ! $is_manager ) {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT r.id, st.class_id
					   FROM " . DatabaseHandler::results_table() . " r
					   JOIN " . DatabaseHandler::students_table() . " st ON st.id = r.student_id
					  WHERE r.id = %d",
					$result_id
					),
				OBJECT
			);
			if ( ! $row ) {
				wp_send_json_error( [ 'message' => __( 'Result not found.', 'noor-tms' ) ] );
			}
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			if ( ! in_array( (int) $row->class_id, $class_ids, true ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'noor-tms' ) ], 403 );
			}
		}

		if ( ! $result_id || ! DatabaseHandler::delete_result( $result_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete result.', 'noor-tms' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Result deleted.', 'noor-tms' ) ] );
	}
}
