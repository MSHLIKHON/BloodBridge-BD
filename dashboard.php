<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = db();

// Role-specific stats calculation
$totalDonors = (int) $pdo->query(
    "SELECT COUNT(*) FROM users
     WHERE role = 'donor' AND is_available = 1 AND screening_status = 'Eligible'
       AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL 120 DAY) <= CURDATE())"
)->fetchColumn();
$pendingRequests = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'Pending'")->fetchColumn();
$stockUnits = (int) $pdo->query('SELECT COALESCE(SUM(units), 0) FROM blood_inventory')->fetchColumn();
$totalDonationsCount = (int) $pdo->query('SELECT COUNT(*) FROM donation_history')->fetchColumn();

// Seeker specific stats
$myTotalReqStmt = $pdo->prepare('SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ?');
$myTotalReqStmt->execute([(int) $user['id']]);
$myTotalRequests = (int) $myTotalReqStmt->fetchColumn();

$myPendingReqStmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND status = 'Pending'");
$myPendingReqStmt->execute([(int) $user['id']]);
$myPendingRequests = (int) $myPendingReqStmt->fetchColumn();

$myAcceptedReqStmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND status = 'Accepted'");
$myAcceptedReqStmt->execute([(int) $user['id']]);
$myAcceptedRequests = (int) $myAcceptedReqStmt->fetchColumn();

// Donor specific stats
$matchingDonorRequests = 0;
$donorEffectiveStatus = 'Eligible';
$donorEligibleDate = null;
if ($user['role'] === 'donor') {
    // Refresh donor data from db for accurate dashboard
    $dStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $dStmt->execute([(int) $user['id']]);
    $donorData = $dStmt->fetch() ?: $user;
    $donorEffectiveStatus = donor_effective_status($donorData);
    $donorEligibleDate = next_eligible_date($donorData['last_donation_date'] ?? null);

    if (!empty($donorData['blood_group'])) {
        $mStmt = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE blood_group = ? AND source_type = 'Donor' AND status = 'Pending'");
        $mStmt->execute([$donorData['blood_group']]);
        $matchingDonorRequests = (int) $mStmt->fetchColumn();
    }
}

// Hospital specific stats
$hospitalLowStockCount = 0;
$hospitalStockUnits = 0;
$hospitalPendingRequests = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE source_type = 'Blood Bank' AND status = 'Pending'")->fetchColumn();
if ($user['role'] === 'hospital') {
    // Fetch matched hospital
    $hospitals = $pdo->query('SELECT id, location FROM hospitals')->fetchAll();
    $matchedHId = null;
    foreach ($hospitals as $h) {
        if (stripos($h['location'], $user['location']) !== false || stripos($user['location'], $h['location']) !== false) {
            $matchedHId = (int) $h['id'];
            break;
        }
    }
    $targetHId = $matchedHId ?? ($hospitals ? (int) $hospitals[0]['id'] : null);
    if ($targetHId) {
        $hInvStmt = $pdo->prepare('SELECT blood_group, units FROM blood_inventory WHERE hospital_id = ?');
        $hInvStmt->execute([$targetHId]);
        $hRows = $hInvStmt->fetchAll();
        foreach ($hRows as $hr) {
            $u = (int) $hr['units'];
            $hospitalStockUnits += $u;
            if ($u < 3) $hospitalLowStockCount++;
        }
    }
}

// Recent requests query based on role
$recentSql =
    'SELECT br.*, u.full_name AS seeker_name
     FROM blood_requests br
     JOIN users u ON u.id = br.seeker_id ';
$parameters = [];
if ($user['role'] === 'seeker') {
    $recentSql .= 'WHERE br.seeker_id = ? ';
    $parameters[] = (int) $user['id'];
} elseif ($user['role'] === 'donor' && !empty($user['blood_group'])) {
    $recentSql .= "WHERE br.blood_group = ? AND br.source_type = 'Donor' ";
    $parameters[] = $user['blood_group'];
} elseif ($user['role'] === 'hospital') {
    $recentSql .= "WHERE br.source_type = 'Blood Bank' ";
}
$recentSql .= 'ORDER BY br.created_at DESC LIMIT 5';
$recentStatement = $pdo->prepare($recentSql);
$recentStatement->execute($parameters);
$recentRequests = $recentStatement->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-panel">
    <div>
        <span class="eyebrow"><?= e(ucfirst($user['role'])) ?> dashboard</span>
        <h1>Hello, <?= e($user['full_name']) ?></h1>
        <p>Monitor blood requests, donor matches, inventory stock and donation milestones.</p>
    </div>
    <div class="hero-actions">
        <a class="button button-light" href="search.php">Search Blood</a>
        <?php if ($user['role'] === 'donor'): ?>
            <a class="button button-white" href="donor_profile.php">Donor Profile</a>
            <a class="button button-white" href="donation_history.php">Donation History</a>
        <?php elseif ($user['role'] === 'hospital'): ?>
            <a class="button button-white" href="inventory.php">Manage Inventory</a>
            <a class="button button-white" href="reports.php">View Reports</a>
        <?php elseif ($user['role'] === 'seeker'): ?>
            <a class="button button-white" href="request_create.php">+ Create Request</a>
        <?php elseif ($user['role'] === 'admin'): ?>
            <a class="button button-white" href="inventory.php">Inventory</a>
            <a class="button button-white" href="reports.php">Reports</a>
            <a class="button button-white" href="request_create.php">New Request</a>
        <?php endif; ?>
    </div>
