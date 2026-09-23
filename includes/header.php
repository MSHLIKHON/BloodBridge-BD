<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
if(!empty($enableMap)) header('Referrer-Policy: strict-origin-when-cross-origin');
$flashMessage = pull_flash();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$notificationCount = $user ? unread_notification_count((int) $user['id']) : 0;
$liveVersion = $user && !empty($enableLiveUpdates) ? live_data_version((int) $user['id']) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=72">
    <?php if(!empty($enableMap)): ?><link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css"><?php endif; ?>
</head>
<body<?= $liveVersion !== '' ? ' data-live-page data-live-version="' . e($liveVersion) . '"' : '' ?>>
<header class="topbar">
    <a class="brand" href="<?= $user ? 'dashboard.php' : 'login.php' ?>">
        <span class="brand-mark<?= $user && $user['role'] === 'hospital' ? ' hospital-mark' : '' ?>"><span>+</span></span>
        <span>BloodBridge BD</span>
    </a>
    <?php if ($user): ?>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-nav-toggle>Menu</button>
        <nav class="nav" data-nav>
            <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
            <a class="<?= in_array($currentPage,['services.php','clubs.php','health_records.php','direct_donations.php','reports.php','settings.php','stock_settings.php','request_documents.php'],true) ? 'active' : '' ?>" href="services.php">Services</a>
            <?php if (in_array($user['role'], ['seeker', 'donor'], true)): ?><a class="<?= $currentPage === 'search.php' ? 'active' : '' ?>" href="search.php">Search</a><?php endif; ?>
            <?php if($user['role']!=='admin'): ?><a class="<?= in_array($currentPage, ['requests.php', 'request_edit.php'], true) ? 'active' : '' ?>" href="requests.php">Requests</a><?php endif; ?>
            <?php if (in_array($user['role'],['donor','seeker'],true)): ?>
                <a class="<?= $currentPage === 'donor_profile.php' ? 'active' : '' ?>" href="donor_profile.php">My Profile</a>
                <a class="<?= $currentPage === 'donation_history.php' ? 'active' : '' ?>" href="donation_history.php">History</a>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['hospital', 'admin'], true)): ?>
                <a class="<?= $currentPage === 'inventory.php' ? 'active' : '' ?>" href="inventory.php">Inventory</a>
                <a class="<?= $currentPage === 'reservations.php' ? 'active' : '' ?>" href="reservations.php">Reservations</a>
                <a class="<?= in_array($currentPage, ['donors.php', 'donor_details.php'], true) ? 'active' : '' ?>" href="donors.php">Donors</a>
            <?php elseif (in_array($user['role'],['donor','seeker'],true)): ?>
                <a class="<?= $currentPage === 'reservations.php' ? 'active' : '' ?>" href="reservations.php">Reservations</a>
            <?php endif; ?>
            <?php if ($user['role'] === 'admin'): ?>
                <a class="<?= $currentPage === 'admin_hospitals.php' ? 'active' : '' ?>" href="admin_hospitals.php">Hospitals</a>
                <a class="<?= $currentPage === 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php">Users</a>
            <?php endif; ?>
            <a class="notification-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="notifications.php" aria-label="Notifications">Alerts<?php if ($notificationCount > 0): ?><span><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span><?php endif; ?></a>
            <?php if (in_array($user['role'], ['seeker', 'donor'], true)): ?><a class="nav-primary" href="request_create.php">New Request</a><?php endif; ?>
            <a class="nav-logout" href="logout.php">Logout</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-shell">
    <?php if ($user): ?><div class="session-strip"><span><?= e(role_label((string) $user['role'])) ?></span><?php if ($user['role'] === 'hospital' && !empty($user['hospital_id'])): ?><span>Verified hospital staff</span><?php endif; ?><span class="live-mini"><i></i>Database connected</span></div><?php endif; ?>
    <?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="alert"><?= e($flashMessage['message']) ?></div><?php endif; ?>
