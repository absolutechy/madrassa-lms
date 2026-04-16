# Noor-TMS Architecture Reference

> **Contract document** — the items listed here (hook names, action names,
> shortcodes, capability names, table names) must remain unchanged through
> every refactoring phase.  If any entry needs to change, update this file
> at the same time and note the migration path.

---

## Plugin Constants

| Constant | Value / Description |
|---|---|
| `NOOR_TMS_VERSION` | `1.0.8` |
| `NOOR_TMS_PLUGIN_FILE` | Absolute path to `noor-tms.php` |
| `NOOR_TMS_PLUGIN_DIR` | Absolute path to plugin root directory (trailing slash) |
| `NOOR_TMS_PLUGIN_URL` | Public URL to plugin root (trailing slash) |
| `NOOR_TMS_PLUGIN_BASE` | `plugin_basename()` result |

---

## Autoloader Namespace Map

```
Noor_TMS\Admin\*         → admin/class-noor-tms-<slug>.php
Noor_TMS\Includes\*      → includes/class-noor-tms-<slug>.php
Noor_TMS\PublicFacing\*  → public/class-noor-tms-<slug>.php
```

PascalCase class name is kebab-cased to form the file slug.
Example: `DatabaseHandler` → `class-noor-tms-database-handler.php`

---

## WordPress Hooks Registered

### Actions (via Loader)

| Hook | Callback class → method | Notes |
|---|---|---|
| `plugins_loaded` | `Plugin → load_plugin_textdomain` | i18n |
| `admin_menu` | `Admin → register_menus` | Admin menu pages |
| `admin_enqueue_scripts` | `Admin → enqueue_assets` | Admin CSS/JS |
| `init` | `PublicController → register_shortcodes` | Shortcodes + custom hook sub |
| `wp_enqueue_scripts` | `PublicController → enqueue_assets` | Front CSS/JS |
| `template_redirect` | `PublicController → handle_early_requests` | Auth guard + cache headers |
| `admin_post_noor_tms_save_student` | `PublicController → process_student_form` | Front student form |
| `admin_post_noor_tms_save_class` | `PublicController → process_class_form` | Front class form |
| `admin_post_noor_tms_save_settings` | `PublicController → process_settings_form` | Front settings form |
| `admin_post_noor_tms_save_teacher` | `PublicController → process_teacher_form` | Front teacher form |

### Filters (via Loader)

| Hook | Callback class → method | Priority | Args |
|---|---|---|---|
| `login_redirect` | `PublicController → redirect_after_login` | 10 | 3 |

### AJAX Actions (logged-in only, via Loader)

All registered on `wp_ajax_*` (no `nopriv` variant — unauthenticated users cannot call these).

| Action slug | Delegated to |
|---|---|
| `noor_tms_save_result` | `Admin → ajax_save_result` → `Results::ajax_save_result` |
| `noor_tms_delete_student` | `Admin → ajax_delete_student` → `Students::ajax_delete_student` |
| `noor_tms_delete_result` | `Admin → ajax_delete_result` → `Results::ajax_delete_result` |
| `noor_tms_delete_class` | `Admin → ajax_delete_class` → `Classes::ajax_delete_class` |
| `noor_tms_get_subjects` | `Admin → ajax_get_subjects` → `Classes::ajax_get_subjects` |
| `noor_tms_get_students_for_class` | `Admin → ajax_get_students_for_class` → `Classes::ajax_get_students_for_class` |
| `noor_tms_save_report` | `Admin → ajax_save_report` → `Results::ajax_save_report` |
| `noor_tms_delete_teacher` | `Admin → ajax_delete_teacher` → `Teachers::ajax_delete_teacher` |
| `noor_tms_save_student_attendance` | `Admin → ajax_save_student_attendance` → `Attendance::ajax_save_student_attendance` |
| `noor_tms_save_teacher_attendance` | `Admin → ajax_save_teacher_attendance` → `Teachers::ajax_save_teacher_attendance` |

