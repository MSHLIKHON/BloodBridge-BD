<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!logged_in() || !database_ready()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}
$currentUser = current_user();
$userId = (int) $currentUser['id'];
$payload = [
    'version' => live_data_version($userId),
    'notifications' => unread_notification_count($userId),
    'server_time' => date(DATE_ATOM),
];

if ($currentUser['role'] === 'seeker') {
    $pdo = db();
    $requestCount = $pdo->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND status IN ('Pending', 'Accepted')");
    $requestCount->execute([$userId]);
    $reservationCount = $pdo->prepare("SELECT COUNT(*) FROM blood_reservations WHERE seeker_id = ? AND status IN ('Pending', 'Approved')");
    $reservationCount->execute([$userId]);
    $payload['active_requests'] = (int) $requestCount->fetchColumn();
    $payload['active_reservations'] = (int) $reservationCount->fetchColumn();
}

echo json_encode($payload);
