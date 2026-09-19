<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'hospital']);

$donors = db()->query(
    "SELECT id, full_name, blood_group, location, last_donation_date, is_available,
            screening_status, verified_by_hospital
     FROM users WHERE donor_enabled = 1 AND account_status = 'Active' ORDER BY full_name"
)->fetchAll();

$pageTitle = 'Donors';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Authorized access</span><h1>Registered donors</h1><p>Review donor availability and screening status.</p></div></section>
<section class="content-card">
    <?php if (!$donors): ?><div class="empty-state">No donors registered.</div><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Donor</th><th>Blood group</th><th>Location</th><th>Last donation</th><th>Status</th><th>Verification</th><th></th></tr></thead>
        <tbody><?php foreach ($donors as $donor): $effectiveStatus = donor_effective_status($donor); ?><tr>
            <td><strong><?= e($donor['full_name']) ?></strong></td>
            <td><strong class="blood-group"><?= e($donor['blood_group']) ?></strong></td>
            <td><?= e($donor['location']) ?></td>
            <td><?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?></td>
            <td><span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span></td>
            <td><?= $donor['verified_by_hospital'] ? 'Verified' : 'Pending' ?></td>
            <td><a href="donor_details.php?id=<?= (int) $donor['id'] ?>">Review</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
