<?php
/** File purpose: Request Create handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_role(['seeker', 'donor']);
require_once __DIR__.'/includes/address.php';
require_once __DIR__.'/includes/geo.php';

$pdo = db();
$hospitals = $pdo->query("SELECT id, name, location FROM hospitals WHERE status = 'Verified' ORDER BY name")->fetchAll();
$errors = [];
$values = [
    'blood_group' => (string) ($_POST['blood_group'] ?? 'B+'),
    'location' => (string) ($_POST['location'] ?? (current_user()['location'] ?? '')),
    'units' => (string) ($_POST['units'] ?? '1'),
    'urgency' => (string) ($_POST['urgency'] ?? 'Urgent'),
    'source_type' => (string) ($_POST['source_type'] ?? 'Donor'),
    'hospital_id' => (string) ($_POST['hospital_id'] ?? ''),
    'note' => (string) ($_POST['note'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bloodGroup = $values['blood_group'];
    $location = trim($values['location']);
    $point=null;
    try { $location=address_input($_POST);$point=bangladesh_point($_POST['latitude']??null,$_POST['longitude']??null); } catch(DomainException $e) { $errors[]=$e->getMessage(); }
    $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    $urgency = $values['urgency'];
    $sourceType = $values['source_type'];
    $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
    $note = trim($values['note']);

    if (!in_array($bloodGroup, valid_blood_groups(), true)) $errors[] = 'Choose a valid blood group.';
    if ($location === '' || strlen($location) > 120) $errors[] = 'Enter a location within 120 characters.';
    if ($units === false || $units === null) $errors[] = 'Units must be between 1 and 10.';
    if (!in_array($urgency, ['Normal', 'Urgent', 'Emergency'], true)) $errors[] = 'Choose a valid urgency.';
    if (!in_array($sourceType, ['Donor', 'Blood Bank'], true)) $errors[] = 'Choose a valid blood source.';
    if ($sourceType === 'Blood Bank' && !$hospitalId) $errors[] = 'Choose a hospital blood bank.';
    if ($sourceType === 'Donor') $hospitalId = null;
    if ($sourceType === 'Donor' && (int)$units !== 1) $errors[] = 'A donor request represents one donation (1 unit). Use separate requests for multiple donors.';
    $patientRelation=(string)($_POST['patient_relation']??'Self');
    $patientReceived=trim((string)($_POST['patient_last_received']??''));
    $reviewHospital=(int)($_POST['review_hospital_id']??0);
    if($sourceType==='Blood Bank') $reviewHospital=(int)$hospitalId;
    if(!in_array($patientRelation,['Self','Family','Other'],true)) $errors[]='Choose who the request is for.';
    if($patientReceived!=='' && !valid_past_date($patientReceived)) $errors[]='Patient last received date must be a valid past date.';
    if(!isset($_POST['document_consent'])) $errors[]='Confirm permission to share the prescription.';
    if($reviewHospital && !bb_one($pdo,"SELECT id FROM hospitals WHERE id=? AND status='Verified'",[$reviewHospital])) $errors[]='Choose a verified reviewing hospital.';
    $upload=null;
    try { $upload=receive_private_upload('prescription'); } catch(DomainException $e) { $errors[]=$e->getMessage(); }
    if (strlen($note) > 500) $errors[] = 'Note must be within 500 characters.';

    if ($hospitalId) {
        $hospitalCheck = $pdo->prepare("SELECT COUNT(*) FROM hospitals WHERE id = ? AND status = 'Verified'");
        $hospitalCheck->execute([$hospitalId]);
        if ((int) $hospitalCheck->fetchColumn() !== 1) $errors[] = 'Selected hospital is unavailable.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO blood_requests (seeker_id, hospital_id, blood_group, location, units, urgency, source_type, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([(int) current_user()['id'], $hospitalId, $bloodGroup, $location, $units, $urgency, $sourceType, $note ?: null]);
            $requestId = (int) $pdo->lastInsertId();
            bb_exec($pdo,'UPDATE blood_requests SET latitude=?,longitude=? WHERE id=?',[$point[0]??null,$point[1]??null,$requestId]);
            request_event($pdo,$requestId,(int)current_user()['id'],'Request created');
            $expiryHours=app_setting('request_expiry_hours',72);
            bb_exec($pdo,"UPDATE blood_requests SET patient_relation=?,patient_last_received=?,review_hospital_id=?,prescription_status='Pending',expires_at=DATE_ADD(NOW(),INTERVAL $expiryHours HOUR) WHERE id=?",[$patientRelation,$patientReceived?:null,$reviewHospital?:null,$requestId]);
            save_private_document($pdo,(int)current_user()['id'],'Prescription',$upload,$requestId);
            notify_admins($pdo,'New prescription to review','Request #'.$requestId.' awaits review.','request_documents.php?id='.$requestId);
            if($reviewHospital) notify_hospital($pdo,$reviewHospital,'New prescription to review','Request #'.$requestId.' awaits review.','request_documents.php?id='.$requestId);

            if ($sourceType === 'Donor') {
                $matches = $pdo->prepare(
                    "SELECT id FROM users WHERE donor_enabled = 1 AND account_status = 'Active'
                     AND blood_group = ? AND is_available = 1 AND screening_status = 'Eligible'
                     AND (last_donation_date IS NULL OR DATE_ADD(last_donation_date, INTERVAL " . app_setting('donation_interval_days',120) . " DAY) <= CURDATE())"
                );
                $matches->execute([$bloodGroup]);
                foreach ($matches->fetchAll() as $match) create_notification($pdo, (int) $match['id'], 'New matching blood request', $urgency . ' ' . $bloodGroup . ' request in ' . $location . '.', 'request_edit.php?id=' . $requestId, 'Request');
            } else {
                $staff = $pdo->prepare("SELECT id FROM users WHERE role = 'hospital' AND hospital_id = ? AND account_status = 'Active'");
                $staff->execute([$hospitalId]);
                foreach ($staff->fetchAll() as $staffUser) create_notification($pdo, (int) $staffUser['id'], 'New blood-bank request', $bloodGroup . ' • ' . $units . ' unit(s) requested.', 'request_edit.php?id=' . $requestId, 'Request');
            }
            audit_log($pdo, (int) current_user()['id'], 'Create blood request', 'BloodRequest', $requestId, $bloodGroup . ' • ' . $sourceType);
            $pdo->commit();
            flash('success', 'Request #' . $requestId . ' saved and matching stakeholders were notified.');
            redirect('requests.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Request save failed: ' . $exception->getMessage());
            $errors[] = 'Request could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Create Blood Request';
$enableMap=true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Verified request workflow</span><h1>Create blood request</h1><p>Choose a donor match or a specific verified hospital blood bank.</p></div><a class="button button-secondary" href="requests.php">Back to Requests</a></section>
<section class="content-card form-card">
    <?php if ($errors): ?><div class="alert alert-error"><strong>Please fix:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-grid" data-request-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Blood group</span><select name="blood_group" required><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $values['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
        <label><span>Units needed</span><input type="number" name="units" min="1" max="10" value="<?= e($values['units']) ?>" required></label>
        <label><span>Urgency</span><select name="urgency"><option <?= $values['urgency'] === 'Normal' ? 'selected' : '' ?>>Normal</option><option <?= $values['urgency'] === 'Urgent' ? 'selected' : '' ?>>Urgent</option><option <?= $values['urgency'] === 'Emergency' ? 'selected' : '' ?>>Emergency</option></select></label>
        <label><span>Blood source</span><select name="source_type" data-source-select><option <?= $values['source_type'] === 'Donor' ? 'selected' : '' ?>>Donor</option><option <?= $values['source_type'] === 'Blood Bank' ? 'selected' : '' ?>>Blood Bank</option></select></label>
        <label class="form-wide" data-hospital-field><span>Hospital blood bank</span><select name="hospital_id"><option value="">Choose verified hospital</option><?php foreach ($hospitals as $hospital): ?><option value="<?= (int) $hospital['id'] ?>" <?= (int) $values['hospital_id'] === (int) $hospital['id'] ? 'selected' : '' ?>><?= e($hospital['name']) ?> — <?= e($hospital['location']) ?></option><?php endforeach; ?></select></label>
        <?php render_address_picker($values['location']); render_map_picker($point??null); ?>
        <label class="form-wide"><span>Patient note (optional)</span><textarea name="note" maxlength="500" rows="4" placeholder="Hospital ward, contact person or special instruction"><?= e($values['note']) ?></textarea></label>
        <label><span>Request for</span><select name="patient_relation"><option>Self</option><option>Family</option><option>Other</option></select></label>
        <label><span>Patient last received blood (if known)</span><input type="date" name="patient_last_received" max="<?= date('Y-m-d') ?>"></label>
        <label class="form-wide"><span>Reviewing hospital (optional for donor requests)</span><select name="review_hospital_id"><option value="">Administrator review</option><?php foreach($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select><small>For blood-bank requests, the selected blood bank reviews the prescription.</small></label>
        <label class="form-wide"><span>Prescription / requisition · JPG, PNG or PDF · max 2 MB</span><input type="file" name="prescription" accept="image/jpeg,image/png,application/pdf" required></label>
        <label class="checkbox-label form-wide"><input type="checkbox" name="document_consent" required><span>I may share this patient document with the assigned hospital and administrator for review</span></label>
        <div class="form-wide form-actions"><button class="button button-primary" type="submit">Create &amp; Notify</button><a class="button button-secondary" href="requests.php">Cancel</a></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
