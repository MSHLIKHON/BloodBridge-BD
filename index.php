<?php
/** File purpose: Index handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirect(logged_in() ? 'dashboard.php' : 'login.php');
