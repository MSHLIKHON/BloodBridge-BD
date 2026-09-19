<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['seeker', 'donor', 'hospital', 'admin']);
$pdo = db();
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if($user['role']==='admin'){http_response_code(403);exit('Administrators can monitor reservations but cannot process collections or stock.');}
    $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    if (!$reservationId || !in_array($action, ['approve', 'reject', 'collect', 'cancel'], true)) $errors[] = 'Invalid reservation action.';
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $load = $pdo->prepare('SELECT br.*, h.name AS hospital_name FROM blood_reservations br JOIN hospitals h ON h.id = br.hospital_id WHERE br.id = ? FOR UPDATE');
            $load->execute([$reservationId]);
            $reservation = $load->fetch();
            if (!$reservation) throw new RuntimeException('Reservation not found.');
            if($action!=='cancel') { ensure_not_expired($reservation); require_verified_hospital($pdo,(int)$reservation['hospital_id']); }
            $isOwner = (int) $reservation['seeker_id'] === (int) $user['id'];
            $canReview = ($user['role'] === 'hospital' && (int) $user['hospital_id'] === (int) $reservation['hospital_id']);
            if (in_array($action, ['approve', 'reject', 'collect'], true) && !$canReview) throw new RuntimeException('You cannot review this reservation.');
            if ($action === 'cancel' && !$isOwner && !$canReview) throw new RuntimeException('You cannot cancel this reservation.');

            $stock = $pdo->prepare('SELECT * FROM blood_inventory WHERE hospital_id = ? AND blood_group = ? FOR UPDATE');
            $stock->execute([(int) $reservation['hospital_id'], $reservation['blood_group']]);
            $inventory = $stock->fetch();
            if (!$inventory) throw new RuntimeException('Inventory record not found.');

            if ($action === 'approve') {
                if ($reservation['status'] !== 'Pending') throw new RuntimeException('Only a pending reservation can be approved.');
                $available = (int) $inventory['units'] - (int) $inventory['reserved_units'];
                if ($available < (int) $reservation['units']) throw new RuntimeException('Not enough unreserved units are available.');
                $collectionCode = 'BB-' . random_int(100000, 999999);
                $pdo->prepare('UPDATE blood_inventory SET reserved_units = reserved_units + ? WHERE id = ?')->execute([(int) $reservation['units'], (int) $inventory['id']]);
                $pdo->prepare("UPDATE blood_reservations SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), collection_code = ? WHERE id = ?")->execute([(int) $user['id'], $collectionCode, $reservationId]);
                create_notification($pdo, (int) $reservation['seeker_id'], 'Reservation approved', $reservation['hospital_name'] . ' approved your ' . $reservation['blood_group'] . ' reservation. Collection code: ' . $collectionCode, 'reservations.php', 'Reservation');
            } elseif ($action === 'reject') {
                if ($reservation['status'] !== 'Pending') throw new RuntimeException('Only a pending reservation can be rejected.');
                $pdo->prepare("UPDATE blood_reservations SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $reservationId]);
                create_notification($pdo, (int) $reservation['seeker_id'], 'Reservation rejected', $reservation['hospital_name'] . ' could not approve the reservation.', 'reservations.php', 'Reservation');
            } elseif ($action === 'collect') {
                if(!hash_equals((string)$reservation['collection_code'],trim((string)($_POST['collection_code']??'')))) throw new DomainException('Enter the collection code provided by the seeker.');
                if ($reservation['status'] !== 'Approved') throw new RuntimeException('Only an approved reservation can be collected.');
                if ((int) $inventory['reserved_units'] < (int) $reservation['units'] || (int) $inventory['units'] < (int) $reservation['units']) throw new RuntimeException('Reserved stock is inconsistent. Run inventory review.');
                $newBalance = (int) $inventory['units'] - (int) $reservation['units'];
                $pdo->prepare('UPDATE blood_inventory SET units = units - ?, reserved_units = reserved_units - ? WHERE id = ?')->execute([(int) $reservation['units'], (int) $reservation['units'], (int) $inventory['id']]);
                $pdo->prepare("UPDATE blood_reservations SET status = 'Collected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $reservationId]);
                $pdo->prepare("INSERT INTO inventory_transactions (hospital_id, inventory_id, staff_user_id, reservation_id, transaction_type, unit_change, balance_after, note) VALUES (?, ?, ?, ?, 'Reservation Collected', ?, ?, ?)")->execute([(int) $reservation['hospital_id'], (int) $inventory['id'], (int) $user['id'], $reservationId, -(int) $reservation['units'], $newBalance, 'Collection code ' . $reservation['collection_code']]);
                create_notification($pdo, (int) $reservation['seeker_id'], 'Blood collected', 'Reservation #' . $reservationId . ' was completed by ' . $reservation['hospital_name'] . '.', 'reservations.php', 'Reservation');
            } else {
                if (!in_array($reservation['status'], ['Pending', 'Approved'], true)) throw new RuntimeException('This reservation can no longer be cancelled.');
                if ($reservation['status'] === 'Approved') {
                    if((int)$inventory['reserved_units']<(int)$reservation['units']) throw new DomainException('Reserved stock is inconsistent. Ask an administrator to review.');
                    $pdo->prepare('UPDATE blood_inventory SET reserved_units = reserved_units - ? WHERE id = ?')->execute([(int) $reservation['units'], (int) $inventory['id']]);
                }
                $pdo->prepare("UPDATE blood_reservations SET status = 'Cancelled', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $reservationId]);
                if (!$isOwner) create_notification($pdo, (int) $reservation['seeker_id'], 'Reservation cancelled', 'Reservation #' . $reservationId . ' was cancelled.', 'reservations.php', 'Reservation');
            }
            audit_log($pdo, (int) $user['id'], ucfirst($action) . ' reservation', 'BloodReservation', $reservationId, $reservation['hospital_name']);
            $pdo->commit();
            flash('success', 'Reservation updated successfully.');
            redirect('reservations.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }
    }
}

$where = [];
$params = [];
if (in_array($user['role'],['seeker','donor'],true)) { $where[] = 'br.seeker_id = ?'; $params[] = (int) $user['id']; }
elseif ($user['role'] === 'hospital') { $where[] = 'br.hospital_id = ?'; $params[] = (int) $user['hospital_id']; }
$sql = 'SELECT br.*, h.name AS hospital_name, h.location AS hospital_location, u.full_name AS seeker_name, u.phone AS seeker_phone FROM blood_reservations br JOIN hospitals h ON h.id = br.hospital_id JOIN users u ON u.id = br.seeker_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY FIELD(br.status, \'Pending\',\'Approved\',\'Collected\',\'Rejected\',\'Cancelled\'), br.created_at DESC';
$statement = $pdo->prepare($sql); $statement->execute($params); $reservations = $statement->fetchAll();
$pageTitle = 'Blood Reservations'; $enableLiveUpdates = true; require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Protected stock workflow</span><h1>Blood reservations</h1><p>Approval reserves stock; collection is the only step that reduces total units.</p></div></section>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="content-card"><div class="live-status"><span class="live-dot"></span><span>Reservation status updates automatically</span></div><?php if (!$reservations): ?><div class="empty-state"><strong>No reservations found.</strong><span>Blood-bank reservations will appear here.</span></div><?php else: ?><div class="review-list"><?php foreach ($reservations as $reservation): $canReview = ($user['role'] === 'hospital' && (int) $user['hospital_id'] === (int) $reservation['hospital_id']); $isOwner = (int) $reservation['seeker_id'] === (int) $user['id']; ?><article class="review-card"><div><strong>#<?= (int) $reservation['id'] ?> • <?= e($reservation['blood_group']) ?> • <?= (int) $reservation['units'] ?> unit(s)</strong><span><?= e($reservation['hospital_name']) ?></span><small>Requested by <?= e($reservation['seeker_name']) ?> • <?= e(date('d M Y, h:i A', strtotime($reservation['created_at']))) ?></small><?php if ($reservation['note']): ?><small>Note: <?= e($reservation['note']) ?></small><?php endif; ?><?php if ($canReview): ?><small>Contact: <?= e($reservation['seeker_phone']) ?></small><?php endif; ?><?php if ($reservation['collection_code'] && ($isOwner || $canReview)): ?><div class="code-chip">Collection code: <?= e($reservation['collection_code']) ?></div><?php endif; ?></div><span class="badge <?= request_status_class($reservation['status']) ?>"><?= e($reservation['status']) ?></span><div class="review-actions"><?php if ($canReview && $reservation['status'] === 'Pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>"><button class="button button-primary" name="action" value="approve">Approve</button><button class="button button-secondary" name="action" value="reject">Reject</button></form><?php elseif ($canReview && $reservation['status'] === 'Approved'): ?><form method="post" data-confirm="Confirm that blood was collected?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>"><label><span>Seeker collection code</span><input name="collection_code" maxlength="20" required autocomplete="off"></label><button class="button button-primary" name="action" value="collect">Confirm Collection</button></form><?php endif; ?><?php if (($isOwner || $canReview) && in_array($reservation['status'], ['Pending', 'Approved'], true)): ?><form method="post" data-confirm="Cancel this reservation?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>"><button class="text-link-button danger" name="action" value="cancel">Cancel</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
