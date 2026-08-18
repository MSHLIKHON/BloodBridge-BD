<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (logged_in()) {
    redirect('dashboard.php');
}

$error = null;
$ready = database_ready();
$flashMessage = pull_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$ready) {
        $error = 'Database is not ready. Run setup.php first.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Enter a valid email and password.';
        } else {
            $statement = db()->prepare(
                'SELECT id, full_name, email, password_hash, role, blood_group, location, phone
                 FROM users WHERE email = ? LIMIT 1'
            );
            $statement->execute([$email]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']);
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('dashboard.php');
            }

            $error = 'Email or password is incorrect.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | BloodBridge BD</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
<main class="auth-layout">
    <section class="auth-message">
        <span class="eyebrow">Team NullLogic</span>
        <h1>Connect donors, seekers and blood banks.</h1>
        <p>Search blood by group and location, create requests, manage status and monitor hospital stock.</p>
        <div class="progress-card"><strong>Give blood. Save lives.</strong><span>One platform for donors, seekers and hospital blood banks.</span></div>
    </section>
    <section class="auth-card">
        <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>BloodBridge BD</strong></div>
        <h2>Sign in to continue</h2>
        <p class="muted">Enter your account information.</p>

        <?php if ($flashMessage): ?>
            <div class="alert alert-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
        <?php endif; ?>
        <?php if (!$ready): ?>
            <div class="alert alert-error">Database is not ready. <a href="setup.php">Run setup now</a>.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>
                <span>Email address</span>
                <input type="email" name="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" placeholder="Enter password" required>
            </label>
            <button class="button button-primary button-full" type="submit" <?= !$ready ? 'disabled' : '' ?>>Login</button>
        </form>

        <p class="auth-switch">New to BloodBridge BD? <a href="register.php">Create an account</a></p>
    </section>
</main>
</body>
</html>
