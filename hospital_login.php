<?php
/** File purpose: Hospital Login handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
if (logged_in()) redirect('dashboard.php');

$error = null;
$verificationEmail = null;
$ready = database_ready();
$flashMessage = pull_flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$ready) {
        $error = 'Database is not ready. Run setup.php first.';
    } else {
        $result = attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), 'hospital');
        if ($result['success']) {
            if (empty($result['user']['hospital_id'])) {
                unset($_SESSION['user']);
                $error = 'This staff account is not linked to an approved hospital.';
            } else {
                flash('success', 'Hospital portal opened for ' . $result['user']['full_name'] . '.');
                redirect('dashboard.php');
            }
        } else {
            $error = $result['error'];
            $verificationEmail = $result['verification_email'] ?? null;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hospital Staff Login | BloodBridge BD</title><link rel="stylesheet" href="assets/css/style.css?v=70"></head>
<body class="auth-body hospital-auth">
<main class="auth-layout">
    <section class="auth-message hospital-message">
        <span class="eyebrow">Verified hospital portal</span>
        <h1>Manage blood-bank operations.</h1>
        <p>Only approved staff linked to a verified hospital can access donor screening, inventory and reservations.</p>
        <div class="verification-steps"><span>1. Hospital code</span><span>2. Contact OTP</span><span>3. Admin approval</span></div>
    </section>
    <section class="auth-card">
        <div class="auth-brand"><span class="brand-mark hospital-mark"><span>+</span></span><strong>Hospital Staff Portal</strong></div>
        <h2>Authorized staff login</h2>
        <p class="muted">Your application must be approved before access is granted.</p>
        <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
        <?php if (!$ready): ?><div class="alert alert-error">Database is not ready. <a href="setup.php">Run setup now</a>.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?><?php if ($verificationEmail): ?><br><a href="verify_account.php?email=<?= e(urlencode((string) $verificationEmail)) ?>">Complete contact verification</a><?php endif; ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label><span>Official email</span><input type="email" name="email" autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>" required></label>
            <label><span>Password</span><input type="password" name="password" autocomplete="current-password" maxlength="128" required></label>
            <button class="button button-hospital button-full" type="submit" <?= !$ready ? 'disabled' : '' ?>>Open Hospital Portal</button>
        </form>
        <div class="portal-links"><a href="hospital_apply.php">Apply as Hospital Staff</a><a href="login.php">Regular User Login</a></div>
    </section>
</main>
</body>
</html>
