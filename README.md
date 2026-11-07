# Doctor Hospital

A complete local hospital appointment and queue system for Pakistan. Plain PHP, PDO and MySQL/MariaDB; Bootstrap 5, CSS and vanilla JavaScript. No Composer, npm, Node.js, Docker or patient accounts.

## Updating an existing hospital

For the **29 October chatbot update**, follow **[CHATBOT.md](CHATBOT.md)** and use `doctor-hospital-chatbot-update.zip`. It adds the patient assistant to the existing English/Urdu and doctor archive implementation. The earlier archive/translation update remains documented in **[UPGRADE.md](UPGRADE.md)**. Back up your database and site folder before copying an update. Keep your configuration and uploaded photos. Do not run setup or import the fresh-install schema over your existing hospital. `php scripts/migrate.php` checks the archive column and creates/seeds the chatbot knowledge bank without replacing existing entries or hospital records.

## Patient assistance chatbot

Open **Hospital Assistant** on the homepage, doctor directory or booking page. Describe a concern in English, Roman Urdu or Urdu. The response follows the selected English/Urdu site language. The assistant uses 51 editable bilingual topics, synonyms, weighted phrase/word matching, limited spelling tolerance and short routing context. It is a local rule-based NLP project, with no external AI, paid service or runtime dependency beyond the existing PHP/MySQL application.

Doctor cards read active, unarchived doctors and their schedules live. Their booking buttons open the existing appointment form, which checks availability again. Unknown questions and missing specialists lead to reception guidance; ambiguous matches offer a short clarification. Built-in urgent warnings run before normal routing, independently of the editable bank. The assistant does not diagnose, prescribe, allocate appointments or save health-message transcripts.

Super Admin → **Chatbot knowledge bank** provides search, add, edit, specialty assignment and enable/disable controls in both languages. Read **[CHATBOT.md](CHATBOT.md)** for XAMPP instructions, migration details, the complete changed-file list, algorithm explanation, screenshots and viva limitations. **[TESTING.md](TESTING.md)** records executed checks.

## English and Urdu

Use **English | اردو** at the top of any page. English is the default; the selection persists in the PHP session, including after sign-out. Public pages, Super Admin pages, reception, validation, appointment slips and patient lists use the selected language. Urdu uses `lang="ur" dir="rtl"`, local Bootstrap RTL and a bundled Noto Naskh Arabic font. Booking name, phone, reason, selected date and submission key survive switching when JavaScript is enabled. Without JavaScript the switch still works, but unsent form edits cannot be collected. Other unsaved staff forms should be saved before switching.

Weekday/month labels are translated. Numbers, times (AM/PM), mobile numbers and references retain readable Latin digits. Doctor/patient names, qualifications and other user-authored content stay as entered; business statuses and roles stay unchanged in the database. Browser-native date pickers, file chooser and print-dialog controls follow the browser/Windows language.

