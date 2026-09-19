<?php
declare(strict_types=1);

// Called only by the explicit, authorized installer. No DDL during normal requests.
function migrate_v100(PDO $pdo): void
{
    $hadDonorColumn=bb_one($pdo,"SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='donor_enabled'");
    add_missing_columns($pdo, 'users', [
        'donor_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'reminders_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'last_received_date' => 'DATE NULL',
    ]);
    if(!(int)$hadDonorColumn['n']) $pdo->exec("UPDATE users SET donor_enabled = 1 WHERE role = 'donor'");
    add_missing_columns($pdo, 'blood_inventory', ['low_stock_threshold' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 2']);
    add_missing_columns($pdo, 'blood_requests', [
        'expires_at' => 'DATETIME NULL',
        'patient_relation' => "VARCHAR(20) NOT NULL DEFAULT 'Self'",
        'patient_last_received' => 'DATE NULL',
        'prescription_status' => "VARCHAR(30) NOT NULL DEFAULT 'Missing'",
        'prescription_reviewer' => 'INT UNSIGNED NULL',
        'prescription_note' => 'VARCHAR(500) NULL',
        'review_hospital_id' => 'INT UNSIGNED NULL',
    ]);
    add_missing_columns($pdo, 'blood_reservations', ['expires_at' => 'DATETIME NULL']);
    add_missing_columns($pdo, 'hospitals', [
        'verification_reference' => 'VARCHAR(160) NULL',
        'verification_note' => 'VARCHAR(500) NULL',
        'reviewed_by' => 'INT UNSIGNED NULL',
        'reviewed_at' => 'DATETIME NULL',
    ]);
    add_missing_columns($pdo, 'donation_history', ['direct_donation_id' => 'INT UNSIGNED NULL']);
    add_missing_unique_index($pdo, 'donation_history', 'unique_direct_donation', 'direct_donation_id');
    normalize_enum_column($pdo, 'blood_requests', 'status', ['Pending','Accepted','Rejected','Completed','Cancelled','Expired'], 'Pending');
    normalize_enum_column($pdo, 'blood_reservations', 'status', ['Pending','Approved','Rejected','Collected','Cancelled','Expired'], 'Pending');
    $sql = file_get_contents(__DIR__ . '/../migrations/v100.sql');
    if ($sql === false) throw new RuntimeException('Missing migrations/v100.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
    $pdo->exec("UPDATE blood_requests SET expires_at = DATE_ADD(created_at, INTERVAL 72 HOUR) WHERE expires_at IS NULL AND status IN ('Pending','Accepted')");
    $pdo->exec("UPDATE blood_reservations SET expires_at = DATE_ADD(created_at, INTERVAL 48 HOUR) WHERE expires_at IS NULL AND status IN ('Pending','Approved')");
    // All groups exist, including empty stock, so low-stock summaries are complete.
    $stock = $pdo->prepare('INSERT INTO blood_inventory (hospital_id,blood_group,units,reserved_units) SELECT id,?,0,0 FROM hospitals ON DUPLICATE KEY UPDATE hospital_id=VALUES(hospital_id)');
    foreach (valid_blood_groups() as $group) $stock->execute([$group]);
    $settings = ['donation_interval_days'=>'120','reminder_days'=>'105','request_expiry_hours'=>'72','reservation_expiry_hours'=>'48','session_timeout_minutes'=>'30'];
    $insert = $pdo->prepare('INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)');
    foreach ($settings as $key=>$value) $insert->execute([$key,$value]);
}
