<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';

if (logged_in()) redirect('dashboard.php');

$errors = [];
$ready = database_ready();
$values = [
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'role' => (string) ($_POST['role'] ?? 'seeker'),
    'blood_group' => (string) ($_POST['blood_group'] ?? ''),
    'division' => trim((string) ($_POST['division'] ?? '')),
    'district' => trim((string) ($_POST['district'] ?? '')),
    'upazila' => trim((string) ($_POST['upazila'] ?? '')),
    'location' => '',
    'phone' => trim((string) ($_POST['phone'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $phone = normalize_bd_phone($values['phone']);

    if (!$ready) $errors[] = 'Database is not ready. Run setup.php first.';
    if ($values['full_name'] === '' || strlen($values['full_name']) < 3 || strlen($values['full_name']) > 120) $errors[] = 'Enter a full name between 3 and 120 characters.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 160) $errors[] = 'Enter a valid email address.';
    if (!in_array($values['role'], ['donor', 'seeker'], true)) $errors[] = 'Choose Donor or Blood Seeker.';
    if ($values['role'] === 'donor' && !in_array($values['blood_group'], valid_blood_groups(), true)) $errors[] = 'Choose your blood group.';
    if (!valid_bangladesh_location($values['division'], $values['district'], $values['upazila'])) {
        $errors[] = 'Choose a valid Division, District and Upazila / Area.';
    } else {
        $values['location'] = format_bangladesh_location($values['division'], $values['district'], $values['upazila']);
    }
    if ($phone === null) $errors[] = 'Enter a valid Bangladesh mobile number, for example 01712345678.';
    $errors = array_merge($errors, password_validation_errors($password));
    if ($password !== $passwordConfirmation) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $existing = db()->prepare('SELECT email, phone FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $existing->execute([$values['email'], $phone]);
        $duplicate = $existing->fetch();
        if ($duplicate) {
            $errors[] = $duplicate['email'] === $values['email']
                ? 'An account with this email already exists.'
                : 'An account with this phone number already exists.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    "INSERT INTO users
                     (full_name, email, password_hash, role, blood_group, location, phone,
                      account_status, email_verified, phone_verified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 0, 0)"
                );
                $insert->execute([
                    $values['full_name'], $values['email'], password_hash($password, PASSWORD_DEFAULT),
                    $values['role'], $values['role'] === 'donor' ? $values['blood_group'] : null,
                    $values['location'], $phone,
                ]);
                $newUserId = (int) $pdo->lastInsertId();
                bb_exec($pdo,'UPDATE users SET donor_enabled=? WHERE id=?',[$values['role']==='donor'?1:0,$newUserId]);
                create_verification_code($pdo, $newUserId);
                audit_log($pdo, $newUserId, 'Account registered', 'User', $newUserId, role_label($values['role']));
                $pdo->commit();
                redirect('verify_account.php?email=' . urlencode($values['email']));
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Registration save failed: ' . $exception->getMessage());
                $errors[] = 'Account could not be saved. Please run setup and try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create Account | BloodBridge BD</title><link rel="stylesheet" href="assets/css/style.css?v=72"></head>
<body class="auth-body">
<main class="auth-layout">
    <section class="auth-message">
        <span class="eyebrow">Secure registration</span>
        <h1>Join the blood network.</h1>
        <p>Create a verified donor or blood-seeker account. Hospital staff use a separate application and approval process.</p>
        <div class="progress-card"><strong>Protected by validation</strong><span>Unique email and phone • Strong password • Demo OTP verification</span></div>
    </section>
    <section class="auth-card register-card">
        <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>Create Account</strong></div>
        <h2>Donor or Blood Seeker</h2>
        <p class="muted">All fields are checked before the account is activated.</p>
        <?php if (!$ready): ?><div class="alert alert-error">Database is not ready. <a href="setup.php">Run setup now</a>.</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><strong>Please fix:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post" class="form-grid" data-registration-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="form-wide"><span>Full name</span><input type="text" name="full_name" minlength="3" maxlength="120" value="<?= e($values['full_name']) ?>" required></label>
            <label><span>Email address</span><input type="email" name="email" maxlength="160" autocomplete="email" value="<?= e($values['email']) ?>" required></label>
            <label><span>Bangladesh mobile number</span><input type="tel" name="phone" inputmode="tel" maxlength="18" value="<?= e($values['phone']) ?>" placeholder="01712345678" required></label>
            <label><span>Account type</span><select name="role" data-role-select><option value="seeker" <?= $values['role'] === 'seeker' ? 'selected' : '' ?>>Blood Seeker</option><option value="donor" <?= $values['role'] === 'donor' ? 'selected' : '' ?>>Donor</option></select></label>
            <label data-blood-group-field><span>Blood group</span><select name="blood_group"><option value="">Select blood group</option><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $values['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
            <fieldset class="form-wide location-fieldset" data-location-picker>
                <legend>Location</legend>
                <p class="field-hint">Select in order: Division → District → Upazila / Area</p>
                <div class="location-picker-grid">
                    <label><span>Division</span><select name="division" data-location-division data-selected="<?= e($values['division']) ?>" required><option value="">Select division</option><?php foreach (array_keys(bangladesh_location_hierarchy()) as $division): ?><option value="<?= e($division) ?>" <?= $values['division'] === $division ? 'selected' : '' ?>><?= e($division) ?></option><?php endforeach; ?></select></label>
                    <label><span>District (Zila)</span><select name="district" data-location-district data-selected="<?= e($values['district']) ?>" required disabled><option value="">Select division first</option></select></label>
                    <label><span>Upazila / Area</span><select name="upazila" data-location-upazila data-selected="<?= e($values['upazila']) ?>" required disabled><option value="">Select district first</option></select></label>
                </div>
                <script type="application/json" data-location-data><?= json_encode(bangladesh_location_hierarchy(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
            </fieldset>
            <label class="password-field"><span>Strong password</span><input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" placeholder="Example: Blood@123" required><small>8+ characters with uppercase, lowercase, number and symbol.</small></label>
            <label class="password-field"><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" placeholder="Repeat your password" required><small>Re-enter the identical password to confirm.</small></label>
            <div class="form-wide"><button class="button button-primary button-full" type="submit" <?= !$ready ? 'disabled' : '' ?>>Create &amp; Verify Account</button></div>
        </form>
        <p class="auth-switch">Hospital employee? <a href="hospital_apply.php">Apply through Hospital Portal</a></p>
        <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
    </section>
</main>
<script src="assets/js/app.js?v=71"></script>
</body>
</html>
