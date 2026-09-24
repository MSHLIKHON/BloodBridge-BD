<?php
/** File purpose: Health Records handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_login();
$pdo = db(); $user = current_user();
$ownerId = (int) ($_GET['user_id']??$user['id']);
$owner = bb_one($pdo,"SELECT * FROM users WHERE id=? AND role IN ('donor','seeker')",[$ownerId]);
$self = $ownerId === (int) $user['id'];
if (!$owner || !can_access_health($pdo,$user,$ownerId)) { http_response_code(403); exit('No consent to view these health records.'); }
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        bb_transaction($pdo,function () use ($pdo,$user,$ownerId,$self): void {
            $action=(string) ($_POST['action']??'');
            // Lock the same owner row used by donation completion and consent checks.
            $owner=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[$ownerId]);
            if ($action==='preferences' && $self) {
                $blood=(string) ($_POST['blood_group']??'');
                $enabled=isset($_POST['donor_enabled'])?1:0;
                if ($enabled && !in_array($blood,valid_blood_groups(),true)) throw new DomainException('Choose a blood group to enable donation.');
                if ($blood!=='' && !in_array($blood,valid_blood_groups(),true)) throw new DomainException('Invalid blood group.');
                $received=trim((string) ($_POST['last_received_date']??''));
                if ($received!=='' && !valid_past_date($received)) throw new DomainException('Enter a valid past received date.');
                if (($owner['blood_group']!==$blood || !$enabled) && (bb_one($pdo,"SELECT id FROM direct_donations WHERE donor_id=? AND status IN ('Pending','Screened')",[$ownerId]) || bb_one($pdo,"SELECT id FROM blood_requests WHERE accepted_by=? AND source_type='Donor' AND status='Accepted'",[$ownerId]))) throw new DomainException('Resolve your active donation commitment before changing blood group or disabling donation.');
                if ($owner['blood_group']!==$blood) bb_exec($pdo,"UPDATE users SET screening_status='Pending',verified_by_hospital=0 WHERE id=?",[$ownerId]);
                bb_exec($pdo,'UPDATE users SET donor_enabled=?,blood_group=?,reminders_enabled=?,last_received_date=? WHERE id=?',[$enabled,$blood?:null,isset($_POST['reminders_enabled'])?1:0,$received?:null,$ownerId]);
            } elseif (in_array($action,['grant','revoke'],true) && $self) {
                $hospital=(int) ($_POST['hospital_id']??0);
                if ($action==='grant') {
                    require_verified_hospital($pdo,$hospital);
                    if (!isset($_POST['consent'])) throw new DomainException('Consent is required before sharing.');
                    bb_exec($pdo,'INSERT INTO health_access (donor_id,hospital_id) VALUES (?,?) ON DUPLICATE KEY UPDATE granted_at=NOW()',[$ownerId,$hospital]);
                } else bb_exec($pdo,'DELETE FROM health_access WHERE donor_id=? AND hospital_id=?',[$ownerId,$hospital]);
            } elseif ($action==='upload' && $self) {
                $name=trim((string) ($_POST['test_name']??'')); $value=trim((string) ($_POST['test_value']??''));
                $unit=trim((string) ($_POST['test_unit']??'')); $date=trim((string) ($_POST['test_date']??'')); $lab=trim((string) ($_POST['lab_name']??''));
                if ($name==='' || strlen($name)>120 || strlen($value)>40 || strlen($unit)>30 || strlen($lab)>160 || !valid_past_date($date)) throw new DomainException('Enter a test name, valid past test date and values within the stated limits.');
                if (strtolower($name)==='hemoglobin' && (!is_numeric($value) || (float) $value<=0 || (float) $value>30 || $unit!=='g/dL')) throw new DomainException('For Hemoglobin, enter a numeric value above 0 and at most 30 with unit g/dL. This is data validation, not eligibility assessment.');
                save_private_document($pdo,$ownerId,'Health',receive_private_upload('document'),null,['name'=>$name,'value'=>$value,'unit'=>$unit,'date'=>$date,'lab'=>$lab]);
            } elseif ($action==='delete' && $self) {
                $doc=bb_one($pdo,"SELECT id FROM private_documents WHERE id=? AND owner_id=? AND kind='Health'",[(int) ($_POST['document_id']??0),$ownerId]);
                if (!$doc) throw new DomainException('Document not found.');
                bb_exec($pdo,'DELETE FROM private_documents WHERE id=?',[$doc['id']]);
                audit_log($pdo,$ownerId,'Delete private document','PrivateDocument',(int) $doc['id']);
            } elseif ($action==='review' && !$self && $user['role']==='hospital' && can_access_health($pdo,$user,$ownerId)) {
                $status=(string) ($_POST['status']??''); $note=trim((string) ($_POST['review_note']??''));
                if (!in_array($status,['Reviewed','Needs correction'],true) || $note==='' || strlen($note)>500) throw new DomainException('Choose a review result and enter a note within 500 characters.');
                $doc=bb_one($pdo,"SELECT id FROM private_documents WHERE id=? AND owner_id=? AND kind='Health'",[(int) ($_POST['document_id']??0),$ownerId]);
                if (!$doc) throw new DomainException('Document not found.');
                bb_exec($pdo,'UPDATE private_documents SET status=?,review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$note,$user['id'],$doc['id']]);
                create_notification($pdo,$ownerId,'Health report reviewed','A report was reviewed by the hospital you authorized.','health_records.php','Health');
            } else throw new DomainException('Action is not permitted.');
            audit_log($pdo,(int) $user['id'],'Health '.$action,'User',$ownerId);
        });
        flash('success','Saved. Health records remain private; removed reports are deleted from this database (backups may retain copies).');
        redirect('health_records.php?user_id='.$ownerId);
    } catch (Throwable $exception) { $errors[]=safe_error($exception); }
}
$documents=bb_all($pdo,"SELECT id,test_name,test_value,test_unit,test_date,lab_name,status,review_note FROM private_documents WHERE owner_id=? AND kind='Health' ORDER BY test_date DESC,id DESC",[$ownerId]);
$hospitals=bb_all($pdo,"SELECT id,name FROM hospitals WHERE status='Verified' ORDER BY name");
$shares=bb_all($pdo,'SELECT h.id,h.name FROM health_access a JOIN hospitals h ON h.id=a.hospital_id WHERE a.donor_id=?',[$ownerId]);
$pageTitle='Private health records'; require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Private records</span><h1><?= e($owner['full_name']) ?> — health records</h1><p>Reports are visible only to you and verified hospital staff you authorize. Report review does not certify donation eligibility.</p></div></section>
<?php form_errors($errors); ?>
<?php if ($self): ?>
<section class="content-card form-card"><h2>Profile &amp; reminders</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="preferences">
<label><span>Blood group</span><select name="blood_group"><option value="">Not provided</option><?php foreach(valid_blood_groups() as $g): ?><option <?= $owner['blood_group']===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach; ?></select></label>
<label><span>My last received date (not donated)</span><input type="date" name="last_received_date" max="<?= date('Y-m-d') ?>" value="<?= e($owner['last_received_date']) ?>"></label>
<label class="checkbox-label"><input type="checkbox" name="donor_enabled" <?= $owner['donor_enabled']?'checked':'' ?>><span>I also want to donate using this account</span></label>
<label class="checkbox-label"><input type="checkbox" name="reminders_enabled" <?= $owner['reminders_enabled']?'checked':'' ?>><span>Send donation-readiness reminders</span></label>
<div class="form-wide"><button class="button button-primary">Save Preferences</button> <a href="donor_profile.php">Update last donated date</a></div></form></section>
<section class="content-card form-card top-gap"><h2>Add a report</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload">
<label><span>Test name</span><input name="test_name" maxlength="120" placeholder="Hemoglobin" required></label><label><span>Result</span><input name="test_value" maxlength="40" placeholder="13.5"></label><label><span>Unit</span><input name="test_unit" maxlength="30" placeholder="g/dL"></label><label><span>Test date</span><input type="date" name="test_date" max="<?= date('Y-m-d') ?>" required></label><label><span>Lab name</span><input name="lab_name" maxlength="160"></label><label><span>JPG, PNG or PDF · max 2 MB</span><input type="file" name="document" accept="image/jpeg,image/png,application/pdf" required></label><div class="form-wide"><button class="button button-primary">Upload Private Report</button></div></form></section>
<section class="content-card form-card top-gap"><h2>Hospital access consent</h2><p>Grant access only for screening. Revoking stops future access here; it cannot recall a previously downloaded copy.</p><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="grant"><label><span>Hospital</span><select name="hospital_id"><?php foreach($hospitals as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select></label><label class="checkbox-label"><input type="checkbox" name="consent" required><span>I consent to this hospital reviewing my health declaration and reports</span></label><div class="form-wide"><button class="button button-primary">Grant Access</button></div></form>
<?php foreach($shares as $h): ?><form method="post" class="form-actions top-gap"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= (int) $h['id'] ?>"><span><?= e($h['name']) ?></span><button class="button button-secondary" name="action" value="revoke">Revoke Access</button></form><?php endforeach; ?></section>
<?php endif; ?>
<section class="content-card top-gap"><h2>Report history</h2><?php if(!$documents): ?><div class="empty-state">No reports uploaded.</div><?php endif; ?>
<?php foreach($documents as $doc): ?><article class="review-card"><div><strong><?= e($doc['test_name']) ?> · <?= e($doc['test_value']) ?> <?= e($doc['test_unit']) ?></strong><span><?= e($doc['test_date']) ?> · <?= e($doc['lab_name']) ?></span><small><?= e($doc['status']) ?> · <?= e($doc['review_note']) ?></small><a href="document.php?id=<?= (int) $doc['id'] ?>">Download private report</a></div>
<form method="post" class="form-grid" <?= $self?'data-confirm="Delete this report? This cannot be undone here."':'' ?>><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
<?php if($self): ?><button class="button button-secondary" name="action" value="delete">Delete Report</button><?php else: ?><label><span>Review result</span><select name="status"><option>Reviewed</option><option>Needs correction</option></select></label><label><span>Review note</span><input name="review_note" maxlength="500" required></label><button class="button button-primary" name="action" value="review">Save Review</button><?php endif; ?></form></article><?php endforeach; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
