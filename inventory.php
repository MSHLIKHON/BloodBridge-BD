<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['hospital', 'admin']);

$user = current_user();
$pdo = db();
$allBloodGroups = valid_blood_groups();

// Retrieve list of all hospitals
$hospitals = $pdo->query('SELECT id, name, location, phone FROM hospitals ORDER BY name ASC')->fetchAll();

if (!$hospitals) {
    flash('error', 'No hospital records found in database. Run setup.php to initialize.');
    redirect('dashboard.php');
}

// Select active hospital:
// Admin can pick any hospital via query param or default to first.
// Hospital staff defaults to the hospital matching location or first hospital.
$selectedHospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);
if (!$selectedHospitalId) {
    if ($user['role'] === 'hospital') {
        // Try matching hospital by user location substring
        $matched = null;
        foreach ($hospitals as $h) {
            if (stripos($h['location'], $user['location']) !== false || stripos($user['location'], $h['location']) !== false) {
                $matched = (int) $h['id'];
                break;
            }
        }
        $selectedHospitalId = $matched ?? (int) $hospitals[0]['id'];
    } else {
        $selectedHospitalId = (int) $hospitals[0]['id'];
    }
}

// Verify hospital exists
$hospitalStmt = $pdo->prepare('SELECT id, name, location, phone FROM hospitals WHERE id = ?');
$hospitalStmt->execute([$selectedHospitalId]);
$currentHospital = $hospitalStmt->fetch();

if (!$currentHospital) {
    flash('error', 'Selected hospital not found.');
    redirect('inventory.php');
}

$errors = [];

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? 'update_single');
    $postHospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);

    if ($postHospitalId !== $selectedHospitalId) {
        $errors[] = 'Hospital mismatch. Please refresh and try again.';
    }

    if (!$errors && $action === 'update_single') {
        $bloodGroup = (string) ($_POST['blood_group'] ?? '');
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);

        if (!in_array($bloodGroup, $allBloodGroups, true)) {
            $errors[] = 'Invalid blood group specified.';
        }
        if ($units === false || $units === null) {
            $errors[] = 'Units must be a valid number between 0 and 9999.';
        }

        if (!$errors) {
            $upsert = $pdo->prepare(
                'INSERT INTO blood_inventory (hospital_id, blood_group, units)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE units = VALUES(units)'
            );
            $upsert->execute([$selectedHospitalId, $bloodGroup, $units]);
            flash('success', "Stock for {$bloodGroup} updated to {$units} unit(s).");
            redirect('inventory.php?hospital_id=' . $selectedHospitalId);
        }
    } elseif (!$errors && $action === 'adjust') {
        $bloodGroup = (string) ($_POST['blood_group'] ?? '');
        $delta = filter_input(INPUT_POST, 'delta', FILTER_VALIDATE_INT);

        if (!in_array($bloodGroup, $allBloodGroups, true)) {
            $errors[] = 'Invalid blood group specified.';
        }
        if ($delta === false || $delta === null || !in_array($delta, [-1, 1], true)) {
            $errors[] = 'Invalid adjustment amount.';
        }

        if (!$errors) {
            // Fetch current units
            $fetchCurrent = $pdo->prepare('SELECT units FROM blood_inventory WHERE hospital_id = ? AND blood_group = ?');
            $fetchCurrent->execute([$selectedHospitalId, $bloodGroup]);
            $currentVal = $fetchCurrent->fetchColumn();
            $newVal = max(0, ($currentVal !== false ? (int) $currentVal : 0) + $delta);

            $upsert = $pdo->prepare(
                'INSERT INTO blood_inventory (hospital_id, blood_group, units)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE units = VALUES(units)'
            );
            $upsert->execute([$selectedHospitalId, $bloodGroup, $newVal]);
            flash('success', "Stock for {$bloodGroup} adjusted to {$newVal} unit(s).");
            redirect('inventory.php?hospital_id=' . $selectedHospitalId);
        }
    }
}

// Fetch all inventory records for current hospital
$inventoryStmt = $pdo->prepare('SELECT blood_group, units, updated_at FROM blood_inventory WHERE hospital_id = ?');
$inventoryStmt->execute([$selectedHospitalId]);
$inventoryRows = $inventoryStmt->fetchAll();

