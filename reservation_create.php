<?php
/** File purpose: Reservation Create handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['seeker', 'donor']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
verify_csrf();

$hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
$bloodGroup = (string) ($_POST['blood_group'] ?? '');
$units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
$note = trim((string) ($_POST['note'] ?? ''));
if (!$hospitalId || !in_array($bloodGroup, valid_blood_groups(), true) || $units === false || $units === null || strlen($note) > 500) {
    flash('error', 'Invalid reservation information.');
    redirect('search.php');
}

$pdo = db();
$pdo->beginTransaction();
try {
    require_verified_hospital($pdo,(int)$hospitalId);
    $stock = $pdo->prepare('SELECT bi.*, h.name AS hospital_name FROM blood_inventory bi JOIN hospitals h ON h.id = bi.hospital_id WHERE bi.hospital_id = ? AND bi.blood_group = ? FOR UPDATE');
    $stock->execute([$hospitalId, $bloodGroup]);
    $inventory = $stock->fetch();
    if (!$inventory || ((int) $inventory['units'] - (int) $inventory['reserved_units']) < (int) $units) throw new RuntimeException('Requested units are no longer available.');
    $insert = $pdo->prepare('INSERT INTO blood_reservations (seeker_id, hospital_id, blood_group, units, note) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([(int) current_user()['id'], $hospitalId, $bloodGroup, $units, $note ?: null]);
    $reservationId = (int) $pdo->lastInsertId();
    $expiryHours=app_setting('reservation_expiry_hours',48);
    bb_exec($pdo,"UPDATE blood_reservations SET expires_at=DATE_ADD(NOW(),INTERVAL $expiryHours HOUR) WHERE id=?",[$reservationId]);
    $staff = $pdo->prepare("SELECT id FROM users WHERE role = 'hospital' AND hospital_id = ? AND account_status = 'Active'");
    $staff->execute([$hospitalId]);
    foreach ($staff->fetchAll() as $staffUser) create_notification($pdo, (int) $staffUser['id'], 'New blood reservation', $bloodGroup . ' • ' . $units . ' unit(s) requested.', 'reservations.php', 'Reservation');
    audit_log($pdo, (int) current_user()['id'], 'Create reservation', 'BloodReservation', $reservationId, $inventory['hospital_name']);
    $pdo->commit();
    flash('success', 'Reservation #' . $reservationId . ' sent to ' . $inventory['hospital_name'] . '.');
    redirect('reservations.php');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $exception->getMessage());
    redirect('search.php?blood_group=' . urlencode($bloodGroup));
}
