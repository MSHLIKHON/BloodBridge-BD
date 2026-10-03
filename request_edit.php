<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { flash('error', 'Invalid request ID.'); redirect('requests.php'); }
$pdo = db();
$load = $pdo->prepare(
    'SELECT br.*, seeker.full_name AS seeker_name, seeker.phone AS seeker_phone,
            responder.full_name AS accepted_name, responder.phone AS accepted_phone, responder.role AS accepted_role,
            h.name AS hospital_name
     FROM blood_requests br JOIN users seeker ON seeker.id = br.seeker_id
     LEFT JOIN users responder ON responder.id = br.accepted_by
     LEFT JOIN hospitals h ON h.id = br.hospital_id WHERE br.id = ?'
);
$load->execute([$id]);
$request = $load->fetch();
if (!$request) { flash('error', 'Request not found.'); redirect('requests.php'); }

$user = current_user();
$isOwner = (int) $request['seeker_id'] === (int) $user['id'];
$isMatchingDonor = $user['role'] === 'donor' && $request['source_type'] === 'Donor' && $user['blood_group'] === $request['blood_group'];
$isLinkedHospital = $user['role'] === 'hospital' && $request['source_type'] === 'Blood Bank' && (int) $user['hospital_id'] === (int) $request['hospital_id'];
$canView = $user['role'] === 'admin' || $isOwner || $isMatchingDonor || $isLinkedHospital;
if (!$canView) { http_response_code(403); exit('You do not have permission to view this request.'); }
$canEditDetails = $request['status'] === 'Pending' && ($user['role'] === 'admin' || $isOwner);
$canCompleteRequest = $request['source_type'] === 'Blood Bank'
    ? ($user['role'] === 'admin' || $isLinkedHospital)
    : ($user['role'] === 'admin' || $isOwner);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'details' && $canEditDetails) {
        $bloodGroup = (string) ($_POST['blood_group'] ?? '');
        $location = trim((string) ($_POST['location'] ?? ''));
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        $urgency = (string) ($_POST['urgency'] ?? '');
        $sourceType = (string) ($_POST['source_type'] ?? '');
        $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!in_array($bloodGroup, valid_blood_groups(), true)) $errors[] = 'Choose a valid blood group.';
        if ($location === '' || strlen($location) > 120) $errors[] = 'Enter a valid location.';
        if ($units === false || $units === null) $errors[] = 'Units must be between 1 and 10.';
        if (!in_array($urgency, ['Normal', 'Urgent', 'Emergency'], true)) $errors[] = 'Choose valid urgency.';
        if (!in_array($sourceType, ['Donor', 'Blood Bank'], true)) $errors[] = 'Choose a valid source.';
        if ($sourceType === 'Blood Bank' && !$hospitalId) $errors[] = 'Choose a hospital blood bank.';
        if ($sourceType === 'Donor') $hospitalId = null;
        if (strlen($note) > 500) $errors[] = 'Note must be within 500 characters.';
        if ($urgency === 'Emergency' && strlen($note) < 10) $errors[] = 'Emergency requests must include a short contact or hospital instruction (at least 10 characters).';
        if ($hospitalId) {
            $hospitalCheck = $pdo->prepare("SELECT COUNT(*) FROM hospitals WHERE id = ? AND status = 'Verified'");
            $hospitalCheck->execute([$hospitalId]);
            if ((int) $hospitalCheck->fetchColumn() !== 1) $errors[] = 'Selected hospital is unavailable.';
        }
        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $pendingCheck = $pdo->prepare('SELECT status FROM blood_requests WHERE id = ? FOR UPDATE');
                $pendingCheck->execute([$id]);
                if ($pendingCheck->fetchColumn() !== 'Pending') throw new RuntimeException('Only a pending request can be edited.');
                $pdo->prepare('UPDATE blood_requests SET hospital_id = ?, blood_group = ?, location = ?, units = ?, urgency = ?, source_type = ?, note = ? WHERE id = ?')
                    ->execute([$hospitalId, $bloodGroup, $location, $units, $urgency, $sourceType, $note ?: null, $id]);
                $matchingChanged = $request['blood_group'] !== $bloodGroup
                    || $request['source_type'] !== $sourceType
                    || (int) $request['hospital_id'] !== (int) $hospitalId;
                if ($matchingChanged && $sourceType === 'Donor') {
                    $matches = $pdo->prepare(
                        "SELECT id FROM users WHERE role = 'donor' AND account_status = 'Active'
                         AND blood_group = ? AND is_available = 1 AND screening_status = 'Eligible'
                         AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL 120 DAY) <= CURDATE())"
                    );
                    $matches->execute([$bloodGroup]);
                    foreach ($matches->fetchAll() as $match) {
                        create_notification($pdo, (int) $match['id'], 'Updated matching blood request', $urgency . ' ' . $bloodGroup . ' request in ' . $location . '.', 'request_edit.php?id=' . $id, 'Request');
                    }
                } elseif ($matchingChanged && $sourceType === 'Blood Bank') {
                    $staff = $pdo->prepare("SELECT id FROM users WHERE role = 'hospital' AND hospital_id = ? AND account_status = 'Active'");
                    $staff->execute([$hospitalId]);
                    foreach ($staff->fetchAll() as $staffUser) {
                        create_notification($pdo, (int) $staffUser['id'], 'Updated blood-bank request', $bloodGroup . ' • ' . $units . ' unit(s) requested.', 'request_edit.php?id=' . $id, 'Request');
                    }
                }
                audit_log($pdo, (int) $user['id'], 'Edit request', 'BloodRequest', (int) $id);
                $pdo->commit();
                flash('success', 'Request details updated.');
                redirect('request_edit.php?id=' . $id);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Request details could not be updated.';
            }
        }
    } elseif ($action === 'donor_response' && $isMatchingDonor) {
        $response = (string) ($_POST['response'] ?? '');
        if (!in_array($response, ['Accepted', 'Rejected'], true)) $errors[] = 'Choose Accept or Reject.';
        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT * FROM blood_requests WHERE id = ? FOR UPDATE');
                $locked->execute([$id]);
                $current = $locked->fetch();
                if (!$current || $current['source_type'] !== 'Donor' || $current['blood_group'] !== $user['blood_group']) throw new RuntimeException('This request no longer matches your profile.');
                if ($response === 'Accepted') {
                    if ($current['status'] !== 'Pending' || $current['accepted_by']) throw new RuntimeException('Another donor has already accepted this request.');
                    $donorLoad = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
                    $donorLoad->execute([(int) $user['id']]);
                    $donor = $donorLoad->fetch();
                    if (donor_effective_status($donor) !== 'Eligible') throw new RuntimeException('Your donor profile is not currently eligible.');
                    $pdo->prepare("UPDATE blood_requests SET status = 'Accepted', accepted_by = ? WHERE id = ?")->execute([(int) $user['id'], $id]);
                    create_notification($pdo, (int) $current['seeker_id'], 'A donor accepted your request', $user['full_name'] . ' accepted request #' . $id . '. Contact details are now available.', 'request_edit.php?id=' . $id, 'Request');
                }
                $pdo->prepare('INSERT INTO request_responses (request_id, responder_id, response) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response = VALUES(response), responded_at = NOW()')->execute([$id, (int) $user['id'], $response]);
                audit_log($pdo, (int) $user['id'], $response . ' request', 'BloodRequest', (int) $id);
                $pdo->commit();
                flash('success', $response === 'Accepted' ? 'Request accepted. The seeker can now see your contact.' : 'Request rejected for your account.');
                redirect('request_edit.php?id=' . $id);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($action === 'hospital_response' && $isLinkedHospital) {
        $response = (string) ($_POST['response'] ?? '');
        if (!in_array($response, ['Accepted', 'Rejected'], true)) $errors[] = 'Choose Accept or Reject.';
        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT * FROM blood_requests WHERE id = ? FOR UPDATE'); $locked->execute([$id]); $current = $locked->fetch();
                if (!$current || $current['status'] !== 'Pending') throw new RuntimeException('This request has already been processed.');
                $status = $response;
                $acceptedBy = $response === 'Accepted' ? (int) $user['id'] : null;
                if ($response === 'Accepted') {
                    $stock = $pdo->prepare('SELECT * FROM blood_inventory WHERE hospital_id = ? AND blood_group = ? FOR UPDATE');
                    $stock->execute([(int) $current['hospital_id'], $current['blood_group']]);
                    $inventory = $stock->fetch();
                    $available = $inventory ? (int) $inventory['units'] - (int) $inventory['reserved_units'] : 0;
                    if (!$inventory || $available < (int) $current['units']) throw new RuntimeException('Not enough unreserved hospital stock is available.');
                    $pdo->prepare('UPDATE blood_inventory SET reserved_units = reserved_units + ? WHERE id = ?')
                        ->execute([(int) $current['units'], (int) $inventory['id']]);
                }
                $pdo->prepare('UPDATE blood_requests SET status = ?, accepted_by = ? WHERE id = ?')->execute([$status, $acceptedBy, $id]);
                create_notification($pdo, (int) $current['seeker_id'], 'Blood-bank request ' . strtolower($response), ($request['hospital_name'] ?: 'Hospital') . ' ' . strtolower($response) . ' request #' . $id . '.', 'request_edit.php?id=' . $id, 'Request');
                audit_log($pdo, (int) $user['id'], $response . ' blood-bank request', 'BloodRequest', (int) $id);
                $pdo->commit(); flash('success', 'Request ' . strtolower($response) . '.'); redirect('request_edit.php?id=' . $id);
            } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = $exception->getMessage(); }
        }
    } elseif ($action === 'complete') {
        if (!$canCompleteRequest) $errors[] = 'You cannot complete this request.';
        else {
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT * FROM blood_requests WHERE id = ? FOR UPDATE'); $locked->execute([$id]); $current = $locked->fetch();
                if (!$current || $current['status'] !== 'Accepted') throw new RuntimeException('Only an accepted request can be completed.');
                if ($current['source_type'] === 'Blood Bank') {
                    $stock = $pdo->prepare('SELECT * FROM blood_inventory WHERE hospital_id = ? AND blood_group = ? FOR UPDATE');
                    $stock->execute([(int) $current['hospital_id'], $current['blood_group']]);
                    $inventory = $stock->fetch();
                    if (!$inventory || (int) $inventory['units'] < (int) $current['units'] || (int) $inventory['reserved_units'] < (int) $current['units']) {
                        throw new RuntimeException('Reserved hospital stock is inconsistent. Review inventory before completion.');
                    }
                    $newBalance = (int) $inventory['units'] - (int) $current['units'];
                    $pdo->prepare('UPDATE blood_inventory SET units = units - ?, reserved_units = reserved_units - ? WHERE id = ?')
                        ->execute([(int) $current['units'], (int) $current['units'], (int) $inventory['id']]);
                    $pdo->prepare(
                        "INSERT INTO inventory_transactions
                         (hospital_id, inventory_id, staff_user_id, transaction_type, unit_change, balance_after, note)
                         VALUES (?, ?, ?, 'Patient Supplied', ?, ?, ?)"
                    )->execute([(int) $current['hospital_id'], (int) $inventory['id'], (int) $user['id'], -(int) $current['units'], $newBalance, 'Blood-bank request #' . $id]);
                }
                $pdo->prepare("UPDATE blood_requests SET status = 'Completed', completed_at = NOW() WHERE id = ?")->execute([$id]);
                if ($current['source_type'] === 'Donor' && $current['accepted_by']) {
                    $historyCheck = $pdo->prepare('SELECT COUNT(*) FROM donation_history WHERE request_id = ?'); $historyCheck->execute([$id]);
                    if ((int) $historyCheck->fetchColumn() === 0) {
                        $pdo->prepare('INSERT INTO donation_history (request_id, donor_id, verified_by_user_id, blood_group, units, donation_date, notes) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)')->execute([$id, (int) $current['accepted_by'], (int) $user['id'], $current['blood_group'], (int) $current['units'], 'Completed through verified request workflow']);
                        $pdo->prepare('UPDATE users SET last_donation_date = CURDATE(), total_donations = total_donations + 1, profile_updated_at = NOW() WHERE id = ?')->execute([(int) $current['accepted_by']]);
                    }
                    create_notification($pdo, (int) $current['accepted_by'], 'Donation completed', 'Request #' . $id . ' was completed and added to your donation history.', 'donation_history.php', 'Donation');
                }
                create_notification($pdo, (int) $current['seeker_id'], 'Request completed', 'Request #' . $id . ' is now complete.', 'request_edit.php?id=' . $id, 'Request');
                audit_log($pdo, (int) $user['id'], 'Complete request', 'BloodRequest', (int) $id);
                $pdo->commit(); flash('success', 'Request completed and history updated.'); redirect('request_edit.php?id=' . $id);
            } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = $exception->getMessage(); }
        }
    } elseif ($action === 'cancel') {
        if (!$isOwner && $user['role'] !== 'admin') $errors[] = 'Only the request owner or Admin can cancel it.';
        elseif (!in_array($request['status'], ['Pending', 'Accepted'], true)) $errors[] = 'This request can no longer be cancelled.';
        else {
            $pdo->beginTransaction();
            try {
                $locked = $pdo->prepare('SELECT * FROM blood_requests WHERE id = ? FOR UPDATE');
                $locked->execute([$id]);
                $current = $locked->fetch();
                if (!$current || !in_array($current['status'], ['Pending', 'Accepted'], true)) throw new RuntimeException('This request can no longer be cancelled.');
                if ($current['status'] === 'Accepted' && $current['source_type'] === 'Blood Bank') {
                    $stock = $pdo->prepare('SELECT * FROM blood_inventory WHERE hospital_id = ? AND blood_group = ? FOR UPDATE');
                    $stock->execute([(int) $current['hospital_id'], $current['blood_group']]);
                    $inventory = $stock->fetch();
                    if ($inventory) {
                        if ((int) $inventory['reserved_units'] < (int) $current['units']) throw new RuntimeException('Reserved hospital stock is inconsistent.');
                        $pdo->prepare('UPDATE blood_inventory SET reserved_units = GREATEST(reserved_units - ?, 0) WHERE id = ?')
                            ->execute([(int) $current['units'], (int) $inventory['id']]);
                    }
                }
                $pdo->prepare("UPDATE blood_requests SET status = 'Cancelled' WHERE id = ?")->execute([$id]);
                if ($current['accepted_by']) create_notification($pdo, (int) $current['accepted_by'], 'Request cancelled', 'Request #' . $id . ' was cancelled.', 'requests.php', 'Request');
                audit_log($pdo, (int) $user['id'], 'Cancel request', 'BloodRequest', (int) $id);
                $pdo->commit();
                flash('success', 'Request cancelled.');
                redirect('request_edit.php?id=' . $id);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $exception->getMessage();
            }
        }
    } else {
        $errors[] = 'You do not have permission for this update.';
    }

    $load->execute([$id]); $request = $load->fetch();
}

