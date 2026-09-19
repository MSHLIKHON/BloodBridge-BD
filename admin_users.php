<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['admin']);

$pdo = db();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    $find = $pdo->prepare('SELECT id, full_name, role, account_status, email_verified, phone_verified FROM users WHERE id = ? LIMIT 1');
    $find->execute([$userId]);
    $target = $find->fetch();

    if (!$target) {
        flash('error', 'User account was not found.');
    } elseif ($userId === (int) $admin['id'] && $action === 'block') {
        flash('error', 'You cannot block your own administrator account.');
    } elseif ($action === 'unlock') {
        $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$userId]);
        audit_log($pdo, (int) $admin['id'], 'Login lock cleared', 'User', $userId, (string) $target['full_name']);
        flash('success', 'Login lock has been cleared.');
    } elseif (in_array($action, ['activate', 'block'], true)) {
        if ($action === 'activate' && (!(bool) $target['email_verified'] || !(bool) $target['phone_verified'])) {
            flash('error', 'The user must complete contact verification before activation.');
            redirect('admin_users.php');
        }
        if ($target['role'] === 'hospital' && $action === 'activate') {
            $check = $pdo->prepare("SELECT status FROM hospital_staff_applications WHERE user_id = ? AND status = 'Approved' LIMIT 1");
            $check->execute([$userId]);
            if (!$check->fetchColumn()) {
                flash('error', 'Approve the hospital staff application before activating this account.');
                redirect('admin_users.php');
            }
        }

        $status = $action === 'activate' ? 'Active' : 'Blocked';
        $pdo->prepare('UPDATE users SET account_status = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
            ->execute([$status, $userId]);
        create_notification($pdo, $userId, 'Account status updated', 'Your account status is now ' . $status . '.', 'dashboard.php');
        audit_log($pdo, (int) $admin['id'], 'Account ' . strtolower($status), 'User', $userId, (string) $target['full_name']);
        flash('success', 'Account status updated.');
    } else {
        flash('error', 'Unknown account action.');
    }
    redirect('admin_users.php');
}

$role = (string) ($_GET['role'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['search'] ?? ''));
$conditions = ['1 = 1'];
$parameters = [];

if (in_array($role, ['admin', 'donor', 'seeker', 'hospital'], true)) {
    $conditions[] = 'u.role = ?';
    $parameters[] = $role;
}
if (in_array($status, ['Pending', 'Active', 'Blocked', 'Rejected'], true)) {
    $conditions[] = 'u.account_status = ?';
    $parameters[] = $status;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $term = '%' . $search . '%';
    array_push($parameters, $term, $term, $term);
}

$statement = $pdo->prepare(
    'SELECT u.*, h.name AS hospital_name
     FROM users u
     LEFT JOIN hospitals h ON h.id = u.hospital_id
     WHERE ' . implode(' AND ', $conditions) . '
     ORDER BY u.created_at DESC, u.id DESC'
);
$statement->execute($parameters);
$users = $statement->fetchAll();

$enableLiveUpdates = true;
$pageTitle = 'User Management';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading split-heading">
    <div><span class="eyebrow">Administrator</span><h1>User management</h1><p>Review account status, clear login locks and control access.</p></div>
    <a class="button button-secondary" href="admin_hospitals.php">Hospital Verification</a>
</section>

<section class="content-card compact-card">
    <form method="get" class="filter-grid user-filter">
        <label><span>Search</span><input type="search" name="search" value="<?= e($search) ?>" placeholder="Name, email or phone"></label>
        <label><span>Role</span><select name="role"><option value="">All roles</option><?php foreach (['admin', 'donor', 'seeker', 'hospital'] as $option): ?><option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e(role_label($option)) ?></option><?php endforeach; ?></select></label>
        <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (['Pending', 'Active', 'Blocked', 'Rejected'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
        <button class="button button-primary" type="submit">Filter Users</button>
    </form>
</section>

<section class="content-card">
    <div class="section-heading"><div><span class="eyebrow">Accounts</span><h2><?= count($users) ?> user(s)</h2></div></div>
    <?php if (!$users): ?>
        <div class="empty-state">No users match these filters.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Role</th><th>Contact</th><th>Hospital</th><th>Verification</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><strong><?= e($row['full_name']) ?></strong><small class="table-note">Joined <?= e(date('d M Y', strtotime((string) $row['created_at']))) ?></small></td>
                        <td><?= e(role_label((string) $row['role'])) ?></td>
                        <td><?= e($row['email']) ?><small class="table-note"><?= e($row['phone']) ?></small></td>
                        <td><?= e($row['hospital_name'] ?: '—') ?></td>
                        <td><span class="badge <?= (int) $row['email_verified'] && (int) $row['phone_verified'] ? 'badge-green' : 'badge-blue' ?>"><?= (int) $row['email_verified'] && (int) $row['phone_verified'] ? 'Verified' : 'Waiting' ?></span></td>
                        <td><span class="badge <?= account_status_class((string) $row['account_status']) ?>"><?= e($row['account_status']) ?></span><?php if ($row['locked_until'] && strtotime((string) $row['locked_until']) > time()): ?><small class="table-note danger-text">Temporarily locked</small><?php endif; ?></td>
                        <td><?= $row['last_login_at'] ? e(date('d M Y, h:i A', strtotime((string) $row['last_login_at']))) : 'Never' ?></td>
                        <td>
                            <div class="table-actions">
                                <?php if ($row['locked_until'] || (int) $row['failed_login_attempts'] > 0): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="unlock"><button class="text-link-button" type="submit">Unlock</button></form><?php endif; ?>
                                <?php if ($row['account_status'] === 'Active'): ?><form method="post" data-confirm="Block this account?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="block"><button class="text-link-button danger-text" type="submit">Block</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="activate"><button class="text-link-button" type="submit">Activate</button></form><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
