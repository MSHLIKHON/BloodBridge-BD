<?php
/** File purpose: Requests handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['donor','seeker','hospital']);

$user = current_user();
$status = trim((string) ($_GET['status'] ?? ''));
$responseFilter=(string)($_GET['response']??'');
$allowedResponses=['Interested','Selected','Accepted','Rejected','Not selected','Withdrawn'];
$bloodGroup = trim((string) ($_GET['blood_group'] ?? ''));
$allowedStatuses = ['Pending', 'Accepted', 'Rejected', 'Completed', 'Cancelled', 'Expired'];

$where = [];
$parameters = [];
if (in_array($user['role'],['seeker','donor'],true)) {
    $profile=bb_one(db(),'SELECT donor_enabled FROM users WHERE id=?',[$user['id']]);
    $where[]="(br.seeker_id=? OR br.accepted_by=? OR EXISTS (SELECT 1 FROM request_responses own_response WHERE own_response.request_id=br.id AND own_response.responder_id=".(int)$user['id'].") OR (?=1 AND br.source_type='Donor' AND br.blood_group=? AND br.status='Pending' AND NOT EXISTS (SELECT 1 FROM request_responses rr WHERE rr.request_id=br.id AND rr.responder_id=? AND rr.response='Rejected')))";
    $parameters=[(int)$user['id'],(int)$user['id'],(int)$profile['donor_enabled'],$user['blood_group']??'',(int)$user['id']];
} elseif ($user['role'] === 'hospital') {
    $where[] = '(br.hospital_id = ? OR br.review_hospital_id = ?)';
    $parameters = [(int)$user['hospital_id'],(int)$user['hospital_id']];
}
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'br.status = ?';
    $parameters[] = $status;
}
if (in_array($bloodGroup, valid_blood_groups(), true)) {
    $where[] = 'br.blood_group = ?';
    $parameters[] = $bloodGroup;
}

if(personal_account($user) && in_array($responseFilter,$allowedResponses,true)) {$where[]='EXISTS (SELECT 1 FROM request_responses mine WHERE mine.request_id=br.id AND mine.responder_id=? AND mine.response=?)';$parameters[]=(int)$user['id'];$parameters[]=$responseFilter;}
$ownResponses=personal_account($user)?array_column(bb_all(db(),'SELECT request_id,response FROM request_responses WHERE responder_id=?',[$user['id']]),'response','request_id'):[];
$sql =
    'SELECT br.*, u.full_name AS seeker_name, u.phone AS seeker_phone, a.full_name AS accepted_name,
            h.name AS hospital_name
     FROM blood_requests br
     JOIN users u ON u.id = br.seeker_id
     LEFT JOIN users a ON a.id = br.accepted_by
     LEFT JOIN hospitals h ON h.id = br.hospital_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY br.created_at DESC';
$statement = db()->prepare($sql);
$statement->execute($parameters);
$requests = $statement->fetchAll();
$pageTitle = 'Blood Requests';
$enableLiveUpdates = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Request records</span><h1>Blood requests</h1><p>View and manage current blood requests.</p></div>
    <?php if (in_array($user['role'], ['seeker', 'donor'], true)): ?><a class="button button-primary" href="request_create.php">+ Create Request</a><?php endif; ?>
</section>

<section class="filter-card">
    <form method="get" class="filter-form">
        <label><span>Blood group</span><select name="blood_group"><option value="">All groups</option><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $bloodGroup === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
        <label><span>Status</span><select name="status"><option value="">All status</option><?php foreach ($allowedStatuses as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <?php if(personal_account($user)): ?><label><span>My response</span><select name="response"><option value="">All responses</option><?php foreach($allowedResponses as $value): ?><option <?= $responseFilter===$value?'selected':'' ?>><?= e($value) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <button class="button button-secondary" type="submit">Filter</button>
        <a class="text-link" href="requests.php">Clear</a>
    </form>
</section>

<section class="content-card">
    <div class="live-status"><span class="live-dot"></span><span>Live database updates enabled</span></div>
    <?php if (!$requests): ?>
        <div class="empty-state"><strong>No requests found.</strong><span>Try another filter or create a new request.</span></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Request</th><th>Seeker</th><th>Need</th><th>Source</th><th>Urgency</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><strong>#<?= (int) $request['id'] ?></strong><small><?= e(date('d M Y', strtotime($request['created_at']))) ?></small></td>
                        <td><?= e($request['seeker_name']) ?><small><?= e($request['location']) ?></small></td>
                        <td><strong class="blood-group"><?= e($request['blood_group']) ?></strong><small><?= (int) $request['units'] ?> unit(s)</small></td>
                        <td><?= e($request['source_type']) ?></td>
                        <td><?= e($request['urgency']) ?></td>
                        <td><span class="badge <?= request_status_class($request['status']) ?>"><?= e(request_progress_label($request)) ?></span><?php if(isset($ownResponses[$request['id']])): ?><small>Your response: <?= e($ownResponses[$request['id']]) ?></small><?php endif; ?></td>
                        <td class="actions"><a href="request_edit.php?id=<?= (int) $request['id'] ?>">View / Update</a><?php if ($request['status'] === 'Pending' && ((int) $request['seeker_id'] === (int) $user['id'])): ?><form method="post" action="request_delete.php" data-confirm="Cancel this pending request?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><button class="link-danger" type="submit">Cancel</button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
