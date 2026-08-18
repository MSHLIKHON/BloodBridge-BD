<?php
declare(strict_types=1);

// Default XAMPP settings. Personal settings can stay in database.local.php.
$databaseConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'bloodbridge_bd',
    'user' => 'root',
    'pass' => '',
];

$localConfigFile = __DIR__ . '/database.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $databaseConfig = array_merge($databaseConfig, $localConfig);
    }
}

define('DB_HOST', (string) $databaseConfig['host']);
define('DB_PORT', (string) $databaseConfig['port']);
define('DB_NAME', (string) $databaseConfig['name']);
define('DB_USER', (string) $databaseConfig['user']);
define('DB_PASS', (string) $databaseConfig['pass']);

function db(bool $withoutDatabase = false): PDO
{
    static $connection = null;

    if (!$withoutDatabase && $connection instanceof PDO) {
        return $connection;
    }

    $database = $withoutDatabase ? '' : ';dbname=' . DB_NAME;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . $database . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (!$withoutDatabase) {
        ensure_user_columns($pdo);
        $connection = $pdo;
    }

    return $pdo;
}

function ensure_user_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
    )->fetchColumn();

    if ($tableExists === 0) {
        return;
    }

    $columns = [
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

    $columnExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    foreach ($columns as $name => $definition) {
        $columnExists->execute(['users', $name]);
        if ((int) $columnExists->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE users ADD COLUMN `' . $name . '` ' . $definition);
        }
    }

    $checked = true;
}
