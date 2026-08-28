<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['hospital', 'admin']);

$user = current_user();
$pdo = db();
$allBloodGroups = valid_blood_groups();

// 1. Blood Stock Distribution by Group across hospitals
$stockByGroupStmt = $pdo->query(
    'SELECT blood_group, SUM(units) AS total_units, COUNT(DISTINCT hospital_id) AS hospital_count
     FROM blood_inventory
     GROUP BY blood_group
     ORDER BY total_units ASC'
);
$stockByGroup = $stockByGroupStmt->fetchAll();
$stockMap = [];
foreach ($stockByGroup as $row) {
    $stockMap[$row['blood_group']] = [
        'units' => (int) $row['total_units'],
        'hospitals' => (int) $row['hospital_count'],
    ];
}

// 2. Request Status Breakdown
$requestStats = $pdo->query(
    'SELECT status, COUNT(*) AS count, SUM(units) AS total_units
     FROM blood_requests
     GROUP BY status'
)->fetchAll();
$totalRequests = array_sum(array_column($requestStats, 'count'));
$reqStatusMap = [];
foreach ($requestStats as $r) {
    $reqStatusMap[$r['status']] = [
        'count' => (int) $r['count'],
        'units' => (int) ($r['total_units'] ?? 0),
    ];
}

// 3. Requests by Urgency
$urgencyStats = $pdo->query(
    'SELECT urgency, COUNT(*) AS count
     FROM blood_requests
     GROUP BY urgency'
)->fetchAll();

// 4. Donor Screening and Verification Summary
$totalRegisteredDonors = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor'")->fetchColumn();
$verifiedDonors = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor' AND verified_by_hospital = 1")->fetchColumn();
$screeningDist = $pdo->query(
    "SELECT screening_status, COUNT(*) AS count
     FROM users WHERE role = 'donor'
     GROUP BY screening_status"
)->fetchAll();

// 5. Total Donations Summary
$totalDonationLogs = (int) $pdo->query('SELECT COUNT(*) FROM donation_history')->fetchColumn();
$totalDonatedUnits = (int) $pdo->query('SELECT COALESCE(SUM(units), 0) FROM donation_history')->fetchColumn();

// 6. Hospital Stock Overview Table
$hospitals = $pdo->query('SELECT id, name, location, phone FROM hospitals ORDER BY name ASC')->fetchAll();
$hospitalInventories = [];
foreach ($hospitals as $h) {
    $hId = (int) $h['id'];
    $hInvStmt = $pdo->prepare('SELECT blood_group, units FROM blood_inventory WHERE hospital_id = ?');
    $hInvStmt->execute([$hId]);
    $items = $hInvStmt->fetchAll();
    $hMap = [];
    $hTotal = 0;
    foreach ($items as $item) {
        $hMap[$item['blood_group']] = (int) $item['units'];
        $hTotal += (int) $item['units'];
    }
    $hospitalInventories[] = [
        'hospital' => $h,
        'stocks' => $hMap,
        'total' => $hTotal,
    ];
}

$pageTitle = 'Reports & Analytics';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">System intelligence</span>
        <h1>Reports & Summary Analytics</h1>
        <p>Comprehensive overview of blood stock distribution, request fulfillment, and donor screening metrics.</p>
    </div>
    <?php if ($user['role'] === 'admin'): ?>
        <a class="button button-secondary" href="inventory.php">Manage Stock</a>
    <?php endif; ?>
</section>

<section class="stat-grid" aria-label="System KPI Overview">
    <article class="stat-card">
        <span>Total Requests</span>
        <strong><?= $totalRequests ?></strong>
        <small><?= $reqStatusMap['Completed']['count'] ?? 0 ?> fulfilled</small>
    </article>
    <article class="stat-card">
        <span>Total Blood Stock</span>
        <strong><?= (int) array_sum(array_column($stockByGroup, 'total_units')) ?></strong>
        <small>Units across all hospitals</small>
    </article>
    <article class="stat-card">
        <span>Registered Donors</span>
        <strong><?= $totalRegisteredDonors ?></strong>
        <small><?= $verifiedDonors ?> verified by hospital</small>
    </article>
    <article class="stat-card">
        <span>Units Donated</span>
        <strong><?= $totalDonatedUnits ?></strong>
        <small>Across <?= $totalDonationLogs ?> donation logs</small>
    </article>
