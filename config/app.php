<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');
ini_set('session.use_strict_mode', '1');
require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Isolate sessions when the same checkout serves different databases
    // (for example, a local preview alongside an older installation).
    session_name('BBBD_' . substr(hash('sha256', __DIR__ . "\0" . DB_NAME), 0, 12));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

const APP_NAME = 'BloodBridge BD';
const DEMO_OTP_TTL_MINUTES = 10;
const APP_VERSION = '1.1.0';

set_exception_handler(function (Throwable $exception): void {
    error_log($exception->getMessage());
    http_response_code(500);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BloodBridge BD</title></head><body style="font-family:Arial,sans-serif;background:#f5f6f8;color:#182230;padding:30px"><main style="max-width:560px;margin:60px auto;background:white;border:1px solid #d8dee8;border-radius:12px;padding:28px"><h1 style="font-size:24px">The page could not load</h1><p>Please make sure Apache and MySQL are running, then run the database setup once.</p><a href="setup.php" style="color:#b31335;font-weight:600">Open Setup Page</a></main></body></html>';
});

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Invalid form token. Please go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function valid_blood_groups(): array
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

function valid_screening_statuses(): array
{
    return ['Pending', 'Eligible', 'Temporarily Unavailable', 'Permanently Ineligible'];
}

function role_label(string $role): string
{
    return match ($role) {
        'donor' => 'Blood Donor',
        'seeker' => 'Blood Seeker',
        'hospital' => 'Hospital Staff',
        'admin' => 'Administrator',
        default => ucfirst($role),
    };
}

function normalize_bd_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits)) return null;
    if (str_starts_with($digits, '880')) $digits = '0' . substr($digits, 3);
    if (strlen($digits) === 10 && str_starts_with($digits, '1')) $digits = '0' . $digits;
    return preg_match('/^01[3-9][0-9]{8}$/', $digits) ? $digits : null;
}

function password_validation_errors(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if (strlen($password) > 128) $errors[] = 'Password must be within 128 characters.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password needs an uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password needs a lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password needs a number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password needs a special character.';
    return $errors;
}

function next_eligible_date(?string $lastDonationDate, ?int $intervalDays = null): ?string
{
    if (!$lastDonationDate) return null;
    try {
        return (new DateTimeImmutable($lastDonationDate))->modify('+' . ($intervalDays ?? app_setting('donation_interval_days',120)) . ' days')->format('Y-m-d');
    } catch (Throwable $exception) {
        return null;
    }
}

function donor_effective_status(array $donor, ?int $intervalDays = null): string
{
    if (isset($donor['donor_enabled']) && !$donor['donor_enabled']) return 'Unavailable';
    if (($donor['account_status'] ?? 'Active') !== 'Active') return 'Unavailable';
    if (!(bool) ($donor['is_available'] ?? false)) return 'Unavailable';
    $status = (string) ($donor['screening_status'] ?? 'Pending');
    if ($status !== 'Eligible') return $status;
    $eligibleDate = next_eligible_date($donor['last_donation_date'] ?? null,$intervalDays);
    if ($eligibleDate && $eligibleDate > date('Y-m-d')) return 'Temporarily Unavailable';
    return 'Eligible';
}

function donor_status_class(string $status): string
{
    return match ($status) {
        'Eligible' => 'badge-green',
        'Pending' => 'badge-blue',
        default => 'badge-gray',
    };
}

function request_status_class(string $status): string
{
    return match ($status) {
        'Accepted', 'Approved' => 'badge-blue',
        'Completed', 'Collected', 'Active', 'Verified' => 'badge-green',
        'Rejected', 'Cancelled', 'Blocked', 'Suspended' => 'badge-gray',
        default => 'badge-red',
    };
}

function account_status_class(string $status): string
{
    return match ($status) {
        'Active', 'Verified', 'Approved' => 'badge-green',
        'Pending' => 'badge-blue',
        default => 'badge-gray',
    };
}

function create_verification_code(PDO $pdo, int $userId): string
{
    $code = (string) random_int(100000, 999999);
    $pdo->prepare(
        "UPDATE verification_codes SET used_at = NOW()
         WHERE user_id = ? AND purpose = 'Account Verification' AND used_at IS NULL"
    )->execute([$userId]);
    $insert = $pdo->prepare(
        "INSERT INTO verification_codes (user_id, code_hash, purpose, expires_at)
         VALUES (?, ?, 'Account Verification', DATE_ADD(NOW(), INTERVAL " . DEMO_OTP_TTL_MINUTES . " MINUTE))"
    );
    $insert->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
    $_SESSION['demo_otp'] = ['user_id' => $userId, 'code' => $code];
    $_SESSION['verification_user_id'] = $userId;
    $_SESSION['otp_attempts'] = 0;
    return $code;
}

function create_notification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null, string $type = 'Info'): void
{
    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
    );
    $statement->execute([$userId, $type, substr($title, 0, 140), substr($message, 0, 500), $link]);
}

function unread_notification_count(int $userId): int
{
    try {
        $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $statement->execute([$userId]);
        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function audit_log(PDO $pdo, ?int $userId, string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
    );
    $statement->execute([$userId, substr($action, 0, 100), substr($entityType, 0, 60), $entityId, $details ? substr($details, 0, 500) : null]);
}

function live_data_version(int $userId): string
{
    try {
        $statement = db()->prepare(
            "SELECT CONCAT(
                (SELECT COUNT(*) FROM notifications WHERE user_id = ?), ':',
                (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL), ':',
                COALESCE((SELECT MAX(created_at) FROM notifications WHERE user_id = ?), ''), ':',
                (SELECT COUNT(*) FROM blood_requests), ':',
                COALESCE((SELECT MAX(updated_at) FROM blood_requests), ''), ':',
                COALESCE((SELECT MAX(updated_at) FROM blood_inventory), ''), ':',
                COALESCE((SELECT MAX(updated_at) FROM blood_reservations), ''), ':',
                COALESCE((SELECT MAX(updated_at) FROM users), ''), ':',
                COALESCE((SELECT MAX(updated_at) FROM hospital_staff_applications), '')
            )"
        );
        $statement->execute([$userId, $userId, $userId]);
        return (string) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return '';
    }
}

function database_ready(): bool
{
    try {
        $requiredTables = "'users','hospitals','blood_inventory','blood_requests','donation_history',
            'verification_codes','hospital_staff_applications','request_responses','notifications',
            'blood_reservations','inventory_transactions','audit_logs'";
        $tableCount = (int) db()->query(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $requiredTables . ')'
        )->fetchColumn();
        return $tableCount === 12;
    } catch (Throwable $exception) {
        return false;
    }
}

require_once __DIR__ . '/../includes/services.php';
