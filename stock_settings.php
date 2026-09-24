<?php
/** File purpose: Stock Settings handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['hospital','admin']);
$pdo=db();$user=current_user();$errors=[];
$hospitals=bb_all($pdo,"SELECT id,name FROM hospitals WHERE status='Verified' ORDER BY name");
$id=$user['role']==='hospital'?(int)$user['hospital_id']:(int)($_GET['hospital_id']??$_POST['hospital_id']??($hospitals[0]['id']??0));
if(!can_manage_hospital($id)) { http_response_code(403);exit('Hospital access denied.'); }
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 try {
    $thresholds=$_POST['threshold']??[];
    $values=[];
    foreach(valid_blood_groups() as $g) {
        $n=filter_var($thresholds[$g]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000]]);
        if($n===false || $n===null) throw new DomainException('Each minimum must be between 0 and 1000.');
        $values[$g]=$n;
    }
    bb_transaction($pdo,function()use($pdo,$id,$values,$user):void {
        foreach($values as $g=>$n) bb_exec($pdo,'INSERT INTO blood_inventory (hospital_id,blood_group,units,reserved_units,low_stock_threshold) VALUES (?,?,0,0,?) ON DUPLICATE KEY UPDATE low_stock_threshold=VALUES(low_stock_threshold)',[$id,$g,$n]);
        audit_log($pdo,(int)$user['id'],'Update stock thresholds','Hospital',$id,json_encode($values));
    });
    flash('success','Minimum stock levels saved. Alerts appear when available units are below the minimum.');redirect('stock_settings.php?hospital_id='.$id);
 } catch(Throwable $e) { $errors[]=safe_error($e); }
}
$rows=bb_all($pdo,'SELECT blood_group,low_stock_threshold FROM blood_inventory WHERE hospital_id=?',[$id]);$levels=array_column($rows,'low_stock_threshold','blood_group');
$pageTitle='Low-stock settings';require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Low-stock settings</h1><p>A minimum of zero disables alerts for that blood group.</p></div><a class="button button-secondary" href="inventory.php?hospital_id=<?= $id ?>">Inventory</a></section>
<?php form_errors($errors); if($user['role']==='admin'): ?><section class="filter-card"><form method="get" class="filter-form"><label><span>Hospital</span><select name="hospital_id"><?php foreach($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (int)$h['id']===$id?'selected':'' ?>><?= e($h['name']) ?></option><?php endforeach; ?></select></label><button class="button button-secondary">Select</button></form></section><?php endif; ?>
<section class="content-card form-card"><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= $id ?>"><?php foreach(valid_blood_groups() as $g): ?><label><span><?= e($g) ?> minimum units</span><input type="number" name="threshold[<?= e($g) ?>]" min="0" max="1000" value="<?= (int)($levels[$g]??2) ?>" required></label><?php endforeach; ?><div class="form-wide"><button class="button button-primary">Save Thresholds</button></div></form></section>
<?php require __DIR__.'/includes/footer.php'; ?>
