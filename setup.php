<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$success = false;
$error = null;

try {
    $server = db(true);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->exec('USE `' . DB_NAME . '`');

    $schema = file_get_contents(__DIR__ . '/database.sql');
    if ($schema === false) {
        throw new RuntimeException('Could not read database.sql.');
    }

    $schema = preg_replace('/^CREATE DATABASE.*?;\s*/ms', '', $schema);
    $schema = preg_replace('/^USE\s+\w+;\s*/mi', '', (string) $schema);
    $statements = array_filter(array_map('trim', explode(';', (string) $schema)));
    foreach ($statements as $statement) {
        $server->exec($statement);
    }

    // Add donor health fields safely when an older database already exists.
    $donorColumns = [
        'last_donation_date' => 'DATE NULL',
        'total_donations' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
        'is_available' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'screening_status' => "ENUM('Pending', 'Eligible', 'Temporarily Unavailable', 'Permanently Ineligible') NOT NULL DEFAULT 'Pending'",
        'has_medical_condition' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'medical_conditions' => 'TEXT NULL',
        'current_medications' => 'TEXT NULL',
        'verified_by_hospital' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'screening_notes' => 'VARCHAR(500) NULL',
        'profile_updated_at' => 'DATETIME NULL',
    ];
    $columnCheck = $server->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    foreach ($donorColumns as $column => $definition) {
        $columnCheck->execute([DB_NAME, 'users', $column]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $server->exec('ALTER TABLE users ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    $pdo = db();
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $insertUser = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, blood_group, location, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $users = [
            ['System Admin', 'admin@bloodbridge.test', 'admin123', 'admin', null, 'Dhaka', '01700000001'],
            ['Blood Seeker', 'seeker@bloodbridge.test', 'seeker123', 'seeker', 'B+', 'Dhanmondi, Dhaka', '01700000002'],
            ['Rahim Donor', 'donor@bloodbridge.test', 'donor123', 'donor', 'B+', 'Dhanmondi, Dhaka', '01700000003'],
            ['Karim Donor', 'donor2@bloodbridge.test', 'donor123', 'donor', 'O+', 'Mirpur, Dhaka', '01700000004'],
            ['Hospital Staff', 'hospital@bloodbridge.test', 'hospital123', 'hospital', null, 'Shahbag, Dhaka', '01700000005'],
        ];
        foreach ($users as $user) {
            $insertUser->execute([
                $user[0],
                $user[1],
                password_hash($user[2], PASSWORD_DEFAULT),
                $user[3],
                $user[4],
                $user[5],
                $user[6],
            ]);
        }
    }

    // Keep older installations consistent when setup.php is run again.
    $server->exec("UPDATE users SET full_name = 'Blood Seeker' WHERE email = 'seeker@bloodbridge.test' AND full_name = 'Demo Seeker'");
    $server->exec("UPDATE blood_requests SET note = 'Blood needed for a patient.' WHERE note = 'Demo request for the update forum.'");
    $server->exec("UPDATE users SET last_donation_date = '2026-04-01', total_donations = 4, is_available = 1, screening_status = 'Eligible', verified_by_hospital = 1 WHERE email = 'donor@bloodbridge.test' AND last_donation_date IS NULL");
    $server->exec("UPDATE users SET last_donation_date = '2026-06-20', total_donations = 2, is_available = 1, screening_status = 'Eligible', verified_by_hospital = 1 WHERE email = 'donor2@bloodbridge.test' AND last_donation_date IS NULL");

    $hospitalCount = (int) $pdo->query('SELECT COUNT(*) FROM hospitals')->fetchColumn();
    if ($hospitalCount === 0) {
        $insertHospital = $pdo->prepare('INSERT INTO hospitals (name, location, phone) VALUES (?, ?, ?)');
        $insertHospital->execute(['Dhaka Medical College Blood Bank', 'Shahbag, Dhaka', '02-55165088']);
        $dhakaMedicalId = (int) $pdo->lastInsertId();
        $insertHospital->execute(['Kurmitola General Hospital Blood Bank', 'Cantonment, Dhaka', '02-55062350']);
        $kurmitolaId = (int) $pdo->lastInsertId();

        $insertStock = $pdo->prepare('INSERT INTO blood_inventory (hospital_id, blood_group, units) VALUES (?, ?, ?)');
        $stockRows = [
            [$dhakaMedicalId, 'B+', 6], [$dhakaMedicalId, 'O+', 8], [$dhakaMedicalId, 'A+', 5], [$dhakaMedicalId, 'AB+', 2],
            [$kurmitolaId, 'B+', 3], [$kurmitolaId, 'O-', 2], [$kurmitolaId, 'A-', 1], [$kurmitolaId, 'O+', 4],
        ];
        foreach ($stockRows as $stock) {
            $insertStock->execute($stock);
        }
    }

    $requestCount = (int) $pdo->query('SELECT COUNT(*) FROM blood_requests')->fetchColumn();
    if ($requestCount === 0) {
        $seekerId = (int) $pdo->query("SELECT id FROM users WHERE email = 'seeker@bloodbridge.test'")->fetchColumn();
        $insertRequest = $pdo->prepare(
            'INSERT INTO blood_requests (seeker_id, blood_group, location, units, urgency, source_type, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertRequest->execute([$seekerId, 'B+', 'Dhanmondi, Dhaka', 2, 'Emergency', 'Donor', 'Blood needed for a patient.']);
    }

    $success = true;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup | BloodBridge BD</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
<main class="auth-card setup-card">
    <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>BloodBridge BD Setup</strong></div>
    <?php if ($success): ?>
        <h1>Setup completed</h1>
        <p class="muted">Database, donor health fields, initial accounts, hospital stock and one sample request are ready.</p>
        <div class="demo-accounts">
            <p><strong>Admin:</strong> admin@bloodbridge.test / admin123</p>
            <p><strong>Seeker:</strong> seeker@bloodbridge.test / seeker123</p>
            <p><strong>Donor:</strong> donor@bloodbridge.test / donor123</p>
            <p><strong>Hospital:</strong> hospital@bloodbridge.test / hospital123</p>
        </div>
        <a class="button button-primary button-full" href="login.php">Open Login Page</a>
        <p class="security-note">For security, delete or rename setup.php after setup.</p>
    <?php else: ?>
        <h1>Setup could not finish</h1>
        <div class="alert alert-error"><?= e($error) ?></div>
        <p class="muted">Start Apache and MySQL in XAMPP, then check the database settings in config/database.php.</p>
    <?php endif; ?>
</main>
</body>
</html>
