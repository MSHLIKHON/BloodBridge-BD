<?php
/** File purpose: Testing handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['admin']);
require_once __DIR__.'/tests/unit.php';
$results=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();$results=bloodbridge_unit_tests();
 $pdo=db();
 $queries=[
  'Schema version 110'=>['sql'=>"SELECT COUNT(*) FROM app_settings WHERE setting_key='schema_version' AND setting_value='110'",'expected'=>1],
  'Active administrator account exists'=>['sql'=>"SELECT COUNT(*) FROM users WHERE role='admin' AND account_status='Active'",'minimum'=>1],
  'Personal donor-enabled account exists'=>['sql'=>"SELECT COUNT(*) FROM users WHERE role IN ('donor','seeker') AND donor_enabled=1 AND account_status='Active'",'minimum'=>1],
  'Verified hospital exists'=>['sql'=>"SELECT COUNT(*) FROM hospitals WHERE status='Verified'",'minimum'=>1],
  'Hospital staff has a valid hospital'=>['sql'=>"SELECT COUNT(*) FROM users u LEFT JOIN hospitals h ON h.id=u.hospital_id WHERE u.role='hospital' AND h.id IS NULL",'expected'=>0],
  'Inventory covers verified hospitals'=>['sql'=>"SELECT COUNT(*) FROM hospitals h CROSS JOIN (SELECT 'A+' blood_group UNION ALL SELECT 'A-' UNION ALL SELECT 'B+' UNION ALL SELECT 'B-' UNION ALL SELECT 'AB+' UNION ALL SELECT 'AB-' UNION ALL SELECT 'O+' UNION ALL SELECT 'O-') g LEFT JOIN blood_inventory bi ON bi.hospital_id=h.id AND bi.blood_group=g.blood_group WHERE h.status='Verified' AND bi.id IS NULL",'expected'=>0],
  'Stock nonnegative and reservation bounds'=>['sql'=>"SELECT COUNT(*) FROM blood_inventory WHERE units<0 OR reserved_units<0 OR reserved_units>units",'expected'=>0],
  'Reserved stock equals live commitments'=>['sql'=>"SELECT COUNT(*) FROM blood_inventory bi WHERE reserved_units<>(SELECT COALESCE(SUM(br.units),0) FROM blood_reservations br WHERE br.hospital_id=bi.hospital_id AND br.blood_group=bi.blood_group AND br.status='Approved')+(SELECT COALESCE(SUM(r.units),0) FROM blood_requests r WHERE r.hospital_id=bi.hospital_id AND r.blood_group=bi.blood_group AND r.source_type='Blood Bank' AND r.status='Accepted')",'expected'=>0],
  'User blood groups are valid'=>['sql'=>"SELECT COUNT(*) FROM users WHERE blood_group IS NOT NULL AND blood_group NOT IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')",'expected'=>0],
  'Request blood groups are valid'=>['sql'=>"SELECT COUNT(*) FROM blood_requests WHERE blood_group NOT IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')",'expected'=>0],
  'Request statuses are valid'=>['sql'=>"SELECT COUNT(*) FROM blood_requests WHERE status NOT IN ('Pending','Accepted','Rejected','Completed','Cancelled','Expired')",'expected'=>0],
  'Donor request units stay at one'=>['sql'=>"SELECT COUNT(*) FROM blood_requests WHERE source_type='Donor' AND units<>1",'expected'=>0],
  'Selected donors match request group'=>['sql'=>"SELECT COUNT(*) FROM blood_requests r JOIN users u ON u.id=r.accepted_by WHERE r.source_type='Donor' AND u.blood_group<>r.blood_group",'expected'=>0],
  'Request responses have valid owners'=>['sql'=>"SELECT COUNT(*) FROM request_responses rr LEFT JOIN blood_requests r ON r.id=rr.request_id LEFT JOIN users u ON u.id=rr.responder_id WHERE r.id IS NULL OR u.id IS NULL",'expected'=>0],
  'No duplicate donor responses'=>['sql'=>"SELECT COUNT(*) FROM (SELECT request_id,responder_id FROM request_responses GROUP BY request_id,responder_id HAVING COUNT(*)>1) duplicates",'expected'=>0],
  'No duplicate direct donation histories'=>['sql'=>"SELECT COUNT(*) FROM (SELECT direct_donation_id FROM donation_history WHERE direct_donation_id IS NOT NULL GROUP BY direct_donation_id HAVING COUNT(*)>1) d",'expected'=>0],
  'No duplicate request donation histories'=>['sql'=>"SELECT COUNT(*) FROM (SELECT request_id FROM donation_history WHERE request_id IS NOT NULL GROUP BY request_id HAVING COUNT(*)>1) d",'expected'=>0],
  'Completed direct donations have one history'=>['sql'=>"SELECT COUNT(*) FROM direct_donations d WHERE d.status='Completed' AND (SELECT COUNT(*) FROM donation_history dh WHERE dh.direct_donation_id=d.id)<>1",'expected'=>0],
  'No overdue active requests'=>['sql'=>"SELECT COUNT(*) FROM blood_requests WHERE status IN ('Pending','Accepted') AND donor_reported_at IS NULL AND expires_at<=NOW()",'expected'=>0],
  'No overdue active reservations'=>['sql'=>"SELECT COUNT(*) FROM blood_reservations WHERE status IN ('Pending','Approved') AND expires_at<=NOW()",'expected'=>0],
  'Notifications reference valid users'=>['sql'=>"SELECT COUNT(*) FROM notifications n LEFT JOIN users u ON u.id=n.user_id WHERE u.id IS NULL",'expected'=>0],
  'Location consent always has coordinates'=>['sql'=>"SELECT COUNT(*) FROM users WHERE location_consent=1 AND (latitude IS NULL OR longitude IS NULL OR location_updated_at IS NULL)",'expected'=>0],
  'Saved coordinates stay in Bangladesh bounds'=>['sql'=>"SELECT COUNT(*) FROM users WHERE latitude IS NOT NULL AND (latitude<20.5 OR latitude>26.7 OR longitude<88 OR longitude>92.7)",'expected'=>0],
  'Private document sizes respect limit'=>['sql'=>"SELECT COUNT(*) FROM private_documents WHERE size_bytes<=0 OR size_bytes>2097152",'expected'=>0],
  'Club members reference valid records'=>['sql'=>"SELECT COUNT(*) FROM club_members m LEFT JOIN clubs c ON c.id=m.club_id LEFT JOIN users u ON u.id=m.user_id WHERE c.id IS NULL OR u.id IS NULL",'expected'=>0],
  'Campaign registrations reference valid records'=>['sql'=>"SELECT COUNT(*) FROM campaign_registrations r LEFT JOIN campaigns c ON c.id=r.campaign_id LEFT JOIN users u ON u.id=r.user_id WHERE c.id IS NULL OR u.id IS NULL",'expected'=>0],
 ];
 foreach($queries as $name=>$check) {
    try{$count=(int)$pdo->query($check['sql'])->fetchColumn();$passed=isset($check['minimum'])?$count>=(int)$check['minimum']:$count===(int)$check['expected'];$expectation=isset($check['minimum'])?'at least '.$check['minimum']:(string)$check['expected'];$results[]=['test'=>$name,'status'=>$passed?'PASS':'FAIL','detail'=>'Observed '.$count.'; expected '.$expectation];}
    catch(Throwable $e){$results[]=['test'=>$name,'status'=>'FAIL','detail'=>'Database check failed; inspect server logs.'];error_log($e->getMessage());}
 }
 $runId=date('Ymd-His').'-'.bin2hex(random_bytes(3));
 foreach($results as $result) audit_log($pdo,(int)current_user()['id'],'Test '.$result['status'],'TestResult',null,json_encode(['run_id'=>$runId,'test'=>$result['test'],'status'=>$result['status'],'detail'=>$result['detail']??''],JSON_UNESCAPED_SLASHES));
 audit_log($pdo,(int)current_user()['id'],'Run persisted tests','System',null,json_encode(['run_id'=>$runId,'checks'=>count($results),'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS'))]));
}
$pageTitle='Testing centre';require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Testing centre</h1><p>Run unit and read-only database checks against this installation, then save each result in the audit database.</p></div></section>
<section class="content-card"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-primary">Run &amp; Save Test Results</button></form><p>Application records are not rewritten. Each PASS/FAIL result is stored with a test run ID. Use a separate test database for workflow mutation tests.</p>
<?php if($results): $passed=count(array_filter($results,fn($r)=>$r['status']==='PASS')); ?><h2><?= $passed ?>/<?= count($results) ?> passed</h2><div class="table-wrap"><table><thead><tr><th>Check</th><th>Actual result</th><th>Detail</th></tr></thead><tbody><?php foreach($results as $r): ?><tr><td><?= e($r['test']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['detail']??'') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
