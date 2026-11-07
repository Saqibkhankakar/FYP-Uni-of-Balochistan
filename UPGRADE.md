# Update the existing XAMPP hospital

**Latest update, 29 October 2026:** the patient assistant is supplied separately in `doctor-hospital-chatbot-update.zip`. Follow **[CHATBOT.md](CHATBOT.md)** for its exact files, repeatable migration and XAMPP steps. The instructions and database counts below record the earlier **26 October archive/translation update**. The current migration command also creates/seeds the chatbot bank; existing knowledge edits are preserved.

Use **doctor-hospital-update.zip**, not the fresh-install setup. This update continues the existing project and preserves its records, configuration and photos. The development directory had no Git metadata, so changes were compared against the original `doctor-hospital-usb.zip`; the original archive was retained outside the project before rebuilding the full handover archive.

## Database status on this laptop

On 26 October 2026, the actual XAMPP database was inspected. `doctors.archived_at` was missing. Before changing it, a complete SQL export was saved outside the web folder:

`C:\Users\Luveabid\AppData\Local\DoctorHospitalTools\backups\xampp-doctor-hospital-20261026-225032.sql`

The additive migration is **already applied to that database**. Verification confirmed all pre-existing fields and rows unchanged: 4 doctors, 14 schedules, 6 appointments, 2 appointment-day snapshots and 2 staff accounts. Keep this backup private; it contains hospital data and password hashes.

The only schema change is:

```sql
ALTER TABLE doctors ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER active;
```

The SQL file `database/migrations/001_doctor_archive.sql` is a one-time reference. Prefer the repeatable CLI below: it checks `information_schema`, obtains a migration lock and skips an existing column. It never resets the database, inserts demo records or changes tokens/statuses.

## Copy the updated application

1. Finish active staff work and briefly stop Apache in XAMPP while copying. Leave MySQL running. Back up `C:\xampp\htdocs\doctor-hospital` as a folder, including configuration and uploaded photos. If data has changed since the backup above, export a fresh copy of `doctor_hospital` through phpMyAdmin first.
2. Extract `doctor-hospital-update.zip` into **`C:\xampp\htdocs\doctor-hospital`**, merging its directories and replacing the listed code files. The ZIP has files directly at its root: `language.php` must become `C:\xampp\htdocs\doctor-hospital\language.php`.
3. The ZIP deliberately excludes `config.php`, `config.local.php`, all uploaded photos, database exports, `database/schema.sql` and demo data. Keep your existing configuration, accounts and `assets/uploads` directory. Do not import schema/demo SQL or run `setup-xampp.bat` for this update.
4. Open PowerShell in the hospital folder and run:

   ```powershell
   & C:\xampp\php\php.exe scripts\migrate.php
   ```

   On this laptop it should simply confirm the existing column. On another installation, **check its schema and export its database first**, then run this command. The migration command does not make a backup automatically.
5. Start Apache, open `http://localhost/doctor-hospital/`, and hard-refresh once to refresh cached JavaScript/CSS. Use **English | اردو** in the header. Sign in and check Doctors, Schedules, Receptionists, patient queues and printing.

The current running hospital's application files were not overwritten during development. Testing used a separate temporary XAMPP folder/database. The empty QA database was removed and the QA site was archived outside the web folder after verification.

## Using doctor management

- **Delete doctor:** Super Admin only, shown for a doctor with no appointment history. A confirmation dialog appears. Even cancelled appointments prevent permanent deletion.
- **Archive doctor:** preserves the doctor, schedules, appointments and private slips. Resolve future visits by contacting patients and cancelling bookings; clear today's outstanding queue first. The warning links to the affected date. Public booking closes when archive succeeds.
- **Restore doctor:** restores active public booking using the existing schedules and token counters. Historical queues remain accessible to authorized staff even while archived.

## Verification and remaining checks

The full suite passed on MySQL and on XAMPP MariaDB: **59 database + 78 HTTP assertions**. All original 75 checks are retained. The catalogue audit passed for 396 entries; all 38 delivered PHP files passed syntax checks. Browser checks covered both language directions, booking draft preservation, Urdu font loading, mobile/desktop RTL, slips and queue lists. See TESTING.md for details.

Check actual A4 print/PDF pagination in normal Edge/Chrome, especially a long Urdu queue. The embedded preview could render the pages but could not complete its native print dialog. Browser-native date/file/print controls use the Windows/browser language; names and other user-entered text are intentionally not translated.

## Exact update ZIP file list

- `.gitignore`
- `.htaccess`
- `admin/doctors.php`
- `admin/index.php`
- `admin/receptionists.php`
- `admin/schedules.php`
- `appointment.php`
- `assets/css/rtl.css`
- `assets/fonts/NotoNaskhArabic.ttf`
- `assets/fonts/OFL.txt`
- `assets/js/app.js`
- `assets/vendor/bootstrap.rtl.min.css`
- `book.php`
- `database/migrations/001_doctor_archive.sql`
- `doctors.php`
- `includes/auth.php`
- `includes/booking.php`
- `includes/bootstrap.php`
- `includes/doctor-card.php`
- `includes/doctor-management.php`
- `includes/error-page.php`
- `includes/footer.php`
- `includes/functions.php`
- `includes/header.php`
- `includes/i18n.php`
- `includes/migrations.php`
- `index.php`
- `lang/.htaccess`
- `lang/en.php`
- `lang/ur.php`
- `language.php`
- `login.php`
- `logout.php`
- `README.md`
- `reception/index.php`
- `reception/print.php`
- `reception/queue.php`
- `scripts/dev-router.php`
- `scripts/install.php`
- `scripts/migrate.php`
- `TESTING.md`
- `tests/feature_cases.py`
- `tests/http-fixture.php`
- `tests/http_smoke.py`
- `tests/run.php`
- `tests/translations.php`
- `UPGRADE.md`

`database/schema.sql` is also updated in the development source and full USB ZIP for fresh installations, but is intentionally omitted from this update ZIP.

The full `doctor-hospital-usb.zip` is rebuilt from the latest source for **new installations only**. Do not extract that full package over your configured running hospital.
