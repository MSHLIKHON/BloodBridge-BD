<?php
/** File purpose: Workflow provides automated regression coverage for BloodBridge BD. */
declare(strict_types=1);
// Sequential business-rule simulation only. No MySQL lock or migration claim.
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../includes/donations.php';
require_once __DIR__.'/../includes/clubs.php';
require_once __DIR__.'/../includes/maintenance.php';

final class SimulationPDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $this->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'),0);
        $this->sqliteCreateFunction('CURDATE',fn()=>date('Y-m-d'),0);
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $query=str_replace(' FOR UPDATE','',$query);
        if(str_contains($query,'ON DUPLICATE KEY UPDATE')) {
            [$head,$tail]=explode('ON DUPLICATE KEY UPDATE',$query,2);
            $tail=preg_replace('/VALUES\((\w+)\)/i','excluded.$1',$tail);
            $query=$head.' ON CONFLICT DO UPDATE SET '.$tail;
        }
        if(str_starts_with($query,'DELETE cr FROM campaign_registrations')) $query='DELETE FROM campaign_registrations WHERE campaign_id IN (SELECT id FROM campaigns WHERE club_id=?) AND user_id=?';
        return parent::prepare($query,$options);
    }
}

function bloodbridge_workflow_simulation(?PDO &$fixture=null):array
{
    $pdo=new SimulationPDO();
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT, account_status TEXT DEFAULT 'Active', hospital_id INTEGER, blood_group TEXT DEFAULT 'B+', donor_enabled INTEGER DEFAULT 1, is_available INTEGER DEFAULT 1, screening_status TEXT DEFAULT 'Eligible', last_donation_date TEXT, total_donations INTEGER DEFAULT 0, verified_by_hospital INTEGER DEFAULT 1, screened_by_user_id INTEGER,screened_at TEXT,screening_notes TEXT,profile_updated_at TEXT);
      CREATE TABLE hospitals (id INTEGER PRIMARY KEY,status TEXT DEFAULT 'Verified',name TEXT);
      CREATE TABLE direct_donations (id INTEGER PRIMARY KEY AUTOINCREMENT,donor_id INTEGER,hospital_id INTEGER,club_id INTEGER,blood_group TEXT,appointment_date TEXT,status TEXT DEFAULT 'Pending',screened_by INTEGER,screened_at TEXT,screening_note TEXT,completed_at TEXT);
      CREATE TABLE donation_history (id INTEGER PRIMARY KEY AUTOINCREMENT,request_id INTEGER UNIQUE,direct_donation_id INTEGER UNIQUE,donor_id INTEGER,hospital_id INTEGER,verified_by_user_id INTEGER,blood_group TEXT,units INTEGER,donation_date TEXT,notes TEXT);
      CREATE TABLE blood_inventory (id INTEGER PRIMARY KEY AUTOINCREMENT,hospital_id INTEGER,blood_group TEXT,units INTEGER,reserved_units INTEGER,UNIQUE(hospital_id,blood_group));
      CREATE TABLE inventory_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT,hospital_id INTEGER,inventory_id INTEGER,staff_user_id INTEGER,transaction_type TEXT,unit_change INTEGER,balance_after INTEGER,note TEXT);
      CREATE TABLE health_access (donor_id INTEGER,hospital_id INTEGER,granted_at TEXT,PRIMARY KEY(donor_id,hospital_id));
      CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,type TEXT,title TEXT,message TEXT,link TEXT);
      CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER,details TEXT);
      CREATE TABLE blood_requests (id INTEGER PRIMARY KEY,seeker_id INTEGER,hospital_id INTEGER,review_hospital_id INTEGER,source_type TEXT,blood_group TEXT,status TEXT,accepted_by INTEGER,expires_at TEXT,units INTEGER,prescription_status TEXT,location TEXT,outcome TEXT DEFAULT 'Awaiting');
      CREATE TABLE blood_reservations (id INTEGER PRIMARY KEY,seeker_id INTEGER,hospital_id INTEGER,blood_group TEXT,status TEXT,expires_at TEXT,units INTEGER);
      CREATE TABLE clubs (id INTEGER PRIMARY KEY AUTOINCREMENT,coordinator_id INTEGER,name TEXT,university TEXT,location TEXT,reference TEXT,status TEXT DEFAULT 'Pending',review_note TEXT,reviewed_by INTEGER,reviewed_at TEXT,UNIQUE(university,name));
      CREATE TABLE club_members (club_id INTEGER,user_id INTEGER,status TEXT DEFAULT 'Pending',joined_at TEXT,PRIMARY KEY(club_id,user_id));
      CREATE TABLE club_request_shares (club_id INTEGER,request_id INTEGER,PRIMARY KEY(club_id,request_id));
      CREATE TABLE campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT,club_id INTEGER,hospital_id INTEGER,title TEXT,location TEXT,event_at TEXT,status TEXT DEFAULT 'Scheduled');
      CREATE TABLE campaign_registrations (campaign_id INTEGER,user_id INTEGER,PRIMARY KEY(campaign_id,user_id));
      CREATE TABLE private_documents (id INTEGER PRIMARY KEY AUTOINCREMENT,owner_id INTEGER,request_id INTEGER,kind TEXT,filename TEXT,mime_type TEXT,size_bytes INTEGER,file_data BLOB,test_name TEXT,test_value TEXT,test_unit TEXT,test_date TEXT,lab_name TEXT);");
    foreach([[1,'Admin','admin',null],[2,'Donor','donor',null],[3,'Seeker','seeker',null],[4,'Hospital A','hospital',1],[5,'Hospital B','hospital',2],[6,'Member','donor',null],[7,'Coordinator','seeker',null]] as $u) bb_exec($pdo,'INSERT INTO users (id,full_name,role,hospital_id) VALUES (?,?,?,?)',$u);
    bb_exec($pdo,"INSERT INTO hospitals (id,name) VALUES (1,'Test Hospital A'),(2,'Test Hospital B')");
    $actor=fn($id)=>bb_one($pdo,'SELECT * FROM users WHERE id=?',[$id]);
    $results=[];$check=static function(string $name,callable $work)use(&$results):void { try{$ok=$work();$results[]=['test'=>$name,'status'=>$ok===true?'PASS':'FAIL','environment'=>'SQLite simulation'];}catch(Throwable $e){$results[]=['test'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];} };
    $denied=static function(callable $work):bool { try{$work();return false;}catch(DomainException $e){return true;} };
    $donationId=0;
    $check('Create direct donation and consent',function()use($pdo,$actor,&$donationId):bool { $donationId=create_direct_donation($pdo,$actor(2),1,date('Y-m-d'),null);return $donationId>0 && can_access_health($pdo,$actor(4),2); });
    $check('Block second active donation commitment',fn()=>$denied(fn()=>create_direct_donation($pdo,$actor(2),1,date('Y-m-d'),null)));
    $check('Other hospital cannot screen',fn()=>$denied(fn()=>transition_direct_donation($pdo,$actor(5),$donationId,'screen','Demo screening',true)));
    $check('Admin cannot clinically screen',fn()=>$denied(fn()=>transition_direct_donation($pdo,$actor(1),$donationId,'screen','Demo screening',true)));
    $check('Reject confirmation before screening',fn()=>$denied(fn()=>transition_direct_donation($pdo,$actor(4),$donationId,'complete','Demo screening',true)));
    $check('Reject screening without attestation',fn()=>$denied(fn()=>transition_direct_donation($pdo,$actor(4),$donationId,'screen','Demo screening',false)));
    $check('Hospital records screening',function()use($pdo,$actor,$donationId):bool { transition_direct_donation($pdo,$actor(4),$donationId,'screen','Fictional on-site assessment',true);return bb_one($pdo,'SELECT status FROM direct_donations WHERE id=?',[$donationId])['status']==='Screened'; });
    $check('Revoke consent prevents completion',function()use($pdo,$actor,$donationId,$denied):bool { bb_exec($pdo,'DELETE FROM health_access WHERE donor_id=2');$blocked=$denied(fn()=>transition_direct_donation($pdo,$actor(4),$donationId,'complete','Demo',true));bb_exec($pdo,'INSERT INTO health_access VALUES (2,1,NULL)');return $blocked; });
    $check('Confirm donation updates stock and history',function()use($pdo,$actor,$donationId):bool { transition_direct_donation($pdo,$actor(4),$donationId,'complete','Fictional released unit',true);$u=bb_one($pdo,'SELECT * FROM users WHERE id=2');return (int)$pdo->query('SELECT units FROM blood_inventory WHERE hospital_id=1')->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM donation_history')->fetchColumn()===1 && (int)$u['total_donations']===1 && $u['last_donation_date']===date('Y-m-d') && $u['screening_status']==='Pending'; });
    $check('Duplicate completion blocked without extra stock',fn()=>$denied(fn()=>transition_direct_donation($pdo,$actor(4),$donationId,'complete','Demo',true)) && (int)$pdo->query('SELECT units FROM blood_inventory')->fetchColumn()===1);
    $check('Health consent does not expose another donor',fn()=>!can_access_health($pdo,$actor(4),6));
    $check('Health access denied to seekers',fn()=>!can_access_health($pdo,$actor(3),2));
    $check('Health access denied to admin without clinical role',fn()=>!can_access_health($pdo,$actor(1),2));
    $check('Health owner has access',fn()=>can_access_health($pdo,$actor(2),2));
    $check('Suspension revokes hospital health access',function()use($pdo,$actor):bool {bb_exec($pdo,"UPDATE hospitals SET status='Suspended' WHERE id=1");$ok=!can_access_health($pdo,$actor(4),2);bb_exec($pdo,"UPDATE hospitals SET status='Verified' WHERE id=1");return $ok;});
    bb_exec($pdo,"INSERT INTO blood_requests (id,seeker_id,hospital_id,review_hospital_id,source_type,blood_group,status,accepted_by,expires_at,units,prescription_status,location) VALUES (10,3,1,1,'Blood Bank','B+','Accepted',4,'2000-01-01',1,'Reviewed','Test location')");
    bb_exec($pdo,'UPDATE blood_inventory SET units=5,reserved_units=3 WHERE hospital_id=1');
    bb_exec($pdo,"INSERT INTO blood_reservations VALUES (11,3,1,'B+','Approved','2000-01-01',2)");
    $check('Expired bank request releases its reservation',fn()=>expire_record($pdo,'blood_requests',10) && (int)$pdo->query('SELECT reserved_units FROM blood_inventory')->fetchColumn()===2);
    $check('Expired reservation releases only reserved units',fn()=>expire_record($pdo,'blood_reservations',11) && (int)$pdo->query('SELECT reserved_units FROM blood_inventory')->fetchColumn()===0 && (int)$pdo->query('SELECT units FROM blood_inventory')->fetchColumn()===5);
    $check('Expiry is idempotent',fn()=>expire_record($pdo,'blood_reservations',11)===false);
    bb_exec($pdo,"INSERT INTO blood_reservations VALUES (12,3,1,'B+','Approved','2000-01-01',2)");
    $check('Inconsistent stock expiry rolls back',fn()=>$denied(fn()=>expire_record($pdo,'blood_reservations',12)) && bb_one($pdo,'SELECT status FROM blood_reservations WHERE id=12')['status']==='Approved');
    $r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=10');
    $check('Assigned hospital may review prescription',fn()=>can_review_prescription($pdo,$actor(4),$r));
    $check('Other hospital cannot review prescription',fn()=>!can_review_prescription($pdo,$actor(5),$r));
    $check('Owner cannot approve own prescription',fn()=>!can_review_prescription($pdo,$actor(3),$r));
    $check('Donor cannot read seeker prescription',fn()=>!can_read_document($pdo,$actor(2),['owner_id'=>3,'kind'=>'Prescription','request_id'=>10]));
    $clubId=0;
    $check('Create club awaiting verification',function()use($pdo,$actor,&$clubId):bool {$clubId=club_action($pdo,$actor(7),['action'=>'create','name'=>'Test Club','university'=>'Test University','division'=>'Dhaka','district'=>'Dhaka','upazila'=>'Savar','reference'=>'TEST-001']);return bb_one($pdo,'SELECT status FROM clubs WHERE id=?',[$clubId])['status']==='Pending';});
    $check('Unapproved club cannot recruit',fn()=>$denied(fn()=>club_action($pdo,$actor(6),['action'=>'join','club_id'=>$clubId,'consent'=>1])));
    $check('Coordinator cannot approve their club',fn()=>$denied(fn()=>club_action($pdo,$actor(7),['action'=>'review','club_id'=>$clubId,'status'=>'Approved','review_note'=>'test'])));
    $check('Administrator approves club',function()use($pdo,$actor,$clubId):bool {club_action($pdo,$actor(1),['action'=>'review','club_id'=>$clubId,'status'=>'Approved','review_note'=>'Fictional verification']);return bb_one($pdo,'SELECT status FROM clubs WHERE id=?',[$clubId])['status']==='Approved';});
    $check('Joining needs consent',fn()=>$denied(fn()=>club_action($pdo,$actor(6),['action'=>'join','club_id'=>$clubId])));
    $check('Consenting member application saved',function()use($pdo,$actor,$clubId):bool{club_action($pdo,$actor(6),['action'=>'join','club_id'=>$clubId,'consent'=>1]);return bb_one($pdo,'SELECT status FROM club_members WHERE club_id=? AND user_id=6',[$clubId])['status']==='Pending';});
    $check('Unrelated user cannot manage members',fn()=>$denied(fn()=>club_action($pdo,$actor(3),['action'=>'member','club_id'=>$clubId,'member_id'=>6,'status'=>'Approved'])));
    $check('Coordinator approves member',function()use($pdo,$actor,$clubId):bool{club_action($pdo,$actor(7),['action'=>'member','club_id'=>$clubId,'member_id'=>6,'status'=>'Approved']);return bb_one($pdo,'SELECT status FROM club_members WHERE club_id=? AND user_id=6',[$clubId])['status']==='Approved';});
    $check('Create future campaign',function()use($pdo,$actor,$clubId):bool{club_action($pdo,$actor(7),['action'=>'campaign','club_id'=>$clubId,'title'=>'Test Event','division'=>'Dhaka','district'=>'Dhaka','upazila'=>'Savar','event_at'=>date('Y-m-d\TH:i',time()+86400),'hospital_id'=>1]);return (int)$pdo->query('SELECT COUNT(*) FROM campaigns')->fetchColumn()===1;});
    $check('Member registers only once',function()use($pdo,$actor,$clubId):bool{for($i=0;$i<2;$i++)club_action($pdo,$actor(6),['action'=>'rsvp','club_id'=>$clubId,'campaign_id'=>1]);return (int)$pdo->query('SELECT COUNT(*) FROM campaign_registrations')->fetchColumn()===1;});
    $check('Non-member cannot register',fn()=>$denied(fn()=>club_action($pdo,$actor(3),['action'=>'rsvp','club_id'=>$clubId,'campaign_id'=>1])));
    $check('Future campaign cannot be completed',fn()=>$denied(fn()=>club_action($pdo,$actor(7),['action'=>'campaign_status','club_id'=>$clubId,'campaign_id'=>1,'status'=>'Completed'])));
    $check('Leaving removes active campaign registration',function()use($pdo,$actor,$clubId):bool{club_action($pdo,$actor(6),['action'=>'leave','club_id'=>$clubId]);return (int)$pdo->query('SELECT COUNT(*) FROM campaign_registrations')->fetchColumn()===0;});
    $fixture=$pdo;
    return $results;
}

if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $results=bloodbridge_workflow_simulation();echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
    exit(count(array_filter($results,fn($r)=>$r['status']==='FAIL'))?1:0);
}
