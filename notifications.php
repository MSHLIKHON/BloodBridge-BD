<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([(int) current_user()['id']]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'read') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?')->execute([$id, (int) current_user()['id']]);
    }
    redirect('notifications.php');
}

$statement = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$statement->execute([(int) current_user()['id']]);
$notifications = $statement->fetchAll();
$pageTitle = 'Notifications';
$enableLiveUpdates = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><span class="eyebrow">Near real-time</span><h1>Notifications</h1><p>Important updates from requests, donations, reservations and approvals.</p></div><?php if ($notifications): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="read_all"><button class="button button-secondary" type="submit">Mark all as read</button></form><?php endif; ?></section>
<section class="content-card">
    <div class="live-status"><span class="live-dot"></span><span>Updates checked automatically</span></div>
    <?php if (!$notifications): ?><div class="empty-state"><strong>No notifications yet.</strong><span>New activity will appear here.</span></div><?php else: ?><div class="notification-list"><?php foreach ($notifications as $notification): ?><article class="notification-item <?= $notification['read_at'] ? '' : 'unread' ?>"><div><span class="eyebrow"><?= e($notification['type']) ?></span><strong><?= e($notification['title']) ?></strong><p><?= e($notification['message']) ?></p><small><?= e(date('d M Y, h:i A', strtotime($notification['created_at']))) ?></small></div><div class="notification-actions"><?php if ($notification['link']): ?><a class="button button-secondary" href="<?= e($notification['link']) ?>">Open</a><?php endif; ?><?php if (!$notification['read_at']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><button class="text-link-button" type="submit">Mark read</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
