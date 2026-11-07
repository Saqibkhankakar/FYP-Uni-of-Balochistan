# Verification record

## Current chatbot delivery — 29 October 2026

Verification uses real **XAMPP Apache 2.4.65, PHP 8.3.36 and MariaDB 10.11.16**, with a separate site at `/doctor-hospital-chatbot-qa-20261029` and an isolated QA database. Existing hospital data is not used as disposable fixtures. The earlier 26 October record below is retained as history.

| Check | Final result |
| --- | --- |
| Existing database integration suite, `tests/run.php` | 59 assertions passed |
| Chatbot integration suite, `tests/chatbot.php` | 66 assertions passed |
| Complete HTTP suite, including existing language/archive cases and `tests/chatbot_cases.py` | 107 assertions passed |
| Total automated behavioral assertions | **232 passed** |
| Translation audit | 457 English/Urdu entries, 457 literal references; matching placeholders |
| PHP syntax | All 48 project PHP files passed |
| Chatbot JavaScript syntax | `node --check assets/js/chatbot.js` passed; Node is only a development check |

The 66 chatbot integration assertions cover the 51-entry bank, at least 200 patterns (currently 238), equivalent wording, spelling tolerance, Roman Urdu/Urdu, unknown/ambiguous input, medicine-scope refusal, emergency priority, fixed warnings with emergency bank entries disabled, role checks, admin edits, repeat migration without duplicates or re-enabling disabled rows, and context expiry. They also create a real test doctor and verify automatic appearance, specialization changes, schedule changes, full/unavailable dates, archive, restore and inactivity. Booking links retain the existing workflow. These tests create and clean up only their randomly named test database.

The HTTP suite retains all 78 previous checks, including concurrent-booking coverage in the separate integration suite, English/Urdu booking, private slips, receptionist print lists and safe doctor lifecycle. It adds 29 chatbot checks: public/staff widget placement, authorization and CSRF, invalid input including array fields, prepared/escaped admin content, search/create/disable, real doctor cards, context/reset, bilingual responses, rate limiting and emergency bypass. First-admin creation is tested when the QA database has no admin; a run against a database with an existing admin reports **106 HTTP assertions**, with that one check explicitly skipped. Use a separate demo/QA database for HTTP tests because they create temporary accounts and exercise public rate-limit buckets.

Browser checks executed on the isolated QA site: English desktop at 1440×1000, Urdu mobile at 390×844, tablet at 768×1024 and a short 390×420 viewport. Verified launch/close/Escape and focus return, scrolling, readable Urdu, card photo/qualifications/weekly hours, and a booking button reaching the existing doctor form. A mobile panel overflow was corrected and rechecked. Roman Urdu produced an Urdu answer with an honest no-specialist notice. The Super Admin search/editor rendered in both languages, preserved the selected edit URL on switching, and fit the mobile document width. No unexpected JavaScript errors were observed.

The QA knowledge table was temporarily renamed to test failure handling, then restored. Ordinary chat showed the translated service error; the built-in severe-chest-pain warning still worked in Urdu without querying the bank. The expected HTTP 503 was the only induced browser error. Screenshots are linked in [CHATBOT.md](CHATBOT.md).

The actual XAMPP database was inspected and backed up before the additive migration. The bank was initially absent; the archive column was already present. The backup is `C:\Users\Luveabid\AppData\Local\DoctorHospitalTools\backups\xampp-before-chatbot-20261029-070518.sql` (16,500 bytes). Existing domain row hashes matched afterward: **4 doctors, 15 schedules, 13 appointments, 6 day snapshots, 2 staff accounts, 0 unavailable dates**. The existing configuration, uploads and application code in the running hospital were not replaced. Migration repeatability preserves knowledge answers and enabled flags. Only the 51 new bank entries were added; five of those task-created defaults were finalized before delivery with exact-value guards.

### Current commands

Run from the project/QA folder, using its own database configuration:

