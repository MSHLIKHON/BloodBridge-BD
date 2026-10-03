<?php
/** Seed linked fictional records for a complete local demonstration. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php bin/seed_demo_scenarios.php --apply\n");
    exit(2);
}

ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../config/app.php';

const DEMO_SEED_KEY = 'demo_scenarios_seed_version';
const DEMO_SEED_VERSION = '2026-10-03-v1';
const DEMO_TAG = '[DEMO-SCENARIO]';

function demo_one_id(PDO $pdo, string $sql, array $params = []): int
{
    $row = bb_one($pdo, $sql, $params);
    if (!$row) {
        throw new RuntimeException('Required demo record is missing.');
    }
    return (int) array_values($row)[0];
}

function demo_user(PDO $pdo, array $record): int
{
    $existing = bb_one($pdo, 'SELECT id FROM users WHERE email=?', [$record['email']]);
    if ($existing) return (int) $existing['id'];
    bb_exec($pdo,
        'INSERT INTO users (hospital_id,full_name,email,password_hash,role,blood_group,location,phone,account_status,email_verified,phone_verified,last_donation_date,total_donations,is_available,screening_status,verified_by_hospital,screened_by_user_id,screened_at,screening_notes,profile_updated_at,donor_enabled,latitude,longitude,location_consent,location_updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),?,?,?,1,NOW())',
        [
            $record['hospital_id'] ?? null, $record['full_name'], $record['email'],
            password_hash($record['password'] ?? 'donor123', PASSWORD_DEFAULT), $record['role'],
            $record['blood_group'] ?? null, $record['location'], $record['phone'],
            $record['account_status'] ?? 'Active', 1, 1, $record['last_donation_date'] ?? null,
            $record['total_donations'] ?? 0, $record['is_available'] ?? 1,
            $record['screening_status'] ?? 'Eligible', $record['verified_by_hospital'] ?? 1,
            $record['screened_by_user_id'] ?? null, DEMO_TAG . ' Fictional profile for presentation.',
            $record['donor_enabled'] ?? 1, $record['latitude'] ?? null,
            $record['longitude'] ?? null,
        ]
    );
    return (int) $pdo->lastInsertId();
}

function demo_request(PDO $pdo, array $record): int
{
    bb_exec($pdo,
        'INSERT INTO blood_requests (seeker_id,hospital_id,blood_group,location,units,urgency,source_type,note,status,accepted_by,completed_at,created_at,expires_at,patient_relation,prescription_status,prescription_reviewer,prescription_note,review_hospital_id,latitude,longitude,donor_confirmed_at,donor_reported_at,outcome,outcome_note) VALUES (?,?,?,?,?,?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? DAY),?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $record['seeker_id'], $record['hospital_id'] ?? null, $record['blood_group'],
            $record['location'], $record['units'] ?? 1, $record['urgency'] ?? 'Urgent',
            $record['source_type'], DEMO_TAG . ' ' . $record['note'], $record['status'],
            $record['accepted_by'] ?? null, $record['completed_at'] ?? null,
            $record['age_days'] ?? 1, $record['expires_at'], $record['patient_relation'] ?? 'Family',
            $record['prescription_status'] ?? 'Reviewed', $record['prescription_reviewer'] ?? null,
            DEMO_TAG . ' Fictional prescription review.', $record['review_hospital_id'] ?? null,
            $record['latitude'] ?? 23.7465, $record['longitude'] ?? 90.3760,
            $record['donor_confirmed_at'] ?? null, $record['donor_reported_at'] ?? null,
            $record['outcome'] ?? 'Awaiting', $record['outcome_note'] ?? null,
        ]
    );
    return (int) $pdo->lastInsertId();
}

function demo_document(PDO $pdo, int $ownerId, ?int $requestId, string $kind, string $name, string $status, ?int $reviewer): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    bb_exec($pdo,
        'INSERT INTO private_documents (owner_id,request_id,kind,filename,mime_type,size_bytes,file_data,test_name,test_value,test_unit,test_date,lab_name,status,reviewed_by,reviewed_at,review_note) VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?,?)',
        [$ownerId,$requestId,$kind,$name,'image/png',strlen((string)$png),$png,$kind==='Health'?'Hemoglobin':null,$kind==='Health'?'13.8':null,$kind==='Health'?'g/dL':null,'Fictional Demo Lab',$status,$reviewer,$reviewer?date('Y-m-d H:i:s'):null,DEMO_TAG.' Fictional file; not a real clinical document.']
    );
}

$pdo = db();
$current = bb_one($pdo, 'SELECT setting_value FROM app_settings WHERE setting_key=?', [DEMO_SEED_KEY]);
if (($current['setting_value'] ?? '') === DEMO_SEED_VERSION) {
    echo "Demo scenarios are already installed (" . DEMO_SEED_VERSION . ").\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $admin = demo_one_id($pdo, "SELECT id FROM users WHERE email='admin@bloodbridge.test'");
    $seeker = demo_one_id($pdo, "SELECT id FROM users WHERE email='seeker@bloodbridge.test'");
    $donor = demo_one_id($pdo, "SELECT id FROM users WHERE email='donor@bloodbridge.test'");
    $staff = demo_one_id($pdo, "SELECT id FROM users WHERE email='hospital@bloodbridge.test'");
    $hospital = demo_one_id($pdo, "SELECT id FROM hospitals WHERE status='Verified' ORDER BY id LIMIT 1");
    $hospital2 = demo_one_id($pdo, "SELECT id FROM hospitals WHERE status='Verified' AND id<>? ORDER BY id LIMIT 1", [$hospital]);

    $donors = [];
    $donorProfiles = [
        ['Ayesha Demo','demo.donor1@bloodbridge.test','01790001001','B+',23.7468,90.3753,'Dhanmondi, Dhaka, Dhaka Division'],
        ['Farhan Demo','demo.donor2@bloodbridge.test','01790001002','B+',23.7520,90.3810,'Dhanmondi, Dhaka, Dhaka Division'],
        ['Nusrat Demo','demo.donor3@bloodbridge.test','01790001003','B+',23.7380,90.3680,'Dhanmondi, Dhaka, Dhaka Division'],
        ['Sabbir Demo','demo.donor4@bloodbridge.test','01790001004','A+',23.8103,90.4125,'Badda, Dhaka, Dhaka Division'],
        ['Mim Demo','demo.donor5@bloodbridge.test','01790001005','O+',23.8223,90.3654,'Mirpur, Dhaka, Dhaka Division'],
    ];
    foreach ($donorProfiles as [$name,$email,$phone,$group,$lat,$lng,$location]) {
        $donors[] = demo_user($pdo, ['full_name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>'donor','blood_group'=>$group,'location'=>$location,'latitude'=>$lat,'longitude'=>$lng,'screened_by_user_id'=>$staff]);
    }

    $applicants = [];
    foreach ([
        ['Pending Staff Demo','demo.staff.pending@bloodbridge.test','01790002001','Pending'],
        ['Rejected Staff Demo','demo.staff.rejected@bloodbridge.test','01790002002','Rejected'],
        ['Approved Staff Demo','demo.staff.approved@bloodbridge.test','01790002003','Active'],
    ] as [$name,$email,$phone,$accountStatus]) {
        $applicants[] = demo_user($pdo, ['full_name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>'hospital','blood_group'=>null,'location'=>'Shahbag, Dhaka, Dhaka Division','hospital_id'=>$hospital,'account_status'=>$accountStatus,'donor_enabled'=>0,'screening_status'=>'Pending','verified_by_hospital'=>0,'latitude'=>null,'longitude'=>null]);
    }
    foreach ([[$applicants[0],'DEMO-PENDING','Pending',null],[$applicants[1],'DEMO-REJECTED','Rejected',$admin],[$applicants[2],'DEMO-APPROVED','Approved',$admin]] as [$uid,$employee,$status,$reviewer]) {
        bb_exec($pdo,'INSERT INTO hospital_staff_applications (user_id,hospital_id,employee_id,designation,department,status,reviewed_by,reviewed_at,review_note) VALUES (?,?,?,?,?,?,?,?,?)',[$uid,$hospital,$employee,'Demo Blood Bank Officer','Transfusion Medicine',$status,$reviewer,$reviewer?date('Y-m-d H:i:s'):null,DEMO_TAG.' Fictional application.']);
    }

    foreach ([
        ['Demo Community Hospital','DEMO-HOSP-003','Pending'],
        ['Demo City Blood Centre','DEMO-HOSP-004','Verified'],
        ['Demo Suspended Clinic','DEMO-HOSP-005','Suspended'],
    ] as [$name,$registration,$status]) {
        bb_exec($pdo,'INSERT INTO hospitals (name,registration_number,location,phone,status,verification_reference,verification_note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?,?,?,NOW())',[$name,$registration,'Dhaka, Dhaka Division','029000'.substr($registration,-3),$status,DEMO_TAG.' REF',DEMO_TAG.' Fictional hospital for admin demonstration.',$admin]);
    }

    $future = date('Y-m-d H:i:s', strtotime('+5 days'));
    $past = date('Y-m-d H:i:s', strtotime('-2 days'));
    $completedAt = date('Y-m-d H:i:s', strtotime('-3 days'));
    $requestIds = [];
    $requestIds[] = demo_request($pdo,['seeker_id'=>$seeker,'blood_group'=>'B+','location'=>'Dhanmondi, Dhaka, Dhaka Division','source_type'=>'Donor','status'=>'Pending','note'=>'Pending donor request with several available matches.','expires_at'=>$future,'prescription_reviewer'=>$admin,'review_hospital_id'=>$hospital]);
    $requestIds[] = demo_request($pdo,['seeker_id'=>$seeker,'blood_group'=>'B+','location'=>'Dhanmondi, Dhaka, Dhaka Division','source_type'=>'Donor','status'=>'Accepted','accepted_by'=>$donors[0],'note'=>'Selected donor is waiting to confirm.','expires_at'=>$future,'prescription_reviewer'=>$admin,'review_hospital_id'=>$hospital]);
    $requestIds[] = demo_request($pdo,['seeker_id'=>$seeker,'blood_group'=>'B+','location'=>'Dhanmondi, Dhaka, Dhaka Division','source_type'=>'Donor','status'=>'Accepted','accepted_by'=>$donors[1],'note'=>'Donor reported donation; seeker can confirm receipt.','expires_at'=>$future,'prescription_reviewer'=>$admin,'review_hospital_id'=>$hospital,'donor_confirmed_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'donor_reported_at'=>date('Y-m-d H:i:s',strtotime('-2 hours'))]);
    $requestIds[] = demo_request($pdo,['seeker_id'=>$seeker,'hospital_id'=>$hospital,'blood_group'=>'A+','location'=>'Shahbag, Dhaka, Dhaka Division','units'=>2,'source_type'=>'Blood Bank','status'=>'Accepted','accepted_by'=>$staff,'note'=>'Accepted hospital request with reserved stock.','expires_at'=>$future,'prescription_reviewer'=>$admin,'review_hospital_id'=>$hospital]);
    $requestIds[] = demo_request($pdo,['seeker_id'=>$seeker,'blood_group'=>'B+','location'=>'Dhanmondi, Dhaka, Dhaka Division','source_type'=>'Donor','status'=>'Completed','accepted_by'=>$donor,'note'=>'Completed donor request for history demonstration.','expires_at'=>$past,'completed_at'=>$completedAt,'prescription_reviewer'=>$admin,'review_hospital_id'=>$hospital,'donor_confirmed_at'=>$completedAt,'donor_reported_at'=>$completedAt,'outcome'=>'Received']);

    foreach ($requestIds as $index => $requestId) {
        demo_document($pdo,$seeker,$requestId,'Prescription','fictional-prescription-'.($index+1).'.png','Reviewed',$admin);
        bb_exec($pdo,'INSERT INTO request_events (request_id,actor_id,event,details) VALUES (?,?,?,?)',[$requestId,$seeker,'Demo request created',DEMO_TAG.' Scenario '.($index+1)]);
    }
    foreach ([[$requestIds[0],$donor,'Interested'],[$requestIds[0],$donors[2],'Interested'],[$requestIds[1],$donors[0],'Selected'],[$requestIds[2],$donors[1],'Accepted'],[$requestIds[4],$donor,'Accepted']] as [$rid,$uid,$response]) {
        bb_exec($pdo,'INSERT INTO request_responses (request_id,responder_id,response) VALUES (?,?,?)',[$rid,$uid,$response]);
    }

    $reservationRows = [
        [$hospital,'B+',1,'Pending',null,null,$future,'Awaiting hospital review'],
        [$hospital,'A+',2,'Approved',$staff,'BB-DEMO-4201',$future,'Approved and ready for collection'],
        [$hospital,'O+',1,'Rejected',$staff,null,$past,'Rejected example'],
        [$hospital,'AB+',1,'Collected',$staff,'BB-DEMO-4202',$past,'Collected example'],
        [$hospital,'A-',1,'Expired',$staff,'BB-DEMO-4203',$past,'Expired example'],
    ];
    foreach ($reservationRows as [$hid,$group,$units,$status,$reviewer,$code,$expires,$note]) {
        bb_exec($pdo,'INSERT INTO blood_reservations (seeker_id,hospital_id,blood_group,units,status,reviewed_by,reviewed_at,collection_code,note,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$seeker,$hid,$group,$units,$status,$reviewer,$reviewer?date('Y-m-d H:i:s'):null,$code,DEMO_TAG.' '.$note,$expires]);
    }

    $clubs = [];
    foreach ([
        ['Blood Heroes Club','Demo University One','Approved'],['Life Savers Society','Demo University Two','Approved'],
        ['Red Drop Club','Demo University Three','Pending'],['Campus Care Club','Demo University Four','Rejected'],
        ['Unity Blood Club','Demo University Five','Suspended'],
    ] as [$name,$university,$status]) {
        bb_exec($pdo,'INSERT INTO clubs (coordinator_id,name,university,location,reference,status,review_note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?,?,?,NOW())',[$donor,$name,$university,'Dhaka, Dhaka Division',DEMO_TAG.' REF-'.$name,$status,DEMO_TAG.' Fictional club status.',$admin]);
        $clubs[]=(int)$pdo->lastInsertId();
    }
    foreach ($clubs as $index=>$clubId) {
        bb_exec($pdo,'INSERT INTO club_members (club_id,user_id,status) VALUES (?,?,?)',[$clubId,$donor,$index<2?'Approved':($index===2?'Pending':'Rejected')]);
    }
    foreach ([[$clubs[0],$requestIds[0]],[$clubs[0],$requestIds[1]],[$clubs[1],$requestIds[2]],[$clubs[1],$requestIds[3]],[$clubs[0],$requestIds[4]]] as [$clubId,$requestId]) {
        bb_exec($pdo,'INSERT INTO club_request_shares (club_id,request_id) VALUES (?,?)',[$clubId,$requestId]);
    }

    $campaigns=[];
    foreach ([
        [$clubs[0],$hospital,'Autumn Donation Drive','+10 days','Scheduled'],
        [$clubs[0],$hospital,'Emergency Donor Meetup','+20 days','Scheduled'],
        [$clubs[1],$hospital2,'Campus Blood Day','-10 days','Completed'],
        [$clubs[1],$hospital2,'Community Awareness Camp','-20 days','Completed'],
        [$clubs[0],$hospital,'Cancelled Demo Campaign','+30 days','Cancelled'],
    ] as [$clubId,$hid,$title,$when,$status]) {
        bb_exec($pdo,'INSERT INTO campaigns (club_id,hospital_id,title,location,event_at,status) VALUES (?,?,?,?,?,?)',[$clubId,$hid,DEMO_TAG.' '.$title,'Dhaka, Dhaka Division',date('Y-m-d H:i:s',strtotime($when)),$status]);
        $campaigns[]=(int)$pdo->lastInsertId();
    }
    foreach (array_slice($campaigns,0,4) as $campaignId) bb_exec($pdo,'INSERT INTO campaign_registrations (campaign_id,user_id) VALUES (?,?)',[$campaignId,$donor]);

    $directRows = [
        [$donors[2],$hospital,'Pending',date('Y-m-d'),null,null],
        [$donors[3],$hospital,'Screened',date('Y-m-d'),$staff,date('Y-m-d H:i:s')],
        [$donor,$hospital,'Completed',date('Y-m-d',strtotime('-130 days')),$staff,date('Y-m-d H:i:s',strtotime('-130 days'))],
        [$donors[4],$hospital,'Rejected',date('Y-m-d'),$staff,date('Y-m-d H:i:s')],
        [$donors[0],$hospital2,'Cancelled',date('Y-m-d',strtotime('+2 days')),null,null],
    ];
    $directIds=[];
    foreach ($directRows as [$uid,$hid,$status,$appointment,$screenedBy,$screenedAt]) {
        bb_exec($pdo,'INSERT INTO direct_donations (donor_id,hospital_id,club_id,blood_group,appointment_date,status,screened_by,screened_at,screening_note,completed_at) SELECT ?,?,?,blood_group,?,?,?,?,?,? FROM users WHERE id=?',[$uid,$hid,$clubs[0],$appointment,$status,$screenedBy,$screenedAt,DEMO_TAG.' Fictional direct donation.', $status==='Completed'?$screenedAt:null,$uid]);
        $directIds[]=(int)$pdo->lastInsertId();
    }

    bb_exec($pdo,'INSERT INTO donation_history (request_id,donor_id,hospital_id,verified_by_user_id,blood_group,units,donation_date,notes) VALUES (?,?,?,?,?,?,?,?)',[$requestIds[4],$donor,null,$admin,'B+',1,date('Y-m-d',strtotime('-150 days')),DEMO_TAG.' Completed request history.']);
    bb_exec($pdo,'INSERT INTO donation_history (direct_donation_id,donor_id,hospital_id,verified_by_user_id,blood_group,units,donation_date,notes) VALUES (?,?,?,?,?,?,?,?)',[$directIds[2],$donor,$hospital,$staff,'B+',1,date('Y-m-d',strtotime('-130 days')),DEMO_TAG.' Direct donation history.']);
    foreach ([[$donor,'B+',-250],[$donor,'B+',-220],[$donor,'B+',-190]] as [$uid,$group,$days]) {
        bb_exec($pdo,'INSERT INTO donation_history (donor_id,hospital_id,verified_by_user_id,blood_group,units,donation_date,notes) VALUES (?,?,?,?,1,?,?)',[$uid,$hospital,$staff,$group,date('Y-m-d',strtotime($days.' days')),DEMO_TAG.' Historical donation example.']);
    }
    bb_exec($pdo,'UPDATE users SET total_donations=(SELECT COUNT(*) FROM donation_history WHERE donor_id=?),last_donation_date=(SELECT MAX(donation_date) FROM donation_history WHERE donor_id=?) WHERE id=?',[$donor,$donor,$donor]);

    foreach ([[$donor,$hospital],[$donors[0],$hospital],[$donors[1],$hospital],[$donors[2],$hospital],[$donors[3],$hospital2]] as [$uid,$hid]) {
        bb_exec($pdo,'INSERT IGNORE INTO health_access (donor_id,hospital_id) VALUES (?,?)',[$uid,$hid]);
    }
    for ($i=1;$i<=5;$i++) demo_document($pdo,$donor,null,'Health','fictional-health-report-'.$i.'.png',$i<4?'Reviewed':($i===4?'Self-reported':'Needs correction'),$i<4?$staff:null);

    foreach ([$seeker,$donor,$staff,$admin] as $uid) {
        for ($i=1;$i<=5;$i++) {
            bb_exec($pdo,'INSERT INTO notifications (user_id,type,title,message,link,read_at,created_at) VALUES (?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? HOUR))',[$uid,$i%2?'Demo':'Info','Demo notification '.$i,DEMO_TAG.' Fictional notification for feature presentation.','dashboard.php',$i>3?date('Y-m-d H:i:s'):null,$i]);
        }
    }

    $inventoryId=demo_one_id($pdo,'SELECT id FROM blood_inventory WHERE hospital_id=? AND blood_group=?',[$hospital,'B+']);
    $balance=(int)bb_one($pdo,'SELECT units FROM blood_inventory WHERE id=?',[$inventoryId])['units'];
    foreach ([['Donation Received',1],['Patient Supplied',1],['Expired',-1],['Correction',1],['Reservation Collected',-1]] as $index=>[$type,$change]) {
        $balance=max(0,$balance+$change);
        bb_exec($pdo,'INSERT INTO inventory_transactions (hospital_id,inventory_id,staff_user_id,transaction_type,unit_change,balance_after,note) VALUES (?,?,?,?,?,?,?)',[$hospital,$inventoryId,$staff,$type,$change,$balance,DEMO_TAG.' Inventory transaction '.($index+1)]);
    }

    // Reconcile demo commitments without reducing physical stock.
    bb_exec($pdo,"UPDATE blood_inventory bi SET reserved_units=(SELECT COALESCE(SUM(r.units),0) FROM blood_reservations r WHERE r.hospital_id=bi.hospital_id AND r.blood_group=bi.blood_group AND r.status='Approved')+(SELECT COALESCE(SUM(br.units),0) FROM blood_requests br WHERE br.hospital_id=bi.hospital_id AND br.blood_group=bi.blood_group AND br.source_type='Blood Bank' AND br.status='Accepted')");
    bb_exec($pdo,'UPDATE blood_inventory SET units=GREATEST(units,reserved_units+5)');
    bb_exec($pdo,"INSERT INTO audit_logs (user_id,action,entity_type,details) VALUES (?,?,?,?)",[$admin,'Seed fictional demo scenarios','DemoSeed',DEMO_SEED_VERSION]);
    bb_exec($pdo,'INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[DEMO_SEED_KEY,DEMO_SEED_VERSION]);
    $pdo->commit();
    echo "Installed linked fictional demo scenarios: 5 requests, 5 reservations, 5 clubs, 5 campaigns, 5 direct donations, 5 histories, documents, applications, notifications and inventory events.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Demo seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