</section>

<!-- Role-Specific Stat Cards -->
<section class="stat-grid" aria-label="System summary">
    <?php if ($user['role'] === 'donor'): ?>
        <article class="stat-card">
            <span>Eligibility Status</span>
            <strong style="font-size: 24px; margin-top: 5px;">
                <span class="badge <?= donor_status_class($donorEffectiveStatus) ?>"><?= e($donorEffectiveStatus) ?></span>
            </strong>
            <small><?= !empty($donorData['verified_by_hospital']) ? 'Hospital Verified' : 'Verification Pending' ?></small>
        </article>
        <article class="stat-card">
            <span>Next Eligible Date</span>
            <strong style="font-size: 24px; margin-top: 5px;">
                <?= $donorEligibleDate ? e(date('d M Y', strtotime($donorEligibleDate))) : 'Eligible Now' ?>
            </strong>
            <small>120-day interval</small>
        </article>
        <article class="stat-card">
            <span>Total Donations</span>
            <strong><?= (int) ($donorData['total_donations'] ?? 0) ?></strong>
            <small>Recorded donations</small>
        </article>
        <article class="stat-card">
            <span>Matching Requests</span>
            <strong><?= $matchingDonorRequests ?></strong>
            <small>Need <?= e($donorData['blood_group'] ?? 'Blood') ?></small>
        </article>

    <?php elseif ($user['role'] === 'hospital'): ?>
        <article class="stat-card">
            <span>Hospital Blood Units</span>
            <strong><?= $hospitalStockUnits ?></strong>
            <small>Units in blood bank</small>
        </article>
        <article class="stat-card">
            <span>Low Stock Alerts</span>
            <strong style="color: <?= $hospitalLowStockCount > 0 ? '#b54708' : 'inherit' ?>;"><?= $hospitalLowStockCount ?></strong>
            <small>Groups under 3 units</small>
        </article>
        <article class="stat-card">
            <span>Pending Bank Requests</span>
            <strong><?= $hospitalPendingRequests ?></strong>
            <small>Waiting for approval</small>
        </article>
        <article class="stat-card">
            <span>Screened Donors</span>
            <strong><?= $totalDonors ?></strong>
            <small>Available in network</small>
        </article>

    <?php elseif ($user['role'] === 'seeker'): ?>
        <article class="stat-card">
            <span>My Requests</span>
            <strong><?= $myTotalRequests ?></strong>
            <small>Created by this account</small>
        </article>
        <article class="stat-card">
            <span>Pending Requests</span>
            <strong><?= $myPendingRequests ?></strong>
            <small>Awaiting donors</small>
        </article>
        <article class="stat-card">
            <span>Accepted / Active</span>
            <strong><?= $myAcceptedRequests ?></strong>
            <small>In progress</small>
        </article>
        <article class="stat-card">
            <span>Available Donors</span>
            <strong><?= $totalDonors ?></strong>
            <small>Screened and ready</small>
        </article>

    <?php else: /* admin */ ?>
        <article class="stat-card">
            <span>Available Donors</span>
            <strong><?= $totalDonors ?></strong>
            <small>Screened and available</small>
        </article>
        <article class="stat-card">
            <span>Pending Requests</span>
            <strong><?= $pendingRequests ?></strong>
            <small>Waiting for response</small>
        </article>
        <article class="stat-card">
            <span>Blood-Bank Units</span>
            <strong><?= $stockUnits ?></strong>
            <small>Across hospitals</small>
        </article>
        <article class="stat-card">
            <span>Donations Recorded</span>
            <strong><?= $totalDonationsCount ?></strong>
            <small>Total audit logs</small>
        </article>
    <?php endif; ?>
</section>

<section class="content-card">
    <div class="section-heading">
        <div><span class="eyebrow">Live data</span><h2>Recent blood requests</h2></div>
        <a href="requests.php">View all</a>
    </div>
    <?php if (!$recentRequests): ?>
        <div class="empty-state">No matching requests yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Patient need</th><th>Location</th><th>Source</th><th>Urgency</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentRequests as $request): ?>
                    <tr>
                        <td>#<?= (int) $request['id'] ?></td>
                        <td><strong class="blood-group"><?= e($request['blood_group']) ?></strong> <?= (int) $request['units'] ?> unit(s)</td>
                        <td><?= e($request['location']) ?></td>
                        <td><?= e($request['source_type']) ?></td>
                        <td>
                            <span class="<?= $request['urgency'] === 'Emergency' ? 'badge badge-red' : ($request['urgency'] === 'Urgent' ? 'badge badge-gray' : '') ?>">
                                <?= e($request['urgency']) ?>
                            </span>
                        </td>
                        <td><span class="badge <?= request_status_class($request['status']) ?>"><?= e($request['status']) ?></span></td>
                        <td><a href="request_edit.php?id=<?= (int) $request['id'] ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
