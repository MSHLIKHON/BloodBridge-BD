<?php
/** File purpose: Matching provides shared application logic and presentation helpers. */
declare(strict_types=1);

function personal_account(array $user): bool
{
    return in_array($user['role']??'', ['donor','seeker'], true);
}

function request_event(PDO $pdo, int $requestId, int $actorId, string $event, string $details=''): void
{
    bb_exec($pdo,'INSERT INTO request_events (request_id,actor_id,event,details) VALUES (?,?,?,?)',[$requestId,$actorId,$event,$details]);
    audit_log($pdo,$actorId,$event,'BloodRequest',$requestId,$details);
}

function request_match_action(PDO $pdo, array $actor, int $id, string $action, int $candidateId=0, string $note=''): void
{
    if (!personal_account($actor)) throw new DomainException('Only personal accounts can respond or select a donor.');
    if(strlen($note)>500) throw new DomainException('Keep the note within 500 characters.');
    bb_transaction($pdo,function() use($pdo,$actor,$id,$action,$candidateId,$note):void {
        $r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=? FOR UPDATE',[$id]);
        if(!$r || $r['source_type']!=='Donor') throw new DomainException('Donor request not found.');
        ensure_not_expired($r);
        if(!in_array($r['status'],['Pending','Accepted'],true)) throw new DomainException('This request is already closed.');
        $uid=(int)$actor['id']; $owner=$uid===(int)$r['seeker_id'];
        $self=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[$uid]);
        if(!$self || $self['account_status']!=='Active') throw new DomainException('Account unavailable.');
        $response=bb_one($pdo,'SELECT * FROM request_responses WHERE request_id=? AND responder_id=?',[$id,$uid]);
        $setResponse=static function(int $donor,string $status)use($pdo,$id):void {
            bb_exec($pdo,'INSERT INTO request_responses (request_id,responder_id,response) VALUES (?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response),responded_at=NOW()',[$id,$donor,$status]);
        };
        $link='request_edit.php?id='.$id;
        if(in_array($action,['interest','decline'],true)) {
            if($owner || $r['status']!=='Pending' || !$self['donor_enabled'] || $self['blood_group']!==$r['blood_group']) throw new DomainException('This request is not available for your response.');
            if($action==='interest') {
                if($r['prescription_status']!=='Reviewed') throw new DomainException('The prescription must be reviewed first.');
                if(donor_effective_status($self)!=='Eligible') throw new DomainException('Your donor profile is not currently eligible.');
                assert_no_active_donation($pdo,$uid);
            }
            $status=$action==='interest'?'Interested':'Rejected';
            if(($response['response']??'')===$status) return;
            $setResponse($uid,$status);
            create_notification($pdo,(int)$r['seeker_id'],'Donor response','A donor marked request #'.$id.' as '.$status.'.',$link,'Request');
            request_event($pdo,$id,$uid,$status);
        } elseif($action==='select') {
            if(!$owner || $r['status']!=='Pending' || $r['accepted_by']) throw new DomainException('Only the owner can select one donor on a pending request.');
            if($r['prescription_status']!=='Reviewed' || (int)$r['units']!==1) throw new DomainException('A reviewed one-unit request is required.');
            $offer=bb_one($pdo,"SELECT * FROM request_responses WHERE request_id=? AND responder_id=? AND response='Interested'",[$id,$candidateId]);
            $donor=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[$candidateId]);
            if(!$offer || !$donor || !personal_account($donor) || $candidateId===$uid || $donor['blood_group']!==$r['blood_group'] || donor_effective_status($donor)!=='Eligible') throw new DomainException('This donor is no longer available.');
            assert_no_active_donation($pdo,$candidateId);
            bb_exec($pdo,"UPDATE blood_requests SET status='Accepted',accepted_by=?,donor_confirmed_at=NULL,donor_reported_at=NULL,outcome='Awaiting' WHERE id=?",[$candidateId,$id]);
            $others=bb_all($pdo,"SELECT responder_id FROM request_responses WHERE request_id=? AND response='Interested' AND responder_id<>?",[$id,$candidateId]);
            bb_exec($pdo,"UPDATE request_responses SET response='Not selected',responded_at=NOW() WHERE request_id=? AND response='Interested'",[$id]);
            $setResponse($candidateId,'Selected');
            foreach($others as $other) create_notification($pdo,(int)$other['responder_id'],'Donor selection updated','Another donor was selected for request #'.$id.'.',$link,'Request');
            create_notification($pdo,$candidateId,'You were selected','Confirm whether you can donate for request #'.$id.'.',$link,'Request');
            request_event($pdo,$id,$uid,'Donor selected','Donor #'.$candidateId);
        } elseif(in_array($action,['confirm','withdraw','donated'],true)) {
            $selected=(int)$r['accepted_by']===$uid;
            if($action==='withdraw' && !$selected) {
                if(!$response || $response['response']!=='Interested') throw new DomainException('No active offer to withdraw.');
                $setResponse($uid,'Withdrawn');request_event($pdo,$id,$uid,'Offer withdrawn');return;
            }
            if(!$selected || $r['status']!=='Accepted') throw new DomainException('Only the selected donor can take this action.');
            if($action==='withdraw') {
                if($r['donor_reported_at']) throw new DomainException('Donation was reported. Resolve the outcome with the seeker.');
                $setResponse($uid,'Withdrawn');
                bb_exec($pdo,"UPDATE blood_requests SET status='Pending',accepted_by=NULL,donor_confirmed_at=NULL WHERE id=?",[$id]);
            } else {
                if($r['prescription_status']!=='Reviewed' || donor_effective_status($self)!=='Eligible') throw new DomainException('Reviewed prescription and an eligible donor profile are required.');
                if($action==='confirm') {
                    if($r['donor_confirmed_at']) return;
                    bb_exec($pdo,'UPDATE blood_requests SET donor_confirmed_at=NOW() WHERE id=?',[$id]);$setResponse($uid,'Accepted');
                } else {
                    if(!$r['donor_confirmed_at']) throw new DomainException('Confirm your selection before reporting donation.');
                    if($r['donor_reported_at']) return;
                    bb_exec($pdo,'UPDATE blood_requests SET donor_reported_at=NOW() WHERE id=?',[$id]);
                }
            }
            request_event($pdo,$id,$uid,$action==='confirm'?'Donor confirmed':($action==='withdraw'?'Selected donor withdrew':'Donation reported'));
            create_notification($pdo,(int)$r['seeker_id'],'Donation progress updated','Request #'.$id.': '.$action.'.',$link,'Request');
        } elseif($action==='correct_report') {
            if((int)$r['accepted_by']!==$uid || $r['outcome']!=='Disputed' || !$r['donor_reported_at']) throw new DomainException('Only the selected donor can correct a disputed donation report.');
            if(trim($note)==='') throw new DomainException('Explain the incorrect donation report.');
            bb_exec($pdo,"UPDATE blood_requests SET status='Cancelled',outcome='Not received',outcome_note=? WHERE id=?",[$note,$id]);
            $setResponse($uid,'Withdrawn');
            request_event($pdo,$id,$uid,'Donation report corrected',$note);
            create_notification($pdo,(int)$r['seeker_id'],'Donation dispute resolved','The donor confirmed no donation occurred. Request #'.$id.' is closed without donation history.',$link,'Request');
        } elseif($action==='not_received') {
            if(!$owner || trim($note)==='') throw new DomainException('The request owner must provide a reason.');
            if($r['donor_reported_at']) {
                bb_exec($pdo,"UPDATE blood_requests SET outcome='Disputed',outcome_note=? WHERE id=?",[$note,$id]);
                notify_admins($pdo,'Donation outcome disputed','Request #'.$id.' needs review; donor commitment remains reserved.','reports.php?kind=Requests');
            } else bb_exec($pdo,"UPDATE blood_requests SET status='Cancelled',outcome='Not received',outcome_note=? WHERE id=?",[$note,$id]);
            if($r['accepted_by']) create_notification($pdo,(int)$r['accepted_by'],'Blood not received',($r['donor_reported_at']?'The seeker disputes receipt for request #':'The seeker closed request #').$id.'.',$link,'Request');
            request_event($pdo,$id,$uid,$r['donor_reported_at']?'Receipt disputed':'Not received',$note);
        } else throw new DomainException('Unknown request action.');
    });
}

