<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'Invalid request ID.');
    redirect('requests.php');
}

$statement = db()->prepare('SELECT seeker_id, status FROM blood_requests WHERE id = ?');
$statement->execute([$id]);
$request = $statement->fetch();
$user = current_user();

if (!$request) {
    flash('error', 'Request not found.');
} elseif ($user['role'] !== 'admin' && ((int) $request['seeker_id'] !== (int) $user['id'] || $request['status'] !== 'Pending')) {
    flash('error', 'Only the owner can delete a pending request.');
} else {
    $delete = db()->prepare('DELETE FROM blood_requests WHERE id = ?');
    $delete->execute([$id]);
    flash('success', 'Blood request deleted successfully.');
}

redirect('requests.php');
