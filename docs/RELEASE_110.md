# Release 1.1.0

| Account | Operations | Monitoring / administration |
| --- | --- | --- |
| Donor / Seeker | Own requests, offers, selection, receipt, reservations, health, location, clubs | Own progress, notifications and history |
| Hospital staff | Assigned requests, stock, reservations, direct donations, consented screening | Own hospital records and prescription review |
| Admin | Account/organization approvals, document review and system policy | Stock/reservation read-only, reports, audit, tests |

Admin cannot create requests, act as a donor or process stock/collections. Club coordination is a permission on an approved personal account, not an extra medical role.

Source groups changed: request pages and includes/matching.php; includes/schema_v110.php and setup.php; includes/address.php, locations.php, assets/js/address.js; includes/geo.php, my_location.php, nearby.php, assets/js/maps.js; role guards/header/services; tests and documentation. Existing main stylesheet is unchanged. Leaflet's stylesheet applies to maps only.

Upgrade by running setup.php with existing admin credentials after backing up both files and database. Keep custom config/database.local.php. Do not import database.sql on top of an existing database to reset it. Old closed records remain. Known addresses gain canonical division suffixes; custom addresses are preserved.

New donor responses are Interested, Selected, Accepted, Rejected, Not selected and Withdrawn. Main request status remains Pending/Accepted/Completed/Rejected/Cancelled/Expired. The outcome records Awaiting/Received/Not received/Disputed. A reported donation is retained past request expiry pending receipt/dispute resolution. History uses the donation report date, not a delayed receipt confirmation date.

Map matching uses saved coordinates, clinical availability flags and straight-line Haversine distance, not road routing or medical compatibility logic. The donor must opt in and refresh location within 30 days. No coordinates are seeded. Nearby search needs a reviewed request owned by the caller. Markers round coordinates to two decimal places; consequently a displayed approximate point may sit outside the drawn circle even though the saved point is inside it.

The app and test source are included; mobile apps, production hosting, SMS/email/push gateways and clinical certification are outside this local release. See TEST_REPORT.md for actual executed checks and limits.
