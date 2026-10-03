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

$user = current_user();
$pdo = db();
$pdo->beginTransaction();
try {
    $statement = $pdo->prepare('SELECT seeker_id, status FROM blood_requests WHERE id = ? FOR UPDATE');
    $statement->execute([$id]);
    $request = $statement->fetch();
    if (!$request) {
        throw new RuntimeException('Request not found.');
    }
    if ($request['status'] !== 'Pending') {
        throw new RuntimeException('Only a pending request can be deleted. Use Cancel for an accepted request.');
    }
    if ($user['role'] !== 'admin' && (int) $request['seeker_id'] !== (int) $user['id']) {
        throw new RuntimeException('Only the owner or Admin can delete this pending request.');
    }

    $responders = $pdo->prepare('SELECT responder_id FROM request_responses WHERE request_id = ?');
    $responders->execute([$id]);
    $responderIds = array_column($responders->fetchAll(), 'responder_id');

    audit_log($pdo, (int) $user['id'], 'Delete request', 'BloodRequest', (int) $id, 'Pending request deleted');
    $delete = $pdo->prepare('DELETE FROM blood_requests WHERE id = ?');
    $delete->execute([$id]);
    foreach ($responderIds as $responderId) {
        create_notification($pdo, (int) $responderId, 'Blood request closed', 'Request #' . $id . ' was deleted by the seeker/Admin.', 'requests.php', 'Request');
    }
    $pdo->commit();
    flash('success', 'Blood request deleted successfully.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The request could not be deleted.');
}

redirect('requests.php');
