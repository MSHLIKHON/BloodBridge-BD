<?php
declare(strict_types=1);
require_once __DIR__.'/workflow.php';
require_once __DIR__.'/../includes/matching.php';
require_once __DIR__.'/../includes/geo.php';

function bloodbridge_matching_tests():array
{
    $pdo=null;bloodbridge_workflow_simulation($pdo);
    $pdo->exec("ALTER TABLE users ADD COLUMN latitude REAL;ALTER TABLE users ADD COLUMN longitude REAL;ALTER TABLE users ADD COLUMN location_consent INTEGER DEFAULT 0;ALTER TABLE users ADD COLUMN location_updated_at TEXT;ALTER TABLE users ADD COLUMN last_received_date TEXT;
    ALTER TABLE blood_requests ADD COLUMN latitude REAL;ALTER TABLE blood_requests ADD COLUMN longitude REAL;ALTER TABLE blood_requests ADD COLUMN donor_confirmed_at TEXT;ALTER TABLE blood_requests ADD COLUMN donor_reported_at TEXT;ALTER TABLE blood_requests ADD COLUMN outcome_note TEXT;ALTER TABLE blood_requests ADD COLUMN patient_relation TEXT DEFAULT 'Self';ALTER TABLE blood_requests ADD COLUMN completed_at TEXT;
    CREATE TABLE request_responses (request_id INTEGER,responder_id INTEGER,response TEXT,responded_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(request_id,responder_id));
    CREATE TABLE request_events (id INTEGER PRIMARY KEY AUTOINCREMENT,request_id INTEGER,actor_id INTEGER,event TEXT,details TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    UPDATE users SET screening_status='Eligible',is_available=1,last_donation_date=NULL,verified_by_hospital=1;");
    $actor=fn($id)=>bb_one($pdo,'SELECT * FROM users WHERE id=?',[$id]);
    $future=date('Y-m-d H:i:s',time()+86400);
    foreach([100,101,102,103,104] as $id) bb_exec($pdo,"INSERT INTO blood_requests (id,seeker_id,source_type,blood_group,status,units,prescription_status,expires_at,latitude,longitude) VALUES (?,3,'Donor','B+','Pending',1,'Reviewed',?,23.81,90.41)",[$id,$future]);
    $results=[];
    $check=function(string $name,callable $test)use(&$results):void{try{$results[]=['test'=>$name,'status'=>$test()===true?'PASS':'FAIL','environment'=>'SQLite sequential simulation'];}catch(Throwable $e){$results[]=['test'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];}};
    $denied=static function(callable $test):bool{try{$test();return false;}catch(DomainException $e){return true;}};
    $check('Admin cannot express interest',fn()=>$denied(fn()=>request_match_action($pdo,$actor(1),100,'interest')));
    $check('Hospital cannot act as personal donor',fn()=>$denied(fn()=>request_match_action($pdo,$actor(4),100,'interest')));
    $check('Owner cannot offer to own request',fn()=>$denied(fn()=>request_match_action($pdo,$actor(3),100,'interest')));
    $check('Two interested donors leave request pending',function()use($pdo,$actor){request_match_action($pdo,$actor(2),100,'interest');request_match_action($pdo,$actor(6),100,'interest');return bb_one($pdo,'SELECT status FROM blood_requests WHERE id=100')['status']==='Pending' && (int)$pdo->query('SELECT COUNT(*) FROM request_responses WHERE request_id=100')->fetchColumn()===2;});
    $check('Repeated interest does not duplicate notification',function()use($pdo,$actor){$before=$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();request_match_action($pdo,$actor(2),100,'interest');return $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===$before;});
    $check('Another seeker cannot select donor',fn()=>$denied(fn()=>request_match_action($pdo,$actor(7),100,'select',2)));
    $check('Owner selection marks other donor not selected',function()use($pdo,$actor){request_match_action($pdo,$actor(3),100,'select',2);return bb_one($pdo,'SELECT response FROM request_responses WHERE request_id=100 AND responder_id=6')['response']==='Not selected' && (int)bb_one($pdo,'SELECT accepted_by FROM blood_requests WHERE id=100')['accepted_by']===2;});
    $check('Second selection cannot overwrite selected donor',fn()=>$denied(fn()=>request_match_action($pdo,$actor(3),100,'select',6)));
    $check('Selected donor cannot commit to another request',fn()=>$denied(fn()=>request_match_action($pdo,$actor(2),101,'interest')));
    $check('Receipt blocked before donor reports donation',fn()=>$denied(fn()=>confirm_donor_receipt($pdo,$actor(3),100)));
    $check('Unselected donor cannot confirm',fn()=>$denied(fn()=>request_match_action($pdo,$actor(6),100,'confirm')));
    $check('Donation report blocked before confirmation',fn()=>$denied(fn()=>request_match_action($pdo,$actor(2),100,'donated')));
    $check('Donor confirms and reports donation',function()use($pdo,$actor){request_match_action($pdo,$actor(2),100,'confirm');request_match_action($pdo,$actor(2),100,'donated');return !empty(bb_one($pdo,'SELECT donor_reported_at FROM blood_requests WHERE id=100')['donor_reported_at']);});
    $check('Donated request cannot silently expire',function()use($pdo){bb_exec($pdo,"UPDATE blood_requests SET expires_at='2000-01-01' WHERE id=100");return !expire_record($pdo,'blood_requests',100);});
    $check('Disputed receipt retains donor commitment',function()use($pdo,$actor){request_match_action($pdo,$actor(3),100,'not_received',0,'Awaiting hospital clarification');$r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=100');return $r['outcome']==='Disputed' && $r['status']==='Accepted';});
    $check('Admin cannot mark personal donation received',fn()=>$denied(fn()=>confirm_donor_receipt($pdo,$actor(1),100)));
    $check('Owner receipt records one donation and received date',function()use($pdo,$actor){confirm_donor_receipt($pdo,$actor(3),100);return (int)$pdo->query('SELECT COUNT(*) FROM donation_history WHERE request_id=100')->fetchColumn()===1 && bb_one($pdo,'SELECT last_received_date FROM users WHERE id=3')['last_received_date']===date('Y-m-d');});
    $check('Duplicate receipt blocked',fn()=>$denied(fn()=>confirm_donor_receipt($pdo,$actor(3),100)));
    $check('Decline does not reject whole request',function()use($pdo,$actor){request_match_action($pdo,$actor(6),101,'decline');return bb_one($pdo,'SELECT status FROM blood_requests WHERE id=101')['status']==='Pending';});
    $check('Withdrawal permits later donor selection',function()use($pdo,$actor){request_match_action($pdo,$actor(6),101,'interest');request_match_action($pdo,$actor(3),101,'select',6);request_match_action($pdo,$actor(6),101,'withdraw');$r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=101');return $r['status']==='Pending' && $r['accepted_by']===null;});
    $check('Not received before donation closes with no history',function()use($pdo,$actor){request_match_action($pdo,$actor(3),101,'not_received',0,'No longer required');return bb_one($pdo,'SELECT outcome FROM blood_requests WHERE id=101')['outcome']==='Not received' && (int)$pdo->query('SELECT COUNT(*) FROM donation_history WHERE request_id=101')->fetchColumn()===0;});
    $check('Coordinate pair required',fn()=>$denied(fn()=>bangladesh_point('23.8','')));
    $check('Outside Bangladesh bounds rejected',fn()=>$denied(fn()=>bangladesh_point(51.5,-0.1)));
    $check('Same-point distance is zero',fn()=>distance_km(23.81,90.41,23.81,90.41)===0.0);
    $check('Nearby point is inside one kilometre',fn()=>distance_km(23.81,90.41,23.814,90.41)<1);
    $check('Distant point is outside one kilometre',fn()=>distance_km(23.81,90.41,23.83,90.41)>1);
    bb_exec($pdo,"UPDATE users SET location_consent=1,location_updated_at=?,latitude=23.8141234,longitude=90.4112345 WHERE id=6",[date('Y-m-d H:i:s')]);
    $check('Only eligible consenting nearby donor returned',fn()=>count(nearby_candidates($pdo,$actor(3),102)['donors'])===1);
    $check('Nearby output hides exact coordinate and identity',function()use($pdo,$actor){$d=nearby_candidates($pdo,$actor(3),102)['donors'][0];return $d['latitude']===23.81 && !isset($d['phone']) && !isset($d['full_name']) && !isset($d['distance']);});
    $check('Another seeker cannot query request origin',fn()=>$denied(fn()=>nearby_candidates($pdo,$actor(7),102)));
    $check('Unreviewed request cannot search donors',function()use($pdo,$actor,$denied){bb_exec($pdo,"UPDATE blood_requests SET prescription_status='Pending' WHERE id=103");return $denied(fn()=>nearby_candidates($pdo,$actor(3),103));});
    $check('Revocation removes nearby visibility',function()use($pdo,$actor){bb_exec($pdo,'UPDATE users SET location_consent=0 WHERE id=6');return nearby_candidates($pdo,$actor(3),102)['donors']===[];});
    $check('Stale location excluded',function()use($pdo,$actor){bb_exec($pdo,"UPDATE users SET location_consent=1,location_updated_at='2000-01-01' WHERE id=6");return nearby_candidates($pdo,$actor(3),102)['donors']===[];});
    $check('Cross-division address rejected server-side',fn()=>$denied(fn()=>address_input(['division'=>'Dhaka','district'=>'Cumilla','upazila'=>'Savar'])));
    $check('Address formats consistently',fn()=>address_input(['division'=>'Dhaka','district'=>'Dhaka','upazila'=>'Savar'])==='Savar, Dhaka, Dhaka Division');
    $check('Optional district search does not require area',fn()=>address_input(['division'=>'Dhaka','district'=>'Dhaka'],true)==='Dhaka, Dhaka Division');
    $check('Donor cannot correct an undisputed report',function()use($pdo,$actor,$denied){request_match_action($pdo,$actor(6),104,'interest');request_match_action($pdo,$actor(3),104,'select',6);request_match_action($pdo,$actor(6),104,'confirm');request_match_action($pdo,$actor(6),104,'donated');return $denied(fn()=>request_match_action($pdo,$actor(6),104,'correct_report',0,'Mistake'));});
    $check('Seeker cannot impersonate donor correction',function()use($pdo,$actor,$denied){request_match_action($pdo,$actor(3),104,'not_received',0,'No donation took place');return $denied(fn()=>request_match_action($pdo,$actor(3),104,'correct_report',0,'Mistake'));});
    $check('Both sides agree no donation: close without history',function()use($pdo,$actor){request_match_action($pdo,$actor(6),104,'correct_report',0,'Clicked report accidentally; did not donate');$r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=104');return $r['status']==='Cancelled' && $r['outcome']==='Not received' && (int)$pdo->query('SELECT COUNT(*) FROM donation_history WHERE request_id=104')->fetchColumn()===0;});
    return $results;
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$r=bloodbridge_matching_tests();echo json_encode($r,JSON_PRETTY_PRINT).PHP_EOL;exit(count(array_filter($r,fn($t)=>$t['status']==='FAIL'))?1:0);}
