<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_role(['admin']);
$pdo=db(); $errors=[];
$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
$kind=(string)($_GET['kind']??'Requests');
$kinds=['Requests','Donations','Stock','Clubs','Audit'];
if(!valid_past_date($from) || !valid_past_date($to) || $from>$to) { http_response_code(422);exit('Choose valid past dates with From before To.'); }
if(!in_array($kind,$kinds,true)) { http_response_code(422);exit('Invalid report type.'); }
$params=[$from.' 00:00:00',$to.' 23:59:59'];
$sql=match($kind) {
 'Requests'=>'SELECT id,blood_group,units,source_type,status,outcome,accepted_by,donor_confirmed_at,donor_reported_at,prescription_status,created_at FROM blood_requests WHERE created_at BETWEEN ? AND ? ORDER BY id DESC',
 'Donations'=>'SELECT id,donor_id,hospital_id,request_id,direct_donation_id,blood_group,units,donation_date FROM donation_history WHERE donation_date BETWEEN DATE(?) AND DATE(?) ORDER BY id DESC',
 'Audit'=>'SELECT a.id,a.created_at,a.user_id,a.action,a.entity_type,a.entity_id,a.details FROM audit_logs a WHERE a.created_at BETWEEN ? AND ? ORDER BY a.id DESC',
 'Stock'=>'SELECT h.name,bi.blood_group,bi.units,bi.reserved_units,bi.units-bi.reserved_units AS available,bi.low_stock_threshold FROM blood_inventory bi JOIN hospitals h ON h.id=bi.hospital_id ORDER BY h.name,bi.blood_group',
 'Clubs'=>"SELECT c.id,c.name,c.university,c.status,(SELECT COUNT(*) FROM club_members m WHERE m.club_id=c.id AND m.status='Approved') AS approved_members,(SELECT COUNT(*) FROM direct_donations d WHERE d.club_id=c.id AND d.status='Completed') AS confirmed_donations FROM clubs c ORDER BY c.id",
};
if(in_array($kind,['Stock','Clubs'],true)) $params=[];
$export=($_GET['export']??'')==='csv';$limit=$export?5000:200;
$rows=bb_all($pdo,$sql.' LIMIT '.$limit,$params);
if($export) {
    audit_log($pdo,(int)current_user()['id'],'Export report','Report',null,$kind.' '.$from.' to '.$to);
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="bloodbridge-'.strtolower($kind).'.csv"');header('Cache-Control: no-store');
    $out=fopen('php://output','w');
    if($rows) {
        fputcsv($out,array_keys($rows[0]));
        foreach($rows as $row) {
            $safe=array_map(static function($value):string { $s=(string)$value; return preg_match('/^[\s]*[=+@-]/u',$s)?"'".$s:$s; },$row);
            fputcsv($out,$safe);
        }
    } else fputcsv($out,['No matching records']);
    fclose($out);exit;
}
$counts=bb_one($pdo,"SELECT (SELECT COUNT(*) FROM users WHERE donor_enabled=1 AND account_status='Active') AS donors,(SELECT COUNT(*) FROM blood_requests) AS requests,(SELECT COUNT(*) FROM donation_history) AS donations,(SELECT COUNT(*) FROM clubs WHERE status='Approved') AS clubs");
$pageTitle='Reports & audit'; require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>Reports &amp; audit</h1><p>All-time totals: <?= (int)$counts['donors'] ?> active donors · <?= (int)$counts['requests'] ?> requests · <?= (int)$counts['donations'] ?> recorded donations · <?= (int)$counts['clubs'] ?> approved clubs.</p></div></section>
<section class="filter-card"><form method="get" class="filter-form"><label><span>Report</span><select name="kind"><?php foreach($kinds as $k): ?><option <?= $kind===$k?'selected':'' ?>><?= e($k) ?></option><?php endforeach; ?></select></label><label><span>From</span><input type="date" name="from" value="<?= e($from) ?>" required></label><label><span>To</span><input type="date" name="to" value="<?= e($to) ?>" required></label><button class="button button-primary">View</button><button class="button button-secondary" name="export" value="csv">Export CSV</button></form></section>
<section class="content-card"><h2><?= e($kind) ?></h2><p class="muted">Page: latest 200 rows. CSV: up to 5,000 rows; narrow dates for larger datasets. Stock and club summaries are current snapshots, independent of dates. No prescription or test-result contents are exported.</p><?php if(!$rows): ?><div class="empty-state">No matching records.</div><?php else: ?><div class="table-wrap"><table><thead><tr><?php foreach(array_keys($rows[0]) as $column): ?><th><?= e(str_replace('_',' ',$column)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $value): ?><td><?= e((string)$value) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
