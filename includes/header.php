<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$flashMessage = pull_flash();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= $user ? 'dashboard.php' : 'login.php' ?>">
        <span class="brand-mark"><span>+</span></span>
        <span>BloodBridge BD</span>
    </a>
    <?php if ($user): ?>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" data-nav-toggle>Menu</button>
        <nav class="nav" data-nav>
            <a class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
            <a class="<?= $currentPage === 'search.php' ? 'active' : '' ?>" href="search.php">Search Blood</a>
            <a class="<?= in_array($currentPage, ['requests.php', 'request_edit.php'], true) ? 'active' : '' ?>" href="requests.php">Requests</a>
            <?php if (in_array($user['role'], ['admin', 'hospital'], true)): ?>
                <a class="<?= in_array($currentPage, ['inventory.php'], true) ? 'active' : '' ?>" href="inventory.php">Inventory</a>
                <a class="<?= in_array($currentPage, ['donors.php', 'donor_details.php'], true) ? 'active' : '' ?>" href="donors.php">Donors</a>
            <?php elseif ($user['role'] === 'donor'): ?>
                <a class="<?= $currentPage === 'donor_profile.php' ? 'active' : '' ?>" href="donor_profile.php">My Profile</a>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['seeker', 'admin'], true)): ?>
                <a class="nav-primary" href="request_create.php">New Request</a>
            <?php endif; ?>
            <a class="nav-logout" href="logout.php">Logout</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-shell">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?>" role="alert">
            <?= e($flashMessage['message']) ?>
        </div>
    <?php endif; ?>
