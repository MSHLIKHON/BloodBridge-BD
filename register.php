<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';

if (logged_in()) {
    redirect('dashboard.php');
}

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
    'phone' => trim((string) ($_POST['phone'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!$ready) $errors[] = 'Database is not ready. Run setup.php first.';
    if ($values['full_name'] === '' || strlen($values['full_name']) > 120) $errors[] = 'Enter your full name.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!in_array($values['role'], ['donor', 'seeker'], true)) $errors[] = 'Choose Donor or Blood Seeker.';
    if ($values['role'] === 'donor' && !in_array($values['blood_group'], valid_blood_groups(), true)) $errors[] = 'Choose your blood group.';
    if (!validate_location_hierarchy($values['division'], $values['district'], $values['upazila'])) {
        $errors[] = 'Select a valid Division, District, and Upazila.';
    }
    if (!preg_match('/^[0-9+() -]{7,20}$/', $values['phone'])) $errors[] = 'Enter a valid phone number.';
    if (strlen($password) < 6) $errors[] = 'Password must contain at least 6 characters.';
    if ($password !== $passwordConfirmation) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $existing = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $existing->execute([$values['email']]);
        if ($existing->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $bloodGroup = $values['blood_group'] !== '' ? $values['blood_group'] : null;
            $location = format_location_string($values['upazila'], $values['district'], $values['division']);
            $insert = db()->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, blood_group, location, phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $values['full_name'], $values['email'], password_hash($password, PASSWORD_DEFAULT),
                $values['role'], $bloodGroup, $location, $values['phone'],
            ]);
            flash('success', 'Account created successfully. You can now sign in.');
            redirect('login.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | BloodBridge BD</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
<main class="auth-layout">
    <section class="auth-message">
        <span class="eyebrow">BloodBridge BD</span>
        <h1>Join the blood donation network.</h1>
        <p>Create a donor or blood-seeker account and connect with people and blood banks near you.</p>
    </section>
    <section class="auth-card register-card">
        <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>Create Account</strong></div>
        <h2>Register with BloodBridge BD</h2>
        <p class="muted">Hospital and administrator accounts are managed separately.</p>

        <?php if (!$ready): ?><div class="alert alert-error">Database is not ready. <a href="setup.php">Run setup now</a>.</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <form method="post" class="form-grid" data-registration-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="form-wide"><span>Full name</span><input type="text" name="full_name" maxlength="120" value="<?= e($values['full_name']) ?>" required></label>
            <label><span>Email address</span><input type="email" name="email" autocomplete="email" value="<?= e($values['email']) ?>" required></label>
            <label><span>Phone number</span><input type="tel" name="phone" value="<?= e($values['phone']) ?>" placeholder="01XXXXXXXXX" required></label>
            <label><span>Account type</span><select name="role" data-role-select><option value="seeker" <?= $values['role'] === 'seeker' ? 'selected' : '' ?>>Blood Seeker</option><option value="donor" <?= $values['role'] === 'donor' ? 'selected' : '' ?>>Donor</option></select></label>
            <label data-blood-group-field><span>Blood group</span><select name="blood_group"><option value="">Select blood group</option><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $values['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>

            <!-- Bangladesh Dependent Location Selection (Division -> District -> Upazila) -->
            <?php render_location_dropdowns('', null, true, [
                'division' => $values['division'],
                'district' => $values['district'],
                'upazila' => $values['upazila'],
            ]); ?>

            <label><span>Strong password</span><input type="password" name="password" autocomplete="new-password" placeholder="Example: Blood@123" minlength="6" required><small>8+ characters with uppercase, lowercase, number and symbol.</small></label>
            <label><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat your password" minlength="6" required></label>

            <div class="form-wide"><button class="button button-primary button-full" type="submit" <?= !$ready ? 'disabled' : '' ?>>Create Account</button></div>
        </form>
        <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
    </section>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
