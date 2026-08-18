<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database.php';

const APP_NAME = 'BloodBridge BD';

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

function next_eligible_date(?string $lastDonationDate): ?string
{
    if (!$lastDonationDate) return null;
    try {
        return (new DateTimeImmutable($lastDonationDate))->modify('+120 days')->format('Y-m-d');
    } catch (Throwable $exception) {
        return null;
    }
}

function donor_effective_status(array $donor): string
{
    if (!(bool) ($donor['is_available'] ?? false)) return 'Unavailable';
    $status = (string) ($donor['screening_status'] ?? 'Pending');
    if ($status !== 'Eligible') return $status;
    $eligibleDate = next_eligible_date($donor['last_donation_date'] ?? null);
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
        'Accepted' => 'badge-blue',
        'Completed' => 'badge-green',
        'Rejected', 'Cancelled' => 'badge-gray',
        default => 'badge-red',
    };
}

function database_ready(): bool
{
    try {
        $requiredTables = "'users','hospitals','blood_inventory','blood_requests','donation_history'";
        $tableCount = (int) db()->query(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $requiredTables . ')'
        )->fetchColumn();
        return $tableCount === 5;
    } catch (Throwable $exception) {
        return false;
    }
}
