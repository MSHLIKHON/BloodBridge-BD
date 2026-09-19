# Test report — BloodBridge BD 1.1.0

## Result and limits

The implementation was checked with **PHP 8.3.32 compiled to WebAssembly**, Node.js and an in-memory SQLite simulation. Results are in `automated-test-results.json`.

| Executed check | Actual result |
| --- | --- |
| PHP native parser through `token_get_all(..., TOKEN_PARSE)` | 60 / 60 PHP files passed |
| JavaScript `node --check` | Passed |
| Unit validation and policy tests | 49 / 49 passed |
| Sequential workflow simulation | 36 / 36 passed |
| Donor matching, outcome, privacy and addresses (SQLite) | 38 / 38 passed |
| JavaScript dropdown and map fallback DOM checks (jsdom) | 9 / 9 passed |
| Anonymous HTTP route and CSRF checks | 17 / 17 passed |
| Existing stylesheet comparison | Exact SHA-256 match to input ZIP |

The 149 executable assertions are **not** 100% system coverage. SQLite simulation removes `FOR UPDATE` and adapts upsert syntax to exercise business logic sequentially. It cannot verify MySQL SQL dialects, MySQL constraints, transaction isolation, race conditions, actual migration behavior or scheduler infrastructure.

The test runtime contains no real patient/donor records. No real hospital was contacted. No email, SMS, push service, live GitHub repository or user database was modified.

## Tested behavior

- Phone normalization and invalid phone rejection; strong/weak password rules; HTML escaping.
- Date validation including leap dates, future/invalid dates and configurable interval calculation.
- Donor account/availability/screening restrictions and recent-donation deferral.
- Offline hierarchy: 8 divisions, 64 districts, 494 original entries plus supplied urban-area entries; invalid cross-division selection rejected.
- Due-time boundary checks; missing uploads rejected; CSRF absent/wrong/valid cases.
- Anonymous access redirected from 14 protected pages without exposing content.
- Direct donation: creation, hospital consent, second commitment rejection, wrong-hospital/admin denial, required attestation, screening, revoke-consent denial, one-time completion, stock/history updates.
- Health permission boundaries and hospital suspension.
- Expiry: reserved units released without changing physical stock, repeated expiry does nothing, inconsistent stock causes rollback.
- Prescription owner/reviewer boundaries and donor access denial.
- Club approval, consented membership, coordinator scopes, campaign registration uniqueness, outsiders denied, future event completion blocked, leaving clears registration.

## Prepared but NOT executed in this environment

- `tests/mysql_integration.php`: native fresh installer, actual direct-donation transactions, reminder/low-stock idempotence.
- `tests/mysql_integration.php legacy`: legacy ENUM upgrade and data/password preservation.
- `.github/workflows/php-tests.yml`: GitHub Actions + MariaDB runner; no remote push or CI run was performed here.
- Actual browser uploads/downloads and multi-user browser workflows.
- Concurrent transactions, Mac/Windows XAMPP, physical second PC, OS scheduling while no one visits, responsive/visual review.

These are explicitly **NOT TESTED**, not assumed PASS. See `MANUAL_ACCEPTANCE.md` for expected/actual-result fields.

## Local testing without terminal

After setup, sign in as Admin → Services → Testing Centre → Run Unit & Database Checks. This evaluates current local data (including reserved-stock reconciliation) and displays actual PASS/FAIL results. It does not repair stock silently and is not a replacement for the acceptance checklist. Normal maintenance may run on authenticated visits, and the test execution itself is audited.

## Reproduce executable suites

Use XAMPP's PHP executable from the project directory:

```sh
php tests/unit.php
php tests/workflow.php
php tests/matching.php
php tests/mysql_integration.php
php tests/mysql_integration.php legacy
```

Native integration tests use a newly generated `bb_qa_...` database only and clean up only that database. The configured MySQL account needs create/drop privileges. Never edit a test to use your normal database name. A failed assertion gives nonzero exit status.

## Clinical and security boundaries

This is a software-lab demonstration, not a validated transfusion system. A reminder is not eligibility, a reviewed report is not clinical clearance, and campaign attendance is not a completed donation. On-site assessment and required blood testing/release remain human clinical duties. Demo OTP is visible on screen and is not actual email/phone verification. Production hardening and medical/privacy review remain future work, not a claim of this package.

## 1.1.0 evidence details

New sequential tests cover two offers without automatic acceptance, single selection, non-owner denial, selected-donor confirmation, no duplicate history, owner receipt, dispute retention, correction by the selected donor only, withdrawal, expiry after a donation report, 1 km distance boundaries, consent revocation, stale locations, hidden exact coordinates, wrong-owner query denial, and cross-division address rejection.

The 9 JavaScript tests execute in jsdom, not a visual browser: cascading options, clearing dependent selections, independent forms, no automatic GPS request, manual entry without Leaflet, clearing a pin and denied-GPS fallback. Reproduce with Node 24: `npm install --prefix tests`, then `node tests/frontend.mjs`. These dependencies are only for development and are not needed by XAMPP.

The actual MySQL setup, new ALTER/ENUM migrations and lock behavior still require the prepared native tests. Do not equate syntax/simulation PASS with an executed installer or browser session. Street tile delivery and real GPS accuracy have not been tested.

The existing `assets/css/style.css` SHA-256 is unchanged:
`f06e3264e09e26f96072738f017c9ac48a8da337311af4062fd076ee709237db`.
