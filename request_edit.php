<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'Invalid request ID.');
    redirect('requests.php');
}

$load = db()->prepare(
    'SELECT br.*, u.full_name AS seeker_name, u.phone AS seeker_phone, a.full_name AS accepted_name
     FROM blood_requests br
     JOIN users u ON u.id = br.seeker_id
     LEFT JOIN users a ON a.id = br.accepted_by
     WHERE br.id = ?'
);
$load->execute([$id]);
$request = $load->fetch();
if (!$request) {
    flash('error', 'Request not found.');
    redirect('requests.php');
}

$user = current_user();
$isOwner = (int) $request['seeker_id'] === (int) $user['id'];
$canView = $user['role'] === 'admin'
    || $isOwner
    || ($user['role'] === 'donor' && $request['source_type'] === 'Donor' && $user['blood_group'] === $request['blood_group'])
    || ($user['role'] === 'hospital' && $request['source_type'] === 'Blood Bank');
if (!$canView) {
    http_response_code(403);
    exit('You do not have permission to view this request.');
}
$canEditDetails = $user['role'] === 'admin' || ($isOwner && $request['status'] === 'Pending');
$canUpdateStatus = $user['role'] === 'admin'
    || ($user['role'] === 'donor' && $request['source_type'] === 'Donor' && $user['blood_group'] === $request['blood_group'])
    || ($user['role'] === 'hospital' && $request['source_type'] === 'Blood Bank');

$errors = [];
$parsedReqLoc = parse_location_string($request['location'] ?? '');
$overrideLoc = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'details' && $canEditDetails) {
        $bloodGroup = (string) ($_POST['blood_group'] ?? '');
        $division = trim((string) ($_POST['division'] ?? ''));
        $district = trim((string) ($_POST['district'] ?? ''));
        $upazila = trim((string) ($_POST['upazila'] ?? ''));
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        $urgency = (string) ($_POST['urgency'] ?? '');
        $sourceType = (string) ($_POST['source_type'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));

        $overrideLoc = ['division' => $division, 'district' => $district, 'upazila' => $upazila];

        if (!in_array($bloodGroup, valid_blood_groups(), true)) $errors[] = 'Choose a valid blood group.';
        if (!validate_location_hierarchy($division, $district, $upazila)) $errors[] = 'Select a valid Division, District, and Upazila.';
        if ($units === false || $units === null) $errors[] = 'Units must be between 1 and 10.';
        if (!in_array($urgency, ['Normal', 'Urgent', 'Emergency'], true)) $errors[] = 'Choose valid urgency.';
        if (!in_array($sourceType, ['Donor', 'Blood Bank'], true)) $errors[] = 'Choose a valid source.';
        if (strlen($note) > 500) $errors[] = 'Note must be within 500 characters.';

        if (!$errors) {
            $formattedLocation = format_location_string($upazila, $district, $division);
            $update = db()->prepare(
                'UPDATE blood_requests SET blood_group = ?, location = ?, units = ?, urgency = ?, source_type = ?, note = ? WHERE id = ?'
            );
            $update->execute([$bloodGroup, $formattedLocation, $units, $urgency, $sourceType, $note !== '' ? $note : null, $id]);
            flash('success', 'Request details updated successfully.');
            redirect('request_edit.php?id=' . $id);
        }
    } elseif ($action === 'status' && $canUpdateStatus) {
        $status = (string) ($_POST['status'] ?? '');
        $allowed = $user['role'] === 'admin'
            ? ['Pending', 'Accepted', 'Rejected', 'Completed', 'Cancelled']
            : ['Accepted', 'Rejected', 'Completed'];

        if (!in_array($status, $allowed, true)) {
            $errors[] = 'Choose a valid status.';
        } else {
            $acceptedBy = in_array($status, ['Accepted', 'Completed'], true)
                ? ((int) ($request['accepted_by'] ?: $user['id']))
                : null;
            $update = db()->prepare('UPDATE blood_requests SET status = ?, accepted_by = ? WHERE id = ?');
            $update->execute([$status, $acceptedBy, $id]);

            // If request is marked Completed, record in donation_history if applicable
            if ($status === 'Completed') {
                $targetDonorId = $acceptedBy ?: ($user['role'] === 'donor' ? (int) $user['id'] : null);
                if ($targetDonorId) {
                    $chkHist = db()->prepare('SELECT COUNT(*) FROM donation_history WHERE request_id = ?');
                    $chkHist->execute([$id]);
                    if ((int) $chkHist->fetchColumn() === 0) {
                        $unitsDonated = (int) $request['units'];
                        $insHist = db()->prepare(
                            'INSERT INTO donation_history (request_id, donor_id, blood_group, units, donation_date, notes)
                             VALUES (?, ?, ?, ?, CURDATE(), ?)'
                        );
                        $insHist->execute([
                            $id,
                            $targetDonorId,
                            $request['blood_group'],
                            $unitsDonated,
                            'Fulfilled request #' . $id . ' at ' . $request['location']
                        ]);

                        // Update donor stats
                        $updDonor = db()->prepare(
                            'UPDATE users SET total_donations = total_donations + ?, last_donation_date = CURDATE(), profile_updated_at = NOW() WHERE id = ?'
                        );
                        $updDonor->execute([$unitsDonated, $targetDonorId]);
                    }
                }
            }

            flash('success', 'Request status updated to ' . $status . '.');
            redirect('request_edit.php?id=' . $id);
        }
    } else {
        $errors[] = 'You do not have permission for this update.';
    }

    $load->execute([$id]);
    $request = $load->fetch();
}

