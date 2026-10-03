<?php
/** File purpose: Search handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/address.php';
require_role(['donor','seeker']);

$bloodGroup = trim((string) ($_GET['blood_group'] ?? 'B+'));
$location='';$addressError='';
try{$location=address_input($_GET,true);}catch(DomainException $e){$addressError=$e->getMessage();}
$searched=isset($_GET['blood_group']) || isset($_GET['division']);
$donors = [];
$stocks = [];
$mapDonors = [];

if ($addressError==='' && $searched && in_array($bloodGroup, valid_blood_groups(), true)) {
    $locationLike = '%' . $location;
    $donorStatement = db()->prepare(
        "SELECT id, full_name, blood_group, location, last_donation_date, total_donations,
                is_available, screening_status, verified_by_hospital, donor_enabled,
                latitude, longitude, location_consent, location_updated_at
         FROM users
         WHERE donor_enabled = 1 AND account_status = 'Active' AND role IN ('donor','seeker') AND blood_group = ? AND location LIKE ?
           AND is_available = 1 AND screening_status = 'Eligible'
           AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL " . (int) app_setting('donation_interval_days',120) . " DAY) <= CURDATE())
           AND id <> ".(int)current_user()['id']."
           AND NOT EXISTS (SELECT 1 FROM blood_requests b WHERE b.accepted_by=users.id AND b.source_type='Donor' AND b.status='Accepted')
           AND NOT EXISTS (SELECT 1 FROM direct_donations d WHERE d.donor_id=users.id AND d.status IN ('Pending','Screened'))
         ORDER BY full_name"
    );
    $donorStatement->execute([$bloodGroup, $locationLike]);
    $donors = $donorStatement->fetchAll();
    foreach ($donors as $donor) {
        $locationIsCurrent = !empty($donor['location_updated_at']) && strtotime((string) $donor['location_updated_at']) >= time() - 30 * 86400;
        if (!$donor['location_consent'] || !$locationIsCurrent || $donor['latitude'] === null || $donor['longitude'] === null) continue;
        $mapDonors[] = [
            'label' => 'Available donor #' . (int) $donor['id'],
            'blood_group' => $donor['blood_group'],
            'latitude' => round((float) $donor['latitude'], 2),
            'longitude' => round((float) $donor['longitude'], 2),
            'area' => $donor['location'],
        ];
    }

    $stockStatement = db()->prepare(
        'SELECT bi.hospital_id, bi.blood_group, bi.units, bi.reserved_units,
                GREATEST(bi.units - bi.reserved_units, 0) AS available_units,
                h.name, h.location, h.phone
         FROM blood_inventory bi
         JOIN hospitals h ON h.id = bi.hospital_id
         WHERE bi.blood_group = ? AND h.location LIKE ? AND h.status = \'Verified\'
           AND (bi.units - bi.reserved_units) > 0
         ORDER BY available_units DESC'
    );
    $stockStatement->execute([$bloodGroup, $locationLike]);
    $stocks = $stockStatement->fetchAll();
}

$pageTitle = 'Search Blood';
$enableLiveUpdates = true;
$enableMap = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Search workflow</span><h1>Find blood near you</h1><p>Search both registered donors and hospital blood-bank stock.</p></div>
</section>

<?php if($addressError): ?><div class="alert alert-error"><?= e($addressError) ?></div><?php endif; ?>
<section class="search-panel">
    <form method="get" class="search-form">
        <label><span>Blood group</span><select name="blood_group"><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $bloodGroup === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
        <?php render_address_picker('',true); ?>
        <button class="button button-white" type="submit">Search Blood</button>
    </form>
</section>

<?php if ($searched): ?>
<?php if ($mapDonors): ?><div class="map-result-actions"><button class="button button-primary" type="button" data-search-map-open aria-haspopup="dialog">View <?= count($mapDonors) ?> mapped donor<?= count($mapDonors) === 1 ? '' : 's' ?> in this area</button><span>Markers use approximate, consented locations.</span></div><?php endif; ?>
<div class="result-grid">
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Option 1</span><h2>Matching donors</h2></div><span class="count-pill"><?= count($donors) ?> found</span></div>
        <?php if (!$donors): ?><div class="empty-state">No matching donor found in this location.</div><?php else: ?><div class="result-list"><?php foreach ($donors as $donor): $effectiveStatus = donor_effective_status($donor); $eligibleDate = next_eligible_date($donor['last_donation_date']); ?><article class="result-item"><span class="blood-icon"><?= e($donor['blood_group']) ?></span><div class="result-content"><div class="result-title"><strong><?= e($donor['full_name']) ?></strong><span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span></div><span><?= e($donor['location']) ?></span><small>Last donated: <?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?> • Total: <?= (int) $donor['total_donations'] ?></small><small>Next interval date: <?= $eligibleDate ? e(date('d M Y', strtotime($eligibleDate))) : 'Requires screening' ?> • <?= $donor['verified_by_hospital'] ? 'Hospital verified' : 'Verification pending' ?></small><a href="donor_details.php?id=<?= (int) $donor['id'] ?>">View Donor Profile</a></div></article><?php endforeach; ?></div><?php endif; ?>
        <?php if (in_array(current_user()['role'], ['seeker', 'donor'], true)): ?><a class="button button-secondary button-full" href="request_create.php">Create Donor Request</a><?php endif; ?>
    </section>
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Option 2</span><h2>Blood-bank stock</h2></div><span class="count-pill"><?= count($stocks) ?> found</span></div>
        <?php if (!$stocks): ?><div class="empty-state">No unreserved hospital stock found in this location.</div><?php else: ?><div class="result-list"><?php foreach ($stocks as $stock): ?><article class="result-item"><span class="blood-icon bank"><?= e($stock['blood_group']) ?></span><div class="result-content"><strong><?= e($stock['name']) ?></strong><span><?= e($stock['location']) ?></span><small><?= (int) $stock['available_units'] ?> available • <?= (int) $stock['reserved_units'] ?> reserved • <?= e($stock['phone']) ?></small><?php if (in_array(current_user()['role'], ['seeker', 'donor'], true)): ?><form method="post" action="reservation_create.php" class="mini-reservation"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= (int) $stock['hospital_id'] ?>"><input type="hidden" name="blood_group" value="<?= e($stock['blood_group']) ?>"><input type="number" name="units" min="1" max="<?= min(10, (int) $stock['available_units']) ?>" value="1" aria-label="Units" required><input type="text" name="note" maxlength="500" placeholder="Optional patient note"><button class="button button-secondary" type="submit">Request Reservation</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
        <?php if (in_array(current_user()['role'], ['seeker', 'donor'], true)): ?><a class="button button-secondary button-full" href="request_create.php">Create Blood-Bank Request</a><?php endif; ?>
    </section>
</div>
<?php if ($mapDonors): ?>
<div class="map-modal" data-search-map-modal hidden>
    <div class="map-modal-backdrop" data-search-map-close></div>
    <section class="map-modal-panel" role="dialog" aria-modal="true" aria-labelledby="search-map-title" tabindex="-1">
        <div class="section-heading"><div><span class="eyebrow">Bangladesh donor map</span><h2 id="search-map-title"><?= e($bloodGroup) ?> donors in <?= e($location) ?></h2></div><button class="map-modal-close" type="button" data-search-map-close aria-label="Close donor map">&times;</button></div>
        <p class="muted">Only donors who consented to location sharing are shown. Markers are rounded approximate areas, not exact addresses or live tracking.</p>
        <div class="search-map-canvas" data-search-map-canvas aria-label="Approximate available donor locations in Bangladesh"></div>
        <p data-map-message role="status"><?= count($mapDonors) ?> available donor<?= count($mapDonors) === 1 ? '' : 's' ?> shown.</p>
    </section>
</div>
<script type="application/json" data-search-map-data><?= json_encode($mapDonors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
