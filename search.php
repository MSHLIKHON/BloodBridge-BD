<?php 
declare(strict_types=1); 
 
require_once __DIR__ . '/includes/auth.php'; 
require_once __DIR__ . '/includes/locations.php'; 
require_login(); 
 
$bloodGroup = trim((string) ($_GET['blood_group'] ?? 'B+')); 
$location = trim((string) ($_GET['location'] ?? 'Dhaka')); 
$minUnits = filter_input(INPUT_GET, 'min_units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]) ?: 1; 
$searched = isset($_GET['blood_group']) || isset($_GET['location']); 
$donors = []; 
$stocks = []; 
 
if ($searched && in_array($bloodGroup, valid_blood_groups(), true)) { 
    $locationLike = '%' . $location . '%'; 
    $donorStatement = db()->prepare( 
        "SELECT id, full_name, blood_group, location, phone, last_donation_date, total_donations, 
                is_available, screening_status, verified_by_hospital 
         FROM users 
         WHERE role = 'donor' AND account_status = 'Active' AND blood_group = ? AND location LIKE ? 
           AND is_available = 1 AND screening_status = 'Eligible' 
           AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL 120 DAY) <= CURDATE()) 
         ORDER BY full_name" 
    ); 
    $donorStatement->execute([$bloodGroup, $locationLike]); 
    $donors = $donorStatement->fetchAll(); 
 
    $stockStatement = db()->prepare( 
        'SELECT bi.hospital_id, bi.blood_group, bi.units, bi.reserved_units, 
                GREATEST(bi.units - bi.reserved_units, 0) AS available_units, 
                h.name, h.location, h.phone 
         FROM blood_inventory bi 
         JOIN hospitals h ON h.id = bi.hospital_id 
         WHERE bi.blood_group = ? AND h.location LIKE ? AND h.status = \'Verified\' 
           AND (bi.units - bi.reserved_units) >= ? 
         ORDER BY available_units DESC' 
    ); 
    $stockStatement->execute([$bloodGroup, $locationLike, $minUnits]); 
    $stocks = $stockStatement->fetchAll(); 
} 
 
$pageTitle = 'Search Blood'; 
$enableLiveUpdates = true; 
require __DIR__ . '/includes/header.php'; 
?> 
<section class="page-heading"> 
    <div><span class="eyebrow">Search workflow</span><h1>Find blood near you</h1><p>Search both registered donors and hospital blood-bank stock.</p></div> 
</section> 
 
<section class="search-panel"> 
    <form method="get" class="search-form" style="display:flex; align-items:flex-end; gap:15px; flex-wrap:wrap;"> 
        <label><span>Blood group</span><select name="blood_group"><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $bloodGroup === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label> 
        <label><span>Location</span><input type="text" name="location" list="search-locations" value="<?= e($location) ?>" placeholder="Start typing a location" autocomplete="off" required><?php render_location_datalist('search-locations'); ?></label><label><span>Minimum bank units</span><input type="number" name="min_units" min="1" max="10" value="<?= (int) $minUnits ?>" required></label> 
        <button class="button button-white" type="submit">Search Blood</button> 
    </form> 
</section> 
 
<?php if ($searched): ?> 
<div class="result-grid"> 
    <section class="content-card"> 
        <div class="section-heading"><div><span class="eyebrow">Option 1</span><h2>Matching donors</h2></div><span class="count-pill"><?= count($donors) ?> found</span></div> 
        <?php if (!$donors): ?><div class="empty-state">No matching donor found in this location.</div><?php else: ?><div class="result-list"><?php foreach ($donors as $donor): $effectiveStatus = donor_effective_status($donor); $eligibleDate = next_eligible_date($donor['last_donation_date']); ?><article class="result-item"><span class="blood-icon"><?= e($donor['blood_group']) ?></span><div class="result-content"><div class="result-title"><strong><?= e($donor['full_name']) ?></strong><span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span></div><span><?= e($donor['location']) ?></span><small>Last donated: <?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?> • Total: <?= (int) $donor['total_donations'] ?></small><small>Next eligible: <?= $eligibleDate ? e(date('d M Y', strtotime($eligibleDate))) : 'Requires screening' ?> • <?= $donor['verified_by_hospital'] ? 'Hospital verified' : 'Verification pending' ?></small><a href="donor_details.php?id=<?= (int) $donor['id'] ?>">View Donor Profile</a></div></article><?php endforeach; ?></div><?php endif; ?> 
        <?php if (in_array(current_user()['role'], ['seeker', 'admin'], true)): ?><a class="button button-secondary button-full" href="request_create.php">Create Donor Request</a><?php endif; ?> 
    </section> 
    <section class="content-card"> 
        <div class="section-heading"><div><span class="eyebrow">Option 2</span><h2>Blood-bank stock</h2></div><span class="count-pill"><?= count($stocks) ?> found</span></div> 
        <?php if (!$stocks): ?><div class="empty-state">No unreserved hospital stock found in this location.</div><?php else: ?><div class="result-list"><?php foreach ($stocks as $stock): ?><article class="result-item"><span class="blood-icon bank"><?= e($stock['blood_group']) ?></span><div class="result-content"><strong><?= e($stock['name']) ?></strong><span><?= e($stock['location']) ?></span><small><?= (int) $stock['available_units'] ?> available • <?= (int) $stock['reserved_units'] ?> reserved • <?= e($stock['phone']) ?></small><?php if (in_array(current_user()['role'], ['seeker', 'admin'], true)): ?><form method="post" action="reservation_create.php" class="mini-reservation"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= (int) $stock['hospital_id'] ?>"><input type="hidden" name="blood_group" value="<?= e($stock['blood_group']) ?>"><input type="number" name="units" min="1" max="<?= min(10, (int) $stock['available_units']) ?>" value="1" aria-label="Units" required><input type="text" name="note" maxlength="500" placeholder="Optional patient note"><button class="button button-secondary" type="submit">Request Reservation</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?> 
        <?php if (in_array(current_user()['role'], ['seeker', 'admin'], true)): ?><a class="button button-secondary button-full" href="request_create.php">Create Blood-Bank Request</a><?php endif; ?> 
    </section> 
</div> 
<?php endif; ?> 
<?php require __DIR__ . '/includes/footer.php'; ?>