<?php
/**
 * Front-end results – class detail view with add-marks form and summary table.
 *
 * Variables in scope:
 *   $class      array   Class row.
 *   $class_id   int
 *   $student_id int     Pre-selected student (optional).
 *   $exam_date  string  Selected exam date filter (optional).
 *   $action     string  'list' or 'add'.
 *   $exam_dates array   List of distinct past exam dates.
 *   $subjects   array
 *   $students   array   Dropdown data (active students only).
 *   $summary    array   Per-student result summary.
 *   $opts       array   Plugin settings.
 *   $is_ctc     bool    True when using click-to-chat WhatsApp.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

use Noor_TMS\Admin\WhatsApp;

if ( 'add' === $action ) {
$page_title     = esc_html__( 'Add Exam Results', 'noor-tms' ) . ' — ' . esc_html( $class['name'] );
$active_nav     = 'results';
$topbar_actions = '<a href="' . esc_url( add_query_arg( [ 'class_id' => $class_id ], home_url( '/tms-results/' ) ) ) . '" class="noor-btn noor-btn--secondary">'
. '&larr; ' . esc_html__( 'Back to Results', 'noor-tms' ) . '</a>';
} elseif ( $exam_date ) {
$page_title     = esc_html( $class['name'] ) . ' — ' . date_i18n( get_option( 'date_format' ), strtotime( $exam_date ) );
$active_nav     = 'results';
$topbar_actions = '<a href="' . esc_url( add_query_arg( [ 'class_id' => $class_id ], home_url( '/tms-results/' ) ) ) . '" class="noor-btn noor-btn--secondary">'
. '&larr; ' . esc_html__( 'Back to Dates', 'noor-tms' ) . '</a>';
$topbar_actions .= '<a href="' . esc_url( add_query_arg( [ 'class_id' => $class_id, 'tms_action' => 'add' ], home_url( '/tms-results/' ) ) ) . '" class="noor-btn noor-btn--primary">+ ' . esc_html__( 'Add Exam Results', 'noor-tms' ) . '</a>';
} else {
$page_title     = esc_html( $class['name'] ) . ' — ' . __( 'Exam Results', 'noor-tms' );
$active_nav     = 'results';
$topbar_actions = '<a href="' . esc_url( home_url( '/tms-results/' ) ) . '" class="noor-btn noor-btn--secondary">'
. '&larr; ' . esc_html__( 'All Classes', 'noor-tms' ) . '</a>';
$topbar_actions .= '<a href="' . esc_url( add_query_arg( [ 'class_id' => $class_id, 'tms_action' => 'add' ], home_url( '/tms-results/' ) ) ) . '" class="noor-btn noor-btn--primary">+ ' . esc_html__( 'Add Exam Results', 'noor-tms' ) . '</a>';
}

include __DIR__ . '/layout.php';
?>

<?php if ( 'add' === $action ) : ?>
<!-- ======================================================================
     Add Exam Results Form
     ====================================================================== -->
<div class="noor-card">
<h2><?php esc_html_e( 'Add Exam Results', 'noor-tms' ); ?></h2>

<?php if ( empty( $students ) ) : ?>
<div class="noor-notice noor-notice--warning">
<?php
printf(
/* translators: %s: link */
esc_html__( 'No active students in this class. %s to this class first.', 'noor-tms' ),
'<a href="' . esc_url( add_query_arg( 'tms_action', 'add', home_url( '/tms-students/' ) ) ) . '">'
. esc_html__( 'Add a student', 'noor-tms' ) . '</a>'
);
?>
</div>
<?php elseif ( empty( $subjects ) ) : ?>
<div class="noor-notice noor-notice--warning">
<?php
printf(
/* translators: %s: link */
esc_html__( 'No subjects defined for this class. %s to add subjects first.', 'noor-tms' ),
'<a href="' . esc_url( add_query_arg( [ 'tms_action' => 'edit', 'class_id' => $class_id ], home_url( '/tms-classes/' ) ) ) . '">'
. esc_html__( 'Edit class', 'noor-tms' ) . '</a>'
);
?>
</div>
<?php else : ?>
<form id="noor-tms-result-form" method="post" novalidate>
<?php wp_nonce_field( 'noor_tms_save_result_ajax', 'noor_tms_result_nonce' ); ?>
<input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />

