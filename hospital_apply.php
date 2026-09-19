<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
if (logged_in()) redirect('dashboard.php');
if (!database_ready()) redirect('setup.php');

$pdo = db();
$hospitals = $pdo->query("SELECT id, name, location FROM hospitals WHERE status = 'Verified' ORDER BY name")->fetchAll();
$errors = [];
$values = [
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'hospital_id' => (string) ($_POST['hospital_id'] ?? ''),
    'employee_id' => strtoupper(trim((string) ($_POST['employee_id'] ?? ''))),
    'designation' => trim((string) ($_POST['designation'] ?? '')),
    'department' => trim((string) ($_POST['department'] ?? 'Blood Bank')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $hospitalCode = trim((string) ($_POST['hospital_code'] ?? ''));
    $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
    $phone = normalize_bd_phone($values['phone']);

    if ($values['full_name'] === '' || strlen($values['full_name']) < 3 || strlen($values['full_name']) > 120) $errors[] = 'Enter a valid full name.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 160) $errors[] = 'Enter a valid official email.';
    if ($phone === null) $errors[] = 'Enter a valid Bangladesh mobile number.';
    if (!$hospitalId) $errors[] = 'Choose your hospital.';
    if (!preg_match('/^[A-Z0-9-]{3,80}$/', $values['employee_id'])) $errors[] = 'Employee ID may contain letters, numbers and hyphens.';
    if ($values['designation'] === '' || strlen($values['designation']) > 120) $errors[] = 'Enter your designation.';
    if (strlen($values['department']) > 120) $errors[] = 'Department name is too long.';
    $errors = array_merge($errors, password_validation_errors($password));
    if ($password !== $confirmation) $errors[] = 'Passwords do not match.';

    $hospital = null;
    if ($hospitalId) {
        $hospitalLookup = $pdo->prepare("SELECT id, name, verification_code_hash FROM hospitals WHERE id = ? AND status = 'Verified'");
        $hospitalLookup->execute([$hospitalId]);
        $hospital = $hospitalLookup->fetch();
        if (!$hospital) $errors[] = 'The selected hospital is not approved.';
        elseif (!$hospital['verification_code_hash'] || !password_verify($hospitalCode, (string) $hospital['verification_code_hash'])) $errors[] = 'Hospital verification code is incorrect.';
    }

    if (!$errors) {
        $duplicate = $pdo->prepare(
            'SELECT id FROM users WHERE email = ? OR phone = ?
             UNION SELECT user_id FROM hospital_staff_applications WHERE hospital_id = ? AND employee_id = ? LIMIT 1'
        );
        $duplicate->execute([$values['email'], $phone, $hospitalId, $values['employee_id']]);
        if ($duplicate->fetchColumn()) {
            $errors[] = 'This email, phone number or hospital employee ID is already registered.';
        } else {
            try {
                $pdo->beginTransaction();
                $insertUser = $pdo->prepare(
                    "INSERT INTO users
                     (hospital_id, full_name, email, password_hash, role, location, phone,
                      account_status, email_verified, phone_verified)
                     VALUES (?, ?, ?, ?, 'hospital', ?, ?, 'Pending', 0, 0)"
                );
                $insertUser->execute([$hospitalId, $values['full_name'], $values['email'], password_hash($password, PASSWORD_DEFAULT), $hospital['name'] . ', ' . $values['department'], $phone]);
                $userId = (int) $pdo->lastInsertId();

                $insertApplication = $pdo->prepare(
                    'INSERT INTO hospital_staff_applications
                     (user_id, hospital_id, employee_id, designation, department)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertApplication->execute([$userId, $hospitalId, $values['employee_id'], $values['designation'], $values['department'] ?: null]);
                $applicationId = (int) $pdo->lastInsertId();
                create_verification_code($pdo, $userId);

                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND account_status = 'Active'")->fetchAll();
                foreach ($admins as $admin) {
                    create_notification($pdo, (int) $admin['id'], 'Hospital staff approval needed', $values['full_name'] . ' applied under ' . $hospital['name'] . '.', 'admin_hospitals.php', 'Approval');
                }
                audit_log($pdo, $userId, 'Hospital staff application', 'HospitalApplication', $applicationId, $hospital['name']);
                $pdo->commit();
                redirect('verify_account.php?email=' . urlencode($values['email']));
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Hospital application failed: ' . $exception->getMessage());
                $errors[] = 'Application could not be saved. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hospital Staff Application | BloodBridge BD</title><link rel="stylesheet" href="assets/css/style.css?v=70"></head>
<body class="auth-body hospital-auth">
<main class="auth-layout application-layout">
    <section class="auth-message hospital-message">
        <span class="eyebrow">Hospital verification</span><h1>Apply for authorized access.</h1>
        <p>Hospital staff must verify their hospital code, contact information and employee identity before Admin approval.</p>
        <div class="verification-steps"><span>Hospital code checked</span><span>Demo OTP verified</span><span>Admin reviews application</span></div>
    </section>
    <section class="auth-card register-card">
        <div class="auth-brand"><span class="brand-mark hospital-mark"><span>+</span></span><strong>Hospital Staff Application</strong></div>
        <h2>Employment information</h2>
        <?php if ($errors): ?><div class="alert alert-error"><strong>Please fix:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="form-wide"><span>Full name</span><input type="text" name="full_name" value="<?= e($values['full_name']) ?>" minlength="3" maxlength="120" required></label>
            <label><span>Official email</span><input type="email" name="email" value="<?= e($values['email']) ?>" maxlength="160" required></label>
            <label><span>Bangladesh mobile</span><input type="tel" name="phone" value="<?= e($values['phone']) ?>" placeholder="01712345678" required></label>
            <label class="form-wide"><span>Hospital</span><select name="hospital_id" required><option value="">Choose verified hospital</option><?php foreach ($hospitals as $hospitalOption): ?><option value="<?= (int) $hospitalOption['id'] ?>" <?= (int) $values['hospital_id'] === (int) $hospitalOption['id'] ? 'selected' : '' ?>><?= e($hospitalOption['name']) ?> — <?= e($hospitalOption['location']) ?></option><?php endforeach; ?></select></label>
            <label><span>Employee ID</span><input type="text" name="employee_id" value="<?= e($values['employee_id']) ?>" pattern="[A-Za-z0-9-]{3,80}" maxlength="80" placeholder="DMCH-1234" required></label>
            <label><span>Hospital verification code</span><input type="password" name="hospital_code" maxlength="80" required></label>
            <label><span>Designation</span><input type="text" name="designation" value="<?= e($values['designation']) ?>" maxlength="120" placeholder="Blood Bank Officer" required></label>
            <label><span>Department</span><input type="text" name="department" value="<?= e($values['department']) ?>" maxlength="120"></label>
            <label><span>Strong password</span><input type="password" name="password" minlength="8" maxlength="128" placeholder="Example: Hospital@123" required></label>
            <label><span>Confirm password</span><input type="password" name="password_confirmation" minlength="8" maxlength="128" required></label>
            <div class="form-wide"><button class="button button-hospital button-full" type="submit">Verify Code &amp; Submit Application</button></div>
        </form>
        <p class="auth-switch"><a href="hospital_login.php">Back to Hospital Staff Login</a></p>
    </section>
</main>
</body>
</html>
