<?php
/** File purpose: Schema V110 applies an idempotent database schema upgrade. */
declare(strict_types=1);

function migrate_v110(PDO $pdo): void
{
    $hadConfirmation=bb_one($pdo,"SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blood_requests' AND COLUMN_NAME='donor_confirmed_at'");
    add_missing_columns($pdo, 'users', [
        'latitude'=>'DECIMAL(10,7) NULL', 'longitude'=>'DECIMAL(10,7) NULL',
        'location_consent'=>'TINYINT(1) NOT NULL DEFAULT 0', 'location_updated_at'=>'DATETIME NULL',
    ]);
    add_missing_columns($pdo, 'blood_requests', [
        'latitude'=>'DECIMAL(10,7) NULL', 'longitude'=>'DECIMAL(10,7) NULL',
        'donor_confirmed_at'=>'DATETIME NULL', 'donor_reported_at'=>'DATETIME NULL',
        'outcome'=>"VARCHAR(30) NOT NULL DEFAULT 'Awaiting'", 'outcome_note'=>'VARCHAR(500) NULL',
    ]);
    normalize_enum_column($pdo,'request_responses','response',['Interested','Selected','Accepted','Rejected','Not selected','Withdrawn'],'Rejected');
    $pdo->exec("CREATE TABLE IF NOT EXISTS request_events (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      request_id INT UNSIGNED NOT NULL, actor_id INT UNSIGNED NULL,
      event VARCHAR(80) NOT NULL, details VARCHAR(500) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX request_event_order(request_id,id),
      FOREIGN KEY(request_id) REFERENCES blood_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $pdo->exec("UPDATE blood_requests SET outcome='Received' WHERE status='Completed' AND outcome='Awaiting'");
    $pdo->exec("UPDATE blood_requests SET outcome='Not received' WHERE status IN ('Rejected','Cancelled','Expired') AND outcome='Awaiting'");
    if(!(int)$hadConfirmation['n']) $pdo->exec("UPDATE blood_requests SET donor_confirmed_at=COALESCE(updated_at,created_at) WHERE source_type='Donor' AND status='Accepted' AND donor_confirmed_at IS NULL");
}
