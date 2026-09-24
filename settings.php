<?php
/** File purpose: Settings handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['admin']);
$pdo=db();$errors=[];
$fields=[
 'donation_interval_days'=>['Donation interval days (centre policy)',56,365,120],
 'reminder_days'=>['Reminder after donation (days)',30,365,105],
 'request_expiry_hours'=>['New blood request lifetime (hours)',1,168,72],
 'reservation_expiry_hours'=>['New reservation lifetime (hours)',1,168,48],
 'session_timeout_minutes'=>['Inactivity logout (minutes)',5,120,30],
];
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        if(($_POST['action']??'')==='maintenance') {
            $result=run_maintenance($pdo);
            flash('success','Maintenance completed: '.json_encode($result));redirect('settings.php');
        }
        $values=[];
        foreach($fields as $key=>$rule) {
            $n=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$rule[1],'max_range'=>$rule[2]]]);
            if($n===false || $n===null) throw new DomainException($rule[0].' must be between '.$rule[1].' and '.$rule[2].'.');
            $values[$key]=$n;
        }
        if($values['reminder_days']>$values['donation_interval_days']) throw new DomainException('Set reminder days no later than the configured interval.');
        $reason=trim((string)($_POST['reason']??''));
        if($reason==='' || strlen($reason)>300 || !isset($_POST['policy_confirmed'])) throw new DomainException('Confirm the policy and enter its reference/reason.');
        bb_transaction($pdo,function() use($pdo,$values,$reason):void {
            foreach($values as $key=>$value) bb_exec($pdo,'INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,(string)$value]);
            audit_log($pdo,(int)current_user()['id'],'Update system policy','System',null,$reason.' '.json_encode($values));
        });
        flash('success','Settings saved. Expiry durations apply to newly created requests/reservations.');redirect('settings.php');
    } catch(Throwable $e) { $errors[]=safe_error($e); }
}
$runs=bb_all($pdo,'SELECT * FROM maintenance_runs ORDER BY id DESC LIMIT 15');
$pageTitle='System settings';require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>System settings</h1><p>Local lab policy. Reminders never certify medical eligibility.</p></div></section>
<?php form_errors($errors); ?>
<section class="content-card form-card"><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save">
<?php foreach($fields as $key=>$rule): ?><label><span><?= e($rule[0]) ?></span><input type="number" name="<?= e($key) ?>" value="<?= app_setting($key,$rule[3]) ?>" min="<?= $rule[1] ?>" max="<?= $rule[2] ?>" required></label><?php endforeach; ?>
<label class="form-wide"><span>Policy reference / reason</span><input name="reason" maxlength="300" required></label><label class="checkbox-label form-wide"><input type="checkbox" name="policy_confirmed" required><span>These are demo settings, or have been reviewed against the responsible blood centre's policy. On-site screening is still mandatory.</span></label><div class="form-wide"><button class="button button-primary">Save Settings</button></div></form></section>
<section class="content-card top-gap"><h2>Automatic jobs</h2><p>Reminders are in-app alerts. A scheduled server task runs them even when no one visits; XAMPP must be running. During a local demonstration, logged-in visits also check once per minute.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-primary" name="action" value="maintenance">Run Due Jobs Now</button></form><p class="muted">This button does not advance dates or pretend that time has passed.</p><div class="table-wrap"><table><thead><tr><th>Started</th><th>Finished</th><th>Result</th></tr></thead><tbody><?php foreach($runs as $run): ?><tr><td><?= e($run['started_at']) ?></td><td><?= e($run['finished_at']?:'Incomplete / running') ?></td><td><?= e($run['summary']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