Translations live in `lang/en.php` and `lang/ur.php`, through `t()` in `includes/i18n.php`. Add matching keys and placeholders to both files. No translation API or internet connection is needed. [Noto Naskh Arabic](https://github.com/google/fonts/tree/main/ofl/notonaskharabic) is bundled under the SIL Open Font License in `assets/fonts/OFL.txt`; Bootstrap RTL retains Bootstrap's MIT license in `assets/vendor/BOOTSTRAP-LICENSE.txt`.

## Delete, archive and restore doctors

Sign in as Super Admin and open **Doctors**. **Delete doctor** appears only for a doctor who has never had an appointment, including cancelled appointments. Confirm the dialog to remove that unused doctor and their dependent schedules. The server independently enforces authorization and appointment-history protection.

Use **Archive doctor** for a doctor with history. The page warns about unresolved appointments and links to the first affected queue. Contact patients and cancel future visits; finish or cancel today's outstanding visits, then archive. Future visits cannot be bypassed by marking them completed or no-show early. Archiving preserves the doctor, schedules, all appointments and private slips, and closes public booking. Staff can still select the historical date and open its queue or print list. **Restore doctor** makes the doctor active again using the preserved schedule and token counters. Editing an archived doctor's active checkbox cannot restore them; use the explicit Restore action.

Deletion and archiving lock the same doctor row as booking to prevent concurrent requests from allocating appointments to a removed or archived doctor. No patient records are deleted by doctor management.

## Start on a Windows laptop with XAMPP

These setup instructions are for a **new installation**. Existing installations use the update instructions above.

1. Install XAMPP for Windows from [Apache Friends](https://www.apachefriends.org/). Use a PHP **8.2 or newer** XAMPP package. Install it at `C:\xampp`.
2. Copy this project's contents into **`C:\xampp\htdocs\doctor-hospital`**. `index.php` must be directly inside that folder, not inside another nested folder.
3. **Do not copy `config.local.php` from the development computer.** It is a machine-specific, ignored development override. The supplied `config.php` already targets XAMPP: `127.0.0.1:3306`, database `doctor_hospital`, username `root`, blank database password, URL `/doctor-hospital`.
4. Open XAMPP Control Panel and start **Apache** and **MySQL**. XAMPP's MySQL button normally starts MariaDB.
5. Double-click **`setup-xampp.bat`** in the project folder. It installs the database, fictional doctors and current/future sample appointments, then asks you to create your administrator. The password prompt is masked. If an administrator already exists, first-admin setup correctly refuses to create another.
6. Open **[http://localhost/doctor-hospital/](http://localhost/doctor-hospital/)**.

Everything the website loads is included locally, including Bootstrap, JavaScript, doctor illustrations and the hospital artwork. After XAMPP is installed, the project needs no internet connection. Illustrations and fictional demo people are placeholders; the admin can upload real doctor photos.

### Manual setup instead of the setup button

Open PowerShell in `C:\xampp\htdocs\doctor-hospital` and run:

```powershell
& C:\xampp\php\php.exe scripts\install.php --demo
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\create-admin.ps1
```

Omit `--demo` for an empty hospital. The installer creates the database, tables, indexes and foreign keys. Re-running it preserves existing data and does not reset schedules or passwords. Demo sample dates and private tokens are generated by the real PHP booking service; no old hardcoded appointment dates or predictable private links are imported.

Alternatively, on a new installation, open `http://localhost/phpmyadmin/`, import **`database/schema.sql`**, select `doctor_hospital`, and import **`database/demo_data.sql`**. Then run `php scripts/migrate.php` to install the additive migrations and chatbot bank. The SQL demo file adds fictional doctors and weekly schedules. Run `php scripts/install.php --demo` to also generate the four sample appointments securely. There is intentionally no web installer.

If XAMPP is somewhere else, use the actual PHP executable path and pass it to the admin helper:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\create-admin.ps1 -PhpPath D:\xampp\php\php.exe
```

If your database has a different port, user or password, copy `config.example.php` to `config.local.php` and update it. Keep that file private. For a different folder name, change `base_url` as well. The required PHP extensions are `pdo_mysql`, `mbstring`, `fileinfo`, and `gd`. Enable missing extensions in XAMPP's `php.ini`, then restart Apache.

## Create and use the first Super Admin

The setup helper prompts for your name, username and a password of at least 12 characters (maximum 72 bytes). Passwords are hashed with PHP `password_hash`; no permanent default login is included. Passwords are passed to the CLI over stdin, never command-line arguments or saved source files.

Visit **`http://localhost/doctor-hospital/login.php?role=admin`**, choose **Super Admin**, and use the credentials you created. The first-admin script closes once any admin exists. On a Unix-like system, `php scripts/create-admin.php` provides a hidden terminal password prompt. The PowerShell helper is the recommended Windows option.

## The three modules

### 1. Super Admin

- **Overview:** active doctors, appointments for today, unique patient mobile numbers booked for today (excluding cancelled), completed visits, and today's schedules.
- **Doctors:** add/edit doctors, qualifications, specialization, Pakistani mobile number, photo and active status. Photos must be JPG, PNG or WebP, up to 2 MB and 4000 × 4000 pixels. Uploaded photos are decoded and re-encoded as JPG under a random filename.
- **Schedules:** select a doctor and save each working weekday once. Different weekdays may have different opening, cutoff, consultation and duration values. Saving an existing weekday updates it. Upcoming dates are generated automatically.
- **Unavailable dates:** mark a day absent or restore it. If a date already has bookings, contact those patients and cancel their bookings in the queue first. The system will not silently invalidate their appointments.
- **Receptionists:** create/edit accounts and activate/deactivate them. Leaving an existing account's password blank preserves it. Deactivation takes effect on the staff member's next request, even if they are already signed in.
- Admins can also open queues to handle appointments.

### 2. Receptionist

Visit **`login.php?role=receptionist`** with an account created by the admin. Select today's doctor, or choose another date.

The queue is ordered by token. Search by name, mobile number or exact token. Mark patients **Arrived**, **In Consultation**, **Completed**, **No-show**, or **Cancelled**. Only one patient per doctor/date can be in consultation. Complete the current consultation before calling the next patient. Future visits cannot be marked in consultation. Completed and cancelled records are closed.

The **Add a walk-in patient** form follows exactly the same booking window, capacity and token allocation rules. Receptionists cannot access doctor, schedule or account management, including by typing an admin URL directly.

**Printing the patient list:** open a doctor/date queue and click **Print patient list**. A separate full list opens, including doctor, specialization, date, consultation start, tokens, names, phone numbers, estimates and statuses. Search filters never truncate the printed list. Click its print button, use A4 paper and 100% scale, and disable the browser's optional headers/footers. Navigation and controls are hidden by print CSS. Table headers repeat on subsequent pages. Choose “Save as PDF” to save rather than send to a printer.

### 3. Patient — no login

1. Open the public homepage or **Our doctors**.
2. Search by doctor name or specialization, then click **Book appointment**.
3. Choose an available working date within the next 30 days. Full, closed and unavailable dates cannot be submitted. The first available date is selected automatically, so today's cutoff leads naturally to the next working day.
4. Enter only your full name and Pakistani mobile number; reason for visit is optional. Local `03XXXXXXXXX`, `+923XXXXXXXXX` and `00923XXXXXXXXX` numbers are accepted and normalized.
5. Click **Confirm appointment** to receive a reference, token and estimated time.
6. Copy/bookmark the **private confirmation link**, or click **Print appointment slip**. Choose “Save as PDF” in the print dialog to save it. Anyone with the private link can see that slip, so keep it private. There is no public phone-number or sequential-ID lookup.

Arrive 10–15 minutes early. Estimates are not guaranteed consultation times. Patients contact reception to cancel; self-service cancellation and waiting lists are intentionally not included.

## Booking rules and design decisions

- All application and database session times use **Asia/Karachi / UTC+05:00**.
- **Future dates can be booked anytime. On the appointment day, opening is inclusive and cutoff is exclusive.** For example, `09:00–16:00` allows same-day requests from 09:00:00 through 15:59:59. This is the chosen advance-booking policy.
- Booking cutoff must be no later than consultation start. Overnight schedules are not supported.
- Capacity is `floor((consultation end - start) / duration)`: 3 hours / 5 minutes = **36**. An incomplete final interval is not sold.
- A **token number** is a permanent, increasing booking-order number for one doctor/date. A **position number** is the consultation time slot. These are deliberately separate. When token 2 cancels, a new patient may receive token 37 and position 2, with position 2's original time. Token 37 does not imply the 37th time slot. Existing tokens and estimates never move, and the queue remains ordered by token as requested. Staff should account for the displayed estimate when a cancelled position is refilled.
- Only **Cancelled** frees a position. Completed and No-show appointments continue to occupy their original positions, unless a No-show is explicitly cancelled. Cancelled tokens are never restored or reused.
- The first booking saves an `appointment_days` schedule snapshot. Weekly edits/removals affect unbooked dates; already-booked dates retain their hours and remain bookable up to their saved capacity. Use the absence workflow to close an existing date. This protects previously promised times.
- All bookings, cancellations and schedule changes lock the same doctor row in an InnoDB transaction. The date counter allocates tokens. A database unique constraint on active positions provides a second guard against collisions; another constraint prevents multiple simultaneous consultations.
- Server-issued submission keys make retries idempotent. Random 256-bit confirmation tokens protect slips. Rate limiting is stored in the database (10 public booking attempts per 10 minutes per IP; 15 staff login attempts per 15 minutes per IP). A shared network shares this basic limit.
- Staff sessions use HttpOnly, SameSite cookies, session-ID regeneration, a two-hour inactivity timeout, and fresh account status checks. HTTPS connections automatically use Secure cookies. All state-changing forms check CSRF; SQL values use PDO prepared statements; HTML output is escaped.
- The project is configured for a local XAMPP demonstration. Keep Apache on your trusted local machine/network. A public deployment needs its own HTTPS, access and operational setup.

## Folder guide

```text
index.php, doctors.php       Public homepage and directory
book.php, appointment.php    Booking form and private slip
login.php, logout.php        Role-based staff authentication
admin/                      Overview, doctors, schedules, receptionists
reception/                  Dashboard, queue, full printable patient list
includes/                   Database, authentication, validation, booking service, layout
assets/css/, assets/js/      Responsive design and small browser interactions
assets/vendor/              Local Bootstrap 5.3.3 + MIT license
assets/images/              Original hospital and doctor illustrations
assets/uploads/             Safely re-encoded doctor photo uploads
database/                   Schema and fictional doctor/schedule seeds
scripts/                    CLI database installer and first-admin helper
tests/                      Optional integration/HTTP tests and QA screenshots
```

The five main domain tables are staff users, doctors, weekly schedules, unavailable dates and appointments. Two small support tables hold booked-day snapshots/counters and expiring rate-limit buckets. See inline comments in `includes/booking.php` and `database/schema.sql`.

## USB handover

For a fresh demonstration, copy the project folder (or extract the handover ZIP), excluding `config.local.php`, to `C:\xampp\htdocs\doctor-hospital` on the other laptop. Install XAMPP, start Apache/MySQL, and run setup. The other laptop creates its own database and admin password. No development PHP runtime or test database needs to be copied.

To transfer existing hospital data instead, export `doctor_hospital` from phpMyAdmin and import that export on the destination; copy `assets/uploads` with it. Database backups contain patient records and staff password hashes and must be kept private. Use the existing administrator credentials after restoring a database; do not rerun first-admin creation. Paths stored in the database are relative, so uploaded images survive a move.

## Tests and troubleshooting

See **TESTING.md** for executed results and remaining manual checks. Optional database integration tests need only PHP and the configured MySQL/MariaDB server:

```powershell
& C:\xampp\php\php.exe tests\run.php
```

They create and remove their own randomly named `doctor_hospital_test_*` database. Never aim a destructive custom test at the live hospital database. Optional HTTP tests additionally require Python; it is a development tool only, never an application requirement.

- **Connection refused / setup fails:** start MySQL, check its port and `config.local.php`.
- **404 or missing styles:** check the folder is exactly `htdocs\doctor-hospital` and `base_url` is `/doctor-hospital`.
- **Could not find driver:** enable `pdo_mysql` in the PHP configuration used by Apache and CLI.
- **Photo error:** enable GD/fileinfo and ensure `assets/uploads` is writable. PHP's `upload_max_filesize` should be at least 2M and `post_max_size` at least 3M.
- **No bookable dates today:** the current Pakistan time may be outside booking hours. Choose an upcoming date, or configure another weekday.
- **“Too many attempts”:** wait for the relevant rate-limit window to expire.
- **Print button not responding inside an embedded preview:** open the site in a normal desktop browser and use its print dialog.
- **Apache access protection:** XAMPP must allow `.htaccess` (`AllowOverride All`, its usual htdocs setup). `database/schema.sql`, `tests/run.php`, and `config.local.php` should return 403/404 over HTTP. Directory listing and uploaded executable files are blocked.
