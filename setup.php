<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schema_v100.php';
require_once __DIR__ . '/includes/schema_v110.php';
require_once __DIR__ . '/includes/locations.php';

$success = false;
$error = null;
$existingUsers = false;
try { $existingUsers = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; } catch (Throwable $exception) {}
$installed = v100_ready();

function add_missing_columns(PDO $pdo, string $table, array $columns): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    foreach ($columns as $name => $definition) {
        $check->execute([DB_NAME, $table, $name]);
        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $definition);
        }
    }
}

function add_missing_unique_index(PDO $pdo, string $table, string $indexName, string $column): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $check->execute([DB_NAME, $table, $indexName]);
    if ((int) $check->fetchColumn() === 0) {
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $indexName . '` (`' . $column . '`)');
        } catch (Throwable $exception) {
            error_log('Unique index migration skipped for ' . $table . '.' . $column . ': ' . $exception->getMessage());
        }
    }
}

/**
 * Converts status columns from older prototype ENUM definitions without
 * deleting existing rows. Unknown legacy values fall back to the safe default.
 */
function normalize_enum_column(
    PDO $pdo,
    string $table,
    string $column,
    array $allowedValues,
    string $defaultValue,
    array $aliases = []
): void {
    foreach ([$table, $column] as $identifier) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid migration identifier.');
        }
    }

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute([DB_NAME, $table, $column]);
    if ((int) $check->fetchColumn() === 0) return;

    $quotedDefault = $pdo->quote($defaultValue);
    $pdo->exec(
        "ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(80) NOT NULL DEFAULT {$quotedDefault}"
    );

    foreach ($aliases as $target => $legacyValues) {
        $normalizedLegacyValues = array_values(array_unique(array_map(
            static fn(string $value): string => strtolower(trim($value)),
            $legacyValues
        )));
        if (!$normalizedLegacyValues) continue;

        $quotedLegacyValues = implode(', ', array_map([$pdo, 'quote'], $normalizedLegacyValues));
        $pdo->exec(
            "UPDATE `{$table}` SET `{$column}` = " . $pdo->quote($target) .
            " WHERE LOWER(TRIM(`{$column}`)) IN ({$quotedLegacyValues})"
        );
    }

    $quotedAllowedValues = implode(', ', array_map([$pdo, 'quote'], $allowedValues));
    $pdo->exec(
        "UPDATE `{$table}` SET `{$column}` = {$quotedDefault}
         WHERE `{$column}` NOT IN ({$quotedAllowedValues}) OR `{$column}` = ''"
    );

    $enumValues = implode(', ', array_map([$pdo, 'quote'], $allowedValues));
    $pdo->exec(
        "ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$enumValues})
         NOT NULL DEFAULT {$quotedDefault}"
    );
}

