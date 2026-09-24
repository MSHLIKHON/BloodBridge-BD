-- File purpose: Defines the base database schema and demo seed data.
CREATE DATABASE IF NOT EXISTS bloodbridge_bd
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bloodbridge_bd;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('donor', 'seeker', 'hospital', 'admin') NOT NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL,
  location VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NULL,
  account_status ENUM('Pending', 'Active', 'Blocked', 'Rejected') NOT NULL DEFAULT 'Pending',
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  phone_verified TINYINT(1) NOT NULL DEFAULT 0,
  failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  last_donation_date DATE NULL,
  total_donations SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  screening_status ENUM('Pending', 'Eligible', 'Temporarily Unavailable', 'Permanently Ineligible') NOT NULL DEFAULT 'Pending',
  has_medical_condition TINYINT(1) NOT NULL DEFAULT 0,
  medical_conditions TEXT NULL,
  current_medications TEXT NULL,
  verified_by_hospital TINYINT(1) NOT NULL DEFAULT 0,
  screened_by_user_id INT UNSIGNED NULL,
  screened_at DATETIME NULL,
  screening_notes VARCHAR(500) NULL,
  profile_updated_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_phone (phone),
  INDEX idx_users_role_status (role, account_status),
  INDEX idx_users_hospital (hospital_id),
  INDEX idx_users_blood_location (blood_group, location)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hospitals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  registration_number VARCHAR(80) NULL,
  location VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  verification_code_hash VARCHAR(255) NULL,
  status ENUM('Pending', 'Verified', 'Suspended') NOT NULL DEFAULT 'Verified',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_hospital_registration (registration_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blood_inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  units INT UNSIGNED NOT NULL DEFAULT 0,
  reserved_units INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_hospital_blood (hospital_id, blood_group),
  CONSTRAINT fk_inventory_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blood_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seeker_id INT UNSIGNED NOT NULL,
  hospital_id INT UNSIGNED NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  location VARCHAR(120) NOT NULL,
  units TINYINT UNSIGNED NOT NULL DEFAULT 1,
  urgency ENUM('Normal', 'Urgent', 'Emergency') NOT NULL DEFAULT 'Urgent',
  source_type ENUM('Donor', 'Blood Bank') NOT NULL DEFAULT 'Donor',
  note VARCHAR(500) NULL,
  status ENUM('Pending', 'Accepted', 'Rejected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  accepted_by INT UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_request_seeker FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
  CONSTRAINT fk_request_acceptor FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_request_match (blood_group, location, status),
  INDEX idx_request_hospital (hospital_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donation_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NULL,
  donor_id INT UNSIGNED NULL,
  hospital_id INT UNSIGNED NULL,
  verified_by_user_id INT UNSIGNED NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  units TINYINT UNSIGNED NOT NULL DEFAULT 1,
  donation_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_completed_request (request_id),
  CONSTRAINT fk_history_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS verification_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  purpose VARCHAR(40) NOT NULL DEFAULT 'Account Verification',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_code_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_code_lookup (user_id, purpose, used_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hospital_staff_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  hospital_id INT UNSIGNED NOT NULL,
  employee_id VARCHAR(80) NOT NULL,
  designation VARCHAR(120) NOT NULL,
  department VARCHAR(120) NULL,
  status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_staff_user (user_id),
  UNIQUE KEY unique_hospital_employee (hospital_id, employee_id),
  CONSTRAINT fk_application_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS request_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  responder_id INT UNSIGNED NOT NULL,
  response ENUM('Accepted', 'Rejected') NOT NULL,
  responded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_request_responder (request_id, responder_id),
  CONSTRAINT fk_response_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_response_user FOREIGN KEY (responder_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'Info',
  title VARCHAR(140) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notification_user (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blood_reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seeker_id INT UNSIGNED NOT NULL,
  hospital_id INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  units TINYINT UNSIGNED NOT NULL,
  status ENUM('Pending', 'Approved', 'Rejected', 'Collected', 'Cancelled') NOT NULL DEFAULT 'Pending',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  collection_code VARCHAR(20) NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservation_seeker FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservation_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservation_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_reservation_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_reservation_hospital (hospital_id, status),
  INDEX idx_reservation_seeker (seeker_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  inventory_id INT UNSIGNED NOT NULL,
  staff_user_id INT UNSIGNED NOT NULL,
  reservation_id INT UNSIGNED NULL,
  transaction_type ENUM('Donation Received', 'Patient Supplied', 'Expired', 'Correction', 'Reservation Collected') NOT NULL,
  unit_change SMALLINT NOT NULL,
  balance_after INT UNSIGNED NOT NULL,
  note VARCHAR(300) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transaction_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
  CONSTRAINT fk_transaction_inventory FOREIGN KEY (inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE,
  CONSTRAINT fk_transaction_staff FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transaction_reservation FOREIGN KEY (reservation_id) REFERENCES blood_reservations(id) ON DELETE SET NULL,
  INDEX idx_transaction_hospital_date (hospital_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;
