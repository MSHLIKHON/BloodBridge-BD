<?php
/** File purpose: Dashboard handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = db();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$stats = [];
$quickLinks = [];
$recentRequests = [];
$heroTitle = 'Welcome back, ' . $user['full_name'];
$heroText = 'Your live workspace is connected to the BloodBridge database.';
$panelTitle = 'Recent requests';
$panelText = 'The latest records relevant to your account.';

if ($role === 'donor') {
    $donorStatement = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $donorStatement->execute([$userId]);
    $donor = $donorStatement->fetch() ?: [];
    $effectiveStatus = donor_effective_status($donor);
    $nextDate = next_eligible_date($donor['last_donation_date'] ?? null);

    $matchStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM blood_requests br
         WHERE br.blood_group = ? AND br.source_type = 'Donor' AND br.status = 'Pending'
           AND NOT EXISTS (
             SELECT 1 FROM request_responses rr
             WHERE rr.request_id = br.id AND rr.responder_id = ? AND rr.response = 'Rejected'
           )"
    );
    $matchStatement->execute([(string) ($donor['blood_group'] ?? ''), $userId]);
    $matchingRequests = $effectiveStatus === 'Eligible' ? (int) $matchStatement->fetchColumn() : 0;

    $acceptedStatement = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE accepted_by = ? AND status = 'Accepted'");
    $acceptedStatement->execute([$userId]);
    $historyStatement = $pdo->prepare('SELECT COUNT(*) FROM donation_history WHERE donor_id = ?');
    $historyStatement->execute([$userId]);

    $stats = [
        ['label' => 'Donation status', 'value' => $effectiveStatus, 'note' => $nextDate && $nextDate > date('Y-m-d') ? 'Interval ends ' . date('d M Y', strtotime($nextDate)) : 'Based on screening and availability'],
        ['label' => 'Matching requests', 'value' => $matchingRequests, 'note' => 'Pending requests for ' . ($donor['blood_group'] ?: 'your group')],
        ['label' => 'Accepted cases', 'value' => (int) $acceptedStatement->fetchColumn(), 'note' => 'Waiting for donation completion'],
        ['label' => 'Completed donations', 'value' => (int) $historyStatement->fetchColumn(), 'note' => 'Verified donation records'],
    ];
    $heroText = 'See matching blood requests, keep your health screening current and track verified donations.';
    $quickLinks = [
        ['title' => 'Update donor profile', 'text' => 'Availability, last donation and health information.', 'url' => 'donor_profile.php'],
        ['title' => 'Matching requests', 'text' => 'Accept or reject requests for your blood group.', 'url' => 'requests.php'],
        ['title' => 'Donation history', 'text' => 'Review completed and verified donations.', 'url' => 'donation_history.php'],
    ];
    $recentStatement = $pdo->prepare(
        "SELECT br.*, u.full_name AS seeker_name, h.name AS hospital_name
         FROM blood_requests br
         JOIN users u ON u.id = br.seeker_id
         LEFT JOIN hospitals h ON h.id = br.hospital_id
         WHERE br.blood_group = ? AND br.source_type = 'Donor'
           AND (br.status = 'Pending' OR br.accepted_by = ?)
           AND NOT EXISTS (
             SELECT 1 FROM request_responses rr
             WHERE rr.request_id = br.id AND rr.responder_id = ? AND rr.response = 'Rejected'
           )
         ORDER BY FIELD(br.status, 'Accepted', 'Pending', 'Completed', 'Cancelled', 'Rejected'), br.created_at DESC LIMIT 6"
    );
    $recentStatement->execute([(string) ($donor['blood_group'] ?? ''), $userId, $userId]);
    $recentRequests = $recentStatement->fetchAll();
    $panelTitle = 'Requests matching your profile';
    $panelText = 'Contact information becomes available only after you accept a request.';
} elseif ($role === 'seeker') {
    $countRequest = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND status IN ('Pending', 'Accepted')");
    $countRequest->execute([$userId]);
    $completed = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND status = 'Completed'");
    $completed->execute([$userId]);
    $reservations = $pdo->prepare("SELECT COUNT(*) FROM blood_reservations WHERE seeker_id = ? AND status IN ('Pending', 'Approved')");
    $reservations->execute([$userId]);
    $available = (int) $pdo->query('SELECT COALESCE(SUM(GREATEST(units - reserved_units, 0)), 0) FROM blood_inventory')->fetchColumn();

    $stats = [
        ['label' => 'Active requests', 'value' => (int) $countRequest->fetchColumn(), 'note' => 'Pending or accepted'],
        ['label' => 'Reservations', 'value' => (int) $reservations->fetchColumn(), 'note' => 'Waiting or approved'],
        ['label' => 'Completed requests', 'value' => (int) $completed->fetchColumn(), 'note' => 'Successfully closed cases'],
        ['label' => 'Available bank units', 'value' => $available, 'note' => 'Live unreserved stock'],
    ];
    $heroText = 'Create urgent requests, find eligible donors and reserve verified hospital blood-bank stock.';
    $quickLinks = [
        ['title' => 'Create blood request', 'text' => 'Choose donor or a verified hospital blood bank.', 'url' => 'request_create.php'],
        ['title' => 'Search available blood', 'text' => 'Filter donors and live stock by group and location.', 'url' => 'search.php'],
        ['title' => 'Track reservations', 'text' => 'See approval and collection status.', 'url' => 'reservations.php'],
    ];
    $recentStatement = $pdo->prepare(
        'SELECT br.*, u.full_name AS seeker_name, h.name AS hospital_name
         FROM blood_requests br JOIN users u ON u.id = br.seeker_id
         LEFT JOIN hospitals h ON h.id = br.hospital_id
         WHERE br.seeker_id = ? ORDER BY br.created_at DESC LIMIT 6'
    );
    $recentStatement->execute([$userId]);
    $recentRequests = $recentStatement->fetchAll();
    $panelTitle = 'My latest blood requests';
    $panelText = 'Open a request to check responses and complete the workflow.';
} elseif ($role === 'hospital') {
    $hospitalId = (int) ($user['hospital_id'] ?? 0);
    $hospitalStatement = $pdo->prepare('SELECT name, location, status FROM hospitals WHERE id = ? LIMIT 1');
    $hospitalStatement->execute([$hospitalId]);
    $hospital = $hospitalStatement->fetch() ?: ['name' => 'Assigned hospital', 'location' => '', 'status' => 'Pending'];

    $inventory = $pdo->prepare(
        'SELECT COALESCE(SUM(GREATEST(units - reserved_units, 0)), 0) AS available_units,
                COALESCE(SUM(reserved_units), 0) AS reserved_units,
                SUM(CASE WHEN GREATEST(units - reserved_units, 0) < low_stock_threshold THEN 1 ELSE 0 END) AS low_groups
         FROM blood_inventory WHERE hospital_id = ?'
    );
    $inventory->execute([$hospitalId]);
    $stock = $inventory->fetch() ?: [];
    $pendingRequests = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE hospital_id = ? AND source_type = 'Blood Bank' AND status = 'Pending'");
    $pendingRequests->execute([$hospitalId]);
    $pendingReservations = $pdo->prepare("SELECT COUNT(*) FROM blood_reservations WHERE hospital_id = ? AND status = 'Pending'");
    $pendingReservations->execute([$hospitalId]);

    $stats = [
        ['label' => 'Available units', 'value' => (int) ($stock['available_units'] ?? 0), 'note' => 'Total minus reserved stock'],
        ['label' => 'Reserved units', 'value' => (int) ($stock['reserved_units'] ?? 0), 'note' => 'Approved for collection'],
        ['label' => 'Low-stock groups', 'value' => (int) ($stock['low_groups'] ?? 0), 'note' => 'Below the configured minimum'],
        ['label' => 'Needs review', 'value' => (int) $pendingRequests->fetchColumn() + (int) $pendingReservations->fetchColumn(), 'note' => 'Requests and reservations'],
    ];
    $heroTitle = (string) $hospital['name'];
    $heroText = 'Manage live inventory, approve reservations, respond to blood-bank requests and screen donors.';
    $quickLinks = [
        ['title' => 'Update inventory', 'text' => 'Record received, expired or corrected blood units.', 'url' => 'inventory.php'],
        ['title' => 'Review reservations', 'text' => 'Approve stock and confirm patient collection.', 'url' => 'reservations.php'],
        ['title' => 'Screen donors', 'text' => 'Review donor health profiles and eligibility.', 'url' => 'donors.php'],
    ];
    $recentStatement = $pdo->prepare(
        "SELECT br.*, u.full_name AS seeker_name, h.name AS hospital_name
         FROM blood_requests br JOIN users u ON u.id = br.seeker_id
         LEFT JOIN hospitals h ON h.id = br.hospital_id
         WHERE br.hospital_id = ? AND br.source_type = 'Blood Bank'
         ORDER BY br.created_at DESC LIMIT 6"
    );
    $recentStatement->execute([$hospitalId]);
    $recentRequests = $recentStatement->fetchAll();
    $panelTitle = 'Hospital blood-bank requests';
    $panelText = 'Only requests addressed to your verified hospital are shown.';
} else {
    $activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'Active'")->fetchColumn();
    $activeDonors = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE donor_enabled = 1 AND account_status = 'Active' AND is_available = 1 AND screening_status = 'Eligible'
         AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL " . app_setting('donation_interval_days',120) . " DAY) <= CURDATE())"
    )->fetchColumn();
    $pendingStaff = (int) $pdo->query("SELECT COUNT(*) FROM hospital_staff_applications WHERE status = 'Pending'")->fetchColumn();
    $openRequests = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status IN ('Pending', 'Accepted')")->fetchColumn();

    $stats = [
        ['label' => 'Active users', 'value' => $activeUsers, 'note' => 'Across all stakeholder roles'],
        ['label' => 'Available donors', 'value' => $activeDonors, 'note' => 'Screened and marked available'],
        ['label' => 'Pending staff reviews', 'value' => $pendingStaff, 'note' => 'Hospital applications'],
        ['label' => 'Open blood requests', 'value' => $openRequests, 'note' => 'Pending or accepted'],
    ];
    $heroText = 'Monitor accounts, hospitals, inventory and live request activity across the platform.';
    $quickLinks = [
        ['title' => 'Verify hospital staff', 'text' => 'Approve applications and register hospitals.', 'url' => 'admin_hospitals.php'],
        ['title' => 'Manage users', 'text' => 'Control account access and login locks.', 'url' => 'admin_users.php'],
        ['title' => 'View system inventory', 'text' => 'Review stock and transaction history.', 'url' => 'inventory.php'],
    ];
    $recentRequests = $pdo->query(
        'SELECT br.*, u.full_name AS seeker_name, h.name AS hospital_name
         FROM blood_requests br JOIN users u ON u.id = br.seeker_id
         LEFT JOIN hospitals h ON h.id = br.hospital_id
         ORDER BY br.created_at DESC LIMIT 6'
    )->fetchAll();
    $panelTitle = 'Platform request activity';
    $panelText = 'The newest donor and blood-bank requests across the system.';
}

$notificationStatement = $pdo->prepare('SELECT title, message, link, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4');
$notificationStatement->execute([$userId]);
$recentNotifications = $notificationStatement->fetchAll();

$enableLiveUpdates = true;
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-panel role-hero role-<?= e($role) ?>">
    <div>
        <span class="eyebrow"><?= e(role_label($role)) ?> workspace</span>
        <h1><?= e($heroTitle) ?></h1>
        <p><?= e($heroText) ?></p>
    </div>
    <div class="hero-actions">
        <?php if ($role === 'donor'): ?><a class="button button-light" href="requests.php">View Matches</a><a class="button button-white" href="donor_profile.php">My Health Profile</a><?php endif; ?>
        <?php if ($role === 'seeker'): ?><a class="button button-light" href="request_create.php">New Request</a><a class="button button-white" href="search.php">Search Blood</a><?php endif; ?>
        <?php if ($role === 'hospital'): ?><a class="button button-light" href="inventory.php">Update Stock</a><a class="button button-white" href="reservations.php">Review Reservations</a><?php endif; ?>
        <?php if ($role === 'admin'): ?><a class="button button-light" href="admin_hospitals.php">Review Staff</a><a class="button button-white" href="admin_users.php">Manage Users</a><?php endif; ?>
    </div>
</section>

<section class="stat-grid" aria-label="Live account summary">
    <?php foreach ($stats as $stat): ?>
        <article class="stat-card"><span><?= e((string) $stat['label']) ?></span><strong class="stat-value"><?= e((string) $stat['value']) ?></strong><small><?= e((string) $stat['note']) ?></small></article>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <div class="content-card dashboard-main">
        <div class="section-heading">
            <div><span class="eyebrow">Live database</span><h2><?= e($panelTitle) ?></h2><p><?= e($panelText) ?></p></div>
            <a href="<?= $role==='admin'?'reports.php?kind=Requests':'requests.php' ?>">View all</a>
        </div>
        <?php if (!$recentRequests): ?>
            <div class="empty-state">No relevant requests are available yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Request</th><th>Need</th><th>Location</th><th>Source</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRequests as $request): ?>
                        <tr>
                            <td><strong>#<?= (int) $request['id'] ?></strong><small class="table-note"><?= e(date('d M, h:i A', strtotime((string) $request['created_at']))) ?></small></td>
                            <td><strong class="blood-group"><?= e($request['blood_group']) ?></strong> <?= (int) $request['units'] ?> unit(s)</td>
                            <td><?= e($request['location']) ?></td>
                            <td><?= e($request['source_type']) ?><?php if ($request['hospital_name']): ?><small class="table-note"><?= e($request['hospital_name']) ?></small><?php endif; ?></td>
                            <td><span class="badge <?= request_status_class((string) $request['status']) ?>"><?= e(request_progress_label($request)) ?></span></td>
                            <td><?php if($role==='admin'): ?><a href="reports.php?kind=Requests">Report</a><?php else: ?><a href="request_edit.php?id=<?= (int) $request['id'] ?>">Open</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <aside class="dashboard-side">
        <section class="content-card compact-card">
            <div class="section-heading"><div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div></div>
            <div class="quick-list">
                <?php foreach ($quickLinks as $link): ?><a class="quick-card" href="<?= e($link['url']) ?>"><strong><?= e($link['title']) ?></strong><span><?= e($link['text']) ?></span><b aria-hidden="true">→</b></a><?php endforeach; ?>
            </div>
        </section>
        <section class="content-card compact-card">
            <div class="section-heading"><div><span class="eyebrow">Updates</span><h2>Recent alerts</h2></div><a href="notifications.php">All</a></div>
            <?php if (!$recentNotifications): ?><div class="empty-state small-empty">No alerts yet.</div><?php else: ?><div class="mini-alerts"><?php foreach ($recentNotifications as $notification): ?><a href="<?= e($notification['link'] ?: 'notifications.php') ?>" class="mini-alert <?= $notification['read_at'] ? '' : 'unread' ?>"><strong><?= e($notification['title']) ?></strong><span><?= e($notification['message']) ?></span><small><?= e(date('d M, h:i A', strtotime((string) $notification['created_at']))) ?></small></a><?php endforeach; ?></div><?php endif; ?>
        </section>
    </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
