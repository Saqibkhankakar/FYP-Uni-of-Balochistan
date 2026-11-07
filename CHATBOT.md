# Hospital Assistant — delivery and viva guide

Completed source update: **29 October 2026**. This extends the existing PHP hospital project; booking, queue, login, doctor archive and English/Urdu features remain in place. No framework, external model, paid API, Composer, npm or runtime Python is added.

## What the assistant does

The public homepage, doctor directory and booking page have an optional **Hospital Assistant** button. Staff pages, private slips and print views do not contain the widget. It accepts English, Roman Urdu and Urdu. Responses follow the site's selected English/Urdu language, including RTL layout, errors, suggestions and doctor-card dates.

The initial bank contains **51 bilingual topics and 238 wording patterns**, covering 13 specialty groups. Four entries concern emergency warnings; additional fixed warnings run independently of the editable bank. Matching is deterministic local NLP, not an LLM or a trained clinical model.

Recommendations query the existing `doctors` table on every request. Only active, unarchived doctors with a matching specialization appear. Cards show the actual registered name, qualifications, stored photo or existing placeholder, weekly hours and the next date currently open for booking. Cards link to `book.php?doctor=ID`. The chatbot never writes an appointment, reserves a position or issues a token.

The date search reuses `booking_state()` across the configured booking horizon. This respects absence, booked-date schedule snapshots, capacity and same-day opening/cutoff rules. Future dates remain bookable at any time, as previously agreed. The booking page checks again when the patient continues. Weekly clinic hours are a guide; the date-specific clinic start can differ when a previous schedule snapshot exists.

## Install the update into your existing XAMPP hospital

Use **`doctor-hospital-chatbot-update.zip`** for the existing project with the completed language/archive update. It contains only the chatbot changes listed below, including tests and documentation. It excludes configuration, uploaded photos, database exports, the fresh schema and fictional hospital demo data.

1. Back up `C:\xampp\htdocs\doctor-hospital` and export the current hospital database through phpMyAdmin. Keep backups outside `htdocs`. If using a different database name or credentials, use that installation's settings.
2. Finish active staff work and briefly stop **Apache** while copying; leave **MySQL** running.
3. Extract the update ZIP into **`C:\xampp\htdocs\doctor-hospital`**, merging folders and replacing the listed code files. There is no enclosing folder in the update ZIP: `chatbot.php` must end up directly inside `doctor-hospital`.
4. Keep your existing `config.php`, `config.local.php`, accounts and `assets/uploads`. Do **not** run `setup-xampp.bat`, import `database/schema.sql`, import demo data or recreate the database for this update.
5. Open PowerShell in the hospital folder and run:

   ```powershell
   Set-Location C:\xampp\htdocs\doctor-hospital
   & C:\xampp\php\php.exe scripts\migrate.php
   ```

