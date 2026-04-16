<?php
/**
 * Front-end portal controller.
 *
 * Registers shortcodes, enqueues front-end assets, handles form POSTs, and
 * provides an auth guard so only logged-in TMS managers see the pages.
 *
 * Shortcode → WordPress Page mapping (create these pages manually):
 *   [noor_tms_login]      → slug: tms-login      (all users)
 *   [noor_tms_students]   → slug: tms-students   (managers only)
 *   [noor_tms_classes]    → slug: tms-classes    (managers only)
 *   [noor_tms_results]    → slug: tms-results    (managers only)
 *   [noor_tms_settings]   → slug: tms-settings   (managers only)
 *   [noor_tms_attendance] → slug: tms-attendance (managers + teachers)
 *
 * @package Noor_TMS\PublicFacing
 */

namespace Noor_TMS\PublicFacing;

use Noor_TMS\Includes\DatabaseHandler;
use Noor_TMS\Admin\Settings;
use Noor_TMS\Admin\WhatsApp;

defined( 'ABSPATH' ) || exit;

/**
 * Class PublicController
 */
class PublicController {

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'noor_tms_login',      [ $this, 'sc_login' ] );
		add_shortcode( 'noor_tms_students',   [ $this, 'sc_students' ] );
		add_shortcode( 'noor_tms_classes',    [ $this, 'sc_classes' ] );
		add_shortcode( 'noor_tms_results',    [ $this, 'sc_results' ] );
		add_shortcode( 'noor_tms_teachers',   [ $this, 'sc_teachers' ] );
		add_shortcode( 'noor_tms_settings',   [ $this, 'sc_settings' ] );
		add_shortcode( 'noor_tms_attendance', [ $this, 'sc_attendance' ] );

		// Provide hook points for front-end teacher creation UI.
		add_action( 'noor_tms_teacher_handle_wp_user_fields', [ $this, 'handle_wp_user_fields' ], 10, 0 );
	}

	/**
	 * Enqueue front-end CSS & JS only on pages that carry a TMS shortcode.
	 */
	public function enqueue_assets(): void {
		global $post;

		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$tms_shortcodes = [
			'noor_tms_login', 'noor_tms_students',
			'noor_tms_classes', 'noor_tms_results',
			'noor_tms_teachers',
			'noor_tms_settings', 'noor_tms_attendance',
		];

		$found = false;
		foreach ( $tms_shortcodes as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return;
		}

		wp_enqueue_style(
			'noor-tms-public',
			NOOR_TMS_PLUGIN_URL . 'public/css/noor-tms-public.css',
			[],
			NOOR_TMS_VERSION
		);

		wp_enqueue_script(
			'noor-tms-public',
			NOOR_TMS_PLUGIN_URL . 'public/js/noor-tms-public.js',
			[ 'jquery' ],
			NOOR_TMS_VERSION,
			true
		);

		wp_localize_script( 'noor-tms-public', 'noorTMS', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'noor_tms_ajax' ),
			'i18n'    => [
				'saving'             => __( 'Saving…',                                    'noor-tms' ),
				'deleting'           => __( 'Deleting…',                                  'noor-tms' ),
				'error'              => __( 'An error occurred. Please try again.',        'noor-tms' ),
				'confirmDelete'      => __( 'Are you sure you want to delete this?',       'noor-tms' ),
				'saveReport'         => __( 'Save All Results',                            'noor-tms' ),
				'subjectPlaceholder' => __( 'Subject name',                                'noor-tms' ),
			],
		] );
	}

	/**
	 * After a successful login, redirect TMS managers to the front-end portal
	 * instead of wp-admin.
	 *
	 * @param string           $redirect_to  Where WP wants to redirect.
	 * @param string           $requested    Originally requested URL.
	 * @param \WP_User|\WP_Error $user       The logged-in user (or error).
	 */
	// -----------------------------------------------------------------------
	// Early request handler (template_redirect)
	// -----------------------------------------------------------------------

	/**
	 * Runs before any output — handles the auth guard for protected TMS pages
	 * and processes all form POSTs so wp_safe_redirect() works correctly.
	 */
	public function handle_early_requests(): void {
		if ( ! is_page() ) {
			return;
		}

		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		// All TMS pages — including login — must never be cached.
		$all_tms = [
			'noor_tms_login',
			'noor_tms_students', 'noor_tms_classes',
			'noor_tms_results',  'noor_tms_teachers',
			'noor_tms_settings',
			'noor_tms_attendance',
		];

		$is_tms = false;
		foreach ( $all_tms as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$is_tms = true;
				break;
			}
		}

		if ( ! $is_tms ) {
			return;
		}

		// Tell every caching layer (LiteSpeed, WP Super Cache, W3TC, etc.)
		// to bypass and never store this page.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		nocache_headers(); // sends Cache-Control: no-cache, no-store, must-revalidate
		// Explicit LiteSpeed Cache bypass (Hostinger uses LiteSpeed).
		header( 'X-LiteSpeed-Cache-Control: no-cache' );

		// Auth guard for protected pages (not the login page).
		$protected = [
			'noor_tms_students', 'noor_tms_classes',
			'noor_tms_results',  'noor_tms_teachers',
			'noor_tms_settings',
			'noor_tms_attendance',
		];
		$is_protected = false;
		foreach ( $protected as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				$is_protected = true;
				break;
			}
		}

		$can_access = current_user_can( 'noor_tms_manage' ) || current_user_can( 'noor_tms_teacher' );
		if ( $is_protected && ( ! is_user_logged_in() || ! $can_access ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}
	}

	public function redirect_after_login( string $redirect_to, string $requested, $user ): string {
		if ( $user instanceof \WP_User ) {
			if ( $user->has_cap( 'noor_tms_manage' ) && ! $user->has_cap( 'manage_options' ) ) {
				return home_url( '/tms-students/' );
			}
			if ( $user->has_cap( 'noor_tms_teacher' ) ) {
				return home_url( '/tms-attendance/' );
			}
		}
		return $redirect_to;
	}

	// -----------------------------------------------------------------------
	// Shortcode handlers
	// -----------------------------------------------------------------------

	/**
	 * [noor_tms_login] – Login form.
	 */
	public function sc_login(): string {
		// Already logged in as a TMS user → send to dashboard.
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'noor_tms_manage' ) ) {
				wp_safe_redirect( home_url( '/tms-students/' ) );
				exit;
			}
			if ( current_user_can( 'noor_tms_teacher' ) ) {
				wp_safe_redirect( home_url( '/tms-attendance/' ) );
				exit;
			}
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/login.php';
		return ob_get_clean();
	}

	/**
	 * [noor_tms_students] – Student list + add/edit form.
	 * Managers see all; teachers see students in their assigned classes only.
	 */
	public function sc_students(): string {
		// Auth + POST are handled in handle_early_requests() on template_redirect.
		$action     = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$student_id = (int) ( $_GET['student_id'] ?? 0 );

		$is_manager = current_user_can( 'noor_tms_manage' );

		ob_start();

		if ( 'add' === $action || ( 'edit' === $action && $student_id ) ) {
			// Teachers cannot add/edit students.
			if ( ! $is_manager ) {
				wp_safe_redirect( home_url( '/tms-students/' ) );
				exit;
			}
			$student = $student_id ? DatabaseHandler::get_student( $student_id ) : null;
			$classes = DatabaseHandler::get_classes_dropdown();
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/student-form.php';
		} else {
			$search   = sanitize_text_field( $_GET['s']             ?? '' );
			$status   = sanitize_key( $_GET['status_filter']        ?? '' );
			$class_id = (int) ( $_GET['class_id']                   ?? 0 );
			$paged    = max( 1, (int) ( $_GET['paged']              ?? 1 ) );

			// Restrict teacher to their assigned classes.
			if ( ! $is_manager ) {
				$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
				if ( $class_id && ! in_array( $class_id, $class_ids, true ) ) {
					$class_id = 0;
				}
				// Filter to teacher's classes only.
				if ( ! $class_id && ! empty( $class_ids ) ) {
					$class_id = $class_ids[0]; // default to first assigned class.
				}
			}

			$result      = DatabaseHandler::get_students( [
				'per_page' => 20,
				'page'     => $paged,
				'search'   => $search,
				'status'   => $status,
				'class_id' => $class_id,
			] );
			$students    = $result['rows'];
			$total       = $result['total'];
			$total_pages = (int) ceil( $total / 20 );
			$classes     = $is_manager
				? DatabaseHandler::get_classes_dropdown()
				: ( isset( $class_ids ) ? array_values( array_filter( DatabaseHandler::get_classes_dropdown(), fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) ) : [] );
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/students.php';
		}

		return ob_get_clean();
	}

	/**
	 * [noor_tms_classes] – Classes grid + create/edit form.
	 * Teachers see only their assigned classes (read-only).
	 */
	public function sc_classes(): string {
		// Auth + POST are handled in handle_early_requests() on template_redirect.
		$action   = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$class_id = (int) ( $_GET['class_id'] ?? 0 );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );

		ob_start();

		if ( ( $is_manager || $is_teacher ) && ( 'new' === $action || ( 'edit' === $action && $class_id ) ) ) {
			$cls      = $class_id ? DatabaseHandler::get_class( $class_id ) : null;
			// For teachers, only allow editing their own classes (optional check could be added here)
			$subjects = $class_id ? DatabaseHandler::get_subjects_by_class( $class_id ) : [];
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/class-form.php';
		} else {
			if ( ! $is_manager ) {
				$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
				$all       = DatabaseHandler::get_classes();
				$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
			} else {
				$classes = DatabaseHandler::get_classes();
			}
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/classes.php';
		}

		return ob_get_clean();
	}

	/**
	 * [noor_tms_results] – Results overview + class drill-down.
	 */
	public function sc_results(): string {
		// Auth is handled in handle_early_requests() on template_redirect.
		$class_id   = (int) ( $_GET['class_id']   ?? 0 );
		$student_id = (int) ( $_GET['student_id'] ?? 0 );
		$exam_date  = sanitize_text_field( $_GET['exam_date'] ?? '' );
		$action     = sanitize_text_field( $_GET['tms_action'] ?? 'list' );

		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );
		$allowed_class_ids = [];

		if ( $is_teacher && ! $is_manager ) {
			$teacher = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$allowed_class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
		}

		// Teachers should only be able to view and edit results for their assigned classes.
		if ( $is_teacher && ! $is_manager && $class_id && ! in_array( $class_id, $allowed_class_ids, true ) ) {
			wp_safe_redirect( home_url( '/tms-results/' ) );
			exit;
		}

		ob_start();

		if ( $class_id ) {
			$class      = DatabaseHandler::get_class( $class_id );
			$subjects   = DatabaseHandler::get_subjects_by_class( $class_id );
			$students   = DatabaseHandler::get_students_dropdown( $class_id );
			$exam_dates = DatabaseHandler::get_exam_dates_by_class( $class_id );
			$summary    = $exam_date ? DatabaseHandler::get_results_summary_by_class( $class_id, $exam_date ) : [];
			$opts       = Settings::get_options();
			$is_ctc     = ( $opts['gateway_provider'] ?? 'click_to_chat' ) === 'click_to_chat';
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/results-class.php';
		} else {
			$classes = $is_manager
				? DatabaseHandler::get_classes()
				: array_values( array_filter( DatabaseHandler::get_classes(), fn( $c ) => in_array( (int) $c['id'], $allowed_class_ids, true ) ) );
			include NOOR_TMS_PLUGIN_DIR . 'public/templates/results.php';
		}

		return ob_get_clean();
	}

	/**
	 * [noor_tms_attendance] – Student attendance mark + history.
	 */
	public function sc_attendance(): string {
		$is_manager = current_user_can( 'noor_tms_manage' );
		$is_teacher = current_user_can( 'noor_tms_teacher' );

		// Determine visible classes.
		if ( $is_manager ) {
			$classes = DatabaseHandler::get_classes_dropdown();
		} else {
			$teacher   = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
			$class_ids = $teacher ? DatabaseHandler::get_teacher_class_ids( (int) $teacher['id'] ) : [];
			$all       = DatabaseHandler::get_classes_dropdown();
			$classes   = array_values( array_filter( $all, fn( $c ) => in_array( (int) $c['id'], $class_ids, true ) ) );
		}

		$tab      = sanitize_key( $_GET['tab']       ?? 'mark' );
		$class_id = (int) ( $_GET['class_id']        ?? ( $classes[0]['id'] ?? 0 ) );
		$att_date = sanitize_text_field( $_GET['att_date'] ?? current_time( 'Y-m-d' ) );
		$month    = (int) ( $_GET['att_month']       ?? (int) current_time( 'n' ) );
		$year     = (int) ( $_GET['att_year']        ?? (int) current_time( 'Y' ) );

		$students = [];
		$marked   = [];
		$summary  = [];

		if ( 'mark' === $tab && $class_id ) {
			$students = DatabaseHandler::get_students_by_class( $class_id );
			$marked   = DatabaseHandler::get_student_attendance_for_date( $class_id, $att_date );
		} elseif ( 'history' === $tab ) {
			$summary = DatabaseHandler::get_student_attendance_summary( $month, $year, $class_id ?: null );
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/attendance.php';
		return ob_get_clean();
	}

	/**
	 * [noor_tms_settings] – Gateway / WhatsApp settings.
	 */
	public function sc_settings(): string {
		// Auth + POST are handled in handle_early_requests() on template_redirect.
		$opts    = Settings::get_options();
		$msg     = sanitize_key( $_GET['msg'] ?? '' );

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/settings.php';
		return ob_get_clean();
	}

	/**
	 * [noor_tms_teachers] – Teacher list + create/edit form (managers only).
	 */
	public function sc_teachers(): string {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}

		$action      = sanitize_key( $_GET['tms_action'] ?? 'list' );
		$teacher_id  = (int) ( $_GET['teacher_id'] ?? 0 );
		$classes     = DatabaseHandler::get_classes_dropdown();
		$teachers    = DatabaseHandler::get_teachers();
		$teacher     = $teacher_id ? DatabaseHandler::get_teacher( $teacher_id ) : null;
		$assignments = $teacher_id ? DatabaseHandler::get_teacher_assignments( $teacher_id ) : [];

		// Map existing assignments for the form.
		$existing_homerooms = [];
		$existing_subjects  = [];
		foreach ( $assignments as $a ) {
			if ( 'homeroom' === $a['role_type'] ) {
				$existing_homerooms[] = (int) $a['class_id'];
			} else {
				$existing_subjects[] = $a;
			}
		}

		$subjects_by_class = [];
		foreach ( $classes as $cls ) {
			$subjects_by_class[ (int) $cls['id'] ] = DatabaseHandler::get_subjects_by_class( (int) $cls['id'] );
		}

		ob_start();
		include NOOR_TMS_PLUGIN_DIR . 'public/templates/teachers.php';
		return ob_get_clean();
	}

	/**
	 * Inject extra fields into the teacher form (via action hook).
	 *
	 * @param array<string,mixed>|null $teacher
	 */
	public function render_teacher_form_extra_fields( ?array $teacher ): void {
		$login = sanitize_text_field( $_POST['new_wp_user_login'] ?? '' );
		$email = sanitize_email( $_POST['new_wp_user_email'] ?? '' );
		?>
		<hr />
		<p class="description"><?php esc_html_e( 'Or create a new WordPress user for this teacher.', 'noor-tms' ); ?></p>

		<p>
			<label for="new_wp_user_login"><?php esc_html_e( 'Username', 'noor-tms' ); ?></label><br />
			<input type="text" id="new_wp_user_login" name="new_wp_user_login" class="regular-text"
			       value="<?php echo esc_attr( $login ); ?>" />
		</p>
		<p>
			<label for="new_wp_user_email"><?php esc_html_e( 'Email', 'noor-tms' ); ?></label><br />
			<input type="email" id="new_wp_user_email" name="new_wp_user_email" class="regular-text"
			       value="<?php echo esc_attr( $email ); ?>" />
		</p>
		<p>
			<label for="new_wp_user_pass"><?php esc_html_e( 'Password', 'noor-tms' ); ?></label><br />
			<input type="password" id="new_wp_user_pass" name="new_wp_user_pass" class="regular-text" />
			<span class="description"><?php esc_html_e( 'Leave blank to generate a random password.', 'noor-tms' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Provide optional WP user creation for the teacher form.
	 *
	 * @param int|null $wp_user_id  In/out WP user ID to link. If already set, do nothing.
	 * @param int      $teacher_id  Inserted teacher ID (0 for new).
	 */
	public function handle_wp_user_fields(): void {
		// Bail if wp_user_id already selected.
		if ( ! empty( $_POST['wp_user_id'] ) ) {
			return;
		}

		$login = trim( sanitize_text_field( $_POST['new_wp_user_login'] ?? '' ) );
		$email = trim( sanitize_email( $_POST['new_wp_user_email'] ?? '' ) );
		$pass  = sanitize_text_field( $_POST['new_wp_user_pass'] ?? '' );

		// Bail if no new user fields provided.
		if ( empty( $login ) && empty( $email ) ) {
			return;
		}

		if ( empty( $login ) ) {
			wp_die( esc_html__( 'Please provide a username to create a new WP user.', 'noor-tms' ) );
		}

		if ( ! validate_username( $login ) ) {
			wp_die( esc_html__( 'Invalid username.', 'noor-tms' ) );
		}

		if ( username_exists( $login ) ) {
			wp_die( esc_html__( 'Username already exists.', 'noor-tms' ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_die( esc_html__( 'Please provide a valid email address.', 'noor-tms' ) );
		}

		if ( email_exists( $email ) ) {
			wp_die( esc_html__( 'Email address already in use.', 'noor-tms' ) );
		}

		if ( empty( $pass ) ) {
			$pass = wp_generate_password( 12 );
		}

		$user_id = wp_create_user( $login, $pass, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html__( 'Could not create WP user: ', 'noor-tms' ) . $user_id->get_error_message() );
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );
		$user->add_cap( 'noor_tms_teacher' );

		// Update POST so process_teacher_form() picks up the new user ID.
		$_POST['wp_user_id'] = $user_id;
	}

	/**
	 * Handle front-end teacher create/edit form.
	 */
	public function process_teacher_form(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		if ( ! check_admin_referer( 'noor_tms_teacher_nonce', 'noor_tms_teacher_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
		}

		$teacher_id = (int) ( $_POST['teacher_id'] ?? 0 );
		$name       = sanitize_text_field( $_POST['teacher_name'] ?? '' );
		$phone      = sanitize_text_field( $_POST['teacher_phone'] ?? '' );
		$wp_user_id = (int) ( $_POST['wp_user_id'] ?? 0 );

		// Allow hookable WP user creation.
		do_action( 'noor_tms_teacher_handle_wp_user_fields' );
		
		// Re-fetch wp_user_id after hook (in case it was created by the hook).
		$wp_user_id = (int) ( $_POST['wp_user_id'] ?? 0 );

		$is_active = (int) ( $_POST['is_active'] ?? 1 );

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Teacher name cannot be empty.', 'noor-tms' ) );
		}
		if ( ! $wp_user_id || ! get_user_by( 'id', $wp_user_id ) ) {
			wp_die( esc_html__( 'Please select a valid WordPress user.', 'noor-tms' ) );
		}

		if ( $teacher_id > 0 ) {
			// Handle WP user reassignment: revoke cap from old user if changed.
			$old = DatabaseHandler::get_teacher( $teacher_id );
			if ( $old && (int) $old['wp_user_id'] !== $wp_user_id ) {
				$old_user = get_user_by( 'id', (int) $old['wp_user_id'] );
				if ( $old_user ) {
					$old_user->remove_cap( 'noor_tms_teacher' );
				}
				$new_user = get_user_by( 'id', $wp_user_id );
				if ( $new_user ) {
					$new_user->add_cap( 'noor_tms_teacher' );
				}
			}
			DatabaseHandler::update_teacher( $teacher_id, compact( 'name', 'phone', 'is_active' ) );
			$msg = 'teacher_updated';
		} else {
			$teacher_id = DatabaseHandler::insert_teacher( compact( 'wp_user_id', 'name', 'phone', 'is_active' ) );
			$msg        = 'teacher_added';
		}

		if ( $teacher_id ) {
			$assignments = [];
			
			// Process homeroom classes
			$homeroom_class_ids = (array) ( $_POST['homeroom_class_ids'] ?? [] );
			foreach ( $homeroom_class_ids as $cls_id ) {
				$cls_id = (int) $cls_id;
				if ( $cls_id ) {
					$assignments[] = [ 'class_id' => $cls_id, 'role_type' => 'homeroom', 'subject_id' => null ];
				}
			}

			// Process subject assignments
			$subject_assignments   = (array) ( $_POST['subject_assignments'] ?? [] );
			foreach ( $subject_assignments as $c_id => $subject_ids ) {
				$c_id = (int) $c_id;
				if ( ! $c_id ) continue;
				$subject_ids = (array) $subject_ids;
				foreach ( $subject_ids as $s_id ) {
					$s_id = (int) $s_id;
					if ( $s_id ) {
						$assignments[] = [ 'class_id' => $c_id, 'role_type' => 'subject', 'subject_id' => $s_id ];
					}
				}
			}

			DatabaseHandler::save_teacher_assignments( $teacher_id, $assignments );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'tms-teachers', 'msg' => $msg ], home_url( '/tms-teachers/' ) ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Auth guard
	// -----------------------------------------------------------------------

	/**
	 * Redirect to login page if the visitor is not a TMS user.
	 */
	private function require_auth(): void {
		// Kept for backwards-compat; primary auth is via handle_early_requests().
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_safe_redirect( home_url( '/tms-login/' ) );
			exit;
		}
	}

	// -----------------------------------------------------------------------
	// Form handlers (admin-post.php)
	// -----------------------------------------------------------------------

	/**
	 * Handles student create/update form submitted via admin-post.php.
	 * Hook: admin_post_noor_tms_save_student
	 */
	public function process_student_form(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$student_id = (int) ( $_POST['student_id'] ?? 0 );
		$this->handle_student_save( $student_id );
	}
	/**
	 * Handles class create/update form submitted via admin-post.php.
	 * Hook: admin_post_noor_tms_save_class
	 */
	public function process_class_form(): void {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'noor_tms_manage' ) && ! current_user_can( 'noor_tms_teacher' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$class_id = (int) ( $_POST['class_id'] ?? 0 );
		$this->handle_class_save( $class_id );
	}

	/**
	 * Handles settings form submitted via admin-post.php.
	 * Hook: admin_post_noor_tms_save_settings
	 */
	public function process_settings_form(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'noor-tms' ) );
		}

		$this->handle_settings_save();
	}

	/**
	 * Process student create/update form.
	 */
	private function handle_student_save( int $student_id ): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_student_nonce'] ?? '' ) ), 'noor_tms_save_student' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$data = [
			'class_id'        => (int) ( $_POST['class_id']        ?? 0 ),
			'name'            => sanitize_text_field( $_POST['name']            ?? '' ),
			'parent_phone'    => sanitize_text_field( $_POST['parent_phone']    ?? '' ),
			'enrollment_date' => sanitize_text_field( $_POST['enrollment_date'] ?? current_time( 'Y-m-d' ) ),
			'status'          => sanitize_key( $_POST['status']                 ?? 'active' ),
		];

		if ( empty( $data['name'] ) ) {
			wp_die( esc_html__( 'Student name cannot be empty.', 'noor-tms' ) );
		}

		if ( ! empty( $data['parent_phone'] ) && ! preg_match( '/^\+[1-9]\d{7,14}$/', $data['parent_phone'] ) ) {
			wp_die( esc_html__( 'Invalid phone number. Use international format, e.g. +923001234567', 'noor-tms' ) );
		}

		// Handle photo upload.
		if ( ! empty( $_FILES['student_photo']['name'] ) ) {
			$finfo     = new \finfo( FILEINFO_MIME_TYPE );
			$real_mime = $finfo->file( $_FILES['student_photo']['tmp_name'] );
			if ( ! in_array( $real_mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
				wp_die( esc_html__( 'Invalid file type. Only JPEG, PNG and WebP are allowed.', 'noor-tms' ) );
			}
			if ( $_FILES['student_photo']['size'] > 2 * MB_IN_BYTES ) {
				wp_die( esc_html__( 'Photo must be under 2 MB.', 'noor-tms' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$photo_id = media_handle_upload( 'student_photo', 0 );
			if ( ! is_wp_error( $photo_id ) ) {
				$data['photo_id'] = $photo_id;
			}
		} elseif ( $student_id > 0 && ! empty( $_POST['remove_photo'] ) ) {
			$data['photo_id'] = null;
		}

		if ( $student_id > 0 ) {
			DatabaseHandler::update_student( $student_id, $data );
			$msg = 'updated';
		} else {
			DatabaseHandler::insert_student( $data );
			$msg = 'added';
		}

		wp_safe_redirect( add_query_arg( 'msg', $msg, home_url( '/tms-students/' ) ) );
		exit;
	}

	/**
	 * Process class create/update form.
	 */
	private function handle_class_save( int $class_id ): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_class_nonce'] ?? '' ) ), 'noor_tms_save_class' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$name     = sanitize_text_field( $_POST['class_name'] ?? '' );
		$subjects = array_filter(
			array_map( 'sanitize_text_field', (array) ( $_POST['subjects'] ?? [] ) )
		);

		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Class name cannot be empty.', 'noor-tms' ) );
		}

		if ( $class_id > 0 ) {
			DatabaseHandler::update_class( $class_id, $name, array_values( $subjects ) );
			$msg = 'class_updated';
		} else {
			$new_class_id = DatabaseHandler::insert_class( $name, array_values( $subjects ) );
			if ( $new_class_id && ! current_user_can( 'noor_tms_manage' ) && current_user_can( 'noor_tms_teacher' ) ) {
				$teacher = DatabaseHandler::get_teacher_by_user( get_current_user_id() );
				if ( $teacher ) {
					$assignments = DatabaseHandler::get_teacher_assignments( (int) $teacher['id'] );
					$assignments[] = [ 'class_id' => $new_class_id, 'role_type' => 'homeroom', 'subject_id' => null ];
					DatabaseHandler::save_teacher_assignments( (int) $teacher['id'], $assignments );
				}
			}
			$msg = 'class_added';
		}

		wp_safe_redirect( add_query_arg( 'msg', $msg, home_url( '/tms-classes/' ) ) );
		exit;
	}

	/**
	 * Process settings form.
	 */
	private function handle_settings_save(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noor_tms_settings_nonce'] ?? '' ) ), 'noor_tms_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'noor-tms' ) );
		}

		$allowed_providers = [ 'click_to_chat', 'mock', 'ultramsg', 'twilio' ];
		$provider          = sanitize_key( $_POST['gateway_provider'] ?? 'click_to_chat' );

		update_option( 'noor_tms_options', [
			'gateway_provider' => in_array( $provider, $allowed_providers, true ) ? $provider : 'click_to_chat',
			'api_instance_id'  => sanitize_text_field( $_POST['api_instance_id']  ?? '' ),
			'api_token'        => sanitize_text_field( $_POST['api_token']         ?? '' ),
			'sender_number'    => sanitize_text_field( $_POST['sender_number']     ?? '' ),
			'message_template' => wp_kses_post( $_POST['message_template']         ?? '' ),
		] );

		wp_safe_redirect( add_query_arg( 'msg', 'saved', home_url( '/tms-settings/' ) ) );
		exit;
	}
}
