<?php
/** File purpose: Verify Account handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
if (logged_in()) redirect('dashboard.php');
if (!database_ready()) redirect('setup.php');

$email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
$errors = [];
$successMessage = null;
$find = db()->prepare('SELECT id, full_name, role, account_status, email_verified, phone_verified FROM users WHERE email = ? LIMIT 1');
$find->execute([$email]);
$account = $find->fetch();
if (!$account) {
    flash('error', 'Account not found. Please register again.');
    redirect('register.php');
}
if ($account['account_status'] !== 'Pending' || (int)($_SESSION['verification_user_id']??0)!==(int)$account['id']) {
    http_response_code(403); exit('Verification is only available for your pending registration. Sign in with your password to resume it.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');
    if ($action === 'resend') {
        if (time()-(int)($_SESSION['otp_last_sent']??0)<60) { http_response_code(429); exit('Wait one minute before requesting another code.'); }
        create_verification_code(db(), (int) $account['id']);
        $_SESSION['otp_last_sent']=time();
        $successMessage = 'A new demo verification code has been generated.';
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        if ((int)($_SESSION['otp_attempts']??0)>=5) { http_response_code(429); exit('Too many attempts. Request a new code.'); }
        $_SESSION['otp_attempts']=(int)($_SESSION['otp_attempts']??0)+1;
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Enter the 6-digit verification code.';
        } else {
            $loadCode = db()->prepare(
                "SELECT id, code_hash FROM verification_codes
                 WHERE user_id = ? AND purpose = 'Account Verification' AND used_at IS NULL AND expires_at >= NOW()
                 ORDER BY id DESC LIMIT 1"
            );
            $loadCode->execute([(int) $account['id']]);
            $verification = $loadCode->fetch();
            if (!$verification || !password_verify($code, (string) $verification['code_hash'])) {
                $errors[] = 'The code is incorrect or expired.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $lockedAccount=bb_one($pdo,'SELECT account_status FROM users WHERE id=? FOR UPDATE',[(int)$account['id']]);
                    if (!$lockedAccount || $lockedAccount['account_status']!=='Pending') throw new DomainException('Account is no longer pending.');
                    $lockedCode=bb_one($pdo,'SELECT used_at,expires_at FROM verification_codes WHERE id=? FOR UPDATE',[(int)$verification['id']]);
                    if (!$lockedCode || $lockedCode['used_at'] || strtotime($lockedCode['expires_at'])<time()) throw new DomainException('Code already used or expired.');
                    $pdo->prepare('UPDATE verification_codes SET used_at = NOW() WHERE id = ?')->execute([(int) $verification['id']]);
                    $newStatus = 'Active';
                    if ($account['role'] === 'hospital') {
                        $applicationStatus = $pdo->prepare('SELECT status FROM hospital_staff_applications WHERE user_id = ? LIMIT 1');
                        $applicationStatus->execute([(int) $account['id']]);
                        $staffStatus = (string) $applicationStatus->fetchColumn();
                        $newStatus = match ($staffStatus) {
                            'Approved' => 'Active',
                            'Rejected' => 'Rejected',
                            default => 'Pending',
                        };
                    }
                    $pdo->prepare('UPDATE users SET email_verified = 1, phone_verified = 1, account_status = ? WHERE id = ?')
                        ->execute([$newStatus, (int) $account['id']]);
                    audit_log($pdo, (int) $account['id'], 'Account verified', 'User', (int) $account['id'], 'Demo OTP completed');
                    $pdo->commit();
                    unset($_SESSION['demo_otp']);
                    unset($_SESSION['verification_user_id'],$_SESSION['otp_attempts']);
                    if ($account['role'] === 'hospital') {
                        $message = match ($newStatus) {
                            'Active' => 'Contact verification completed. Your approved hospital account is now active.',
                            'Rejected' => 'Contact verification completed, but the hospital staff application was rejected. Contact an administrator.',
                            default => 'Contact verification completed. Your hospital application now needs Admin approval.',
                        };
                        flash('success', $message);
                        redirect('hospital_login.php');
                    }
                    flash('success', 'Account verified successfully. You can now sign in.');
                    redirect('login.php');
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Verification could not be completed. Please try again.';
                }
            }
        }
    }
}

$demoOtp = $_SESSION['demo_otp'] ?? null;
$shownCode = is_array($demoOtp) && (int) ($demoOtp['user_id'] ?? 0) === (int) $account['id'] ? (string) $demoOtp['code'] : null;
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify Account | BloodBridge BD</title><link rel="stylesheet" href="assets/css/style.css?v=70"></head>
<body class="auth-body">
<main class="auth-card setup-card">
    <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>Account Verification</strong></div>
    <h1>Verify email and phone</h1>
    <p class="muted">A six-digit code is used for this academic demonstration.</p>
    <?php if ($shownCode): ?><div class="otp-box"><span>Demo OTP</span><strong><?= e($shownCode) ?></strong><small>Valid for <?= DEMO_OTP_TTL_MINUTES ?> minutes</small></div><?php endif; ?>
    <?php if ($successMessage): ?><div class="alert alert-success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="email" value="<?= e($email) ?>"><input type="hidden" name="action" value="verify">
        <label><span>6-digit code</span><input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
        <button class="button button-primary button-full" type="submit">Verify Account</button>
    </form>
    <form method="post" class="inline-form top-gap"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="email" value="<?= e($email) ?>"><input type="hidden" name="action" value="resend"><button class="button button-secondary" type="submit">Generate New Code</button></form>
</main>
</body>
</html>
