<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['donor', 'hospital', 'admin']);

$user = current_user();
$pdo = db();
$allBloodGroups = valid_blood_groups();

// Fetch hospital list for recording options
$hospitals = $pdo->query('SELECT id, name, location FROM hospitals ORDER BY name ASC')->fetchAll();

// Fetch eligible/active donors for logging donations (hospital/admin)
$donors = $pdo->query("SELECT id, full_name, blood_group, location, phone, last_donation_date, total_donations FROM users WHERE role = 'donor' ORDER BY full_name ASC")->fetchAll();

$errors = [];

// Handle Record New Donation (Hospital or Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!in_array($user['role'], ['hospital', 'admin'], true)) {
        http_response_code(403);
        exit('Unauthorized action.');
    }

    $donorId = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);
    $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
    $bloodGroup = (string) ($_POST['blood_group'] ?? '');
    $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]) ?: 1;
    $donationDate = trim((string) ($_POST['donation_date'] ?? date('Y-m-d')));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $addToInventory = isset($_POST['add_to_inventory']) ? 1 : 0;

    // Validate donor
    $selectedDonor = null;
    if ($donorId) {
        $donorStmt = $pdo->prepare("SELECT id, full_name, blood_group, total_donations, last_donation_date FROM users WHERE id = ? AND role = 'donor'");
        $donorStmt->execute([$donorId]);
        $selectedDonor = $donorStmt->fetch();
        if (!$selectedDonor) {
            $errors[] = 'Selected donor record was not found.';
        } elseif (!$bloodGroup && !empty($selectedDonor['blood_group'])) {
            $bloodGroup = (string) $selectedDonor['blood_group'];
        }
    }

    if (!in_array($bloodGroup, $allBloodGroups, true)) {
        $errors[] = 'Select a valid blood group.';
    }

    $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $donationDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $donationDate || $donationDate > date('Y-m-d')) {
        $errors[] = 'Enter a valid donation date (cannot be in the future).';
    }

    if (strlen($notes) > 255) {
        $errors[] = 'Notes must be within 255 characters.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Insert donation history record
            $histStmt = $pdo->prepare(
                'INSERT INTO donation_history (donor_id, hospital_id, blood_group, units, donation_date, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $histStmt->execute([
                $donorId ?: null,
                $hospitalId ?: null,
                $bloodGroup,
                $units,
                $donationDate,
                $notes !== '' ? $notes : 'Standard donation',
            ]);

            // Update donor stats if linked to donor account
            if ($donorId && $selectedDonor) {
                $newTotal = ((int) $selectedDonor['total_donations']) + $units;
                $updateDonor = $pdo->prepare(
                    'UPDATE users SET total_donations = ?, last_donation_date = ?, profile_updated_at = NOW() WHERE id = ?'
                );
                $updateDonor->execute([$newTotal, $donationDate, $donorId]);
            }

            // Optionally add to hospital inventory stock
            if ($hospitalId && $addToInventory) {
                $invStmt = $pdo->prepare(
                    'INSERT INTO blood_inventory (hospital_id, blood_group, units)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE units = units + VALUES(units)'
                );
                $invStmt->execute([$hospitalId, $bloodGroup, $units]);
            }

            $pdo->commit();
            flash('success', "Donation of {$units} unit(s) [{$bloodGroup}] recorded successfully.");
            redirect('donation_history.php');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Failed to record donation: ' . $ex->getMessage();
        }
    }
}

// Build query based on role
$where = [];
$params = [];

if ($user['role'] === 'donor') {
    $where[] = 'dh.donor_id = ?';
    $params[] = (int) $user['id'];
} elseif ($user['role'] === 'hospital') {
    // If hospital user matches a hospital record
    $matchedHospId = null;
    foreach ($hospitals as $h) {
        if (stripos($h['location'], $user['location']) !== false || stripos($user['location'], $h['location']) !== false) {
            $matchedHospId = (int) $h['id'];
            break;
        }
    }
    if ($matchedHospId) {
        $where[] = '(dh.hospital_id = ? OR dh.hospital_id IS NULL)';
        $params[] = $matchedHospId;
    }
}

$filterGroup = trim((string) ($_GET['blood_group'] ?? ''));
if (in_array($filterGroup, $allBloodGroups, true)) {
    $where[] = 'dh.blood_group = ?';
    $params[] = $filterGroup;
}

$sql =
    'SELECT dh.*, u.full_name AS donor_name, u.phone AS donor_phone, h.name AS hospital_name, h.location AS hospital_location
     FROM donation_history dh
     LEFT JOIN users u ON u.id = dh.donor_id
     LEFT JOIN hospitals h ON h.id = dh.hospital_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY dh.donation_date DESC, dh.id DESC LIMIT 100';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$historyRecords = $statement->fetchAll();

// Metrics calculation
$totalDonationsCount = count($historyRecords);
$totalUnitsDonated = array_sum(array_column($historyRecords, 'units'));

