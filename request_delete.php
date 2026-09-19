<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['donor','seeker']);

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
        throw new RuntimeException('Only a pending request can be cancelled. Use Cancel for an accepted request.');
    }
    if ((int) $request['seeker_id'] !== (int) $user['id']) {
        throw new RuntimeException('Only the owner can cancel this pending request.');
    }

    request_event($pdo,(int)$id,(int)$user['id'],'Request cancelled');
    $delete = $pdo->prepare("UPDATE blood_requests SET status = 'Cancelled', outcome = 'Not received' WHERE id = ?");
    $delete->execute([$id]);
    $pdo->commit();
    flash('success', 'Blood request cancelled successfully.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The request could not be cancelled.');
}

redirect('requests.php');