```powershell
& C:\xampp\php\php.exe tests\run.php
& C:\xampp\php\php.exe tests\chatbot.php
& C:\xampp\php\php.exe tests\translations.php
python tests\http_smoke.py --php C:\xampp\php\php.exe --url http://localhost/doctor-hospital-chatbot-qa-20261029
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

Python is optional test tooling, not a website dependency. No remote AI service is configured. Physical phones, a second-laptop installation and native printer/PDF dialogs were not exercised during this chatbot update; clinical accuracy and large-scale load were not measured. Existing print pages and token ordering passed HTTP regression checks. Updated source must still be copied to the running hospital as described in [CHATBOT.md](CHATBOT.md).

### Verified packages

`doctor-hospital-chatbot-update.zip` contains **34 changed/new files**, including documentation and five screenshots. `doctor-hospital-chatbot-fresh-install.zip` contains **104 files** under `doctor-hospital/`. Both passed ZIP CRC checks, duplicate/path checks, byte-for-byte comparison with saved source and exclusion checks for private configuration, uploads, logs, caches, credentials and database exports. Applying the update entries over the preserved pre-chatbot file snapshot reproduces the complete current deliverable source. The final checksums are in `CHATBOT-SHA256SUMS.txt` beside the archives.

The fresh ZIP was extracted outside the web root and its actual installer executed against a newly named disposable database. It created 51 knowledge entries, 4 fictional doctors and 4 demo appointments, with no permanent staff credentials. English, Roman Urdu and Urdu routing worked from those extracted files. Re-running the packaged migration preserved all doctor, appointment and knowledge rows. Only this task-created package test database was removed afterward.

A final live-database repeat-migration check created a second private **45,073-byte** backup at `C:\Users\Luveabid\AppData\Local\DoctorHospitalTools\backups\xampp-before-chatbot-20261029-121225.sql`. All six domain snapshots and all 51 bank rows matched afterward. The first final-suite attempt could not connect because XAMPP services had stopped; restarting the existing Apache/MySQL services resolved it and the complete rerun passed. No database repair, reset or recreation was needed.

Temporary HTTP/browser staff and fixture records were cleaned by their test prefix. The separate QA database retains only its fictional installation data and the knowledge bank. The temporary QA site is archived outside `htdocs` after verification. Browser viewport overrides were reset and the QA tab closed. Apache/MySQL were left running; the existing hospital code still awaits the update copy.

## Historical verification — 26 October 2026

Executed on **26 October 2026**, using PHP **8.3.36** and a real **MySQL 8.0.45 / InnoDB** server on an isolated loopback port. The same **59 integration + 78 HTTP assertions (137 total)** also passed under **XAMPP Apache 2.4.65 / PHP 8.3.36 / MariaDB 10.11.16**, using a separate temporary site and QA database. The application's timezone and database session were set to Pakistan time. All original 75 assertions remain included.

## Automated tests executed

**59 database integration assertions passed** (`tests/run.php`), including all original 38:

- Database connectivity and schema installation in a randomly named disposable test database.
- Recurring weekday upsert without duplicates; capacity calculation, including 36 five-minute positions in three hours.
- Opening boundary, exact cutoff, future advance bookings, past dates, non-working weekdays and 30-day horizon.
- Pakistani phone rejection and normalization.
- Token allocation, estimates, unpredictable private tokens and duplicate-submission idempotency.
- Full-capacity rejection for online bookings and walk-ins.
- Safe absence handling; booked-date snapshots preserved after schedule edits.
- Cancellation, position reuse, monotonic replacement tokens and unchanged existing estimates.
- Database uniqueness rejection of a deliberately colliding position.
- Arrival, completion, current consultation exclusivity, future consultation rejection and no-show recovery.
- Inactive doctor rejection and independent queues per doctor/date.
- **Eight independent PHP processes booking simultaneously:** eight distinct sequential tokens and positions.
- **Eight processes competing for three positions:** exactly three accepted, five rejected as full.
- Database-backed rate limiting.
- Receptionists cannot delete, archive or restore through the service. Unused doctors and schedules can be removed; any appointment history blocks permanent deletion.
- Future bookings (including premature no-show status) block archive. Archive preserves patient rows/private links and rejects new bookings even if another writer sets active=1. Restore preserves token counters.
- Repeated migration is harmless. A populated pre-upgrade schema was simulated only inside the disposable database; adding the column preserved every doctor and appointment field and defaulted archived_at to NULL.
- English/Urdu helpers, RTL, localized weekday/month labels and phone validation.

**78 HTTP assertions passed** (`tests/http_smoke.py` plus `tests/feature_cases.py`), including all original 37:

- Actual first-admin CLI creation using a random password through stdin; no password stored in source.
- Admin and receptionist login, session cookie flags, authentication redirects and admin route authorization.
- CSRF rejection, doctor creation, valid photo re-encoding, SVG upload rejection, recurring schedule creation and receptionist account creation.
- Public booking without login, private confirmation link, estimated time, HTML escaping, no-referrer/no-store headers and duplicate submission protection.
- Reopening a private link in a separate session; sequential IDs and invalid private tokens cannot expose an appointment.
- Walk-in booking, full-capacity display, patient search, arrival, cancellation and replacement with a new stable token.
- Complete staff-only patient print list, correct token order including cancellations, and private file access protection through the local development router.
- Receptionist deactivation invalidates an already signed-in session.
- All three doctor lifecycle endpoints reject receptionists. Unused doctor deletion, history protection, archive warning, archive after resolution, historical print/slip access and explicit restoration passed.
- Both language directions, selected date, patient draft, submission key, local RTL/font assets, Urdu validation and actual Urdu booking confirmation passed.
- All public and admin pages persist Urdu. Queue status labels translate while form values retain the original English enums. Urdu print lists preserve names, token order and LTR phones.
- Language changes require CSRF, reject unsupported languages and cannot redirect outside the hospital.

**Translation audit passed:** `tests/translations.php` checked **396 matching bilingual entries**, **386 literal translation references**, and matching interpolation placeholders.

**PHP syntax checks passed:** all **38 delivered PHP files** (plus the temporary development configuration during verification).

Temporary HTTP test staff, doctors, uploads and appointments were cleaned up by the test harness. Random test databases were dropped after the integration run. The fictional installation demo data was preserved.

The existing development database already had archived_at. Its schema was checked and a private SQL backup was made outside the web folder before running the idempotent migration CLI. SQL INSERT data before/after verification matched exactly for doctors, staff_users, weekly_schedules, unavailable_dates, appointment_days and appointments: 4 doctors, 14 schedules, 5 appointments and no permanent staff accounts. No database reset or recreation was performed. Temporary test counters may advance AUTO_INCREMENT; expiring test rate-limit buckets are cleaned up.

XAMPP became accessible during final verification. The actual `C:\xampp\htdocs\doctor-hospital` database lacked archived_at. A successful 14,229-byte SQL backup was created at `C:\Users\Luveabid\AppData\Local\DoctorHospitalTools\backups\xampp-doctor-hospital-20261026-225032.sql` before applying the additive column migration. Before/after row hashes matched for every existing field in all six domain tables: **4 doctors, 14 schedules, 6 appointments, 2 booked-day snapshots and 2 staff accounts** (no unavailable dates). Existing configuration, uploads and application files were not replaced. The new code is supplied in the update ZIP for copying into that installation.

The first XAMPP HTTP run found Apache converting the unspecified reason phrase for status 419 to a wire-level 500. CSRF rejection now sends the explicit `419 Page Expired` status line. All 78 HTTP checks then passed, including the previously failing check, both language directions and Apache's actual private-directory restrictions. The empty QA database and temporary accounts were removed after verification; the QA site was archived outside the web folder.

## Browser checks executed

- Rendered the public homepage, booking page, confirmation, staff login, admin overview, schedule editor, reception dashboard, queue and full patient-list page in the in-app Chromium browser.
- Completed a real no-login booking with fictional details and confirmed the resulting slip, token and estimate.
- Signed in with a temporary admin account, opened schedules, selected a future reception date and opened its populated queue and printable list.
- Checked responsive layouts at **390 px**, **768 px**, and **1440 px** viewport overrides. Homepage, slip, admin overview and queue checks found no document-level horizontal overflow; wide staff tables scroll within their containers.
- Checked the mobile navigation toggle. Checked homepage images loaded successfully.
- Captured representative screenshots under `tests/screenshots/`.
- For this update, rendered Urdu homepage at **390, 768 and 1440 px**, mobile booking/slip, all admin management pages, queue and desktop printable list. The local Noto Naskh Arabic font loaded, with no browser console errors observed. Document width stayed within the viewport; wide tables/print lists use their existing internal horizontal scrolling on small screens.
- Filled an Urdu booking draft and switched Urdu → English → Urdu through the live header controls. Name, phone, reason and selected date survived. No extra appointment was submitted by this browser check.
- Urdu private slip showed translated date, token and readable reference; printable queue showed the five preserved demo patients in token order with readable phones/times. The actual Urdu print button was attempted, but the embedded browser's native print interaction timed out.

## Remaining manual verification

- **Updated files still need copying to the running hospital folder.** XAMPP, MariaDB and Apache `.htaccess` were verified in a separate QA site; the live database migration is already applied. After copying, check both languages in your usual browser and clear cached CSS if needed. Installing XAMPP from scratch on a second laptop was not repeated.
- **Native print/PDF dialog and physical paper output are not verified.** The print action was attempted in the embedded browser but its native dialog was not accessible to the automation. Both pages, required columns, token ordering and print CSS exist; browser print pagination must be checked in normal Edge/Chrome on the destination laptop. Test A4 at 100% scale, with browser headers/footers off. For a long queue, verify repeated headers and no cut-off rows.
- The Windows masked PowerShell first-admin prompt was source-reviewed, but was not driven interactively. The underlying PHP CLI account creation and subsequent login were executed successfully through stdin.
- Offline operation on a physically disconnected laptop and transfer to a second laptop have not been executed. All runtime frontend dependencies are local; the handover archive excludes development configuration and credentials.

## Run the checks yourself

```powershell
# PHP + MySQL/MariaDB only; creates its own disposable database.
& C:\xampp\php\php.exe tests\run.php
& C:\xampp\php\php.exe tests\translations.php

