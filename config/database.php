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

// Optional environment configuration, also used by isolated CI test databases.
foreach (['host','port','name','user','pass'] as $key) {
    $override=getenv('BLOODBRIDGE_DB_'.strtoupper($key));
    if($override!==false) $databaseConfig[$key]=$override;
}

define('DB_HOST', (string) $databaseConfig['host']);
define('DB_PORT', (string) $databaseConfig['port']);
define('DB_NAME', (string) $databaseConfig['name']);
define('DB_USER', (string) $databaseConfig['user']);
define('DB_PASS', (string) $databaseConfig['pass']);

if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) throw new RuntimeException('Database name may contain letters, numbers and underscores only.');

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
        $connection = $pdo;
    }

    $pdo->exec("SET time_zone = '+06:00'");

    return $pdo;
}