$pageTitle = 'Request #' . $id;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Request management</span><h1>Request #<?= (int) $request['id'] ?></h1><p>Created by <?= e($request['seeker_name']) ?> on <?= e(date('d M Y, h:i A', strtotime($request['created_at']))) ?>.</p></div>
    <a class="button button-secondary" href="requests.php">Back to Requests</a>
</section>

<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="detail-grid">
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Request details</span><h2><?= e($request['blood_group']) ?> • <?= (int) $request['units'] ?> unit(s)</h2></div><span class="badge <?= request_status_class($request['status']) ?>"><?= e($request['status']) ?></span></div>
        <dl class="detail-list">
            <div><dt>Location</dt><dd><?= e($request['location']) ?></dd></div>
            <div><dt>Blood source</dt><dd><?= e($request['source_type']) ?></dd></div>
            <div><dt>Urgency</dt><dd><?= e($request['urgency']) ?></dd></div>
            <div><dt>Seeker phone</dt><dd><?= e($request['seeker_phone']) ?></dd></div>
            <div><dt>Accepted by</dt><dd><?= e($request['accepted_name'] ?: 'Not accepted yet') ?></dd></div>
            <div><dt>Note</dt><dd><?= e($request['note'] ?: 'No note') ?></dd></div>
        </dl>
    </section>

    <?php if ($canUpdateStatus): ?>
        <section class="content-card">
            <span class="eyebrow">Response</span><h2>Update request status</h2>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="status">
                <label><span>Status</span><select name="status"><?php $statusOptions = $user['role'] === 'admin' ? ['Pending', 'Accepted', 'Rejected', 'Completed', 'Cancelled'] : ['Accepted', 'Rejected', 'Completed']; foreach ($statusOptions as $status): ?><option value="<?= e($status) ?>" <?= $request['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
                <button class="button button-primary" type="submit">Save Status</button>
            </form>
        </section>
    <?php endif; ?>
</div>

<?php if ($canEditDetails): ?>
<section class="content-card form-card top-gap">
    <div class="section-heading"><div><span class="eyebrow">Owner controls</span><h2>Edit request information</h2></div></div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="details">
        <label><span>Blood group</span><select name="blood_group"><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $request['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
        <label><span>Units</span><input type="number" name="units" value="<?= (int) $request['units'] ?>" min="1" max="10" required></label>
        
        <!-- Bangladesh Dependent Location Selection -->
        <?php render_location_dropdowns('', $request['location'], true, $overrideLoc); ?>

        <label><span>Urgency</span><select name="urgency"><?php foreach (['Normal', 'Urgent', 'Emergency'] as $urgency): ?><option <?= $request['urgency'] === $urgency ? 'selected' : '' ?>><?= e($urgency) ?></option><?php endforeach; ?></select></label>
        <label><span>Source</span><select name="source_type"><?php foreach (['Donor', 'Blood Bank'] as $source): ?><option <?= $request['source_type'] === $source ? 'selected' : '' ?>><?= e($source) ?></option><?php endforeach; ?></select></label>
        <label class="form-wide"><span>Note</span><textarea name="note" rows="3" maxlength="500"><?= e($request['note']) ?></textarea></label>
        <div class="form-wide form-actions"><button class="button button-primary" type="submit">Update Request</button></div>
    </form>
</section>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
