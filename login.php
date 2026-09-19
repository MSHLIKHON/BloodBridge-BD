<?php
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
        $result = attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($result['success']) {
            flash('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
            redirect('dashboard.php');
        }
        $error = $result['error'];
        $verificationEmail = $result['verification_email'] ?? null;
        if (!empty($result['user_id'])) $_SESSION['pending_verification_user_id'] = (int) $result['user_id'];
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login | BloodBridge BD</title><link rel="stylesheet" href="assets/css/style.css?v=70"></head>
<body class="auth-body">
<main class="auth-layout">
    <section class="auth-message">
        <span class="eyebrow">BloodBridge BD</span>
        <h1>One network. Faster blood support.</h1>
        <p>Secure access for blood seekers, verified donors and administrators.</p>
        <div class="progress-card"><strong>Near real-time information</strong><span>Requests • Donor responses • Reservations • Notifications</span></div>
    </section>
    <section class="auth-card">
        <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>BloodBridge BD</strong></div>
        <h2>User login</h2>
        <p class="muted">For Donor, Blood Seeker and Administrator accounts.</p>
        <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
        <?php if (!$ready): ?><div class="alert alert-error">Database is not ready. <a href="setup.php">Run setup now</a>.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?><?php if ($verificationEmail): ?><br><a href="verify_account.php?email=<?= e(urlencode((string) $verificationEmail)) ?>">Complete verification</a><?php endif; ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label><span>Email address</span><input type="email" name="email" autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="name@example.com" required></label>
            <label><span>Password</span><input type="password" name="password" autocomplete="current-password" maxlength="128" required></label>
            <button class="button button-primary button-full" type="submit" <?= !$ready ? 'disabled' : '' ?>>Login</button>
        </form>
        <div class="portal-links"><a href="register.php">Create user account</a><a href="hospital_login.php">Hospital Staff Login</a></div>
    </section>
</main>
</body>
</html>
