<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['admin']);
$pdo=db();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 try {
    $id=(int)($_POST['hospital_id']??0);$status=(string)($_POST['status']??'');
    $reference=trim((string)($_POST['reference']??''));$note=trim((string)($_POST['note']??''));
    if(!in_array($status,['Pending','Verified','Suspended'],true) || $reference==='' || strlen($reference)>160 || $note==='' || strlen($note)>500) throw new DomainException('Provide a status, verification reference and reason within the field limits.');
    bb_transaction($pdo,function()use($pdo,$id,$status,$reference,$note):void {
        $hospital=bb_one($pdo,'SELECT id FROM hospitals WHERE id=? FOR UPDATE',[$id]);
        if(!$hospital) throw new DomainException('Hospital not found.');
        bb_exec($pdo,'UPDATE hospitals SET status=?,verification_reference=?,verification_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$reference,$note,current_user()['id'],$id]);
        audit_log($pdo,(int)current_user()['id'],'Review hospital','Hospital',$id,$status.' · '.$reference);
        notify_hospital($pdo,$id,'Hospital status updated','Your hospital status is now '.$status.'.','hospital_login.php');
    });
    flash('success','Hospital verification saved. Pending/suspended hospitals cannot process stock or donor screenings.');redirect('hospital_verification.php');
 } catch(Throwable $e) { $errors[]=safe_error($e); }
}
$hospitals=bb_all($pdo,'SELECT * FROM hospitals ORDER BY name');$pageTitle='Hospital verification records';require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Hospital verification records</h1><p>Record the authority/reference actually checked. A reference entry is not independent accreditation.</p></div><a class="button button-secondary" href="admin_hospitals.php">Hospitals &amp; Staff</a></section>
<?php form_errors($errors); ?>
<?php foreach($hospitals as $h): ?><section class="content-card form-card top-gap"><h2><?= e($h['name']) ?></h2><p>Registration: <?= e($h['registration_number']) ?> · <?= e($h['status']) ?></p><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= (int)$h['id'] ?>"><label><span>Status</span><select name="status"><?php foreach(['Pending','Verified','Suspended'] as $s): ?><option <?= $h['status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></label><label><span>Document / registration verification reference</span><input name="reference" maxlength="160" value="<?= e($h['verification_reference']) ?>" required></label><label class="form-wide"><span>Review evidence / reason</span><textarea name="note" maxlength="500" required><?= e($h['verification_note']) ?></textarea></label><div class="form-wide"><button class="button button-primary">Save Verification</button></div></form></section><?php endforeach; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