$myResponse = null;
if ($user['role'] === 'donor') { $responseStatement = $pdo->prepare('SELECT response FROM request_responses WHERE request_id = ? AND responder_id = ?'); $responseStatement->execute([$id, (int) $user['id']]); $myResponse = $responseStatement->fetchColumn() ?: null; }
$hospitals = $pdo->query("SELECT id, name, location FROM hospitals WHERE status = 'Verified' ORDER BY name")->fetchAll();
$showSeekerPhone = $user['role'] === 'admin' || $isOwner || $isLinkedHospital || ((int) $request['accepted_by'] === (int) $user['id']);
$showAcceptedPhone = $user['role'] === 'admin' || $isOwner || $isLinkedHospital || ((int) $request['accepted_by'] === (int) $user['id']);
$pageTitle = 'Request #' . $id; $enableLiveUpdates = !$canEditDetails; require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Controlled request workflow</span><h1>Request #<?= (int) $request['id'] ?></h1><p>Created by <?= e($request['seeker_name']) ?> on <?= e(date('d M Y, h:i A', strtotime($request['created_at']))) ?>.</p></div><a class="button button-secondary" href="requests.php">Back to Requests</a></section>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="detail-grid">
    <section class="content-card"><div class="section-heading"><div><span class="eyebrow">Request details</span><h2><?= e($request['blood_group']) ?> • <?= (int) $request['units'] ?> unit(s)</h2></div><span class="badge <?= request_status_class($request['status']) ?>"><?= e($request['status']) ?></span></div><dl class="detail-list"><div><dt>Location</dt><dd><?= e($request['location']) ?></dd></div><div><dt>Source</dt><dd><?= e($request['source_type']) ?><?= $request['hospital_name'] ? ' • ' . e($request['hospital_name']) : '' ?></dd></div><div><dt>Urgency</dt><dd><?= e($request['urgency']) ?></dd></div><div><dt>Seeker contact</dt><dd><?= $showSeekerPhone ? e($request['seeker_phone']) : 'Shared after acceptance' ?></dd></div><div><dt>Accepted by</dt><dd><?php if ($request['accepted_name']): ?><?php if ($request['source_type'] === 'Donor'): ?><a href="donor_details.php?id=<?= (int) $request['accepted_by'] ?>"><?= e($request['accepted_name']) ?></a><?php else: ?><?= e($request['accepted_name']) ?><?php endif; ?><?= $showAcceptedPhone ? ' • ' . e($request['accepted_phone']) : '' ?><?php else: ?>Waiting for response<?php endif; ?></dd></div><div><dt>Note</dt><dd><?= e($request['note'] ?: 'No note') ?></dd></div><div><dt>Last updated</dt><dd><?= e(date('d M Y, h:i A', strtotime($request['updated_at']))) ?></dd></div></dl></section>
    <section class="content-card"><span class="eyebrow">Available action</span><h2>Respond safely</h2>
        <?php if ($isMatchingDonor && $request['status'] === 'Pending'): ?><p>Your blood group matches this request. Accept only if you are available and medically eligible.</p><form method="post" class="form-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="donor_response"><button class="button button-primary" name="response" value="Accepted">Accept Request</button><button class="button button-secondary" name="response" value="Rejected">Not Available</button></form><?php if ($myResponse): ?><p class="muted">Your last response: <?= e($myResponse) ?></p><?php endif; ?>
        <?php elseif ($isLinkedHospital && $request['status'] === 'Pending'): ?><p>Review stock and confirm whether your hospital can support this request.</p><form method="post" class="form-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="hospital_response"><button class="button button-primary" name="response" value="Accepted">Accept</button><button class="button button-secondary" name="response" value="Rejected">Reject</button></form>
        <?php elseif ($request['status'] === 'Accepted' && $canCompleteRequest): ?><p>Confirm completion only after blood was donated or supplied.</p><form method="post" data-confirm="Confirm that this request is fully completed?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="complete"><button class="button button-primary" type="submit">Confirm Completion</button></form>
        <?php else: ?><p class="muted">No action is required from your account at this stage.</p><?php endif; ?>
        <?php if (($isOwner || $user['role'] === 'admin') && in_array($request['status'], ['Pending', 'Accepted'], true)): ?><form method="post" class="top-gap" data-confirm="Cancel this blood request?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><button class="text-link-button danger" type="submit">Cancel Request</button></form><?php endif; ?>
    </section>
