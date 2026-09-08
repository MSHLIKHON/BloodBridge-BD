<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_role(['admin']);

$pdo = db();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if (in_array($action, ['approve', 'reject'], true)) {
        $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
        $note = trim((string) ($_POST['review_note'] ?? ''));
        if (!$applicationId) $errors[] = 'Invalid application.';
        if (strlen($note) > 500) $errors[] = 'Review note must be within 500 characters.';
        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $load = $pdo->prepare(
                    'SELECT hsa.*, u.full_name, u.email_verified, u.phone_verified, h.name AS hospital_name
                     FROM hospital_staff_applications hsa JOIN users u ON u.id = hsa.user_id
                     JOIN hospitals h ON h.id = hsa.hospital_id WHERE hsa.id = ? FOR UPDATE'
                );
                $load->execute([$applicationId]);
                $application = $load->fetch();
                if (!$application || $application['status'] !== 'Pending') throw new RuntimeException('Application is not pending.');
                $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
                $pdo->prepare('UPDATE hospital_staff_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?')
                    ->execute([$newStatus, (int) current_user()['id'], $note ?: null, $applicationId]);
                $accountStatus = $action === 'approve' && $application['email_verified'] && $application['phone_verified'] ? 'Active' : ($action === 'approve' ? 'Pending' : 'Rejected');
                $pdo->prepare('UPDATE users SET account_status = ?, hospital_id = ? WHERE id = ?')
                    ->execute([$accountStatus, (int) $application['hospital_id'], (int) $application['user_id']]);
                create_notification($pdo, (int) $application['user_id'], 'Hospital application ' . strtolower($newStatus), $action === 'approve' ? 'Your staff account is approved for ' . $application['hospital_name'] . '.' : 'Your staff application was rejected. ' . ($note ?: ''), 'hospital_login.php', 'Approval');
                audit_log($pdo, (int) current_user()['id'], ucfirst($action) . ' hospital staff', 'HospitalApplication', $applicationId, $application['full_name']);
                $pdo->commit();
                flash('success', 'Application ' . strtolower($newStatus) . ' successfully.');
                redirect('admin_hospitals.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($action === 'add_hospital') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $registration = strtoupper(trim((string) ($_POST['registration_number'] ?? '')));
        $location = trim((string) ($_POST['location'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        if ($name === '' || strlen($name) > 160) $errors[] = 'Enter a valid hospital name.';
        if ($registration === '' || strlen($registration) > 80) $errors[] = 'Enter a registration number.';
        if ($location === '' || strlen($location) > 120) $errors[] = 'Enter a valid location.';
        if (!preg_match('/^[0-9+() -]{7,30}$/', $phone)) $errors[] = 'Enter a valid hospital phone number.';
        if (strlen($code) < 8) $errors[] = 'Hospital verification code must contain at least 8 characters.';
        if (!$errors) {
            try {
                $insert = $pdo->prepare(
                    "INSERT INTO hospitals (name, registration_number, location, phone, verification_code_hash, status)
                     VALUES (?, ?, ?, ?, ?, 'Verified')"
                );
                $insert->execute([$name, $registration, $location, $phone, password_hash($code, PASSWORD_DEFAULT)]);
                $hospitalId = (int) $pdo->lastInsertId();
                audit_log($pdo, (int) current_user()['id'], 'Create hospital', 'Hospital', $hospitalId, $name);
                flash('success', 'Hospital created. Give this private staff code to authorized staff: ' . $code);
                redirect('admin_hospitals.php');
            } catch (Throwable $exception) {
                $errors[] = 'Hospital could not be created. Registration number may already exist.';
            }
        }
    }
}

$applications = $pdo->query(
    "SELECT hsa.*, u.full_name, u.email, u.phone, u.email_verified, u.phone_verified, h.name AS hospital_name
     FROM hospital_staff_applications hsa JOIN users u ON u.id = hsa.user_id
     JOIN hospitals h ON h.id = hsa.hospital_id ORDER BY FIELD(hsa.status, 'Pending', 'Approved', 'Rejected'), hsa.created_at DESC"
)->fetchAll();
$pendingApplicationCount = count(array_filter($applications, static fn(array $application): bool => $application['status'] === 'Pending'));
$hospitals = $pdo->query(
    "SELECT h.*, COUNT(u.id) AS staff_count FROM hospitals h LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' AND u.account_status = 'Active' GROUP BY h.id ORDER BY h.name"
)->fetchAll();

$pageTitle = 'Hospital Verification';
$enableLiveUpdates = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Administrator control</span><h1>Hospitals &amp; staff verification</h1><p>Approve staff only after checking their hospital and employee information.</p></div></section>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="detail-grid">
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Pending review</span><h2>Staff applications</h2></div><span class="count-pill"><?= $pendingApplicationCount ?> pending</span></div>
        <?php if (!$applications): ?><div class="empty-state">No staff applications.</div><?php else: ?><div class="review-list"><?php foreach ($applications as $application): ?><article class="review-card"><div><strong><?= e($application['full_name']) ?></strong><span><?= e($application['hospital_name']) ?></span><small><?= e($application['designation']) ?> • ID <?= e($application['employee_id']) ?></small><small><?= e($application['email']) ?> • <?= e($application['phone']) ?></small><small class="<?= $application['email_verified'] && $application['phone_verified'] ? 'positive' : 'danger-text' ?>"><?= $application['email_verified'] && $application['phone_verified'] ? 'Contact verification completed' : 'Contact verification waiting' ?></small></div><span class="badge <?= request_status_class($application['status']) ?>"><?= e($application['status']) ?></span><?php if ($application['status'] === 'Pending'): ?><form method="post" class="review-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><input type="text" name="review_note" maxlength="500" placeholder="Optional review note"><button class="button button-primary" name="action" value="approve">Approve</button><button class="button button-secondary" name="action" value="reject">Reject</button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Verified network</span><h2>Registered hospitals</h2></div></div>
        <div class="review-list"><?php foreach ($hospitals as $hospital): ?><article class="review-card compact"><div><strong><?= e($hospital['name']) ?></strong><span><?= e($hospital['location']) ?></span><small><?= (int) $hospital['staff_count'] ?> active staff • <?= e($hospital['registration_number'] ?: 'No registration number') ?></small></div><span class="badge <?= request_status_class($hospital['status']) ?>"><?= e($hospital['status']) ?></span></article><?php endforeach; ?></div>
    </section>
</div>

<section class="content-card form-card top-gap"><div class="section-heading"><div><span class="eyebrow">Admin only</span><h2>Add verified hospital</h2></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_hospital"><label class="form-wide"><span>Hospital / Blood-bank name</span><input type="text" name="name" maxlength="160" required></label><label><span>Registration number</span><input type="text" name="registration_number" maxlength="80" required></label><label><span>Phone</span><input type="text" name="phone" maxlength="30" required></label><label class="form-wide"><span>Location</span><input type="text" name="location" list="hospital-locations" maxlength="120" required><?php render_location_datalist('hospital-locations'); ?></label><label class="form-wide"><span>Private staff verification code</span><input type="text" name="verification_code" minlength="8" maxlength="80" required><small>Share only with authorized employees.</small></label><div class="form-wide"><button class="button button-primary" type="submit">Add Hospital</button></div></form></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
