<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = db();

$totalDonors = (int) $pdo->query(
    "SELECT COUNT(*) FROM users
     WHERE role = 'donor' AND is_available = 1 AND screening_status = 'Eligible'
       AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL 120 DAY) <= CURDATE())"
)->fetchColumn();
$pendingRequests = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'Pending'")->fetchColumn();
$stockUnits = (int) $pdo->query('SELECT COALESCE(SUM(units), 0) FROM blood_inventory')->fetchColumn();

$myRequestStatement = $pdo->prepare('SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ?');
$myRequestStatement->execute([(int) $user['id']]);
$myRequests = (int) $myRequestStatement->fetchColumn();

$recentSql =
    'SELECT br.*, u.full_name AS seeker_name
     FROM blood_requests br
     JOIN users u ON u.id = br.seeker_id ';
$parameters = [];
if ($user['role'] === 'seeker') {
    $recentSql .= 'WHERE br.seeker_id = ? ';
    $parameters[] = (int) $user['id'];
} elseif ($user['role'] === 'donor' && $user['blood_group']) {
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
        <p>Monitor blood requests, donor matches and blood-bank availability from one place.</p>
    </div>
    <div class="hero-actions">
        <a class="button button-light" href="search.php">Search Blood</a>
        <?php if ($user['role'] === 'donor'): ?><a class="button button-white" href="donor_profile.php">Update Donor Profile</a><?php endif; ?>
        <?php if (in_array($user['role'], ['seeker', 'admin'], true)): ?>
            <a class="button button-white" href="request_create.php">Create Request</a>
        <?php endif; ?>
    </div>
</section>

<section class="stat-grid" aria-label="System summary">
    <article class="stat-card"><span>Available donors</span><strong><?= $totalDonors ?></strong><small>Screened and available</small></article>
    <article class="stat-card"><span>Pending requests</span><strong><?= $pendingRequests ?></strong><small>Waiting for response</small></article>
    <article class="stat-card"><span>Blood-bank units</span><strong><?= $stockUnits ?></strong><small>Across registered hospitals</small></article>
    <article class="stat-card"><span>My requests</span><strong><?= $myRequests ?></strong><small>Created by this account</small></article>
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
                <thead><tr><th>ID</th><th>Patient need</th><th>Location</th><th>Source</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentRequests as $request): ?>
                    <tr>
                        <td>#<?= (int) $request['id'] ?></td>
                        <td><strong class="blood-group"><?= e($request['blood_group']) ?></strong> <?= (int) $request['units'] ?> unit(s)</td>
                        <td><?= e($request['location']) ?></td>
                        <td><?= e($request['source_type']) ?></td>
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
