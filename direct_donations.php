<?php
/** File purpose: Direct Donations handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/donations.php';
require_role(['donor','seeker','hospital']);
$pdo=db(); $user=current_user(); $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $action=(string)($_POST['action']??'');
        if($action==='create') {
            if(!isset($_POST['consent'])) throw new DomainException('Your consent is required to share health information with this hospital.');
            create_direct_donation($pdo,$user,(int)($_POST['hospital_id']??0),(string)($_POST['appointment_date']??''),(int)($_POST['club_id']??0)?:null);
        } else transition_direct_donation($pdo,$user,(int)($_POST['donation_id']??0),$action,trim((string)($_POST['note']??'')),isset($_POST['clinical_confirmed']));
        flash('success','Donation workflow updated.'); redirect('direct_donations.php');
    } catch(Throwable $e) { $errors[]=safe_error($e); }
}
$where='d.donor_id=?'; $params=[$user['id']];
if($user['role']==='hospital') { $where='d.hospital_id=?'; $params=[$user['hospital_id']]; }
elseif($user['role']==='admin') { $where='1=1'; $params=[]; }
$rows=bb_all($pdo,"SELECT d.*,u.full_name,h.name AS hospital_name,c.name AS club_name FROM direct_donations d JOIN users u ON u.id=d.donor_id JOIN hospitals h ON h.id=d.hospital_id LEFT JOIN clubs c ON c.id=d.club_id WHERE $where ORDER BY d.id DESC LIMIT 200",$params);
$hospitals=bb_all($pdo,"SELECT id,name FROM hospitals WHERE status='Verified' ORDER BY name");
$clubs=bb_all($pdo,"SELECT c.id,c.name FROM clubs c JOIN club_members m ON m.club_id=c.id WHERE c.status='Approved' AND m.status='Approved' AND m.user_id=?",[$user['id']]);
$pageTitle='Direct hospital donations'; require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Direct hospital donations</h1><p>Request → on-site screening → confirmed donation. One confirmed whole-blood donation records one unit, once.</p></div></section>
<?php form_errors($errors); ?>
<?php if(in_array($user['role'],['donor','seeker'],true)): ?><section class="content-card form-card"><h2>Request a donation appointment</h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label><span>Verified hospital</span><select name="hospital_id" required><?php foreach($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select></label><label><span>Appointment date</span><input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></label><label><span>Club attribution (optional)</span><select name="club_id"><option value="">Independent donation</option><?php foreach($clubs as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label><label class="checkbox-label form-wide"><input type="checkbox" name="consent" required><span>I consent to this hospital viewing my health declaration and reports for screening. I can revoke access under Health Records.</span></label><div class="form-wide"><button class="button button-primary">Request Appointment</button> <a href="health_records.php">My health profile</a></div></form></section><?php endif; ?>
<section class="content-card top-gap"><h2>Donation records</h2><?php if(!$rows): ?><div class="empty-state">No donation requests yet.</div><?php endif; ?>
<?php foreach($rows as $d): $active=in_array($d['status'],['Pending','Screened'],true); $staff=$user['role']==='hospital'; ?><article class="review-card"><div><strong>#<?= (int)$d['id'] ?> · <?= e($d['blood_group']) ?> · <?= e($d['full_name']) ?></strong><span><?= e($d['hospital_name']) ?> · <?= e($d['appointment_date']) ?></span><small><?= e($d['club_name']?:'Independent') ?> · <?= e($d['status']) ?></small><?php if($staff): ?><a href="health_records.php?user_id=<?= (int)$d['donor_id'] ?>">Consented health records</a><?php endif; ?></div>
<?php if($active): ?><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
<?php if($staff||$user['role']==='admin'): ?><label class="form-wide"><span>Screening / rejection reference</span><textarea name="note" maxlength="500"></textarea></label><?php endif; ?>
<?php if($staff): ?><label class="checkbox-label form-wide"><input type="checkbox" name="clinical_confirmed"><span>I confirm on-site donor assessment. For completion, collection and required blood screening/testing are complete and this unit is released for inventory by the hospital.</span></label><div class="form-wide"><button class="button button-primary" name="action" value="screen">Record / Renew Screening</button> <?php if($d['status']==='Screened'): ?><button class="button button-primary" name="action" value="complete">Confirm Donation</button><?php endif; ?> <button class="button button-secondary" name="action" value="reject">Reject</button></div>
<?php else: ?><button class="button button-secondary" name="action" value="cancel">Cancel</button><?php endif; ?></form><?php endif; ?></article><?php endforeach; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