$pageTitle = 'Donation History';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">Donation audit trail</span>
        <h1>Donation History</h1>
        <p>
            <?= $user['role'] === 'donor'
                ? 'Review your past blood donations and next eligibility dates.'
                : 'Track and record completed blood donations and units received.' ?>
        </p>
    </div>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<section class="stat-grid" aria-label="Donation statistics">
    <article class="stat-card">
        <span>Recorded donations</span>
        <strong><?= $totalDonationsCount ?></strong>
        <small>Total donation records</small>
    </article>
    <article class="stat-card">
        <span>Units collected</span>
        <strong><?= $totalUnitsDonated ?></strong>
        <small>Total blood units</small>
    </article>
    <?php if ($user['role'] === 'donor'):
        $lastDon = $user['last_donation_date'] ?? null;
        $nextElig = next_eligible_date($lastDon);
    ?>
        <article class="stat-card">
            <span>Last donation</span>
            <strong><?= $lastDon ? e(date('d M', strtotime($lastDon))) : '&mdash;' ?></strong>
            <small><?= $lastDon ? e(date('Y', strtotime($lastDon))) : 'None recorded' ?></small>
        </article>
        <article class="stat-card">
            <span>Next eligible date</span>
            <strong><?= $nextElig ? e(date('d M', strtotime($nextElig))) : 'Eligible' ?></strong>
            <small><?= $nextElig ? (strtotime($nextElig) <= time() ? 'Eligible now' : 'Wait until date') : 'Ready to donate' ?></small>
        </article>
    <?php else: ?>
        <article class="stat-card">
            <span>Registered donors</span>
            <strong><?= count($donors) ?></strong>
            <small>Available in network</small>
        </article>
        <article class="stat-card">
            <span>Connected hospitals</span>
            <strong><?= count($hospitals) ?></strong>
            <small>Active blood banks</small>
        </article>
    <?php endif; ?>
</section>

<?php if (in_array($user['role'], ['hospital', 'admin'], true)): ?>
<section class="content-card form-card" style="margin-bottom: 22px;">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Log completed donation</span>
            <h2>Record a New Donation</h2>
        </div>
    </div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label>
            <span>Select Donor (Optional if walk-in)</span>
            <select name="donor_id">
                <option value="">-- Walk-in / Unregistered Donor --</option>
                <?php foreach ($donors as $d): ?>
                    <option value="<?= (int) $d['id'] ?>">
                        <?= e($d['full_name']) ?> (<?= e($d['blood_group'] ?: 'No group') ?>) &bull; <?= e($d['location']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Blood Group</span>
            <select name="blood_group" required>
                <option value="">Select blood group</option>
                <?php foreach ($allBloodGroups as $g): ?>
                    <option value="<?= e($g) ?>"><?= e($g) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Hospital / Blood Bank</span>
            <select name="hospital_id">
                <option value="">-- No specific hospital --</option>
                <?php foreach ($hospitals as $h): ?>
                    <option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?> (<?= e($h['location']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Units Donated</span>
            <input type="number" name="units" min="1" max="10" value="1" required>
        </label>

        <label>
            <span>Donation Date</span>
            <input type="date" name="donation_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required>
        </label>

        <label class="checkbox-label">
            <input type="checkbox" name="add_to_inventory" value="1" checked>
            <span>Automatically add units to selected hospital inventory</span>
        </label>

        <label class="form-wide">
            <span>Notes / Remarks</span>
            <input type="text" name="notes" maxlength="255" placeholder="e.g. Regular voluntary donation, Blood drive camp, etc.">
        </label>

        <div class="form-wide form-actions">
            <button class="button button-primary" type="submit">Record Donation</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Historical logs</span>
            <h2>Past Donation Records</h2>
        </div>
        <form method="get" class="filter-form" style="margin: 0;">
            <select name="blood_group" onchange="this.form.submit()" style="min-height: 38px; width: auto;">
                <option value="">All blood groups</option>
                <?php foreach ($allBloodGroups as $bg): ?>
                    <option value="<?= e($bg) ?>" <?= $filterGroup === $bg ? 'selected' : '' ?>><?= e($bg) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!$historyRecords): ?>
        <div class="empty-state">
            <strong>No donation records found.</strong>
            <span>Donations will appear here once recorded.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Donor</th>
                        <th>Blood Group</th>
                        <th>Units</th>
                        <th>Facility / Blood Bank</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyRecords as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e(date('d M Y', strtotime($item['donation_date']))) ?></strong>
                            </td>
                            <td>
                                <?php if ($item['donor_name']): ?>
                                    <strong><?= e($item['donor_name']) ?></strong>
                                    <?php if ($item['donor_phone'] && in_array($user['role'], ['admin', 'hospital'], true)): ?>
                                        <small><?= e($item['donor_phone']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">Anonymous / Walk-in</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="blood-group"><?= e($item['blood_group']) ?></strong>
                            </td>
                            <td>
                                <span><?= (int) $item['units'] ?> unit(s)</span>
                            </td>
                            <td>
                                <?= e($item['hospital_name'] ?: 'External center') ?>
                                <?php if ($item['hospital_location']): ?>
                                    <small><?= e($item['hospital_location']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($item['notes'] ?: '&mdash;') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
