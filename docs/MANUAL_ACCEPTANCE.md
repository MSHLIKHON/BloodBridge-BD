# Native XAMPP / browser acceptance checklist

<!-- File purpose: MANUAL ACCEPTANCE documents the BloodBridge BD release, workflow, or verification process. -->

Use a disposable copy and synthetic records. Actual-result cells are deliberately blank; do not turn them into PASS without executing the steps. Record PHP, MariaDB/MySQL, OS and browser versions, date, tester and screenshots separately.

| ID | Test / steps | Expected result | Actual result | Status |
| --- | --- | --- | --- | --- |
| M01 | New database: open setup with GET, then submit installer POST | GET does not install; POST creates tables and demo users | Not run here | NOT TESTED |
| M02 | Upgrade a backed-up old database with legacy status ENUMs | No 1265 status error; new columns/tables added; data retained | Not run here | NOT TESTED |
| M03 | Compare existing passwords, inventory and user count before/after upgrade | No reset or demo reseeding into existing data | Not run here | NOT TESTED |
| M04 | Reopen/repost setup after success | Locked; no stock/password reset | Not run here | NOT TESTED |
| M05 | Login with each fresh demo account; try hospital credentials in regular portal | Correct dashboard; hospital requires separate portal | Not run here | NOT TESTED |
| M06 | Wrong password repeatedly; wait/unlock and retry | Temporary lock, correct recovery, no account enumeration beyond demo behavior | Not run here | NOT TESTED |
| M07 | Register; invalid location, weak/mismatched passwords, duplicate phone/email | Invalid data rejected; valid registration follows demo OTP | Not run here | NOT TESTED |
| M08 | Replay OTP; resend too quickly; try verifying a blocked/other account | Used/expired/unauthorized codes and blocked accounts rejected | Not run here | NOT TESTED |
| M09 | Create request with 2 MB+ file, PHP renamed JPG, malformed file, missing consent | Invalid file/consent rejected with no partial request | Not run here | NOT TESTED |
| M10 | Create a valid sample request and review its latest prescription | Owner + assigned reviewer only; unreviewed acceptance blocked | Not run here | NOT TESTED |
| M11 | Upload a new prescription while reviewer has older page open | Stale document ID cannot approve the new file | Not run here | NOT TESTED |
| M12 | Guess health/prescription document IDs as another user or club coordinator | 403/404 or login; no file bytes disclosed | Not run here | NOT TESTED |
| M13 | Grant/revoke hospital consent; download as two hospitals | Only selected verified hospital can access; revocation blocks new downloads | Not run here | NOT TESTED |
| M14 | Health report upload/review/delete and date/unit validation | Valid metadata retained; self-reported vs reviewed clear; no automatic eligibility | Not run here | NOT TESTED |
| M15 | Enable donation on a seeker account and create a blood request as a donor account | Same account can use both workflows; medical rules still apply | Not run here | NOT TESTED |
| M16 | Request one direct donation, screen, confirm; replay POST | One history and one-unit stock increase; replay rejected | Not run here | NOT TESTED |
| M17 | Open two accept/create actions for the same donor in separate sessions | At most one active commitment after concurrent commit/rollback | Not run here | NOT TESTED |
| M18 | Two hospital staff approve reservations competing for last unit | Total reserved never exceeds total stock | Not run here | NOT TESTED |
| M19 | Complete/cancel/expire accepted bank request or approved reservation | Exact reserved release; total falls only for issued/collected units | Not run here | NOT TESTED |
| M20 | Collect using wrong/right collection code; replay collection | Wrong rejected; correct collection once only | Not run here | NOT TESTED |
| M21 | Change group threshold; cross below; run job twice; refill and cross again | One alert per low-stock episode; 0 threshold disables | Not run here | NOT TESTED |
| M22 | Apply club, admin approve/reject/suspend, join consent, coordinator approval | Unapproved/suspended clubs cannot operate; no forced membership | Not run here | NOT TESTED |
| M23 | Share reviewed request with matching members twice | First notified; duplicate share rejected; no medical files shared | Not run here | NOT TESTED |
| M24 | Campaign register twice, withdraw, cancel; try outsider registration | No duplicates; member rules apply; no inventory changes from event completion | Not run here | NOT TESTED |
| M25 | Suspend hospital then attempt clinical/inventory/prescription actions | New access denied; admin can manage remaining cancellations | Not run here | NOT TESTED |
| M26 | Configure reminder; run twice on truly due test data; opt out | One in-app alert per donation date; opt-out respected; not auto-eligible | Not run here | NOT TESTED |
| M27 | Configure OS task and leave site unopened while XAMPP stays on | Job still runs; alerts recorded; computer-off limitation confirmed | Not run here | NOT TESTED |
| M28 | Sit idle past timeout while background polling continues | Polling does not extend user session; next protected action requires login | Not run here | NOT TESTED |
| M29 | Complete Self vs Family/Other request | Only Self updates requester's last-received date; donated date separate | Not run here | NOT TESTED |
| M30 | Date-filter reports, export CSV and formula-like user content | Correct permitted rows; no raw medical documents; formula cells neutralized | Not run here | NOT TESTED |
| M31 | 375px phone viewport, desktop, keyboard and 200% zoom | Forms/menu usable; existing stylesheet retained; no password alignment regression | Not run here | NOT TESTED |
| M32 | Extract on another Mac/Windows PC with fresh XAMPP, no internet | Setup/login/core flows run; no machine-specific paths in application | Not run here | NOT TESTED |
| M33 | Run native CLI tests and review GitHub Actions after pushing source | Fresh/migration/workflow jobs pass on actual MySQL/MariaDB | Not run here | NOT TESTED |

Release acceptance: complete the relevant rows, fix critical failures, attach evidence and obtain supervisor/user approval. A count of automated assertions is not a project-completion percentage.


## 1.1.0 required acceptance scenarios (not yet executed on XAMPP)

| Scenario | Expected result | Actual result |
| --- | --- | --- |
| Existing 1.0 upgrade with backup | Existing users/passwords/history preserved; schema 110 | NOT RUN |
| Admin opens request_create/edit URLs | 403; no New Request button | NOT RUN |
| Admin posts a stock/reservation action | 403; no inventory change | NOT RUN |
| Hospital opens another hospital's request | Denied | NOT RUN |
| All address forms and mobile registration | Cascading fields, required validation, password alignment unchanged | NOT RUN |
| Two eligible donors offer on one reviewed request | Pending; two Interested responses | NOT RUN |
| Seeker selects one | Selected; competitor Not selected | NOT RUN |
| Competing tabs select different donors | Only one succeeds | NOT RUN |
| Selected donor confirms and reports; owner receives twice | One completion, one history; duplicate denied | NOT RUN |
| Owner disputes receipt | Disputed; commitment retained | NOT RUN |
| Selected donor corrects a mistaken report after dispute | Cancelled / Not received; no history | NOT RUN |
| Donor Location consent and saved point; request point within 1 km | Available donor shown approximately after review | NOT RUN |
| Donor point outside 1 km / stale / revoked consent | Donor excluded | NOT RUN |
| Browser refuses GPS / street tiles unavailable | Clear message; manual coordinates work | NOT RUN |
| Maps on localhost in Chrome/Firefox | Attribution visible; zoom and pin selection work | NOT RUN |
| All earlier club, document, hospital, stock and scheduler scenarios | Follow original checklist below/above | NOT RUN |

Run the native suites and record actual results before calling this release fully accepted.