# Optional HTTP suite: requires Python only for testing, and Apache/MySQL running.
python tests\http_smoke.py --php C:\xampp\php\php.exe --url http://localhost/doctor-hospital

# Syntax check all project PHP files.
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

The first-admin assertion runs only when the hospital database contains no admin; otherwise the HTTP harness creates its own temporary admin and reports 77 HTTP assertions. Run HTTP tests in a demonstration database: they briefly create real staff accounts and use the public rate-limit bucket for loopback traffic. Apply the additive migration first when testing an older installation.

## Demonstration checklist

1. Create the first admin, then a receptionist account.
2. Add a doctor, upload a photo and save two different weekly schedules.
3. Book a future appointment as a patient without signing in.
4. Save the private link, open it in a new session and print/save the slip.
5. As receptionist, select that date, search for the patient and mark them Arrived.
6. On today's test clinic, call a patient into consultation, then complete the visit.
7. Cancel a booking and refill it. Confirm other tokens/times did not move.
8. Use a short consultation period to demonstrate full capacity.
9. Print the full doctor/date list and confirm its A4 layout.
10. Mark an empty future date unavailable and verify patients cannot book it.
11. Switch English/Urdu in all modules and verify an in-progress patient booking draft is retained.
12. Delete an unused test doctor; try deleting a doctor with appointment history and verify it is refused.
13. Resolve future bookings, archive the test doctor, reopen a historical slip and print list, then restore and book again.
14. In normal Edge/Chrome, print Urdu and English slips/lists to PDF on A4. Verify Urdu shaping, phone/time direction, token order and multipage repeated table headers.