$inventoryMap = [];
foreach ($inventoryRows as $row) {
    $inventoryMap[$row['blood_group']] = [
        'units' => (int) $row['units'],
        'updated_at' => $row['updated_at'],
    ];
}

// Calculate summary metrics
$totalUnits = 0;
$lowStockCount = 0;
$criticalStockThreshold = 3;

foreach ($allBloodGroups as $group) {
    $count = $inventoryMap[$group]['units'] ?? 0;
    $totalUnits += $count;
    if ($count < $criticalStockThreshold) {
        $lowStockCount++;
    }
}

$pageTitle = 'Blood Inventory';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Hospital stock management</span>
        <h1>Blood Inventory</h1>
        <p>Monitor and update available blood units by blood group.</p>
    </div>
    <?php if (count($hospitals) > 1 && $user['role'] === 'admin'): ?>
        <form method="get" class="hospital-switcher">
            <label>
                <span>Select Hospital:</span>
                <select name="hospital_id" onchange="this.form.submit()">
                    <?php foreach ($hospitals as $h): ?>
                        <option value="<?= (int) $h['id'] ?>" <?= $selectedHospitalId === (int) $h['id'] ? 'selected' : '' ?>>
                            <?= e($h['name']) ?> (<?= e($h['location']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<section class="hospital-info-banner">
    <div>
        <strong><?= e($currentHospital['name']) ?></strong>
        <span>Location: <?= e($currentHospital['location']) ?> &bull; Phone: <?= e($currentHospital['phone'] ?: 'N/A') ?></span>
    </div>
    <div class="banner-stats">
        <span class="stat-badge">Total Stock: <strong><?= $totalUnits ?> units</strong></span>
        <?php if ($lowStockCount > 0): ?>
            <span class="stat-badge badge-warning">Low Stock Alerts: <strong><?= $lowStockCount ?> groups</strong></span>
        <?php else: ?>
            <span class="stat-badge badge-success">All groups stocked</span>
        <?php endif; ?>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Stock matrix</span>
            <h2>Blood Stock by Group</h2>
        </div>
        <span class="muted">Threshold: Under <?= $criticalStockThreshold ?> units is marked Low Stock</span>
    </div>

    <div class="inventory-grid">
        <?php foreach ($allBloodGroups as $group):
            $units = $inventoryMap[$group]['units'] ?? 0;
            $updatedAt = $inventoryMap[$group]['updated_at'] ?? null;
            $isLow = $units < $criticalStockThreshold;
        ?>
            <div class="inventory-card <?= $isLow ? 'is-low' : '' ?>">
                <div class="inv-header">
                    <span class="blood-icon <?= $isLow ? 'bank-low' : 'bank' ?>"><?= e($group) ?></span>
                    <div class="inv-status">
                        <?php if ($units === 0): ?>
                            <span class="badge badge-red">Out of Stock</span>
                        <?php elseif ($isLow): ?>
                            <span class="badge badge-gray">Low Stock</span>
                        <?php else: ?>
                            <span class="badge badge-green">Adequate</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="inv-count">
                    <strong><?= $units ?></strong>
                    <span>units available</span>
                </div>

                <div class="inv-actions">
                    <form method="post" class="quick-adjust-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="adjust">
                        <input type="hidden" name="hospital_id" value="<?= $selectedHospitalId ?>">
                        <input type="hidden" name="blood_group" value="<?= e($group) ?>">
                        <button type="submit" name="delta" value="-1" class="btn-adjust" title="Decrease by 1" <?= $units <= 0 ? 'disabled' : '' ?>>&minus;</button>
                        <button type="submit" name="delta" value="1" class="btn-adjust" title="Increase by 1">&plus;</button>
                    </form>

                    <form method="post" class="direct-set-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_single">
                        <input type="hidden" name="hospital_id" value="<?= $selectedHospitalId ?>">
                        <input type="hidden" name="blood_group" value="<?= e($group) ?>">
                        <input type="number" name="units" min="0" max="9999" value="<?= $units ?>" aria-label="Units for <?= e($group) ?>" required>
                        <button type="submit" class="button button-secondary button-small">Set</button>
                    </form>
                </div>

                <small class="inv-updated">
                    <?= $updatedAt ? 'Updated ' . e(date('d M Y, h:i A', strtotime($updatedAt))) : 'No recent updates' ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
