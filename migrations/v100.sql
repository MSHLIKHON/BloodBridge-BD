-- File purpose: V100 contains the versioned SQL migration for an existing installation.
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value VARCHAR(500) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clubs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 coordinator_id INT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL,
 university VARCHAR(160) NOT NULL,
 location VARCHAR(120) NOT NULL,
 reference VARCHAR(160) NOT NULL,
 status ENUM('Pending','Approved','Rejected','Suspended') NOT NULL DEFAULT 'Pending',
 review_note VARCHAR(500) NULL,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (coordinator_id) REFERENCES users(id) ON DELETE RESTRICT,
 UNIQUE KEY club_identity (university,name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS club_members (
 club_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
 joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (club_id,user_id),
 FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS club_request_shares (
 club_id INT UNSIGNED NOT NULL,
 request_id INT UNSIGNED NOT NULL,
 shared_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (club_id,request_id),
 FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
 FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaigns (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 club_id INT UNSIGNED NOT NULL,
 hospital_id INT UNSIGNED NOT NULL,
 title VARCHAR(160) NOT NULL,
 location VARCHAR(120) NOT NULL,
 event_at DATETIME NOT NULL,
 status ENUM('Scheduled','Cancelled','Completed') NOT NULL DEFAULT 'Scheduled',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
 FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaign_registrations (
 campaign_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (campaign_id,user_id),
 FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS health_access (
 donor_id INT UNSIGNED NOT NULL,
 hospital_id INT UNSIGNED NOT NULL,
 granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (donor_id,hospital_id),
 FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS private_documents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id INT UNSIGNED NOT NULL,
 request_id INT UNSIGNED NULL,
 kind ENUM('Health','Prescription') NOT NULL,
 filename VARCHAR(160) NOT NULL,
 mime_type VARCHAR(60) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 file_data MEDIUMBLOB NOT NULL,
 test_name VARCHAR(120) NULL,
 test_value VARCHAR(40) NULL,
 test_unit VARCHAR(30) NULL,
 test_date DATE NULL,
 lab_name VARCHAR(160) NULL,
 status ENUM('Self-reported','Reviewed','Needs correction') NOT NULL DEFAULT 'Self-reported',
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_note VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
 INDEX doc_owner_kind (owner_id,kind),
 INDEX doc_request (request_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_donations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 donor_id INT UNSIGNED NOT NULL,
 hospital_id INT UNSIGNED NOT NULL,
 club_id INT UNSIGNED NULL,
 blood_group VARCHAR(3) NOT NULL,
 appointment_date DATE NOT NULL,
 status ENUM('Pending','Screened','Completed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
 screened_by INT UNSIGNED NULL,
 screened_at DATETIME NULL,
 screening_note VARCHAR(500) NULL,
 completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
 FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE SET NULL,
 INDEX direct_donor (donor_id,status),
 INDEX direct_hospital (hospital_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reminder_deliveries (
 user_id INT UNSIGNED NOT NULL,
 donation_date DATE NOT NULL,
 delivered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,donation_date),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finished_at DATETIME NULL,
 summary VARCHAR(500) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS low_stock_notices (
 inventory_id INT UNSIGNED PRIMARY KEY,
 is_low TINYINT(1) NOT NULL DEFAULT 0,
 FOREIGN KEY (inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB;
