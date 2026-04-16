# Noor-TMS Manual Regression Test Checklist

Run this checklist after **every refactoring phase** before committing.
A checkmark means the feature behaves identically to the pre-refactor baseline.

---

## 0 — Plugin Load

- [ ] Plugin activates without PHP errors or fatal notices
- [ ] Plugin deactivates cleanly (no fatal errors)
- [ ] All 8 custom `mms_*` tables exist after activation
- [ ] WP option `noor_tms_db_version` equals `3.0`
- [ ] Admin menu shows: Students | Classes | Results | Teachers | Attendance | Settings
- [ ] Custom roles `noor_tms_manager` and `noor_tms_teacher` exist

---

## 1 — Login / Authentication

- [ ] `/tms-login/` loads the login form when not logged in
- [ ] Submitting valid manager credentials redirects to `/tms-students/`
- [ ] Submitting valid teacher credentials redirects to `/tms-attendance/`
- [ ] Visiting any protected portal page (`/tms-students/`, `/tms-classes/`, etc.) while logged out redirects to `/tms-login/`
- [ ] A teacher cannot access `/tms-teachers/` or `/tms-settings/` (redirected to login)

---

## 2 — Students (Admin + Front Portal)

### Admin
- [ ] **List**: Student list page loads; search by name, filter by status and class work
- [ ] **Add**: Submitting the Add Student form creates a new student record
- [ ] **Edit**: Editing an existing student and saving updates the record correctly
- [ ] **Delete**: Clicking Delete on a student removes them (AJAX, no page reload); associated results are also deleted
- [ ] **Photo upload**: Attaching a JPEG/PNG/WebP photo saves `photo_id`; invalid types are rejected
- [ ] **Photo remove**: Checking "Remove photo" clears `photo_id`

### Front Portal (`/tms-students/`)
- [ ] Manager sees the same list + Add/Edit links
- [ ] Teacher sees only students in their assigned classes; Add/Edit links are hidden

---

## 3 — Classes (Admin + Front Portal)

### Admin
- [ ] **List**: Classes list with subject count loads
- [ ] **Add**: Creating a class with subjects saves correctly
- [ ] **Edit**: Changing class name and replacing subjects persists
- [ ] **Delete**: Deleting a class removes it; students in that class get `class_id = 0`
- [ ] **AJAX – Get subjects**: Selecting a class in the results form dynamically loads its subjects
- [ ] **AJAX – Get students for class**: Returns active students for a class (used in results)

### Front Portal (`/tms-classes/`)
- [ ] Manager sees full class list with Edit links
- [ ] Teacher sees only their assigned classes (read-only list or limited edit)

---

## 4 — Teachers (Admin + Front Portal)

### Admin
- [ ] **List**: Teacher list with class assignment count loads
- [ ] **Add**: Creating a teacher with an existing WP user works
- [ ] **Add + new WP user**: Filling in username/email/password creates a WP user and links it
- [ ] **Edit**: Updating teacher name, phone, active status persists
- [ ] **Assignments**: Homeroom and subject assignments save and reload correctly
- [ ] **Delete**: Deleting a teacher removes DB row and revokes `noor_tms_teacher` cap from the WP user

### Front Portal (`/tms-teachers/`)
- [ ] Only managers can access this page
- [ ] Same create/edit/delete flows work identically to admin

---

## 5 — Attendance

### Student Attendance (Admin + Front Portal)
- [ ] **Mark tab**: Selecting a class and date loads students with current attendance status
- [ ] **Save**: Submitting attendance marks persists all records; re-submitting updates existing records (upsert)
- [ ] **History tab**: Selecting month/year shows summary with present/absent/late/excused counts and percentage

### Teacher Attendance (Admin)
- [ ] Teacher attendance page loads with teacher list
- [ ] Saving teacher attendance persists correctly (upsert)
- [ ] Monthly summary shows correct aggregates

---

## 6 — Results (Admin + Front Portal)

### Admin
- [ ] **Class overview**: Cards for each class display with subject count
- [ ] **Class results view**: Selecting a class shows the Add Result form and (if date selected) the report summary
- [ ] **Save single result (AJAX)**: Submitting one subject result saves and shows WhatsApp CtC button (if CtC provider)
- [ ] **Save full report (AJAX)**: Submitting all subjects for a student at once saves all rows
- [ ] **Delete result (AJAX)**: A manager can delete any result; a teacher can only delete results in their assigned classes
- [ ] **Teacher permission check**: Teacher cannot save/delete results for a class not assigned to them (403 response)

### Front Portal (`/tms-results/`)
- [ ] Same flows as admin results panel

---

## 7 — Settings

### Admin
- [ ] Settings page loads with current values populated
- [ ] Changing gateway provider shows/hides the correct API fields (inline JS)
- [ ] Saving settings persists to `noor_tms_options`

### Front Portal (`/tms-settings/`)
- [ ] Same form works identically; success message `?msg=saved` appears after save

---

## 8 — WhatsApp Integration

- [ ] **Click-to-Chat**: After saving a result, a WhatsApp button with a `wa.me/` URL is returned in the AJAX response
- [ ] **Report URL**: The bulk report save returns a WhatsApp report URL with all subject marks in the message
- [ ] **Mock provider**: Saving a result stores a transient `noor_tms_last_mock_wa` (verify via `get_transient`)
- [ ] **Ultramsg/Twilio**: `wp_remote_post` is called with the correct endpoint and credentials (test in staging only)

---

## 9 — Uninstall

- [ ] Deactivating the plugin removes custom roles and capabilities but keeps data
- [ ] Deleting the plugin (triggering `uninstall.php`) drops all 8 `mms_*` tables and removes the 3 plugin options

---

## 10 — JavaScript / Front-End

- [ ] Admin JS (`noor-tms-admin.js`) is enqueued only on TMS admin pages
- [ ] Public JS (`noor-tms-public.js`) is enqueued only on pages with a TMS shortcode
- [ ] AJAX nonce `noor_tms_ajax` is localized correctly in both admin and public scripts
- [ ] No JS console errors on any TMS page
- [ ] Attendance bulk-save spinner appears during AJAX call and disappears on completion
- [ ] Result delete shows a confirm dialog before sending the request

---

## Notes

- Test with both a **manager** account and a **teacher** account.
- After each phase: clear browser cache and any server-side page cache before testing.
- Check the PHP error log (`laragon/logs/` or `wp-content/debug.log`) for any new notices or warnings.
