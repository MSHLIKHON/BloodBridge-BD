<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!logged_in()) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }

    if (!database_ready()) {
        redirect('setup.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to open this page.');
    }
}

function refresh_session_user(): void
{
    if (!logged_in()) {
        return;
    }

    $statement = db()->prepare('SELECT id, full_name, email, role, blood_group, location, phone FROM users WHERE id = ?');
    $statement->execute([(int) current_user()['id']]);
    $user = $statement->fetch();
    if ($user) {
        $_SESSION['user'] = $user;
    }
}
