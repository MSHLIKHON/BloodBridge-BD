<?php
/** File purpose: Auth provides shared application logic and presentation helpers. */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/matching.php';

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function logged_in(): bool
{
    return current_user() !== null;
}

function user_session_columns(): string
{
    return 'id, hospital_id, full_name, email, role, blood_group, location, phone,
            account_status, email_verified, phone_verified, last_login_at';
}

function refresh_session_user(): void
{
    if (!logged_in() || !database_ready()) return;
    $statement = db()->prepare('SELECT ' . user_session_columns() . ' FROM users WHERE id = ? LIMIT 1');
    $statement->execute([(int) current_user()['id']]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['user']);
        return;
    }
    $_SESSION['user'] = $user;
}

function require_login(): void
{
    $backgroundPoll = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'live_updates.php';
    if (logged_in() && isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > app_setting('session_timeout_minutes',30) * 60) {
        unset($_SESSION['user'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
        flash('error','Your session expired after inactivity. Please log in again.');
    }
    if (!logged_in()) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }
    if (!database_ready()) redirect('setup.php');

    refresh_session_user();
    $user = current_user();
    if (!$user || ($user['account_status'] ?? 'Active') !== 'Active') {
        unset($_SESSION['user']);
        flash('error', 'This account is not active. Please complete verification or contact an administrator.');
        redirect('login.php');
    }
    if ($user['role'] === 'hospital' && !bb_one(db(),"SELECT id FROM hospitals WHERE id=? AND status='Verified'",[(int) $user['hospital_id']])) {
        unset($_SESSION['user']);
        flash('error','Your hospital is pending or suspended. Contact the administrator.');
        redirect('hospital_login.php');
    }
    if (!v100_ready()) redirect('setup.php');
    if (!$backgroundPoll) $_SESSION['last_activity'] = time();
    require_once __DIR__ . '/maintenance.php';
    maintenance_tick(db());
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to open this page.');
    }
}

function attempt_login(string $email, string $password, ?string $requiredRole = null): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 128) {
        return ['success' => false, 'error' => 'Enter a valid email and password.'];
    }

    $statement = db()->prepare(
        'SELECT ' . user_session_columns() . ', password_hash, failed_login_attempts, locked_until
         FROM users WHERE email = ? LIMIT 1'
    );
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user) return ['success' => false, 'error' => 'Email or password is incorrect.'];

    if ($user['locked_until'] && strtotime((string) $user['locked_until']) > time()) {
        return ['success' => false, 'error' => 'Too many failed attempts. Try again after ' . date('h:i A', strtotime((string) $user['locked_until'])) . '.'];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $attempts = $user['locked_until'] ? 1 : min(5,(int) $user['failed_login_attempts'] + 1);
        $update = db()->prepare(
            'UPDATE users SET failed_login_attempts = ?,
             locked_until = CASE WHEN ? >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) ELSE NULL END
             WHERE id = ?'
        );
        $update->execute([$attempts, $attempts, (int) $user['id']]);
        $remaining = max(0, 5 - $attempts);
        return ['success' => false, 'error' => $remaining > 0
            ? 'Email or password is incorrect. ' . $remaining . ' attempt(s) remaining.'
            : 'Account temporarily locked for 15 minutes.'];
    }

    if ($requiredRole !== null && $user['role'] !== $requiredRole) {
        return ['success' => false, 'error' => 'This account does not belong to the hospital staff portal.'];
    }
    if ($requiredRole === null && $user['role'] === 'hospital') {
        return ['success' => false, 'error' => 'Hospital staff must use the separate Hospital Staff Login page.'];
    }

    $status = (string) ($user['account_status'] ?? 'Pending');
    if ($status !== 'Active') {
        $contactVerified = (bool) $user['email_verified'] && (bool) $user['phone_verified'];
        if (!$contactVerified && $status==='Pending') $_SESSION['verification_user_id']=(int)$user['id'];
        $message = match ($status) {
            'Pending' => !$contactVerified
                ? 'Complete the six-digit contact verification before logging in.'
                : ($user['role'] === 'hospital'
                    ? 'Your hospital staff application is waiting for administrator approval.'
                    : 'Your account verification is pending.'),
            'Blocked' => 'This account has been blocked. Contact an administrator.',
            'Rejected' => 'This application was rejected. Contact an administrator.',
            default => 'This account is not active.',
        };
        return [
            'success' => false,
            'error' => $message,
            'user_id' => (int) $user['id'],
            'verification_email' => !$contactVerified ? (string) $user['email'] : null,
        ];
    }

    if (!(bool) $user['email_verified'] || !(bool) $user['phone_verified']) {
        return ['success' => false, 'error' => 'Complete account verification before logging in.', 'user_id' => (int) $user['id']];
    }

    db()->prepare(
        'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?'
    )->execute([(int) $user['id']]);
    unset($user['password_hash'], $user['failed_login_attempts'], $user['locked_until']);
    $user['last_login_at'] = date('Y-m-d H:i:s');
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
    audit_log(db(), (int) $user['id'], 'Login', 'User', (int) $user['id'], role_label((string) $user['role']) . ' login');
    return ['success' => true, 'user' => $user];
}

function hospital_scope_id(): ?int
{
    $user = current_user();
    if (!$user || $user['role'] !== 'hospital' || empty($user['hospital_id'])) return null;
    return (int) $user['hospital_id'];
}

function can_manage_hospital(int $hospitalId): bool
{
    $user = current_user();
    if (!$user) return false;
    if (!bb_one(db(),"SELECT id FROM hospitals WHERE id=? AND status='Verified'",[$hospitalId])) return false;
    return $user['role'] === 'admin' || ($user['role'] === 'hospital' && (int) ($user['hospital_id'] ?? 0) === $hospitalId);
}