6. Restart Apache. Open [the hospital](http://localhost/doctor-hospital/) and refresh with Ctrl+F5. Open **Hospital Assistant**. Try `I have a headache`, `Mere pait mein dard hai`, and `میرے پیٹ میں درد ہے`. A specialty without a registered active doctor correctly shows reception guidance.
7. Sign in as your existing Super Admin and open **Chatbot knowledge bank**. Switch English/Urdu, search for `acne`, edit an entry and save. Existing doctors need no separate chatbot configuration.

**Status on this laptop:** the database migration has already been applied safely; the running hospital's application files have not been replaced. Copy the update ZIP to enable the UI. Re-running the migration after copying is safe and does not revert knowledge edits. No existing target code file in this update differed from both the preserved baseline and the new source at delivery time.

For rollback, restore the backed-up application files as a consistent set. The additional bank table may remain unused; there is no need to delete it or reset hospital records.

## Fresh installation

Use **`doctor-hospital-chatbot-fresh-install.zip`** only for a new installation. It contains the complete project inside a `doctor-hospital` folder, including local fonts/assets and fictional demo seeds, with no machine-specific configuration, database dump, patient records or credentials.

Extract that folder into `C:\xampp\htdocs`, start Apache/MySQL and follow [README.md](README.md). `setup-xampp.bat` runs the installer and prompts for your first administrator; its optional demo installation uses fictional people. For an empty hospital, run `php scripts/install.php` without `--demo`, then the existing administrator helper. Installation applies both additive migrations and seeds the chatbot bank. Do not use fresh-install setup over a working hospital.

## Migration and preserved data

`database/migrations/002_chatbot_knowledge.sql` creates one independent InnoDB/UTF-8 table. It holds bilingual questions/answers, newline-separated patterns, a specialty label, optional follow-up prompts, active/emergency flags, a stable unique seed key and an update timestamp. It introduces no foreign keys or changes to existing relationships.

`scripts/migrate.php` uses the existing migration lock, checks the archive column, creates the table if absent and inserts missing seed keys in a transaction. `INSERT IGNORE` preserves existing answers, active flags and custom entries. The SQL file alone creates an empty table; use the PHP migration to seed it. Normal page requests never run migrations. The CLI does not create a backup automatically.

Before the actual XAMPP migration, the archive column was present and the bank table was absent. A successful **16,500-byte** full SQL backup was saved at:

`C:\Users\Luveabid\AppData\Local\DoctorHospitalTools\backups\xampp-before-chatbot-20261029-070518.sql`

Existing domain row hashes matched before and after: **4 doctors, 15 weekly schedules, 13 appointments, 6 booked-day snapshots, 2 staff accounts and 0 unavailable dates**. No existing hospital row was deleted, reset or recreated. During implementation, five newly seeded assistant entries were finalized (four emergency answers and one Urdu spelling); these were the task's new bank data, with exact-value guards preserving edits. Private data snapshots and backups remain outside the project and both ZIPs.

## Explain the algorithm in the viva

1. **Normalize:** lowercase input, normalize common Urdu letter variants, remove punctuation/diacritics and split into words. Remove ordinary stop words and overly broad words such as `pain`/`dard` for category scoring.
2. **Safety and intent:** check fixed emergency patterns first. These return an urgent message with no doctor cards or clarification. Routine medication/diagnosis requests get a scope response; greetings and booking-help requests have small explicit intents.
3. **Score the enabled bank:** exact whole phrases score `0.90 + min(0.09, 0.03 × significant word count)`. Otherwise score `0.75 × matched pattern coverage + 0.10 × matched query overlap`. Exact words score 1; Latin spelling distance 1 scores 0.9; distance 2 scores 0.82 only for words of at least 9 characters. This is PHP Levenshtein matching, not semantic embeddings. Short/Urdu words use exact normalized matching. Synonyms are explicit alternative patterns and specialty aliases.
4. **Choose cautiously:** retain the best entry for each specialty above 0.76. Child-specific wording adjusts overlapping scores toward Pediatrics. If the best two specialties differ by less than 0.12, ask one clarification and offer specialty buttons. Unknown/low-scoring input goes to a reception fallback. These scores are heuristics, not medical probabilities.
5. **Use short context:** retain only a bank entry ID and a five-minute expiry in the existing PHP session. A follow-up such as `when available?` reloads that enabled entry and current doctors. Clearing the chat, ambiguity, unknown input and emergency routing clear context. No patient text is retained in the session.
6. **Read live doctors:** map the specialty against normalized existing specialization labels/aliases, then query schedules and existing booking rules. New doctors, specialty/schedule edits, inactivity, archive and restore appear on the next request without rebuilding a separate index.

Suggested student responsibilities: PHP matching/data queries; widget/languages/RTL; admin editor/migrations/tests. Each can explain how their code connects to the existing booking service.

## Knowledge editor and security

Only an active Super Admin can view or save the editor; the service also independently checks the role. Use canonical specialty labels from the datalist for alias matching, or an exact custom specialization. Enter English/Urdu guidance and English/Roman Urdu/Urdu patterns. Save before changing language: unsaved staff edits are not carried across page navigation. Enabled entries take effect immediately. An emergency entry always sends the fixed urgent message rather than its editable answer.

Patient messages are POST-only, CSRF-protected, UTF-8 validated and limited to 500 characters. The endpoint limits ordinary requests to 20 per minute per IP and limits request size to 8 KiB. Fixed emergency responses bypass the ordinary quota and database access. The existing rate-limit storage holds a hashed bucket and counters, not health messages.

Prepared statements handle database values. HTML output is escaped; the widget creates text nodes rather than interpreting answer HTML and only accepts same-origin card URLs. Responses use `no-store`. The chat is transient browser memory (at most 20 bubbles); no transcript, health-message analytics or localStorage is added. The server logs exception classes only, not submitted messages. Existing server administrators should not enable request-body logging in their hosting configuration.

## Verification and screenshots

See [TESTING.md](TESTING.md) for actual test counts, environment, package verification and remaining manual checks. Browser evidence uses an isolated QA database with fictional demo records:

- [English desktop doctor card](tests/screenshots/chatbot-english-desktop.jpg)
- [Urdu mobile / Roman Urdu input](tests/screenshots/chatbot-urdu-mobile.jpg)
- [Urdu emergency response](tests/screenshots/chatbot-emergency-urdu.jpg)
- [Urdu service error](tests/screenshots/chatbot-error-urdu.jpg)
- [Urdu Super Admin editor](tests/screenshots/chatbot-admin-urdu.jpg)

## Limitations to state honestly

- This is a small rule-based routing demonstration, not general conversational AI, diagnosis or a clinically validated triage system. No accuracy percentage or clinical validation is claimed. A qualified clinician should review starter routing wording and any administrator edits before patient use. The editor cannot guarantee that a human never enters inappropriate content.
- Unknown phrasing, complex histories, negation and multiple concerns can be misunderstood. Emergency matching is deliberately conservative: even negated or historical mentions may show a warning. It cannot detect every emergency. Urgent warnings were informed by public [NHS heart-attack guidance](https://www.nhs.uk/conditions/heart-attack/), [NHS breathing guidance](https://www.nhs.uk/symptoms/shortness-of-breath/) and [American Heart Association warning signs](https://www.heart.org/en/about-us/heart-attack-and-stroke-symptoms); those sources do not validate this algorithm. The UI refers to local emergency services rather than assuming a foreign emergency number.
- Urdu spelling normalization is limited; fuzzy edit distance applies only to Latin words. Roman Urdu has many spellings, so additional locally reviewed patterns may be useful. Site language determines the answer language; it does not auto-switch from detected input.
- Cards show up to six alphabetically ordered matching doctors, not a clinical ranking. Availability is a current lookup and can change. No laboratory, prescription, insurance or hospital-management modules were added.
- The bank is capped at 500 entries through the editor. Its simple in-memory scan is intended for a small local hospital, not benchmarked large-scale deployment. Patients sharing an IP share the ordinary request quota.
- Navigating/reloading loses visible chat history; the short routing ID may remain until expiry or Clear chat. There is no cross-device conversation history. This reduces retention and complexity.
- Automated tests verify behavior, security boundaries and regressions; they do not prove medical suitability. Physical mobile devices, native print dialogs and paper output are outside the browser checks completed for this chatbot update.

## Exact changed-file manifest

There is no `.git` directory in this working copy, so `git status` cannot report a working tree. Files were compared byte-for-byte against the preserved pre-chatbot snapshot `before-chatbot-20261029.zip`. No baseline file was removed. The update package includes exactly the files below; the fresh package contains the complete source. The ZIP files themselves are delivery artifacts, not included recursively.

<!-- CHATBOT_MANIFEST -->

**34 files** (including documentation and screenshots):

- **Updated** — `.htaccess`
- **New** — `admin/chatbot.php`
- **New** — `assets/css/chatbot.css`
- **New** — `assets/js/chatbot.js`
- **New** — `CHATBOT.md`
- **New** — `chatbot.php`
- **New** — `database/chatbot-seeds.php`
- **New** — `database/migrations/002_chatbot_knowledge.sql`
- **New** — `includes/chatbot-migration.php`
- **New** — `includes/chatbot-specialties.php`
- **New** — `includes/chatbot-widget.php`
- **New** — `includes/chatbot.php`
- **Updated** — `includes/footer.php`
- **Updated** — `includes/header.php`
- **Updated** — `includes/i18n.php`
- **Updated** — `includes/migrations.php`
- **New** — `lang/chatbot-en.php`
- **New** — `lang/chatbot-ur.php`
- **Updated** — `lang/en.php`
- **Updated** — `lang/ur.php`
- **Updated** — `README.md`
- **Updated** — `scripts/dev-router.php`
- **Updated** — `scripts/migrate.php`
- **Updated** — `TESTING.md`
- **New** — `tests/chatbot.php`
- **New** — `tests/chatbot_cases.py`
- **Updated** — `tests/http-fixture.php`
- **Updated** — `tests/http_smoke.py`
- **New** — `tests/screenshots/chatbot-admin-urdu.jpg`
- **New** — `tests/screenshots/chatbot-emergency-urdu.jpg`
- **New** — `tests/screenshots/chatbot-english-desktop.jpg`
- **New** — `tests/screenshots/chatbot-error-urdu.jpg`
- **New** — `tests/screenshots/chatbot-urdu-mobile.jpg`
- **Updated** — `UPGRADE.md`

<!-- /CHATBOT_MANIFEST -->