function find_or_create_hospital(PDO $pdo, string $name, string $registration, string $location, string $phone, string $code): int
{
    $find = $pdo->prepare('SELECT id, verification_code_hash FROM hospitals WHERE name = ? LIMIT 1');
    $find->execute([$name]);
    $hospital = $find->fetch();
    if ($hospital) {
        return (int) $hospital['id'];
    }

    $insert = $pdo->prepare(
        "INSERT INTO hospitals (name, registration_number, location, phone, verification_code_hash, status)
         VALUES (?, ?, ?, ?, ?, 'Verified')"
    );
    $insert->execute([$name, $registration, $location, $phone, password_hash($code, PASSWORD_DEFAULT)]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_demo_user(PDO $pdo, array $data): int
{
    $find = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $find->execute([$data['email']]);
    $id = (int) $find->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    $insert = $pdo->prepare(
        "INSERT INTO users
         (hospital_id, full_name, email, password_hash, role, blood_group, location, phone,
          account_status, email_verified, phone_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', 1, 1)"
    );
    $insert->execute([
        $data['hospital_id'], $data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT),
        $data['role'], $data['blood_group'], $data['location'], $data['phone'],
    ]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') try {
    verify_csrf();
    if ($installed) throw new DomainException('Version 1.1.0 is already installed. Setup is locked and will not reset your data.');
    if ($existingUsers) {
        $admin = bb_one(db(),'SELECT * FROM users WHERE email=?',[strtolower(trim((string) ($_POST['admin_email']??'')))]);
        if (!$admin || !in_array($admin['role'],['admin','administrator'],true) || !password_verify((string) ($_POST['admin_password']??''),$admin['password_hash']) || !in_array($admin['account_status']??'Active',['Active','active','approved','verified'],true)) {
            throw new DomainException('Enter the existing administrator credentials to authorize the upgrade.');
        }
    } elseif (!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) {
        throw new DomainException('First installation must be opened locally on the XAMPP computer.');
    }
    if (!extension_loaded('pdo_mysql') || !extension_loaded('fileinfo')) throw new DomainException('Enable pdo_mysql and fileinfo in PHP before setup.');
    $server = db(true);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->exec('USE `' . DB_NAME . '`');

    $schema = file_get_contents(__DIR__ . '/database.sql');
    if ($schema === false) throw new RuntimeException('Could not read database.sql.');
    $schema = preg_replace('/^CREATE DATABASE.*?;\s*/ms', '', $schema);
    $schema = preg_replace('/^USE\s+\w+;\s*/mi', '', (string) $schema);
    $statements = array_filter(array_map('trim', explode(';', (string) $schema)));
    foreach ($statements as $statement) $server->exec($statement);

    // Safe migration for users who already ran an older BloodBridge version.
    add_missing_columns($server, 'users', [
        'hospital_id' => 'INT UNSIGNED NULL',
        'account_status' => "ENUM('Pending', 'Active', 'Blocked', 'Rejected') NOT NULL DEFAULT 'Active'",
        'email_verified' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'phone_verified' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'failed_login_attempts' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'locked_until' => 'DATETIME NULL',
        'last_login_at' => 'DATETIME NULL',
        'last_donation_date' => 'DATE NULL',
        'total_donations' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
        'is_available' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'screening_status' => "ENUM('Pending', 'Eligible', 'Temporarily Unavailable', 'Permanently Ineligible') NOT NULL DEFAULT 'Pending'",
        'has_medical_condition' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'medical_conditions' => 'TEXT NULL',
        'current_medications' => 'TEXT NULL',
        'verified_by_hospital' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'screened_by_user_id' => 'INT UNSIGNED NULL',
        'screened_at' => 'DATETIME NULL',
        'screening_notes' => 'VARCHAR(500) NULL',
        'profile_updated_at' => 'DATETIME NULL',
        'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    add_missing_columns($server, 'hospitals', [
        'registration_number' => 'VARCHAR(80) NULL',
        'verification_code_hash' => 'VARCHAR(255) NULL',
        'status' => "ENUM('Pending', 'Verified', 'Suspended') NOT NULL DEFAULT 'Verified'",
        'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    add_missing_columns($server, 'blood_inventory', [
        'reserved_units' => 'INT UNSIGNED NOT NULL DEFAULT 0',
    ]);
    add_missing_columns($server, 'blood_requests', [
        'hospital_id' => 'INT UNSIGNED NULL',
        'completed_at' => 'DATETIME NULL',
    ]);
    add_missing_columns($server, 'donation_history', [
        'verified_by_user_id' => 'INT UNSIGNED NULL',
    ]);
    add_missing_unique_index($server, 'users', 'unique_user_phone', 'phone');
    add_missing_unique_index($server, 'hospitals', 'unique_hospital_registration', 'registration_number');

    // Normalize ENUM definitions left by earlier 70% prototype databases.
    normalize_enum_column(
        $server,
        'users',
        'role',
        ['donor', 'seeker', 'hospital', 'admin'],
        'seeker',
        [
            'donor' => ['donor'],
            'seeker' => ['seeker', 'user', 'requester'],
            'hospital' => ['hospital', 'hospital_staff', 'staff'],
            'admin' => ['admin', 'administrator'],
        ]
    );
    normalize_enum_column(
        $server,
        'users',
        'account_status',
        ['Pending', 'Active', 'Blocked', 'Rejected'],
        'Pending',
        [
            'Active' => ['active', 'verified', 'approved'],
            'Blocked' => ['blocked', 'suspended', 'inactive'],
            'Rejected' => ['rejected', 'denied'],
            'Pending' => ['pending'],
        ]
    );
    normalize_enum_column(
        $server,
        'hospitals',
        'status',
        ['Pending', 'Verified', 'Suspended'],
        'Pending',
        [
            'Verified' => ['verified', 'approved', 'active'],
            'Suspended' => ['suspended', 'blocked', 'inactive'],
            'Pending' => ['pending'],
        ]
    );
    normalize_enum_column(
        $server,
        'hospital_staff_applications',
        'status',
        ['Pending', 'Approved', 'Rejected'],
        'Pending',
        [
            'Approved' => ['approved', 'verified', 'accepted', 'active'],
            'Rejected' => ['rejected', 'denied', 'blocked'],
            'Pending' => ['pending'],
        ]
    );

    migrate_v100($server);
    migrate_v110($server);
    $pdo = db();
    $pdo->beginTransaction();
    if (!$existingUsers) {
    $dhakaMedicalId = find_or_create_hospital(
        $pdo, 'Dhaka Medical College Blood Bank', 'BB-DMCH-001', 'Shahbag, Dhaka', '02-55165088', 'DMCH2026'
    );
    $kurmitolaId = find_or_create_hospital(
        $pdo, 'Kurmitola General Hospital Blood Bank', 'BB-KGH-002', 'Cantonment, Dhaka', '02-55062350', 'KGH2026'
    );

    $demoUsers = [
        ['name' => 'System Admin', 'email' => 'admin@bloodbridge.test', 'password' => 'admin123', 'role' => 'admin', 'blood_group' => null, 'location' => 'Dhaka', 'phone' => '01700000001', 'hospital_id' => null],
        ['name' => 'Blood Seeker', 'email' => 'seeker@bloodbridge.test', 'password' => 'seeker123', 'role' => 'seeker', 'blood_group' => 'B+', 'location' => 'Dhanmondi, Dhaka', 'phone' => '01700000002', 'hospital_id' => null],
        ['name' => 'Rahim Donor', 'email' => 'donor@bloodbridge.test', 'password' => 'donor123', 'role' => 'donor', 'blood_group' => 'B+', 'location' => 'Dhanmondi, Dhaka', 'phone' => '01700000003', 'hospital_id' => null],
        ['name' => 'Karim Donor', 'email' => 'donor2@bloodbridge.test', 'password' => 'donor123', 'role' => 'donor', 'blood_group' => 'O+', 'location' => 'Mirpur, Dhaka', 'phone' => '01700000004', 'hospital_id' => null],
        ['name' => 'Hospital Staff', 'email' => 'hospital@bloodbridge.test', 'password' => 'hospital123', 'role' => 'hospital', 'blood_group' => null, 'location' => 'Shahbag, Dhaka', 'phone' => '01700000005', 'hospital_id' => $dhakaMedicalId],
    ];
    $ids = [];
    foreach ($demoUsers as $demoUser) $ids[$demoUser['role'] . ':' . $demoUser['email']] = find_or_create_demo_user($pdo, $demoUser);

    $adminId = $ids['admin:admin@bloodbridge.test'];
    $seekerId = $ids['seeker:seeker@bloodbridge.test'];
    $donorId = $ids['donor:donor@bloodbridge.test'];
    $donor2Id = $ids['donor:donor2@bloodbridge.test'];
    $hospitalUserId = $ids['hospital:hospital@bloodbridge.test'];

    $pdo->exec("UPDATE users SET last_donation_date=DATE_SUB(CURDATE(),INTERVAL 130 DAY),donor_enabled=1,screening_status='Eligible',verified_by_hospital=1 WHERE id IN (".$donorId.','.$donor2Id.')');
    bb_exec($pdo,'INSERT INTO health_access (donor_id,hospital_id) VALUES (?,?),(?,?)',[$donorId,$dhakaMedicalId,$donor2Id,$dhakaMedicalId]);

    $application = $pdo->prepare(
        "INSERT INTO hospital_staff_applications
         (user_id, hospital_id, employee_id, designation, department, status, reviewed_by, reviewed_at, review_note)
         VALUES (?, ?, 'DMCH-DEMO-01', 'Blood Bank Officer', 'Transfusion Medicine', 'Approved', ?, NOW(), 'Demo staff account')
         ON DUPLICATE KEY UPDATE hospital_id = VALUES(hospital_id), status = 'Approved', reviewed_by = VALUES(reviewed_by), reviewed_at = NOW()"
    );
    $application->execute([$hospitalUserId, $dhakaMedicalId, $adminId]);

    $insertStock = $pdo->prepare(
        'INSERT INTO blood_inventory (hospital_id, blood_group, units, reserved_units)
         VALUES (?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE units = GREATEST(units, VALUES(units))'
    );
    foreach ([
        [$dhakaMedicalId, 'B+', 6], [$dhakaMedicalId, 'O+', 8], [$dhakaMedicalId, 'A+', 5], [$dhakaMedicalId, 'AB+', 2],
        [$kurmitolaId, 'B+', 3], [$kurmitolaId, 'O-', 2], [$kurmitolaId, 'A-', 1], [$kurmitolaId, 'O+', 4],
    ] as $stock) $insertStock->execute($stock);

    $requestCount = (int) $pdo->query('SELECT COUNT(*) FROM blood_requests')->fetchColumn();
    if ($requestCount === 0) {
        $insertRequest = $pdo->prepare(
            'INSERT INTO blood_requests (seeker_id, blood_group, location, units, urgency, source_type, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertRequest->execute([$seekerId, 'B+', 'Dhanmondi, Dhaka', 1, 'Emergency', 'Donor', 'Fictional lab-demo request. Upload a sample prescription before acceptance.']);
    }
    bb_exec($pdo,"INSERT INTO clubs (coordinator_id,name,university,location,reference,status,reviewed_by,reviewed_at) VALUES (?,'Demo Blood Donor Club','Demo University','Dhaka','DEMO-CLUB-001','Approved',?,NOW())",[$donorId,$adminId]);
    $clubId = (int) $pdo->lastInsertId();
    bb_exec($pdo,"INSERT INTO club_members (club_id,user_id,status) VALUES (?,?,'Approved'),(?,?,'Approved')",[$clubId,$donorId,$clubId,$donor2Id]);
    }
    normalize_saved_addresses($pdo);
    $zeroStock=$pdo->prepare('INSERT INTO blood_inventory (hospital_id,blood_group,units,reserved_units) SELECT id,?,0,0 FROM hospitals ON DUPLICATE KEY UPDATE hospital_id=VALUES(hospital_id)');
    foreach(valid_blood_groups() as $group) $zeroStock->execute([$group]);
    $pdo->exec("UPDATE blood_requests SET expires_at=DATE_ADD(NOW(),INTERVAL 72 HOUR) WHERE expires_at IS NULL AND status='Pending'");
    bb_exec($pdo,"INSERT INTO app_settings (setting_key,setting_value) VALUES ('schema_version','110') ON DUPLICATE KEY UPDATE setting_value='110'");
    $pdo->commit();
    $success = true;
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Setup: '.$exception->getMessage());
    $error = $exception instanceof DomainException ? $exception->getMessage() : 'Setup could not complete. Check Apache/PHP error logs and database settings. Your existing records were not deleted.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup | BloodBridge BD</title>
    <link rel="stylesheet" href="assets/css/style.css?v=70">
</head>
<body class="auth-body">
<main class="auth-card setup-card">
    <div class="auth-brand"><span class="brand-mark"><span>+</span></span><strong>BloodBridge BD Setup</strong></div>
    <?php if ($success): ?>
        <h1>Setup completed</h1>
        <p class="muted">The database schema, safe migrations, stakeholder accounts, hospital verification, inventory and workflow tables are ready.</p>
        <?php if(!$existingUsers): ?><div class="demo-accounts">
            <p><strong>Admin:</strong> admin@bloodbridge.test / admin123</p>
            <p><strong>Seeker:</strong> seeker@bloodbridge.test / seeker123</p>
            <p><strong>Donor:</strong> donor@bloodbridge.test / donor123</p>
            <p><strong>Hospital:</strong> hospital@bloodbridge.test / hospital123</p>
            <p><strong>Demo hospital code:</strong> DMCH2026</p>
        </div><?php else: ?><p class="muted">Your existing accounts and passwords were preserved. Sign in with your previous credentials; no demo passwords were reset.</p><?php endif; ?>
        <div class="form-actions"><a class="button button-primary" href="login.php">User Login</a><a class="button button-secondary" href="hospital_login.php">Hospital Login</a></div>
        <p class="security-note">Demo credentials and codes are for local academic testing only.</p>
    <?php elseif ($error): ?>
        <h1>Setup could not finish</h1>
        <div class="alert alert-error"><?= e($error) ?></div>
        <p class="muted">Start Apache and MySQL in XAMPP, then check the settings in config/database.php.</p>
        <a class="button button-secondary" href="setup.php">Back to setup</a>
    <?php elseif ($installed): ?>
        <h1>Already installed</h1><p>Version 1.1.0 is installed. Setup is locked; existing records and passwords will not be reset.</p><a class="button button-primary" href="login.php">Open Login</a>
    <?php else: ?>
        <h1><?= $existingUsers ? 'Upgrade existing project' : 'Install BloodBridge BD' ?></h1>
        <p class="muted">Version 1.1.0 · Local academic demonstration. Back up an existing database before upgrading. Installation does not delete tables.</p>
        <form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($existingUsers): ?><label class="form-wide"><span>Existing admin email</span><input type="email" name="admin_email" required autocomplete="username"></label><label class="form-wide"><span>Existing admin password</span><input type="password" name="admin_password" required autocomplete="current-password"></label><?php endif; ?>
        <div class="form-wide"><button class="button button-primary" type="submit"><?= $existingUsers ? 'Authorize upgrade' : 'Install demo database' ?></button></div></form>
    <?php endif; ?>
</main>
</body>
</html>
