<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_login();
$pageTitle = 'Services';
require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">BloodBridge BD</span><h1>Services</h1><p>Additional workflows using your existing account.</p></div></section>
<div class="detail-grid">
<?php if(current_user()['role']!=='hospital'): ?><section class="content-card"><h2>University clubs</h2><p>Join an approved club, coordinate requests or register a campaign.</p><a class="button button-primary" href="clubs.php">Open Clubs</a></section><?php endif; ?>
<?php if(current_user()['role']!=='admin'): ?><section class="content-card"><h2>Hospital donations</h2><p>Request, screen and confirm a direct donation.</p><a class="button button-primary" href="direct_donations.php">Open Donations</a></section><?php endif; ?>
<?php if (in_array(current_user()['role'],['donor','seeker'],true)): ?>
<section class="content-card"><h2>My location</h2><p>Division, district, area and optional private nearby matching.</p><a class="button button-primary" href="my_location.php">Manage Location</a></section>
<section class="content-card"><h2>My health records</h2><p>Private reports, consent, blood group and reminder preferences.</p><a class="button button-primary" href="health_records.php">Open Health Records</a><p><a href="donor_profile.php">Donation profile</a> · <a href="donation_history.php">Donation history</a> · <a href="request_create.php">Request blood</a> · <a href="reservations.php">My reservations</a></p></section>
<?php endif; ?>
<?php if (in_array(current_user()['role'],['admin','hospital'],true)): ?>
<section class="content-card"><h2>Prescription review</h2><p>Review only the requests assigned to your account or hospital.</p><a class="button button-primary" href="request_documents.php">Review Requests</a></section>
<section class="content-card"><h2>Stock thresholds</h2><p>Configure the minimum available units for each blood group.</p><a class="button button-primary" href="stock_settings.php">Open Stock Settings</a></section>
<?php endif; ?>
<?php if (current_user()['role']==='admin'): ?>
<section class="content-card"><h2>Testing centre</h2><p>Run unit checks and read-only database consistency checks on your XAMPP installation.</p><a class="button button-primary" href="testing.php">Run Checks</a></section>
<section class="content-card"><h2>Hospital verification</h2><p>Record verification references, approval reasons and suspension decisions.</p><a class="button button-primary" href="hospital_verification.php">Review Hospitals</a></section>
<section class="content-card"><h2>Reports &amp; audit</h2><p>Filtered summaries, CSV exports and recorded actions.</p><a class="button button-primary" href="reports.php">Open Reports</a></section>
<section class="content-card"><h2>System settings</h2><p>Expiry intervals, reminders, session timeout and maintenance.</p><a class="button button-primary" href="settings.php">Open Settings</a></section>
<?php endif; ?>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
