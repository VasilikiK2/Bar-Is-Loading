-- =====================================================
-- GYM MANAGEMENT SYSTEM - DATABASE SCHEMA
-- MySQL Database for Gym Check-in/Check-out System
-- =====================================================

CREATE DATABASE IF NOT EXISTS gym_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gym_system;

-- =====================================================
-- USERS (Admin/Staff)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin user (password: admin123 - ΑΛΛΑΞΕ ΤΟ!)
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin');

-- =====================================================
-- MEMBERS (Ασκούμενοι)
-- =====================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    registration_date DATE NOT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_barcode (barcode),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- =====================================================
-- MEMBERSHIPS (Συνδρομές)
-- Κάθε φορά που το μέλος πληρώνει, δημιουργείται μία νέα γραμμή
-- =====================================================
CREATE TABLE IF NOT EXISTS memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    type ENUM('open_gym','personal') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL COMMENT 'Για open_gym = start_date + 30 ημέρες',
    sessions_total INT NULL COMMENT 'Για personal = 12',
    sessions_used INT DEFAULT 0,
    price DECIMAL(8,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member_active (member_id, is_active),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB;

-- =====================================================
-- CHECK-INS (Είσοδοι/Έξοδοι)
-- =====================================================
CREATE TABLE IF NOT EXISTS checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    membership_id INT NULL,
    checkin_time DATETIME NOT NULL,
    checkout_time DATETIME NULL,
    duration_minutes INT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (membership_id) REFERENCES memberships(id) ON DELETE SET NULL,
    INDEX idx_member_time (member_id, checkin_time),
    INDEX idx_checkin_date (checkin_time)
) ENGINE=InnoDB;

-- =====================================================
-- PAYMENTS (Ιστορικό Πληρωμών)
-- =====================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    membership_id INT NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','other') DEFAULT 'cash',
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (membership_id) REFERENCES memberships(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_date (payment_date),
    INDEX idx_member (member_id)
) ENGINE=InnoDB;

-- =====================================================
-- EMAIL LOG (Ιστορικό αποστολής email)
-- =====================================================
CREATE TABLE IF NOT EXISTS email_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    email_type ENUM('welcome','payment_reminder','low_sessions','membership_expired','custom') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent','failed') NOT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_type_date (email_type, sent_at)
) ENGINE=InnoDB;

-- =====================================================
-- SETTINGS (Ρυθμίσεις γυμναστηρίου)
-- =====================================================
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('gym_name', 'Bar Is Loading'),
('gym_address', 'Διεύθυνση γυμναστηρίου'),
('gym_phone', '+30 210 0000000'),
('gym_email', 'barisloadingltd@gmail.gr'),
('open_gym_price', '45.00'),
('open_gym_duration_days', '30'),
('personal_price', '120.00'),
('personal_sessions', '12'),
('low_sessions_threshold', '3'),
('reminder_days_before', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =====================================================
-- VIEW: Ενεργές συνδρομές με υπόλοιπο
-- =====================================================
CREATE OR REPLACE VIEW v_active_memberships AS
SELECT
    m.id AS member_id,
    m.barcode,
    CONCAT(m.first_name, ' ', m.last_name) AS full_name,
    m.email,
    m.phone,
    ms.id AS membership_id,
    ms.type,
    ms.start_date,
    ms.end_date,
    ms.sessions_total,
    ms.sessions_used,
    (ms.sessions_total - ms.sessions_used) AS sessions_remaining,
    CASE
        WHEN ms.type = 'open_gym' THEN DATEDIFF(ms.end_date, CURDATE())
        ELSE NULL
    END AS days_remaining
FROM members m
INNER JOIN memberships ms ON ms.member_id = m.id
WHERE ms.is_active = 1
  AND m.status = 'active';
