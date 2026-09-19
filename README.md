# BloodBridge BD — lab release 1.1.0

PHP + MySQL/MariaDB software-lab project. The existing interface and stylesheet are retained; additional workflows use the same UI components. Start with **START_HERE_BN.html**.

## Release status

Requested feature implementation is included. This is a **local academic/demo build, not a clinically certified or production-ready medical service**.

Validation performed for this release: 60 PHP files parsed with PHP 8.3.32, JavaScript syntax checked, 49 unit tests, 36 sequential SQLite workflow simulations, 38 donor-matching/privacy/address simulations, 17 HTTP/CSRF checks and 9 JavaScript DOM checks passed. Real MySQL/MariaDB setup/migration, concurrent transactions, browser end-to-end/visual tests, and a second physical PC have **not** been tested in the build environment. See `docs/TEST_REPORT.md` for the evidence and remaining acceptance checks. Passing a simulation is not a native database or concurrency guarantee.

## Start on XAMPP

1. Back up your existing project and database. Do not delete your database.
2. Extract the single `BloodBridge_BD` folder under `htdocs`.
3. Start Apache and MySQL.
4. Open `http://localhost/BloodBridge_BD/setup.php`.
5. Fresh database: click **Install demo database**. Existing database: enter its **existing admin credentials** and authorize upgrade.
6. Open the login page. New services are under **Services**; the existing dashboard stays in place.

Default database: `bloodbridge_bd`, host `localhost`, port `3306`, user `root`, blank password. For non-default XAMPP settings copy `config/database.local.example.php` to `config/database.local.php` and edit that local file. Alternatively use `BLOODBRIDGE_DB_HOST/PORT/NAME/USER/PASS` environment variables. Personal settings and real database dumps must not go into Git.

Run the installer, not only a manual import of `database.sql`: the installer also applies `migrations/v100.sql` and `includes/schema_v110.php`, updates old columns and seeds a fresh demo. Setup is POST + CSRF protected. It is locked after version 110 is recorded. It never drops existing tables. Future releases require an explicit versioned migration; do not remove the lock to reset data.

## Fresh demo accounts only

| Role | Email | Password | Portal |
| --- | --- | --- | --- |
| Administrator | admin@bloodbridge.test | admin123 | login.php |
| Seeker | seeker@bloodbridge.test | seeker123 | login.php |
| Donor / demo club coordinator | donor@bloodbridge.test | donor123 | login.php |
| Second donor | donor2@bloodbridge.test | donor123 | login.php |
| Hospital staff | hospital@bloodbridge.test | hospital123 | hospital_login.php |

The sample hospital staff code is `DMCH2026`. These are published **demo** credentials, not production passwords. Demo accounts and hospital names do not establish a real hospital affiliation. Existing installations keep their passwords; upgrade does not recreate/reset demo accounts.

## Included workflows

- Existing registration, demo OTP, role dashboards, location hierarchy, donor search, hospital stock, requests, reservations, notifications, account controls and history.
- A personal account can also enable donation: Services → Health Records. Donor and seeker activities no longer require two accounts.
- University club applications, admin approval/rejection/suspension, coordinator membership approval, voluntary joining/leaving, reviewed request sharing, campaign registration/cancellation and a hospital-confirmed club donation count.
- Required private JPG/PNG/PDF prescription upload on new blood requests; owner and authorized reviewer access only. Accepting/completing requires review of the latest document. Clinical suitability and compatibility remain hospital responsibilities.
- Private dated health reports, including hemoglobin results and units, optional lab information, review labels, deletion and revocable hospital-specific consent.
- Direct hospital donation: request → same-day staff screening → confirmation after collection and required testing/release. Stock, history and last-donation date update in one transaction; repeat completion is rejected.
- One direct or donor-request commitment at a time. A single-donor request is limited to one unit; create separate requests for multiple donors. Blood-bank requests can still request multiple units.
- Request expiry, reservation expiry, exact reserved-stock release and blocked expired actions.
- Per-hospital/blood-group minimum stock, in-app low-stock alerts on crossing the threshold, and admin reports/CSV/audit views.
- Hospital verification references and reasons; pending/suspended hospitals cannot process clinical/stock workflows.
- Configurable inactivity timeout and sessions separated between project copies.
- Testing Centre: admin-run unit checks and read-only database consistency checks. Normal scheduled maintenance and the test audit entry may still run when opening the page.

## Reminder policy and scheduler

The demo reminder is **105 days** after the recorded donation date. The existing interval default is **120 days**, configurable by Admin. These are demonstration configuration values, **not a universal medical recommendation**. A reminder asks the donor to contact a blood centre for assessment; it does not automatically mark them eligible. Profiles changed by a donor require renewed screening. Donors can opt out.

`bin/maintenance.php` sends in-app reminders and performs expiry/low-stock checks. A scheduler must invoke it while PHP/MySQL are running for idle-site automation. As a local demonstration fallback, authenticated visits check due jobs at most once per minute. Admin → Services → System Settings → **Run Due Jobs Now** also runs only actually due jobs.

Mac XAMPP command (use the actual XAMPP installation path):