<div class="noor-form-row" style="max-width:600px;">
<div class="noor-form-group">
<label for="result_student_id"><?php esc_html_e( 'Student', 'noor-tms' ); ?> <span class="required">*</span></label>
<select id="result_student_id" name="student_id" required>
<option value=""><?php esc_html_e( '— Select Student —', 'noor-tms' ); ?></option>
<?php foreach ( $students as $s ) : ?>
<option value="<?php echo esc_attr( $s['id'] ); ?>"
<?php selected( $student_id, (int) $s['id'] ); ?>>
<?php echo esc_html( $s['name'] ); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="noor-form-group">
<label for="result_exam_date"><?php esc_html_e( 'Exam Date', 'noor-tms' ); ?></label>
<input type="date" id="result_exam_date" name="exam_date"
   value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
</div>
</div>

<h3><?php esc_html_e( 'Subject Marks', 'noor-tms' ); ?></h3>

<table class="noor-marks-table">
<thead>
<tr>
<th><?php esc_html_e( 'Subject', 'noor-tms' ); ?></th>
<th><?php esc_html_e( 'Obtained', 'noor-tms' ); ?></th>
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
   placeholder="0" />
</td>
<td>
<input type="number"
   name="subjects[<?php echo esc_attr( $i ); ?>][total]"
   min="1" max="9999" step="0.5"
   value="100" />
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="noor-form-actions" style="margin-top:20px;">
<button type="submit" id="noor-save-result-btn" class="noor-btn noor-btn--primary">
<?php esc_html_e( 'Save All Results', 'noor-tms' ); ?>
</button>

<span id="noor-result-feedback" class="noor-ajax-feedback" aria-live="polite"></span>

<?php if ( $is_ctc ) : ?>
<a id="noor-wa-report-btn" href="#" target="_blank" rel="noopener"
   class="noor-btn noor-btn--whatsapp" style="display:none;">
&#128172; <?php esc_html_e( 'Send WhatsApp Report', 'noor-tms' ); ?>
</a>
<?php endif; ?>
</div>
</form>
<?php endif; ?>
</div>

<?php elseif ( $exam_date ) : ?>
<!-- ======================================================================
     Student Report Summary Table (Specific Exam Date)
     ====================================================================== -->
<?php if ( empty( $summary ) ) : ?>
<div class="noor-empty">
<p><?php esc_html_e( 'No results found for this date.', 'noor-tms' ); ?></p>
</div>
<?php else :
// Build unique subject column list.
$all_subjects = [];
foreach ( $summary as $s_data ) {
foreach ( $s_data['entries'] as $entry ) {
$all_subjects[ $entry['subject'] ] = true;
}
}
$all_subjects = array_keys( $all_subjects );
sort( $all_subjects );
?>

<div class="noor-card" style="overflow-x:auto;">
<h2><?php esc_html_e( 'Student Report Summary', 'noor-tms' ); ?></h2>

<div class="noor-table-wrap" style="border:none;">
<table class="noor-table">
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
<th><?php esc_html_e( 'Actions', 'noor-tms' ); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ( $summary as $s_data ) :
$by_subj     = [];
foreach ( $s_data['entries'] as $entry ) {
$by_subj[ $entry['subject'] ] = $entry;
}
$overall_pct    = $s_data['sum_total'] > 0
? round( ( $s_data['sum_obtained'] / $s_data['sum_total'] ) * 100, 1 )
: 0;
$pass           = $overall_pct >= 50;
$exam_date_disp = $s_data['exam_date'] ?? '';
$wa_report_url  = '';
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
<?php echo esc_html( $e['obtained'] . '/' . $e['total'] ); ?><br>
<small style="color:var(--tms-muted);"><?php echo $pct; ?>%</small>
</td>
<?php else : ?>
<td>&ndash;</td>
<?php endif;
endforeach; ?>