### Custom Actions (fired inside plugin code)

| Action | Where fired | Purpose |
|---|---|---|
| `noor_tms_teacher_handle_wp_user_fields` | `PublicController::process_teacher_form` | Hookable WP user creation during teacher save |
| `noor_tms_send_whatsapp` | `WhatsApp::send_notification` | Fired before external HTTP dispatch |

---

## Shortcodes

| Shortcode | Handler method | Access |
|---|---|---|
| `[noor_tms_login]` | `PublicController::sc_login` | All users |
| `[noor_tms_students]` | `PublicController::sc_students` | Managers + Teachers |
| `[noor_tms_classes]` | `PublicController::sc_classes` | Managers + Teachers |
| `[noor_tms_results]` | `PublicController::sc_results` | Managers + Teachers |
| `[noor_tms_teachers]` | `PublicController::sc_teachers` | Managers only |
| `[noor_tms_settings]` | `PublicController::sc_settings` | Managers only |
| `[noor_tms_attendance]` | `PublicController::sc_attendance` | Managers + Teachers |

---

## WordPress Capabilities

| Capability | Granted to | Purpose |
|---|---|---|
| `noor_tms_manage` | Role `noor_tms_manager`; also added to `administrator` | Full access to admin menu and all portal pages |
| `noor_tms_teacher` | Role `noor_tms_teacher`; added to teacher WP users | Teacher portal access (attendance, results for assigned classes) |

---

## Custom Roles

| Role slug | Display name | Capabilities |
|---|---|---|
| `noor_tms_manager` | Noor TMS Manager | `read`, `noor_tms_manage` |
| `noor_tms_teacher` | Noor TMS Teacher | `read`, `noor_tms_teacher` |

---

## Custom Database Tables

All tables use the WordPress `$wpdb->prefix` (e.g. `wp_`).

| Table (without prefix) | Purpose | Key columns |
|---|---|---|
| `mms_classes` | Class definitions | `id`, `name`, `created_at` |
| `mms_subjects` | Subjects per class | `id`, `class_id`, `subject_name` |
| `mms_students` | Student records | `id`, `class_id`, `name`, `parent_phone`, `enrollment_date`, `status`, `photo_id` |
| `mms_results` | Exam results | `id`, `student_id`, `subject`, `marks_obtained`, `total_marks`, `exam_date`, `notification_sent` |
| `mms_teachers` | Teacher records | `id`, `wp_user_id`, `name`, `phone`, `is_active` |
| `mms_class_teachers` | Class–Teacher assignments | `id`, `class_id`, `teacher_id`, `role_type` (`homeroom`\|`subject`), `subject_id` |
| `mms_student_attendance` | Student attendance records | `id`, `student_id`, `class_id`, `att_date`, `status`, `marked_by`; UNIQUE `(student_id, att_date)` |
| `mms_teacher_attendance` | Teacher attendance records | `id`, `teacher_id`, `att_date`, `status`, `notes`, `marked_by`; UNIQUE `(teacher_id, att_date)` |

### Schema Version

Tracked in WP option `noor_tms_db_version`. Current: `3.0`.

---

## WordPress Options

| Option key | Content |
|---|---|
| `noor_tms_options` | Serialized array: `gateway_provider`, `api_instance_id`, `api_token`, `sender_number`, `message_template` |
| `noor_tms_activated_at` | Unix timestamp of first activation |
| `noor_tms_db_version` | Current schema version string (e.g. `3.0`) |

Transient: `noor_tms_last_mock_wa` (mock WhatsApp provider, TTL 60 s).

---

## Class Inventory

### includes/

