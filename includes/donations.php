<?php
declare(strict_types=1);

function create_direct_donation(PDO $pdo, array $user, int $hospitalId, string $date, ?int $clubId): int
{
    return bb_transaction($pdo,function() use($pdo,$user,$hospitalId,$date,$clubId):int {
        $donor=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[(int)$user['id']]);
        if(!in_array($donor['role'],['donor','seeker'],true) || !$donor['donor_enabled'] || $donor['account_status']!=='Active') throw new DomainException('Enable donation in your health profile first.');
        if(!in_array($donor['blood_group'],valid_blood_groups(),true)) throw new DomainException('Your blood group is required.');
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed || $parsed->format('Y-m-d')!==$date || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+1 year'))) throw new DomainException('Choose a date today or within the next year.');
        if($donor['screening_status']==='Permanently Ineligible') throw new DomainException('Contact your screening hospital before making a donation request.');
        $next=next_eligible_date($donor['last_donation_date']);
        if($next && $date<$next) throw new DomainException('The appointment must be after the configured donation interval.');
        require_verified_hospital($pdo,$hospitalId);
        assert_no_active_donation($pdo,(int)$user['id']);
        if($clubId && !bb_one($pdo,"SELECT c.id FROM clubs c JOIN club_members cm ON cm.club_id=c.id WHERE c.id=? AND cm.user_id=? AND c.status='Approved' AND cm.status='Approved'",[$clubId,$user['id']])) throw new DomainException('Select an approved club you belong to.');
        bb_exec($pdo,'INSERT INTO direct_donations (donor_id,hospital_id,club_id,blood_group,appointment_date) VALUES (?,?,?,?,?)',[$user['id'],$hospitalId,$clubId,$donor['blood_group'],$date]);
        $id=(int)$pdo->lastInsertId();
        bb_exec($pdo,'INSERT INTO health_access (donor_id,hospital_id) VALUES (?,?) ON DUPLICATE KEY UPDATE granted_at=NOW()',[$user['id'],$hospitalId]);
        notify_hospital($pdo,$hospitalId,'New direct donation request','Donation #'.$id.' awaits screening.','direct_donations.php');
        audit_log($pdo,(int)$user['id'],'Create direct donation','DirectDonation',$id);
        return $id;
    });
}

function transition_direct_donation(PDO $pdo, array $user, int $id, string $action, string $note, bool $clinicalConfirmed): void
{
    bb_transaction($pdo,function() use($pdo,$user,$id,$action,$note,$clinicalConfirmed):void {
        $d=bb_one($pdo,'SELECT * FROM direct_donations WHERE id=? FOR UPDATE',[$id]);
        if(!$d) throw new DomainException('Donation not found.');
        $owner=(int)$d['donor_id']===(int)$user['id'];
        $staff=$user['role']==='hospital' && (int)$user['hospital_id']===(int)$d['hospital_id'];
        $admin=$user['role']==='admin';
        if(!in_array($d['status'],['Pending','Screened'],true)) throw new DomainException('This donation is already closed.');
        $donor=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[(int)$d['donor_id']]);
        if($action==='cancel' && ($owner||$admin)) {
            bb_exec($pdo,"UPDATE direct_donations SET status='Cancelled' WHERE id=?",[$id]);
        } elseif($action==='reject' && ($staff||$admin)) {
            if($note==='' || strlen($note)>500) throw new DomainException('Enter a rejection reason within 500 characters.');
            bb_exec($pdo,"UPDATE direct_donations SET status='Rejected',screening_note=? WHERE id=?",[$note,$id]);
        } elseif(in_array($action,['screen','complete'],true) && $staff && !$owner) {
            require_verified_hospital($pdo,(int)$d['hospital_id']);
            if(!can_access_health($pdo,$user,(int)$donor['id'])) throw new DomainException('The donor must grant your hospital screening consent.');
            if(!$clinicalConfirmed || $note==='' || strlen($note)>500) throw new DomainException('Confirm on-site screening and enter a reference/note.');
            if($donor['account_status']!=='Active' || !$donor['donor_enabled'] || !$donor['is_available'] || $donor['blood_group']!==$d['blood_group']) throw new DomainException('The donor profile has changed or is unavailable. Review it first.');
            $next=next_eligible_date($donor['last_donation_date']);
            if($next && $next>date('Y-m-d')) throw new DomainException('The configured interval has not passed.');
            if($d['appointment_date']>date('Y-m-d')) throw new DomainException('Screening and collection cannot happen before the appointment.');
            if($action==='screen') {
                bb_exec($pdo,"UPDATE direct_donations SET status='Screened',screened_by=?,screened_at=NOW(),screening_note=? WHERE id=?",[$user['id'],$note,$id]);
                bb_exec($pdo,"UPDATE users SET screening_status='Eligible',verified_by_hospital=1,screened_by_user_id=?,screened_at=NOW(),screening_notes=? WHERE id=?",[$user['id'],$note,$donor['id']]);
            } else {
                if($d['status']!=='Screened' || substr((string)$d['screened_at'],0,10)!==date('Y-m-d')) throw new DomainException('Same-day hospital screening is required. Screen again before confirming.');
                complete_donor_history($pdo,$donor,null,$id,(int)$d['hospital_id'],(int)$user['id'],$d['club_id']?(int)$d['club_id']:null);
                bb_exec($pdo,'INSERT INTO blood_inventory (hospital_id,blood_group,units,reserved_units) VALUES (?,?,0,0) ON DUPLICATE KEY UPDATE hospital_id=VALUES(hospital_id)',[$d['hospital_id'],$d['blood_group']]);
                $stock=bb_one($pdo,'SELECT * FROM blood_inventory WHERE hospital_id=? AND blood_group=? FOR UPDATE',[$d['hospital_id'],$d['blood_group']]);
                bb_exec($pdo,'UPDATE blood_inventory SET units=units+1 WHERE id=?',[$stock['id']]);
                bb_exec($pdo,"INSERT INTO inventory_transactions (hospital_id,inventory_id,staff_user_id,transaction_type,unit_change,balance_after,note) VALUES (?,?,?,'Donation Received',1,?,?)",[$d['hospital_id'],$stock['id'],$user['id'],(int)$stock['units']+1,'Direct donation #'.$id.'; screening/testing confirmed by staff']);
                bb_exec($pdo,"UPDATE direct_donations SET status='Completed',completed_at=NOW(),screening_note=? WHERE id=?",[$note,$id]);
            }
        } else throw new DomainException('Action not permitted.');
        create_notification($pdo,(int)$d['donor_id'],'Hospital donation updated','Donation #'.$id.': '.$action.'.','direct_donations.php','Donation');
        audit_log($pdo,(int)$user['id'],'Direct donation '.$action,'DirectDonation',$id);
    });
}
