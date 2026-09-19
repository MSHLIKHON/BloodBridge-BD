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
echo json_encode([
    'version' => live_data_version((int) current_user()['id']),
    'notifications' => unread_notification_count((int) current_user()['id']),
]);
