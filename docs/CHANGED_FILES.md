# Changed-file guide — 1.0.0

<!-- File purpose: CHANGED FILES documents the BloodBridge BD release, workflow, or verification process. -->

Comparison base: the saved `BloodBridge_BD_Portable_Ready.zip` supplied for this project, not unprovided later edits on another computer. No existing file was deleted. No Git commit or push was performed by this build.

## Feature-to-file map

| Change | Main files |
| --- | --- |
| Existing design retained | `assets/css/style.css` and `assets/js/app.js` unchanged; existing header gains a Services link |
| Existing Division → District/Zilla → Upazila/Area and password alignment | Saved build's `includes/locations.php`, JS and CSS retained; `register.php` also initializes donor capability |
| Installer / legacy ENUM upgrade / new tables | `setup.php`, `config/database.php`, `includes/schema_v100.php`, `migrations/v100.sql` |
| Private prescription upload and review | `request_create.php`, `request_documents.php`, `document.php`, `includes/services.php`, `request_edit.php` |
| Donor health reports, hospital consent, donor/seeker capability | `health_records.php`, `donor_profile.php`, `donor_details.php`, `includes/services.php` |
| Direct hospital donation and one-time inventory/history | `direct_donations.php`, `includes/donations.php`, `includes/services.php` |
| University clubs and campaigns | `clubs.php`, `includes/clubs.php` |
| Expiry, reminders and stock alerts | `includes/maintenance.php`, `bin/maintenance.php`, `settings.php`, `stock_settings.php` |
| Hospital verification, reports and audits | `hospital_verification.php`, `admin_hospitals.php`, `reports.php` |
| Session, OTP, authorization, privacy | `config/app.php`, `includes/auth.php`, `verify_account.php`, protected-directory configuration |
| Admin testing and reproducible tests | `testing.php`, `tests/`, CI test workflow, `docs/TEST_REPORT.md`, `docs/MANUAL_ACCEPTANCE.md` |
| Portable installation and demonstration | `START_HERE_BN.html`, `README.md`, `DEMO_GUIDE_BN.md`, `DONOR_FEATURE_GUIDE_BN.md` |

The old stylesheet SHA-256 is identical in this release:

`f06e3264e09e26f96072738f017c9ac48a8da337311af4062fd076ee709237db`

## Modified existing files

- `DEMO_GUIDE_BN.md`
- `DONOR_FEATURE_GUIDE_BN.md`
- `README.md`
- `admin_hospitals.php`
- `config/app.php`
- `config/database.php`
- `dashboard.php`
- `donation_history.php`
- `donor_details.php`
- `donor_profile.php`
- `donors.php`
- `includes/auth.php`
- `includes/header.php`
- `inventory.php`
- `register.php`
- `request_create.php`
- `request_edit.php`
- `requests.php`
- `reservation_create.php`
- `reservations.php`
- `search.php`
- `setup.php`
- `verify_account.php`

## Added files

- `.github/workflows/php-tests.yml`
- `START_HERE_BN.html`
- `bin/maintenance.php`
- `clubs.php`
- `config/.htaccess`
- `direct_donations.php`
- `docs/CHANGED_FILES.md`
- `docs/GIT_UPDATE_BN.md`
- `docs/MANUAL_ACCEPTANCE.md`
- `docs/TEST_REPORT.md`
- `docs/automated-test-results.json`
- `document.php`
- `health_records.php`
- `hospital_verification.php`
- `includes/.htaccess`
- `includes/clubs.php`
- `includes/donations.php`
- `includes/maintenance.php`
- `includes/schema_v100.php`
- `includes/services.php`
- `migrations/.htaccess`
- `migrations/v100.sql`
- `reports.php`
- `request_documents.php`
- `services.php`
- `settings.php`
- `stock_settings.php`
- `testing.php`
- `tests/.htaccess`
- `tests/mysql_integration.php`
- `tests/unit.php`
- `tests/workflow.php`

Use the complete release together: replacing only one PHP page can leave its required tables or shared helpers missing. Preserve your local database configuration and your existing Git history. Read `GIT_UPDATE_BN.md` before updating the repository.

