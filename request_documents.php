<?php
/** File purpose: Request Documents handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_login();
$pdo=db(); $user=current_user(); $id=(int) ($_GET['id']??0); $errors=[];
if (!$id) {
    $where="br.seeker_id=?"; $params=[$user['id']];
    if($user['role']==='admin') { $where='1=1'; $params=[]; }
    elseif($user['role']==='hospital') { $where='br.review_hospital_id=?'; $params=[$user['hospital_id']]; }
    $rows=bb_all($pdo,"SELECT br.id,br.blood_group,br.prescription_status,br.status FROM blood_requests br WHERE $where ORDER BY br.id DESC LIMIT 200",$params);
    $pageTitle='Prescription review'; require __DIR__.'/includes/header.php';
    echo '<section class="page-heading"><div><h1>Prescriptions</h1><p>Review checks request documentation, not clinical suitability or compatibility.</p></div></section><section class="content-card">';
    foreach($rows as $r) echo '<p><a href="request_documents.php?id='.(int)$r['id'].'">Request #'.(int)$r['id'].' · '.e($r['blood_group']).'</a> · '.e($r['prescription_status']).' · '.e($r['status']).'</p>';
    if(!$rows) echo '<div class="empty-state">No requests assigned.</div>';
    echo '</section>'; require __DIR__.'/includes/footer.php'; exit;
}
$request=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=?',[$id]);
$owner=$request && (int)$request['seeker_id']===(int)$user['id'];
if(!$request || (!$owner && !can_review_prescription($pdo,$user,$request))) { http_response_code(403); exit('Prescription access denied.'); }
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        bb_transaction($pdo,function() use($pdo,$user,$id,$owner):void {
            $r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=? FOR UPDATE',[$id]);
            $action=(string)($_POST['action']??'');
            if($action==='upload' && $owner) {
                if(!in_array($r['status'],['Pending','Accepted'],true)) throw new DomainException('Documents can only be added to active requests.');
                ensure_not_expired($r);
                if(!isset($_POST['consent'])) throw new DomainException('Confirm that you may share the patient document.');
                save_private_document($pdo,(int)$user['id'],'Prescription',receive_private_upload('document'),$id);
                bb_exec($pdo,"UPDATE blood_requests SET prescription_status='Pending',prescription_reviewer=NULL,prescription_note=NULL WHERE id=?",[$id]);
                notify_admins($pdo,'Prescription awaiting review','Request #'.$id.' has a new prescription.','request_documents.php?id='.$id);
                if($r['review_hospital_id']) notify_hospital($pdo,(int)$r['review_hospital_id'],'Prescription awaiting review','Request #'.$id.' needs review.','request_documents.php?id='.$id);
            } elseif($action==='review' && can_review_prescription($pdo,$user,$r)) {
                if(!in_array($r['status'],['Pending','Accepted'],true)) throw new DomainException('This request is closed.');
                ensure_not_expired($r);
                $status=(string)($_POST['status']??''); $note=trim((string)($_POST['review_note']??''));
                if(!in_array($status,['Reviewed','Needs correction'],true) || $note==='' || strlen($note)>500) throw new DomainException('Choose a result and enter a review note.');
                $doc=bb_one($pdo,"SELECT id FROM private_documents WHERE request_id=? AND kind='Prescription' ORDER BY id DESC LIMIT 1",[$id]);
                if(!$doc) throw new DomainException('No prescription uploaded.');
                if((int)($_POST['document_id']??0)!==(int)$doc['id']) throw new DomainException('A newer prescription was uploaded. Refresh and review that document first.');
                bb_exec($pdo,'UPDATE blood_requests SET prescription_status=?,prescription_reviewer=?,prescription_note=? WHERE id=?',[$status,$user['id'],$note,$id]);
                bb_exec($pdo,'UPDATE private_documents SET status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?',[$status,$user['id'],$note,$doc['id']]);
                create_notification($pdo,(int)$r['seeker_id'],'Prescription '.$status,'Request #'.$id.': '.$note,'request_documents.php?id='.$id,'Review');
                audit_log($pdo,(int)$user['id'],'Review prescription','BloodRequest',$id,$status);
            } else throw new DomainException('Action not permitted.');
        });
        flash('success','Prescription updated.'); redirect('request_documents.php?id='.$id);
    } catch(Throwable $e) { $errors[]=safe_error($e); }
}
$documents=bb_all($pdo,"SELECT id,status,created_at,review_note FROM private_documents WHERE request_id=? AND kind='Prescription' ORDER BY id DESC",[$id]);
$pageTitle='Request prescription'; require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Prescription · request #<?= $id ?></h1><p><?= e($request['prescription_status']) ?> · <?= e($request['prescription_note']) ?></p></div><a class="button button-secondary" href="<?= $user['role']==='admin'?'request_documents.php':'requests.php' ?>">Back to Records</a></section>
<?php form_errors($errors); ?>
<section class="content-card"><dl class="detail-list"><div><dt>Request for</dt><dd><?= e($request['patient_relation']) ?></dd></div><div><dt>Patient last received</dt><dd><?= e($request['patient_last_received']?:'Not provided') ?></dd></div><div><dt>Expires</dt><dd><?= e($request['expires_at']) ?></dd></div></dl><p>These records are not shared with donors or club coordinators. Avoid including unrelated patient information.</p>
<?php foreach($documents as $doc): ?><p><a href="document.php?id=<?= (int)$doc['id'] ?>">Download prescription #<?= (int)$doc['id'] ?></a> · <?= e($doc['created_at']) ?> · <?= e($doc['status']) ?></p><?php endforeach; ?>
<?php if(!$documents): ?><p>No document uploaded.</p><?php endif; ?></section>
<?php if(in_array($request['status'],['Pending','Accepted'],true)): ?><section class="content-card form-card top-gap">
<?php if($owner): ?><h2>Upload prescription / requisition</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload"><label class="form-wide"><span>JPG, PNG or PDF · max 2 MB</span><input type="file" name="document" accept="image/jpeg,image/png,application/pdf" required></label><label class="checkbox-label form-wide"><input type="checkbox" name="consent" required><span>I am authorized to share this patient document with the assigned hospital and administrator for request review</span></label><button class="button button-primary">Upload for Review</button></form>
<?php elseif($documents): ?><h2>Review latest document</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review"><input type="hidden" name="document_id" value="<?= (int)$documents[0]['id'] ?>"><label><span>Review result</span><select name="status"><option>Reviewed</option><option>Needs correction</option></select></label><label class="form-wide"><span>Reason / verification reference</span><textarea name="review_note" maxlength="500" required></textarea></label><button class="button button-primary">Save Review</button></form><?php endif; ?></section><?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
