<?php
declare(strict_types=1);
require_once __DIR__.'/address.php';

function club_action(PDO $pdo, array $user, array $input): int
{
    return bb_transaction($pdo,function() use($pdo,$user,$input):int {
        $action=(string)($input['action']??'');
        if(in_array($action,['create','campaign'],true)) $input['location']=address_input($input); $id=(int)($input['club_id']??0);
        if($action==='create') {
            if(!in_array($user['role'],['donor','seeker'],true)) throw new DomainException('Use a personal donor/seeker account to apply as coordinator.');
            $values=[];
            foreach(['name'=>160,'university'=>160,'location'=>120,'reference'=>160] as $field=>$max) {
                $value=trim((string)($input[$field]??''));
                if($value==='' || strlen($value)>$max) throw new DomainException('Complete all club fields within their length limits.');
                $values[]=$value;
            }
            if(bb_one($pdo,"SELECT id FROM clubs WHERE coordinator_id=? AND status='Pending'",[$user['id']])) throw new DomainException('You already have a pending club application.');
            bb_exec($pdo,'INSERT INTO clubs (coordinator_id,name,university,location,reference) VALUES (?,?,?,?,?)',array_merge([$user['id']],$values));
            $id=(int)$pdo->lastInsertId();
            bb_exec($pdo,"INSERT INTO club_members (club_id,user_id,status) VALUES (?,?,'Approved')",[$id,$user['id']]);
            notify_admins($pdo,'New university club application',$values[0].' is awaiting approval.','clubs.php?id='.$id);
            audit_log($pdo,(int)$user['id'],'Create club','Club',$id);
            return $id;
        }
        $club=bb_one($pdo,'SELECT * FROM clubs WHERE id=? FOR UPDATE',[$id]);
        if(!$club) throw new DomainException('Club not found.');
        $admin=$user['role']==='admin';
        $coordinator=(int)$club['coordinator_id']===(int)$user['id'];
        $manage=$admin||$coordinator;
        if($action==='review' && $admin) {
            $status=(string)($input['status']??''); $note=trim((string)($input['review_note']??''));
            if(!in_array($status,['Approved','Rejected','Suspended'],true) || $note==='' || strlen($note)>500) throw new DomainException('Select a decision and enter the verification/rejection reason.');
            if((int)$club['coordinator_id']===(int)$user['id']) throw new DomainException('You cannot approve your own organization.');
            bb_exec($pdo,'UPDATE clubs SET status=?,review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$note,$user['id'],$id]);
            create_notification($pdo,(int)$club['coordinator_id'],'Club '.$status,$club['name'].': '.$note,'clubs.php?id='.$id,'Club');
        } elseif($action==='leave') {
            if($coordinator) throw new DomainException('The coordinator cannot leave their club.');
            bb_exec($pdo,'DELETE FROM club_members WHERE club_id=? AND user_id=?',[$id,$user['id']]);
            bb_exec($pdo,'DELETE cr FROM campaign_registrations cr JOIN campaigns c ON c.id=cr.campaign_id WHERE c.club_id=? AND cr.user_id=?',[$id,$user['id']]);
        } else {
            if($club['status']!=='Approved') throw new DomainException('This club is not currently approved.');
            if($action==='join' && in_array($user['role'],['donor','seeker'],true)) {
                if(empty($input['consent'])) throw new DomainException('Consent is required to join.');
                $member=bb_one($pdo,'SELECT * FROM club_members WHERE club_id=? AND user_id=?',[$id,$user['id']]);
                if($member && $member['status']!=='Rejected') throw new DomainException('You have already joined or applied.');
                bb_exec($pdo,"INSERT INTO club_members (club_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE status='Pending',joined_at=NOW()",[$id,$user['id']]);
                create_notification($pdo,(int)$club['coordinator_id'],'Club membership request',$user['full_name'].' requested to join.','clubs.php?id='.$id,'Club');
            } elseif($action==='member' && $manage) {
                $memberId=(int)($input['member_id']??0); $status=(string)($input['status']??'');
                if(!in_array($status,['Approved','Rejected'],true) || $memberId===(int)$club['coordinator_id']) throw new DomainException('Invalid membership update.');
                $member=bb_one($pdo,'SELECT user_id FROM club_members WHERE club_id=? AND user_id=?',[$id,$memberId]);
                if(!$member) throw new DomainException('The user must apply before being added.');
                bb_exec($pdo,'UPDATE club_members SET status=? WHERE club_id=? AND user_id=?',[$status,$id,$memberId]);
                if($status==='Rejected') bb_exec($pdo,'DELETE cr FROM campaign_registrations cr JOIN campaigns c ON c.id=cr.campaign_id WHERE c.club_id=? AND cr.user_id=?',[$id,$memberId]);
                create_notification($pdo,$memberId,'Club membership '.$status,$club['name'],'clubs.php?id='.$id,'Club');
            } elseif($action==='share' && $manage) {
                $requestId=(int)($input['request_id']??0);
                $r=bb_one($pdo,"SELECT * FROM blood_requests WHERE id=? AND source_type='Donor' AND status='Pending' AND prescription_status='Reviewed'",[$requestId]);
                if(!$r) throw new DomainException('Only reviewed, pending donor requests may be shared.');
                ensure_not_expired($r);
                if(bb_one($pdo,'SELECT club_id FROM club_request_shares WHERE club_id=? AND request_id=?',[$id,$requestId])) throw new DomainException('Already shared with this club.');
                bb_exec($pdo,'INSERT INTO club_request_shares (club_id,request_id) VALUES (?,?)',[$id,$requestId]);
                foreach(bb_all($pdo,"SELECT u.* FROM club_members m JOIN users u ON u.id=m.user_id WHERE m.club_id=? AND m.status='Approved' AND u.donor_enabled=1 AND u.account_status='Active' AND u.blood_group=?",[$id,$r['blood_group']]) as $donor) {
                    if((int)$donor['id']!==(int)$r['seeker_id'] && donor_effective_status($donor)==='Eligible') create_notification($pdo,(int)$donor['id'],'Club blood request',$club['name'].': '.$r['blood_group'].' needed in '.$r['location'].'.','request_edit.php?id='.$requestId,'Club');
                }
            } elseif($action==='campaign' && $manage) {
                $title=trim((string)($input['title']??'')); $location=trim((string)($input['location']??''));
                $date=str_replace('T',' ',(string)($input['event_at']??''));
                $parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date);
                if($title==='' || strlen($title)>160 || $location==='' || strlen($location)>120 || !$parsed || $parsed->format('Y-m-d H:i')!==$date || $parsed->getTimestamp()<=time()) throw new DomainException('Enter a title, location and valid future date/time.');
                $hospitalId=(int)($input['hospital_id']??0); require_verified_hospital($pdo,$hospitalId);
                bb_exec($pdo,'INSERT INTO campaigns (club_id,hospital_id,title,location,event_at) VALUES (?,?,?,?,?)',[$id,$hospitalId,$title,$location,$date]);
                foreach(bb_all($pdo,"SELECT user_id FROM club_members WHERE club_id=? AND status='Approved'",[$id]) as $member) create_notification($pdo,(int)$member['user_id'],'New club campaign',$title.' · '.$date,'clubs.php?id='.$id,'Campaign');
            } elseif(in_array($action,['rsvp','unrsvp','campaign_status'],true)) {
                $campaignId=(int)($input['campaign_id']??0);
                $event=bb_one($pdo,'SELECT * FROM campaigns WHERE id=? AND club_id=? FOR UPDATE',[$campaignId,$id]);
                if(!$event) throw new DomainException('Campaign not found.');
                if($action==='campaign_status' && $manage) {
                    $status=(string)($input['status']??'');
                    if(!in_array($status,['Cancelled','Completed'],true) || $event['status']!=='Scheduled') throw new DomainException('Invalid campaign transition.');
                    if($status==='Completed' && strtotime($event['event_at'])>time()) throw new DomainException('A future event cannot be completed.');
                    bb_exec($pdo,'UPDATE campaigns SET status=? WHERE id=?',[$status,$campaignId]);
                    foreach(bb_all($pdo,'SELECT user_id FROM campaign_registrations WHERE campaign_id=?',[$campaignId]) as $r) create_notification($pdo,(int)$r['user_id'],'Campaign '.$status,$event['title'],'clubs.php?id='.$id,'Campaign');
                } elseif($action==='unrsvp') bb_exec($pdo,'DELETE FROM campaign_registrations WHERE campaign_id=? AND user_id=?',[$campaignId,$user['id']]);
                elseif($action==='rsvp') {
                    if($event['status']!=='Scheduled' || strtotime($event['event_at'])<=time()) throw new DomainException('Registration is closed.');
                    if(!bb_one($pdo,"SELECT user_id FROM club_members WHERE club_id=? AND user_id=? AND status='Approved'",[$id,$user['id']])) throw new DomainException('Join this club and obtain approval first.');
                    bb_exec($pdo,'INSERT INTO campaign_registrations (campaign_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)',[$campaignId,$user['id']]);
                } else throw new DomainException('Action not permitted.');
            } else throw new DomainException('Action not permitted.');
        }
        audit_log($pdo,(int)$user['id'],'Club '.$action,'Club',$id);
        return $id;
    });
}
