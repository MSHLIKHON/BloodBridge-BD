<?php
/** File purpose: Database.local.example supplies application configuration without committing local secrets. */
declare(strict_types=1);

// Copy this file as database.local.php and change only the needed values.
// database.local.php is ignored by Git, so a real password is not uploaded.
return [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'bloodbridge_bd',
    'user' => 'root',
    'pass' => 'your_mysql_password',
];