</div>

<?php if ($canEditDetails): ?><section class="content-card form-card top-gap"><div class="section-heading"><div><span class="eyebrow">Owner controls</span><h2>Edit pending request</h2></div></div><form method="post" class="form-grid" data-request-form><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="details"><label><span>Blood group</span><select name="blood_group"><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $request['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label><label><span>Units</span><input type="number" name="units" value="<?= (int) $request['units'] ?>" min="1" max="10" required></label><label><span>Urgency</span><select name="urgency"><?php foreach (['Normal', 'Urgent', 'Emergency'] as $urgency): ?><option <?= $request['urgency'] === $urgency ? 'selected' : '' ?>><?= e($urgency) ?></option><?php endforeach; ?></select></label><label><span>Source</span><select name="source_type" data-source-select><?php foreach (['Donor', 'Blood Bank'] as $source): ?><option <?= $request['source_type'] === $source ? 'selected' : '' ?>><?= e($source) ?></option><?php endforeach; ?></select></label><label class="form-wide" data-hospital-field><span>Hospital</span><select name="hospital_id"><option value="">Choose verified hospital</option><?php foreach ($hospitals as $hospital): ?><option value="<?= (int) $hospital['id'] ?>" <?= (int) $request['hospital_id'] === (int) $hospital['id'] ? 'selected' : '' ?>><?= e($hospital['name']) ?> — <?= e($hospital['location']) ?></option><?php endforeach; ?></select></label><label class="form-wide"><span>Location</span><input type="text" name="location" list="edit-locations" value="<?= e($request['location']) ?>" maxlength="120" required><?php render_location_datalist('edit-locations'); ?></label><label class="form-wide"><span>Note</span><textarea name="note" rows="3" maxlength="500"><?= e($request['note']) ?></textarea></label><div class="form-wide"><button class="button button-primary" type="submit">Update Request</button></div></form></section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
