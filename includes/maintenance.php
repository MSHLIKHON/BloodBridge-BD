<?php
declare(strict_types=1);

function expire_record(PDO $pdo, string $table, int $id): bool
{
    if (!in_array($table,['blood_requests','blood_reservations'],true)) throw new InvalidArgumentException('Invalid expiry table.');
    return bb_transaction($pdo,function () use ($pdo,$table,$id): bool {
        $row = bb_one($pdo,"SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]);
        $request = $table === 'blood_requests';
        $live = $request ? ['Pending','Accepted'] : ['Pending','Approved'];
        if($request && !empty($row['donor_reported_at'])) return false;
        if (!$row || !in_array($row['status'],$live,true) || !$row['expires_at'] || strtotime($row['expires_at']) > time()) return false;
        $reserved = $request ? $row['status']==='Accepted' && $row['source_type']==='Blood Bank' : $row['status']==='Approved';
        if ($reserved) {
            $stock = bb_one($pdo,'SELECT * FROM blood_inventory WHERE hospital_id=? AND blood_group=? FOR UPDATE',[(int) $row['hospital_id'],$row['blood_group']]);
            if (!$stock || (int) $stock['reserved_units'] < (int) $row['units']) throw new DomainException('Reserved stock inconsistency needs administrator review.');
            bb_exec($pdo,'UPDATE blood_inventory SET reserved_units=reserved_units-? WHERE id=?',[(int) $row['units'],(int) $stock['id']]);
        }
        bb_exec($pdo,"UPDATE $table SET status='Expired'".($request?",outcome='Not received'":"")." WHERE id=?",[$id]);
        $link = $request ? 'request_edit.php?id='.$id : 'reservations.php';
        create_notification($pdo,(int) $row['seeker_id'],'Request expired','Request/reservation #'.$id.' expired. Any reserved stock was released.',$link,'Expiry');
        if ($request && $row['accepted_by']) create_notification($pdo,(int) $row['accepted_by'],'Accepted request expired','Request #'.$id.' has expired.','requests.php','Expiry');
        audit_log($pdo,null,'Automatic expiry',$request?'BloodRequest':'BloodReservation',$id);
        return true;
    });
}

function run_maintenance(PDO $pdo): array
{
    $lockName = 'bloodbridge-maintenance-'.DB_NAME;
    $lock = bb_one($pdo,'SELECT GET_LOCK(?,0) AS acquired',[$lockName]);
    if ((int) $lock['acquired'] !== 1) return ['busy'=>1];
    $counts = ['requests'=>0,'reservations'=>0,'reminders'=>0,'low_stock'=>0,'errors'=>0];
    try {
        bb_exec($pdo,'INSERT INTO maintenance_runs (summary) VALUES (?)',['Running']);
        $runId = (int) $pdo->lastInsertId();
        foreach (['blood_requests'=>'requests','blood_reservations'=>'reservations'] as $table=>$counter) {
            $states = $table==='blood_requests' ? "'Pending','Accepted'" : "'Pending','Approved'";
            $excludeReported=$table==='blood_requests'?" AND donor_reported_at IS NULL":"";
            foreach (bb_all($pdo,"SELECT id FROM $table WHERE status IN ($states) AND expires_at<=NOW() $excludeReported ORDER BY id LIMIT 500") as $row) {
                try { if (expire_record($pdo,$table,(int) $row['id'])) ++$counts[$counter]; }
                catch(Throwable $e) { ++$counts['errors'];error_log('Expiry '.$table.' #'.$row['id'].': '.$e->getMessage()); }
            }
        }
        $days = app_setting('reminder_days',105);
        $donors = bb_all($pdo,"SELECT id FROM users u WHERE donor_enabled=1 AND account_status='Active' AND reminders_enabled=1 AND screening_status<>'Permanently Ineligible' AND last_donation_date IS NOT NULL AND DATE_ADD(last_donation_date,INTERVAL $days DAY)<=CURDATE() AND NOT EXISTS (SELECT 1 FROM reminder_deliveries rd WHERE rd.user_id=u.id AND rd.donation_date=u.last_donation_date) ORDER BY id LIMIT 1000");
        foreach ($donors as $item) {
            $sent = bb_transaction($pdo,function () use ($pdo,$item,$days): bool {
                $donor = bb_one($pdo,'SELECT * FROM users WHERE id=? FOR UPDATE',[(int) $item['id']]);
                if (!$donor || !$donor['reminders_enabled'] || !$donor['donor_enabled'] || $donor['account_status']!=='Active' || !$donor['last_donation_date']) return false;
                if ($donor['screening_status']==='Permanently Ineligible') return false;
                $due = (new DateTimeImmutable($donor['last_donation_date']))->modify('+'.$days.' days')->format('Y-m-d');
                if ($due > date('Y-m-d') || bb_one($pdo,'SELECT user_id FROM reminder_deliveries WHERE user_id=? AND donation_date=?',[$donor['id'],$donor['last_donation_date']])) return false;
                bb_exec($pdo,'INSERT INTO reminder_deliveries (user_id,donation_date) VALUES (?,?)',[$donor['id'],$donor['last_donation_date']]);
                create_notification($pdo,(int) $donor['id'],'Time to review your donation readiness','It has been '.$days.' days since your recorded donation. Contact a blood centre for screening. This reminder does not mean you are medically eligible.','donor_profile.php','Reminder');
                return true;
            });
            if ($sent) ++$counts['reminders'];
        }
        foreach (bb_all($pdo,"SELECT bi.id FROM blood_inventory bi JOIN hospitals h ON h.id=bi.hospital_id WHERE h.status='Verified' ORDER BY bi.id") as $item) {
            $sent = bb_transaction($pdo,function () use ($pdo,$item): bool {
                $stock = bb_one($pdo,'SELECT * FROM blood_inventory WHERE id=? FOR UPDATE',[(int) $item['id']]);
                $low = (int) $stock['units'] - (int) $stock['reserved_units'] < (int) $stock['low_stock_threshold'];
                $state = bb_one($pdo,'SELECT * FROM low_stock_notices WHERE inventory_id=?',[$stock['id']]);
                $notify = $low && (!$state || !(bool) $state['is_low']);
                if ($notify) notify_hospital($pdo,(int) $stock['hospital_id'],'Low blood stock',$stock['blood_group'].' is below its configured minimum.','inventory.php');
                bb_exec($pdo,'INSERT INTO low_stock_notices (inventory_id,is_low) VALUES (?,?) ON DUPLICATE KEY UPDATE is_low=VALUES(is_low)',[$stock['id'],$low?1:0]);
                return $notify;
            });
            if ($sent) ++$counts['low_stock'];
        }
        bb_exec($pdo,'UPDATE maintenance_runs SET finished_at=NOW(),summary=? WHERE id=?',[json_encode($counts),$runId]);
        audit_log($pdo,null,'Maintenance completed','System',null,json_encode($counts));
        return $counts;
    } finally {
        bb_one($pdo,'SELECT RELEASE_LOCK(?) AS released',[$lockName]);
    }
}

function maintenance_tick(PDO $pdo): void
{
    try {
        $last = bb_one($pdo,'SELECT started_at FROM maintenance_runs ORDER BY id DESC LIMIT 1');
        if (!$last || strtotime($last['started_at']) < time()-60) run_maintenance($pdo);
    } catch (Throwable $exception) { error_log('Maintenance: '.$exception->getMessage()); }
}