</section>

<div class="detail-grid" style="margin-bottom: 22px;">
    <!-- Blood Stock Summary -->
    <section class="content-card">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Inventory breakdown</span>
                <h2>Stock Distribution by Blood Group</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Blood Group</th>
                        <th>Total Units</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allBloodGroups as $group):
                        $units = $stockMap[$group]['units'] ?? 0;
                        $isLow = $units < 3;
                    ?>
                        <tr>
                            <td><strong class="blood-group"><?= e($group) ?></strong></td>
                            <td><strong><?= $units ?></strong> unit(s)</td>
                            <td>
                                <?php if ($units === 0): ?>
                                    <span class="badge badge-red">Out of Stock</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge badge-gray">Low (Under 3)</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Adequate</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Request Status Breakdown -->
    <section class="content-card">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Fulfillment metrics</span>
                <h2>Request Status Breakdown</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Units</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (['Pending', 'Accepted', 'Completed', 'Cancelled', 'Rejected'] as $st):
                        $count = $reqStatusMap[$st]['count'] ?? 0;
                        $units = $reqStatusMap[$st]['units'] ?? 0;
                        $pct = $totalRequests > 0 ? round(($count / $totalRequests) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><span class="badge <?= request_status_class($st) ?>"><?= e($st) ?></span></td>
                            <td><strong><?= $count ?></strong></td>
                            <td><?= $units ?> units</td>
                            <td><?= $pct ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border);">
            <strong style="display: block; font-size: 13px; color: var(--muted); margin-bottom: 8px;">Urgency Distribution:</strong>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php foreach ($urgencyStats as $u): ?>
                    <span class="stat-badge">
                        <?= e($u['urgency']) ?>: <strong><?= (int) $u['count'] ?></strong>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<!-- Hospital Stock Matrix -->
<section class="content-card" style="margin-bottom: 22px;">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Hospital facility matrix</span>
            <h2>Blood Stock by Hospital & Group</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Hospital Name</th>
                    <th>Location</th>
                    <?php foreach ($allBloodGroups as $bg): ?>
                        <th style="text-align: center;"><?= e($bg) ?></th>
                    <?php endforeach; ?>
                    <th style="text-align: right;">Total Units</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hospitalInventories as $hi): ?>
                    <tr>
                        <td><strong><?= e($hi['hospital']['name']) ?></strong></td>
                        <td><?= e($hi['hospital']['location']) ?></td>
                        <?php foreach ($allBloodGroups as $bg):
                            $cnt = $hi['stocks'][$bg] ?? 0;
                        ?>
                            <td style="text-align: center;">
                                <span class="<?= $cnt < 2 ? 'badge badge-red' : ($cnt < 4 ? 'badge badge-gray' : '') ?>">
                                    <?= $cnt ?>
                                </span>
                            </td>
                        <?php endforeach; ?>
                        <td style="text-align: right;"><strong><?= $hi['total'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Donor Screening Distribution -->
<section class="content-card">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Donor network safety</span>
            <h2>Donor Screening Status Summary</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Screening Status</th>
                    <th>Donor Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($screeningDist as $sd):
                    $count = (int) $sd['count'];
                    $pct = $totalRegisteredDonors > 0 ? round(($count / $totalRegisteredDonors) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><span class="badge <?= donor_status_class($sd['screening_status']) ?>"><?= e($sd['screening_status']) ?></span></td>
                        <td><strong><?= $count ?></strong> donors</td>
                        <td><?= $pct ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
