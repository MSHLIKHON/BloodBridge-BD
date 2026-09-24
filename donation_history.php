<?php
/** File purpose: Donation History handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['donor', 'seeker', 'hospital', 'admin']);
$user = current_user();
$donorId = in_array($user['role'],['donor','seeker'],true) ? (int) $user['id'] : (int) ($_GET['donor_id'] ?? 0);

$where = [];
$params = [];
if ($donorId > 0) { $where[] = 'dh.donor_id = ?'; $params[] = $donorId; }
if ($user['role'] === 'hospital') {
    $where[] = '(dh.hospital_id = ? OR dh.verified_by_user_id = ?)';
    $params[] = (int) $user['hospital_id'];
    $params[] = (int) $user['id'];
}
$sql = 'SELECT dh.*, donor.full_name AS donor_name, h.name AS hospital_name, verifier.full_name AS verifier_name
        FROM donation_history dh LEFT JOIN users donor ON donor.id = dh.donor_id
        LEFT JOIN hospitals h ON h.id = dh.hospital_id LEFT JOIN users verifier ON verifier.id = dh.verified_by_user_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY dh.donation_date DESC, dh.id DESC';
$statement = db()->prepare($sql); $statement->execute($params); $history = $statement->fetchAll();
$pageTitle = 'Donation History';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Verified records</span><h1>Donation history</h1><p>Completed donations are added automatically from the request workflow.</p></div></section>
<section class="content-card"><?php if (!$history): ?><div class="empty-state"><strong>No completed donation found.</strong><span>A verified completion will appear here.</span></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Donor</th><th>Blood</th><th>Units</th><th>Request</th><th>Hospital</th><th>Verified by</th></tr></thead><tbody><?php foreach ($history as $item): ?><tr><td><?= e(date('d M Y', strtotime($item['donation_date']))) ?></td><td><?= e($item['donor_name'] ?: 'Unknown donor') ?></td><td><strong class="blood-group"><?= e($item['blood_group']) ?></strong></td><td><?= (int) $item['units'] ?></td><td><?= $item['request_id'] ? '<a href="request_edit.php?id=' . (int) $item['request_id'] . '">#' . (int) $item['request_id'] . '</a>' : '—' ?></td><td><?= e($item['hospital_name'] ?: 'Direct donation') ?></td><td><?= e($item['verifier_name'] ?: 'System') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