```sh
/Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/BloodBridge_BD/bin/maintenance.php
```

Windows XAMPP command:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\BloodBridge_BD\bin\maintenance.php
```

Schedule every five minutes with cron/launchd (Mac/Linux) or Task Scheduler (Windows). This package does not silently install an OS task. In-app notifications are not email, SMS or mobile push notifications. No reminders run when the XAMPP computer is shut down.

## Tests

From the project directory with PHP on PATH:

```sh
php tests/unit.php
php tests/workflow.php
php tests/matching.php
php tests/mysql_integration.php
php tests/mysql_integration.php legacy
```

The first three do not modify your application database. The workflow suite uses **in-memory SQLite with a documented MySQL-syntax adapter**, so it tests sequential business rules only. The native suite creates a randomly named `bb_qa_...` database with configured MySQL credentials, refuses to overwrite an existing database, then removes only the test database. The MySQL account needs create/drop privileges for that suite. It never selects the normal project database for mutations.

A GitHub Actions workflow is included for PHP + MariaDB tests after you push the source to your own repository. It has not been run on your repository from this workspace. No Git commit/push is claimed.

## Data and privacy boundaries

Documents are stored as bounded database BLOBs, not public upload paths; the full database export therefore includes sensitive documents. Each file is limited to 2 MB, each account to 50 documents. Downloads require current authorization and use attachment/no-store/nosniff headers. MIME checks are not antivirus scanning.

Health declarations/reports: owner and explicitly authorized verified hospital staff only, not seekers/clubs/general admin. Prescription: request owner, administrator reviewer, and explicitly assigned verified hospital reviewer. Consent revocation prevents future access through this application; previously downloaded copies or backups cannot be recalled.

A patient's last-received date belongs to the request's patient; a family request must not update the requester's personal received history. Self-request completion updates the account's last-received date. Donation dates and received dates are distinct.

Use fictional records for the lab. Before a real launch: clinical governance, actual email/SMS verification, HTTPS, protected private storage/backups and keys, malware scanning, request rate limits, operational monitoring, retention/deletion policy, legal/privacy review and independently verified institutions are required. OTP shown on screen is explicitly a local **demo**, not proof of ownership of email/phone.

## Reference material

- [WHO donor-selection guidance](https://www.who.int/publications/i/item/9789241548519): clinical assessment belongs to the blood service; software dates and old reports are not a substitute.
- [PHP Fileinfo](https://www.php.net/manual/en/book.fileinfo.php) and [PDO LOB handling](https://www.php.net/manual/en/pdo.lobs.php).
- [PHP-WASM test runtime](https://wordpress.github.io/wordpress-playground/developers/local-development/php-wasm-node/).
- [MariaDB healthcheck](https://mariadb.com/docs/server/server-management/automated-mariadb-deployment-and-administration/docker-and-mariadb/using-healthcheck-sh).
- Offline location data is retained from the supplied build: 8 divisions, 64 districts and 494 listed upazilas/areas; see `includes/locations.php`. It is not a claim that all current city wards/thanas are covered.


## Changes in 1.1.0

- Admin has no request creation, donor response or request completion route. Admin inventory and reservation access is read-only; hospital staff process stock/collections for their hospital. Administrative prescription review, reports and audit remain.
- Donors and seekers use personal accounts. Any personal account with donation enabled and a reviewed eligible profile can offer to donate.
- Donor request: Interested → seeker selects one → selected donor confirms → donor reports actual donation → owner confirms receipt. Competing offers show Not selected. Declining affects only that donor's response; it does not reject the request.
- Record Not Received cancels before donation. After a donation report it records a dispute and holds the donor commitment. The seeker can later confirm receipt, or the donor can correct an incorrect report after the seeker disputed it. A disagreement remains visible; admin does not invent a clinical outcome.
- Division → District / Zila → Upazila / Area selectors now serve registration, search, request creation/editing, personal location, hospital creation, clubs and campaigns. The 494 original entries and known urban-area entries from the supplied project are retained. District and Zila mean the same level.
- Known old addresses are normalized to the same searchable form during setup. Unknown/custom addresses are preserved; their owners should update the location before filtering by a division/district. No coordinates are invented.
- Services → My location: optional map point and revocable consent. Nearby visibility expires after 30 days unless the user saves again. Request owners can search a 1 km straight-line radius after prescription review. Only available, eligible, uncommitted, matching-group donors with consent appear.
- Nearby results use rounded approximate markers and aliases, not precise donor coordinates, phone numbers or live tracking. At most 100 results are shown; large candidate sets are labelled limited. Saved coordinates may be inaccurate or old.
- Leaflet 1.9.4 is bundled locally. Street tiles require internet. Manual coordinates and error messages remain available if a map cannot load. GPS uses browser permission on localhost/HTTPS. No Google API key is required.

Map source and usage: [Leaflet](https://leafletjs.com/download.html), [OpenStreetMap tile policy](https://operations.osmfoundation.org/policies/tiles/). Attribution is retained; there is no bulk tile download/offline prefetch. The saved-point bounds are a service-area rectangle, not an authoritative national border.

See `docs/RELEASE_110.md` for a role matrix and demo sequence.
