=== Noor-TMS – Madrasa Management System ===
Contributors:      yourhandle
Tags:              madrasa, school, students, results, whatsapp
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A robust, OOP-based Madrasa Management System for student administration and
automated WhatsApp parent notifications.

== Description ==

Noor-TMS (Tablet Management System) helps Islamic schools and Madrasas manage
their students and keep parents informed automatically via WhatsApp.

**Key Features**

* Student CRUD – Add, edit, and delete students with parent WhatsApp numbers.
* Exam Result Management – Record subject-wise marks per student.
* Automated WhatsApp Notifications – Notify parents instantly when results are saved.
* Multi-gateway support – Ultramsg, Twilio, or a built-in Mock/Test mode.
* AJAX-powered admin UI – No page reloads when saving results.
* Secure – Nonces on every form, capability checks, sanitized input & escaped output.
* Scalable schema – Two indexed custom tables (`wp_mms_students`, `wp_mms_results`).
* Easy to extend – Action hook `noor_tms_send_whatsapp` lets third-party plugins
  intercept every notification.

== Requirements ==

* WordPress 6.0+
* PHP 8.0+
* MySQL 5.7+ / MariaDB 10.3+

== Installation ==

= Automatic (recommended) =

1. In your WordPress admin dashboard go to **Plugins → Add New**.
2. Click **Upload Plugin** and select the `noor-tms.zip` file.
3. Click **Install Now**, then **Activate Plugin**.

= Manual =

1. Unzip `noor-tms.zip`.
2. Upload the `noor-tms/` folder to `/wp-content/plugins/` via FTP or your
   hosting file manager.
3. Go to **Plugins** in your WordPress admin and activate **Noor-TMS**.
4. On first activation, the plugin automatically creates the two database
   tables (`wp_mms_students` and `wp_mms_results`).

== Configuration ==

=== Step 1 – Add Students ===

1. Go to **Noor-TMS → Students** in the WordPress admin sidebar.
2. Click **Add New**.
3. Fill in the student's Full Name, Parent WhatsApp Number
   (international format, e.g. `+923001234567`), Enrollment Date, and Status.
4. Click **Add Student**.

=== Step 2 – Configure WhatsApp Gateway ===

1. Go to **Noor-TMS → Settings**.
2. Choose your **Gateway Provider**:
   - **Mock / Test Mode** – No real messages sent; logs to PHP error log.
     Use this during development.
   - **Ultramsg** – Fill in your Instance ID and API Token from
     https://ultramsg.com/
   - **Twilio** – Fill in your Account SID, Auth Token, and the Twilio
     WhatsApp-enabled sender number.
3. Customise the **Message Template** using the available placeholders:
   `{student_name}`, `{subject}`, `{marks_obtained}`, `{total_marks}`,
   `{exam_date}`.
4. Click **Save Settings**.

=== Step 3 – Record Exam Results ===

1. Go to **Noor-TMS → Exam Results**.
2. Fill in the **Add New Result** form:
   - Select a student from the dropdown.
   - Enter the Subject and Marks.
   - Check **"Send WhatsApp notification to parent"** if you want to
     notify the parent immediately.
3. Click **Save Result**.
   The result is saved via AJAX (no page refresh) and appears in the
   Result History table below.

== Frequently Asked Questions ==

= Which WhatsApp API provider do you recommend? =

For small Madrasas,  **Ultramsg** is easiest to set up – create an account,
connect your WhatsApp number, and paste the Instance ID and token into
Settings. For enterprise use, **Twilio** is more reliable.

= How do I switch from Mock to a real provider? =

Go to **Noor-TMS → Settings**, change the Gateway Provider from
"Mock / Test Mode" to "Ultramsg" or "Twilio", fill in your credentials,
and save. No code changes required.

= Can I hook into the WhatsApp notification from my own plugin? =

Yes. Add an action for `noor_tms_send_whatsapp`:

  add_action( 'noor_tms_send_whatsapp', function( $phone, $message ) {
      // Your custom logic here.
  }, 10, 2 );

= Will my data be lost when I deactivate the plugin? =

No. Deactivation only flushes rewrite rules. Your students and results
are preserved. Data is only deleted when you go to Plugins → Delete.

= How do I back up my data? =

Use any database backup plugin (e.g., UpdraftPlus) or export the
`wp_mms_students` and `wp_mms_results` tables directly from phpMyAdmin.

== Screenshots ==

1. Students list page with search, status filter, and inline actions.
2. Add Student form with WhatsApp number field.
3. Exam Results page with AJAX "Add Result" form and WhatsApp toggle.
4. Settings page with gateway selector and message template editor.

== Changelog ==

= 1.0.0 =
* Initial release.
* Student CRUD with WhatsApp number field.
* Exam Result management with AJAX save.
* WhatsApp notification via Mock, Ultramsg, or Twilio.
* Secure nonces, capability checks, sanitized I/O.
* Custom DB tables with indexing via dbDelta().

== Upgrade Notice ==

= 1.0.0 =
First stable release. No upgrade steps required.

== Developer Notes ==

=== File Structure ===

  noor-tms/
  ├── noor-tms.php                              # Plugin entry point + PSR-4 autoloader
  ├── uninstall.php                             # Clean-up on plugin deletion
  ├── README.txt
  ├── includes/
  │   ├── class-noor-tms-plugin.php             # Core orchestrator (singleton)
  │   ├── class-noor-tms-loader.php             # Hook registration queue
  │   ├── class-noor-tms-activator.php          # Activation callback
  │   ├── class-noor-tms-deactivator.php        # Deactivation callback
  │   └── class-noor-tms-database-handler.php   # All DB operations (static)
  └── admin/
      ├── class-noor-tms-admin.php              # Menu, assets, AJAX dispatch
      ├── class-noor-tms-students.php           # Student CRUD pages + AJAX
      ├── class-noor-tms-results.php            # Results page + AJAX save/delete
      ├── class-noor-tms-settings.php           # Settings page & option helpers
      ├── class-noor-tms-whats-app.php          # WhatsApp gateway (Mock/Ultramsg/Twilio)
      ├── css/
      │   └── noor-tms-admin.css
      └── js/
          └── noor-tms-admin.js

=== Action Hooks ===

  noor_tms_send_whatsapp( string $phone, string $message )
    Fires before every WhatsApp dispatch. Hook here to log, override,
    or add custom delivery channels.

=== Extending the WhatsApp Gateway ===

The `WhatsApp` class contains three provider methods:
  - `send_mock()`         – Development/staging
  - `send_via_ultramsg()` – Production (Ultramsg)
  - `send_via_twilio()`   – Production (Twilio)

To add a new provider (e.g., Meta Cloud API):
  1. Add a new `case` to the `match` in `WhatsApp::dispatch()`.
  2. Implement a `send_via_meta()` private static method.
  3. Add the option in `Settings::page_settings()`.

=== Database Tables ===

  {prefix}mms_students
    id              BIGINT PK AUTO_INCREMENT
    name            VARCHAR(255)
    parent_phone    VARCHAR(30)     # international format
    enrollment_date DATE
    status          ENUM(active|inactive|graduated)
    created_at      DATETIME
    updated_at      DATETIME
    INDEX: status, enrollment_date

  {prefix}mms_results
    id                BIGINT PK AUTO_INCREMENT
    student_id        BIGINT FK → mms_students.id
    subject           VARCHAR(255)
    marks_obtained    DECIMAL(6,2)
    total_marks       DECIMAL(6,2)
    exam_date         DATE
    notification_sent TINYINT(1)    # 0 = not sent, 1 = sent
    created_at        DATETIME
    INDEX: student_id, exam_date, notification_sent
