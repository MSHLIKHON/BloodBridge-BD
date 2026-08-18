CREATE DATABASE IF NOT EXISTS bloodbridge_bd
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bloodbridge_bd;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('donor', 'seeker', 'hospital', 'admin') NOT NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL,
  location VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  last_donation_date DATE NULL,
  total_donations SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  screening_status ENUM('Pending', 'Eligible', 'Temporarily Unavailable', 'Permanently Ineligible') NOT NULL DEFAULT 'Pending',
  has_medical_condition TINYINT(1) NOT NULL DEFAULT 0,
  medical_conditions TEXT NULL,
  current_medications TEXT NULL,
  verified_by_hospital TINYINT(1) NOT NULL DEFAULT 0,
  screening_notes VARCHAR(500) NULL,
  profile_updated_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hospitals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  location VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blood_inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  units INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_hospital_blood (hospital_id, blood_group),
  CONSTRAINT fk_inventory_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blood_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seeker_id INT UNSIGNED NOT NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  location VARCHAR(120) NOT NULL,
  units TINYINT UNSIGNED NOT NULL DEFAULT 1,
  urgency ENUM('Normal', 'Urgent', 'Emergency') NOT NULL DEFAULT 'Urgent',
  source_type ENUM('Donor', 'Blood Bank') NOT NULL DEFAULT 'Donor',
  note VARCHAR(500) NULL,
  status ENUM('Pending', 'Accepted', 'Rejected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  accepted_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_request_seeker FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_acceptor FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_request_match (blood_group, location, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donation_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NULL,
  donor_id INT UNSIGNED NULL,
  hospital_id INT UNSIGNED NULL,
  blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  units TINYINT UNSIGNED NOT NULL DEFAULT 1,
  donation_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
) ENGINE=InnoDB;