| Class (namespace `Noor_TMS\Includes`) | File | Responsibility |
|---|---|---|
| `Plugin` | `class-noor-tms-plugin.php` | Singleton orchestrator; wires Loader, admin, public hooks |
| `Loader` | `class-noor-tms-loader.php` | Queues `add_action`/`add_filter`, fires them in `run()` |
| `Activator` | `class-noor-tms-activator.php` | On activate: tables, roles, default pages, option timestamp, flush rewrites |
| `Deactivator` | `class-noor-tms-deactivator.php` | On deactivate: remove roles/caps, flush rewrites |
| `Roles` | `class-noor-tms-roles.php` | Adds/removes custom roles and capabilities |
| `DatabaseHandler` | `class-noor-tms-database-handler.php` | Static data-access layer: schema + CRUD for all 8 tables |

### admin/

| Class (namespace `Noor_TMS\Admin`) | File | Responsibility |
|---|---|---|
| `Admin` | `class-noor-tms-admin.php` | Admin menu registration, asset enqueue, AJAX delegation |
| `Students` | `class-noor-tms-students.php` | Student list/form UI, `ajax_delete_student` |
| `Classes` | `class-noor-tms-classes.php` | Class list/form UI, `ajax_delete_class`, `ajax_get_subjects`, `ajax_get_students_for_class` |
| `Results` | `class-noor-tms-results.php` | Results UI, `ajax_save_result`, `ajax_save_report`, `ajax_delete_result` |
| `Teachers` | `class-noor-tms-teachers.php` | Teacher list/form UI, teacher attendance UI, `ajax_delete_teacher`, `ajax_save_teacher_attendance` |
| `Attendance` | `class-noor-tms-attendance.php` | Student attendance admin UI, `ajax_save_student_attendance` |
| `Settings` | `class-noor-tms-settings.php` | WhatsApp/gateway settings UI, `get_options()` |
| `WhatsApp` | `class-noor-tms-whats-app.php` | Static: message templating, CtC/mock/Ultramsg/Twilio dispatch |

### public/

| Class (namespace `Noor_TMS\PublicFacing`) | File | Responsibility |
|---|---|---|
| `PublicController` | `class-noor-tms-public-controller.php` | Shortcodes, front assets, auth guard, login redirect, 4 admin-post form handlers |

### public/templates/ (procedural views)

`layout.php`, `layout-close.php`, `login.php`, `students.php`, `student-form.php`,
`classes.php`, `class-form.php`, `results.php`, `results-class.php`,
`teachers.php`, `settings.php`, `attendance.php`

---

## Nonce Keys

| Nonce action | Used by |
|---|---|
| `noor_tms_ajax` | All general AJAX requests (localized as `noorTMS.nonce`) |
| `noor_tms_save_result_ajax` | Save result / save report AJAX (separate nonce field in form) |
| `noor_tms_save_student` | Student create/edit form (admin-post) |
| `noor_tms_save_class` | Class create/edit form (admin-post) |
| `noor_tms_save_settings` | Settings form (admin-post) |
| `noor_tms_teacher_nonce` | Teacher create/edit form (admin-post, uses `check_admin_referer`) |

---

## WhatsApp Gateway Providers

| Provider key | Implementation |
|---|---|
| `click_to_chat` | Generates `https://wa.me/` click-to-chat URLs; no server-side HTTP call |
| `mock` | Logs message to WP transient `noor_tms_last_mock_wa`; for development |
| `ultramsg` | `wp_remote_post` to `https://api.ultramsg.com/instance{id}/messages/chat` |
| `twilio` | `wp_remote_post` to `https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json` |

---

## Front-End Portal URL Slugs (default pages created on activation)

| Slug | Shortcode |
|---|---|
| `/tms-login/` | `[noor_tms_login]` |
| `/tms-students/` | `[noor_tms_students]` |
| `/tms-classes/` | `[noor_tms_classes]` |
| `/tms-results/` | `[noor_tms_results]` |
| `/tms-teachers/` | `[noor_tms_teachers]` |
| `/tms-settings/` | `[noor_tms_settings]` |
| `/tms-attendance/` | `[noor_tms_attendance]` |