<td><strong><?php echo esc_html( $s_data['sum_obtained'] . '/' . $s_data['sum_total'] ); ?></strong></td>
<td><strong><?php echo $overall_pct; ?>%</strong></td>
<td>
<?php if ( $pass ) : ?>
<span class="noor-badge noor-badge--success"><?php esc_html_e( 'Pass', 'noor-tms' ); ?></span>
<?php else : ?>
<span class="noor-badge noor-badge--danger"><?php esc_html_e( 'Fail', 'noor-tms' ); ?></span>
<?php endif; ?>
</td>
<?php if ( $is_ctc ) : ?>
<td>
<?php if ( $wa_report_url ) : ?>
<a href="<?php echo esc_url( $wa_report_url ); ?>"
   class="noor-btn noor-btn--small noor-btn--whatsapp noor-btn--icon-only"
   target="_blank" rel="noopener"
   title="<?php esc_attr_e( 'Send Report via WhatsApp', 'noor-tms' ); ?>">
&#128172;
</a>
<?php else : ?>
<span style="color:var(--tms-muted);font-size:12px;"><?php esc_html_e( 'No phone', 'noor-tms' ); ?></span>
<?php endif; ?>
</td>
<?php endif; ?>
<td>
<a href="<?php echo esc_url( add_query_arg( [ 'tms_action' => 'add', 'class_id' => $class_id, 'student_id' => $s_data['student_id'] ], home_url( '/tms-results/' ) ) ); ?>"
   class="noor-btn noor-btn--small noor-btn--secondary">
<?php esc_html_e( 'Edit', 'noor-tms' ); ?>
</a>
<button type="button" class="noor-btn noor-btn--small noor-btn--danger noor-delete-result-btn"
data-student="<?php echo esc_attr( $s_data['student_id'] ); ?>"
data-date="<?php echo esc_attr( $exam_date_disp ); ?>"
data-nonce="<?php echo esc_attr( wp_create_nonce( 'noor_tms_delete_result' ) ); ?>">
&times;
</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php else : ?>
<!-- ======================================================================
     List of past exam dates
     ====================================================================== -->
<p style="margin:0 0 20px;color:var(--tms-muted);font-size:14px;">
<?php esc_html_e( 'Select an exam date to view its results.', 'noor-tms' ); ?>
</p>

<?php if ( empty( $exam_dates ) ) : ?>
<div class="noor-empty">
<span class="noor-empty-icon">&#128197;</span>
<p><?php esc_html_e( 'No previous exams found.', 'noor-tms' ); ?></p>
<a href="<?php echo esc_url( add_query_arg( [ 'class_id' => $class_id, 'tms_action' => 'add' ], home_url( '/tms-results/' ) ) ); ?>"
   class="noor-btn noor-btn--primary">+ <?php esc_html_e( 'Add Exam Results', 'noor-tms' ); ?></a>
</div>
<?php else : ?>
<div class="noor-class-grid">
<?php foreach ( $exam_dates as $date_row ) : 
$date_str = $date_row['exam_date'];
$count    = $date_row['results_count'] ?? 0;
?>
<a href="<?php echo esc_url( add_query_arg( [ 'class_id' => $class_id, 'exam_date' => $date_str ], home_url( '/tms-results/' ) ) ); ?>"
   class="noor-class-card noor-class-card--link">
<div class="noor-class-card__header">
<h3 class="noor-class-card__name">
<?php echo date_i18n( get_option( 'date_format' ), strtotime( $date_str ) ); ?>
</h3>
<span class="noor-class-card__meta">
<?php echo esc_html( sprintf(
_n( '%d Result', '%d Results', (int) $count, 'noor-tms' ),
(int) $count
) ); ?>
</span>
</div>
<span class="noor-class-card__cta">
<?php esc_html_e( 'View Details →', 'noor-tms' ); ?>
</span>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/layout-close.php'; ?>
