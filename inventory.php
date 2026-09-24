<?php
/** File purpose: Inventory handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['hospital', 'admin']);
$pdo = db();
$user = current_user();
$hospitals = $pdo->query("SELECT id, name, location FROM hospitals WHERE status = 'Verified' ORDER BY name")->fetchAll();
$selectedHospitalId = $user['role'] === 'hospital'
    ? (int) ($user['hospital_id'] ?? 0)
    : (int) ($_GET['hospital_id'] ?? $_POST['hospital_id'] ?? ($hospitals[0]['id'] ?? 0));
if (!$selectedHospitalId || !can_manage_hospital($selectedHospitalId)) {
    http_response_code(403);
    exit('No approved hospital is linked to this account.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if($user['role']!=='hospital'){http_response_code(403);exit('Only hospital staff can record stock transactions.');}
    $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
    $bloodGroup = (string) ($_POST['blood_group'] ?? '');
    $type = (string) ($_POST['transaction_type'] ?? '');
    $operation = (string) ($_POST['operation'] ?? 'Add');
    $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    $note = trim((string) ($_POST['note'] ?? ''));
    if (!$hospitalId || !can_manage_hospital((int) $hospitalId)) $errors[] = 'You cannot manage this hospital.';
    if (!in_array($bloodGroup, valid_blood_groups(), true)) $errors[] = 'Choose a valid blood group.';
    if (!in_array($type, ['Donation Received', 'Patient Supplied', 'Expired', 'Correction'], true)) $errors[] = 'Choose a valid transaction type.';
    if (!in_array($operation, ['Add', 'Remove'], true)) $errors[] = 'Choose Add or Remove.';
    if ($type === 'Donation Received' && $operation !== 'Add') $errors[] = 'Donation received must add stock.';
    if (in_array($type, ['Patient Supplied', 'Expired'], true) && $operation !== 'Remove') $errors[] = 'This transaction must remove stock.';
    if ($units === false || $units === null) $errors[] = 'Units must be between 1 and 100.';
    if (strlen($note) > 300) $errors[] = 'Note must be within 300 characters.';
    if ($note==='') $errors[] = 'Record a reason/reference for this stock change.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            require_verified_hospital($pdo,(int)$hospitalId);
            $load = $pdo->prepare('SELECT * FROM blood_inventory WHERE hospital_id = ? AND blood_group = ? FOR UPDATE');
            $load->execute([$hospitalId, $bloodGroup]);
            $inventory = $load->fetch();
            if (!$inventory) {
                if ($operation === 'Remove') throw new RuntimeException('No stock exists for this blood group.');
                $pdo->prepare('INSERT INTO blood_inventory (hospital_id, blood_group, units, reserved_units) VALUES (?, ?, 0, 0)')->execute([$hospitalId, $bloodGroup]);
                $inventoryId = (int) $pdo->lastInsertId();
                $currentUnits = 0;
                $reservedUnits = 0;
            } else {
                $inventoryId = (int) $inventory['id'];
                $currentUnits = (int) $inventory['units'];
                $reservedUnits = (int) $inventory['reserved_units'];
            }
            $change = $operation === 'Add' ? (int) $units : -(int) $units;
            $newBalance = $currentUnits + $change;
            if ($newBalance < $reservedUnits) throw new RuntimeException('Cannot reduce stock below the reserved units.');
            $pdo->prepare('UPDATE blood_inventory SET units = ? WHERE id = ?')->execute([$newBalance, $inventoryId]);
            $pdo->prepare(
                'INSERT INTO inventory_transactions
                 (hospital_id, inventory_id, staff_user_id, transaction_type, unit_change, balance_after, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$hospitalId, $inventoryId, (int) $user['id'], $type, $change, $newBalance, $note ?: null]);
            audit_log($pdo, (int) $user['id'], 'Update inventory', 'BloodInventory', $inventoryId, $bloodGroup . ' ' . ($change > 0 ? '+' : '') . $change);
            $pdo->commit();
            flash('success', $bloodGroup . ' stock updated to ' . $newBalance . ' unit(s).');
            redirect('inventory.php' . ($user['role'] === 'admin' ? '?hospital_id=' . $hospitalId : ''));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }
    }
}

$hospitalStatement = $pdo->prepare('SELECT * FROM hospitals WHERE id = ?');
$hospitalStatement->execute([$selectedHospitalId]);
$hospital = $hospitalStatement->fetch();
$stockStatement = $pdo->prepare('SELECT *, GREATEST(units - reserved_units, 0) AS available_units FROM blood_inventory WHERE hospital_id = ? ORDER BY FIELD(blood_group, \'A+\',\'A-\',\'B+\',\'B-\',\'AB+\',\'AB-\',\'O+\',\'O-\')');
$stockStatement->execute([$selectedHospitalId]);
$inventory = $stockStatement->fetchAll();
$transactionStatement = $pdo->prepare(
    'SELECT it.*, bi.blood_group, u.full_name AS staff_name FROM inventory_transactions it
     JOIN blood_inventory bi ON bi.id = it.inventory_id JOIN users u ON u.id = it.staff_user_id
     WHERE it.hospital_id = ? ORDER BY it.created_at DESC LIMIT 30'
);
$transactionStatement->execute([$selectedHospitalId]);
$transactions = $transactionStatement->fetchAll();
$pageTitle = 'Blood Inventory';
$enableLiveUpdates = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Hospital blood bank</span><h1>Inventory management</h1><p><?= e($hospital['name'] ?? 'Hospital') ?> • <?= e($hospital['location'] ?? '') ?></p></div></section>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($user['role'] === 'admin'): ?><section class="filter-card"><form method="get" class="filter-form"><label><span>Hospital</span><select name="hospital_id"><?php foreach ($hospitals as $hospitalOption): ?><option value="<?= (int) $hospitalOption['id'] ?>" <?= $selectedHospitalId === (int) $hospitalOption['id'] ? 'selected' : '' ?>><?= e($hospitalOption['name']) ?></option><?php endforeach; ?></select></label><button class="button button-secondary" type="submit">View Inventory</button></form></section><?php endif; ?>

<section class="inventory-grid"><?php foreach (valid_blood_groups() as $group): $row = null; foreach ($inventory as $item) if ($item['blood_group'] === $group) $row = $item; $available = $row ? (int) $row['available_units'] : 0; $reserved = $row ? (int) $row['reserved_units'] : 0; ?><article class="stock-card <?= $available === 0 ? 'stock-empty' : ($available < (int)($row['low_stock_threshold'] ?? 2) ? 'stock-low' : '') ?>"><span class="blood-icon"><?= e($group) ?></span><div><strong><?= $available ?></strong><span>available</span><small><?= $reserved ?> reserved • <?= $row ? (int) $row['units'] : 0 ?> total</small></div></article><?php endforeach; ?></section>

<?php if($user['role']==='hospital'): ?><div class="detail-grid top-gap">
    <section class="content-card"><div class="section-heading"><div><span class="eyebrow">Authorized adjustment</span><h2>Record stock transaction</h2></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="hospital_id" value="<?= $selectedHospitalId ?>"><label><span>Blood group</span><select name="blood_group"><?php foreach (valid_blood_groups() as $group): ?><option><?= e($group) ?></option><?php endforeach; ?></select></label><label><span>Transaction type</span><select name="transaction_type"><option>Donation Received</option><option>Patient Supplied</option><option>Expired</option><option>Correction</option></select></label><label><span>Operation</span><select name="operation"><option>Add</option><option>Remove</option></select></label><label><span>Units</span><input type="number" name="units" min="1" max="100" value="1" required></label><label class="form-wide"><span>Reason / note</span><textarea name="note" maxlength="300" rows="3" placeholder="Donation reference, patient supply or correction reason"></textarea></label><div class="form-wide"><button class="button button-primary" type="submit">Save Transaction</button></div></form></section>
    <section class="content-card"><div class="section-heading"><div><span class="eyebrow">Control rules</span><h2>Inventory protection</h2></div></div><ul class="feature-checklist"><li>Every change records staff, time and reason.</li><li>Stock cannot become negative.</li><li>Reserved units cannot be issued manually.</li><li>Reservation collection updates stock automatically.</li><li>Two simultaneous approvals cannot reserve the same unit.</li></ul></section>
</div><?php endif; ?>

<section class="content-card top-gap"><div class="section-heading"><div><span class="eyebrow">Audit trail</span><h2>Recent stock transactions</h2></div></div><?php if (!$transactions): ?><div class="empty-state">No inventory transaction recorded yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Time</th><th>Blood</th><th>Type</th><th>Change</th><th>Balance</th><th>Staff</th><th>Note</th></tr></thead><tbody><?php foreach ($transactions as $transaction): ?><tr><td><?= e(date('d M, h:i A', strtotime($transaction['created_at']))) ?></td><td><strong class="blood-group"><?= e($transaction['blood_group']) ?></strong></td><td><?= e($transaction['transaction_type']) ?></td><td class="<?= (int) $transaction['unit_change'] > 0 ? 'positive' : 'negative' ?>"><?= (int) $transaction['unit_change'] > 0 ? '+' : '' ?><?= (int) $transaction['unit_change'] ?></td><td><?= (int) $transaction['balance_after'] ?></td><td><?= e($transaction['staff_name']) ?></td><td><?= e($transaction['note'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
