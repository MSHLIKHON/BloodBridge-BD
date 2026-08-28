<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_login();

$bloodGroup = trim((string) ($_GET['blood_group'] ?? 'B+'));
$division = trim((string) ($_GET['division'] ?? ''));
$district = trim((string) ($_GET['district'] ?? ''));
$upazila = trim((string) ($_GET['upazila'] ?? ''));
$legacyLocation = trim((string) ($_GET['location'] ?? ''));

// If no explicit division is selected, default to Dhaka
if (!isset($_GET['division']) && !isset($_GET['location'])) {
    $division = 'Dhaka';
}

$searched = isset($_GET['blood_group']) || isset($_GET['division']) || isset($_GET['location']);
$donors = [];
$stocks = [];

// Determine effective search term
$searchLocationTerm = $upazila ?: ($district ?: ($division ?: $legacyLocation));

if ($searched && in_array($bloodGroup, valid_blood_groups(), true)) {
    $locationLike = '%' . ($searchLocationTerm !== '' ? $searchLocationTerm : '') . '%';
    
    $donorStatement = db()->prepare(
        "SELECT id, full_name, blood_group, location, phone, last_donation_date, total_donations,
                is_available, screening_status, verified_by_hospital
         FROM users
         WHERE role = 'donor' AND blood_group = ? AND location LIKE ?
         ORDER BY full_name"
    );
    $donorStatement->execute([$bloodGroup, $locationLike]);
    $donors = $donorStatement->fetchAll();

    $stockStatement = db()->prepare(
        'SELECT bi.blood_group, bi.units, h.name, h.location, h.phone
         FROM blood_inventory bi
         JOIN hospitals h ON h.id = bi.hospital_id
         WHERE bi.blood_group = ? AND h.location LIKE ? AND bi.units > 0
         ORDER BY bi.units DESC'
    );
    $stockStatement->execute([$bloodGroup, $locationLike]);
    $stocks = $stockStatement->fetchAll();
}

$pageTitle = 'Search Blood';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Search workflow</span><h1>Find blood near you</h1><p>Search both registered donors and hospital blood-bank stock by Bangladesh Division, District and Upazila.</p></div>
</section>

<section class="search-panel">
    <form method="get" class="search-form">
        <label>
            <span>Blood group</span>
            <select name="blood_group">
                <?php foreach (valid_blood_groups() as $group): ?>
                    <option value="<?= e($group) ?>" <?= $bloodGroup === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- Dependent Location Search Filter -->
        <div class="search-location-picker">
            <?php render_location_dropdowns('', null, false, [
                'division' => $division,
                'district' => $district,
                'upazila' => $upazila,
            ]); ?>
        </div>

        <button class="button button-white" type="submit">Search Blood</button>
    </form>
</section>

<?php if ($searched): ?>
<div class="result-grid">
    <section class="content-card">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Option 1</span>
                <h2>Matching donors <?= $searchLocationTerm ? 'in ' . e($searchLocationTerm) : '' ?></h2>
            </div>
            <span class="count-pill"><?= count($donors) ?> found</span>
        </div>
        <?php if (!$donors): ?>
            <div class="empty-state">No matching donor found in <?= e($searchLocationTerm ?: 'selected area') ?>.</div>
        <?php else: ?>
            <div class="result-list">
                <?php foreach ($donors as $donor):
                    $effectiveStatus = donor_effective_status($donor);
                    $eligibleDate = next_eligible_date($donor['last_donation_date']);
                ?>
                    <article class="result-item">
                        <span class="blood-icon"><?= e($donor['blood_group']) ?></span>
                        <div class="result-content">
                            <div class="result-title">
                                <strong><?= e($donor['full_name']) ?></strong>
                                <span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span>
                            </div>
                            <span><?= e($donor['location']) ?></span>
                            <small>Last donated: <?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?> &bull; Total: <?= (int) $donor['total_donations'] ?></small>
                            <small>Next eligible: <?= $eligibleDate ? e(date('d M Y', strtotime($eligibleDate))) : 'Requires screening' ?> &bull; <?= $donor['verified_by_hospital'] ? 'Hospital verified' : 'Verification pending' ?></small>
                            <a href="donor_details.php?id=<?= (int) $donor['id'] ?>">View Donor Profile</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (in_array(current_user()['role'], ['seeker', 'admin'], true)): ?>
            <a class="button button-secondary button-full" href="request_create.php">Create Donor Request</a>
        <?php endif; ?>
    </section>

    <section class="content-card">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Option 2</span>
                <h2>Blood-bank stock <?= $searchLocationTerm ? 'in ' . e($searchLocationTerm) : '' ?></h2>
            </div>
            <span class="count-pill"><?= count($stocks) ?> found</span>
        </div>
        <?php if (!$stocks): ?>
            <div class="empty-state">No hospital stock found in <?= e($searchLocationTerm ?: 'selected area') ?>.</div>
        <?php else: ?>
            <div class="result-list">
                <?php foreach ($stocks as $stock): ?>
                    <article class="result-item">
                        <span class="blood-icon bank"><?= e($stock['blood_group']) ?></span>
                        <div>
                            <strong><?= e($stock['name']) ?></strong>
                            <span><?= e($stock['location']) ?></span>
                            <small><?= (int) $stock['units'] ?> unit(s) &bull; <?= e($stock['phone']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (in_array(current_user()['role'], ['seeker', 'admin'], true)): ?>
            <a class="button button-secondary button-full" href="request_create.php">Create Blood-Bank Request</a>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