function request_progress_label(array $request): string
{
    if(($request['outcome']??'')==='Disputed') return 'Disputed · Receipt not confirmed';
    if($request['status']==='Completed') return 'Completed · Blood received';
    if($request['status']!=='Accepted' || $request['source_type']!=='Donor') return $request['status'];
    if(!empty($request['donor_reported_at'])) return 'Donated · Awaiting receipt confirmation';
    return empty($request['donor_confirmed_at'])?'Donor selected · Awaiting confirmation':'Accepted · Donor confirmed';
}

function confirm_donor_receipt(PDO $pdo,array $actor,int $id): void
{
    bb_transaction($pdo,function()use($pdo,$actor,$id):void {
        $r=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=? FOR UPDATE',[$id]);
        if(!$r || $r['source_type']!=='Donor' || $r['status']!=='Accepted' || (int)$r['seeker_id']!==(int)$actor['id'] || !personal_account($actor)) throw new DomainException('Only the owner can confirm receipt of an accepted donor request.');
        if(!$r['donor_confirmed_at'] || !$r['donor_reported_at'] || $r['prescription_status']!=='Reviewed') throw new DomainException('Donor confirmation, donation report and reviewed prescription are required.');
        $donor=bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[$r['accepted_by']]);
        if(!$donor) throw new DomainException('Donor record missing.');
        complete_donor_history($pdo,$donor,$id,null,null,(int)$actor['id'],null,substr($r['donor_reported_at'],0,10));
        bb_exec($pdo,"UPDATE blood_requests SET status='Completed',completed_at=NOW(),outcome='Received',outcome_note=NULL WHERE id=?",[$id]);
        if($r['patient_relation']==='Self') bb_exec($pdo,'UPDATE users SET last_received_date=CURDATE() WHERE id=?',[$actor['id']]);
        request_event($pdo,$id,(int)$actor['id'],'Blood received');
        create_notification($pdo,(int)$donor['id'],'Donation completed','Both sides confirmed request #'.$id.'.','donation_history.php','Donation');
        create_notification($pdo,(int)$actor['id'],'Blood received','Request #'.$id.' is completed.','request_edit.php?id='.$id,'Request');
    });
}
