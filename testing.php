<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['admin']);
require_once __DIR__.'/tests/unit.php';
$results=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();$results=bloodbridge_unit_tests();
 $pdo=db();
 $queries=[
  'Schema version 110'=>"SELECT COUNT(*) FROM app_settings WHERE setting_key='schema_version' AND setting_value='110'",
  'Stock nonnegative and reservation bounds'=>"SELECT COUNT(*) FROM blood_inventory WHERE units<0 OR reserved_units<0 OR reserved_units>units",
  'Reserved stock equals live commitments'=>"SELECT COUNT(*) FROM blood_inventory bi WHERE reserved_units<>(SELECT COALESCE(SUM(br.units),0) FROM blood_reservations br WHERE br.hospital_id=bi.hospital_id AND br.blood_group=bi.blood_group AND br.status='Approved')+(SELECT COALESCE(SUM(r.units),0) FROM blood_requests r WHERE r.hospital_id=bi.hospital_id AND r.blood_group=bi.blood_group AND r.source_type='Blood Bank' AND r.status='Accepted')",
  'No duplicate direct donation histories'=>"SELECT COUNT(*) FROM (SELECT direct_donation_id FROM donation_history WHERE direct_donation_id IS NOT NULL GROUP BY direct_donation_id HAVING COUNT(*)>1) d",
  'No duplicate request donation histories'=>"SELECT COUNT(*) FROM (SELECT request_id FROM donation_history WHERE request_id IS NOT NULL GROUP BY request_id HAVING COUNT(*)>1) d",
  'Completed direct donations have one history'=>"SELECT COUNT(*) FROM direct_donations d WHERE d.status='Completed' AND (SELECT COUNT(*) FROM donation_history dh WHERE dh.direct_donation_id=d.id)<>1",
  'No overdue active requests'=>"SELECT COUNT(*) FROM blood_requests WHERE status IN ('Pending','Accepted') AND donor_reported_at IS NULL AND expires_at<=NOW()",
  'No overdue active reservations'=>"SELECT COUNT(*) FROM blood_reservations WHERE status IN ('Pending','Approved') AND expires_at<=NOW()",
 ];
 foreach($queries as $name=>$sql) {
    try{$count=(int)$pdo->query($sql)->fetchColumn();$expected=$name==='Schema version 110'?1:0;$results[]=['test'=>$name,'status'=>$count===$expected?'PASS':'FAIL','detail'=>'Observed '.$count.'; expected '.$expected];}
    catch(Throwable $e){$results[]=['test'=>$name,'status'=>'FAIL','detail'=>'Database check failed; inspect server logs.'];error_log($e->getMessage());}
 }
 audit_log($pdo,(int)current_user()['id'],'Run read-only tests','System',null,count($results).' checks');
}
$pageTitle='Testing centre';require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Testing centre</h1><p>Run real read-only checks against this installation. These checks do not replace browser, concurrency or fresh-PC testing.</p></div></section>
<section class="content-card"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-primary">Run Unit &amp; Database Checks</button></form><p>Stock inconsistencies are reported, not silently rewritten. Use a separate test database for workflow tests.</p>
<?php if($results): $passed=count(array_filter($results,fn($r)=>$r['status']==='PASS')); ?><h2><?= $passed ?>/<?= count($results) ?> passed</h2><div class="table-wrap"><table><thead><tr><th>Check</th><th>Actual result</th><th>Detail</th></tr></thead><tbody><?php foreach($results as $r): ?><tr><td><?= e($r['test']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['detail']??'') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
