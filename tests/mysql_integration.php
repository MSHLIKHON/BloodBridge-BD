<?php
declare(strict_types=1);
// Native MySQL/MariaDB integration test. Creates and deletes ONLY a random test DB.
if(PHP_SAPI!=='cli') { http_response_code(403);exit('CLI only.'); }
$testDb='bb_qa_'.bin2hex(random_bytes(6));
putenv('BLOODBRIDGE_DB_NAME='.$testDb);
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../includes/donations.php';
require_once __DIR__.'/../includes/maintenance.php';
require_once __DIR__.'/../includes/clubs.php';
$legacy=($argv[1]??'')==='legacy';$results=[];$created=false;
function qa_assert(bool $condition,string $name):void { if(!$condition) throw new RuntimeException('FAIL: '.$name);echo 'PASS: '.$name.PHP_EOL; }
try {
    $server=db(true);
    $exists=bb_one($server,'SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?',[$testDb]);
    if((int)$exists['n']!==0) throw new RuntimeException('Refusing to overwrite an existing test-name database.');
    // The random name is explicit, validated and never the normal project database.
    if(!preg_match('/^bb_qa_[a-f0-9]{12}$/',$testDb)) throw new RuntimeException('Unsafe test database name.');
    $server->exec('CREATE DATABASE `'.$testDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    if($legacy) {
        $server->exec('USE `'.$testDb.'`');
        $schema=file_get_contents(__DIR__.'/../database.sql');
        $schema=preg_replace('/^CREATE DATABASE.*?;\s*/ms','',$schema);
        $schema=preg_replace('/^USE\s+\w+;\s*/mi','',$schema);
        foreach(array_filter(array_map('trim',explode(';',$schema))) as $sql) $server->exec($sql);
        bb_exec($server,"INSERT INTO users (full_name,email,password_hash,role,location,account_status,email_verified,phone_verified) VALUES ('Existing Admin','existing@example.test',?,'admin','Dhaka','Active',1,1)",[password_hash('Existing!123',PASSWORD_DEFAULT)]);
        $server->exec("ALTER TABLE hospitals MODIFY status ENUM('Pending','Active','Blocked') NOT NULL DEFAULT 'Pending'");
        bb_exec($server,"INSERT INTO hospitals (name,location,status) VALUES ('Legacy Hospital','Dhaka','Active')");
        bb_exec($server,"INSERT INTO blood_inventory (hospital_id,blood_group,units,reserved_units) VALUES (1,'B+',9,0)");
    }
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_POST=['csrf_token'=>csrf_token(),'admin_email'=>'existing@example.test','admin_password'=>'Existing!123'];
    ob_start();require __DIR__.'/../setup.php';$setupOutput=ob_get_clean();
    qa_assert($success===true,'Actual installer completes '.($legacy?'legacy upgrade':'fresh installation'));
    $pdo=db();
    qa_assert((int)$pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='schema_version'")->fetchColumn()===110,'Schema version recorded');
    $settingsBefore=$pdo->query('SELECT COUNT(*) FROM app_settings')->fetchColumn();
    migrate_v100($pdo);
    migrate_v110($pdo);
    qa_assert($pdo->query('SELECT COUNT(*) FROM app_settings')->fetchColumn()===$settingsBefore,'Migration is repeatable without duplicate settings');
    if($legacy) {
        qa_assert((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'Upgrade does not add or reset demo users');
        qa_assert(password_verify('Existing!123',$pdo->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()),'Existing password preserved');
        qa_assert((int)$pdo->query("SELECT units FROM blood_inventory WHERE hospital_id=1 AND blood_group='B+'")->fetchColumn()===9,'Existing inventory preserved');
        qa_assert($pdo->query('SELECT status FROM hospitals WHERE id=1')->fetchColumn()==='Verified','Legacy Active hospital status normalized');
    } else {
        qa_assert((int)$pdo->query('SELECT COUNT(*) FROM blood_inventory')->fetchColumn()===16,'All eight groups initialized per demo hospital');
        $donor=bb_one($pdo,"SELECT * FROM users WHERE email='donor@bloodbridge.test'");
        $staff=bb_one($pdo,"SELECT * FROM users WHERE email='hospital@bloodbridge.test'");
        $admin=bb_one($pdo,"SELECT * FROM users WHERE email='admin@bloodbridge.test'");
        qa_assert(password_verify('admin123',$admin['password_hash']),'Seed admin password works');
        $before=(int)bb_one($pdo,"SELECT units FROM blood_inventory WHERE hospital_id=? AND blood_group='B+'",[$staff['hospital_id']])['units'];
        $id=create_direct_donation($pdo,$donor,(int)$staff['hospital_id'],date('Y-m-d'),null);
        transition_direct_donation($pdo,$staff,$id,'screen','Fictional QA screening',true);
        transition_direct_donation($pdo,$staff,$id,'complete','Fictional QA released unit',true);
        qa_assert((int)bb_one($pdo,"SELECT units FROM blood_inventory WHERE hospital_id=? AND blood_group='B+'",[$staff['hospital_id']])['units']===$before+1,'Native transaction increases stock once');
        qa_assert((int)$pdo->query('SELECT COUNT(*) FROM donation_history')->fetchColumn()===1,'Native transaction records history once');
        $blocked=false;try {transition_direct_donation($pdo,$staff,$id,'complete','Duplicate QA',true);}catch(DomainException $e){$blocked=true;}
        qa_assert($blocked,'Duplicate native completion rejected');
        $first=run_maintenance($pdo);$second=run_maintenance($pdo);
        qa_assert(($first['reminders']??0)>=1 && ($second['reminders']??-1)===0,'Native reminder delivery is idempotent');
        qa_assert(($second['low_stock']??-1)===0,'Low-stock alerts do not repeat while low');
    }
    echo 'Native '.($legacy?'migration':'fresh/workflow').' suite complete.'.PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL);$failed=true; }
finally {
    if($created && isset($server) && preg_match('/^bb_qa_[a-f0-9]{12}$/',$testDb)) {
        $server->exec('DROP DATABASE `'.$testDb.'`');
        echo 'Removed disposable test database '.$testDb.'. No normal project database was changed.'.PHP_EOL;
    }
}
exit(isset($failed)?1:0);
