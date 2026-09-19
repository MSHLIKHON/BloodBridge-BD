<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../includes/maintenance.php';
if (!v100_ready()) { fwrite(STDERR,"Run setup.php first.\n"); exit(1); }
try { echo json_encode(run_maintenance(db()),JSON_PRETTY_PRINT).PHP_EOL; }
catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
