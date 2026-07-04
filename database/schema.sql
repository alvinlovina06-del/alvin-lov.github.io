CREATE DATABASE IF NOT EXISTS uaskte_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uaskte_db;

-- ============================================
-- Tabel Users
-- Email di-hash dengan SHA512
-- updated_at otomatis terupdate saat ada perubahan
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_hash VARCHAR(128) NOT NULL COMMENT 'SHA512 hash of email',
    avatar_url TEXT,
    phone VARCHAR(20),
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel OTP Codes
-- Kode OTP di-hash dengan password_hash()
-- ============================================
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL COMMENT 'Hashed OTP using password_hash',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel WebAuthn Credentials (Biometric)
-- Menyimpan public key dari biometric device
-- ============================================
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(512) NOT NULL,
    public_key TEXT NOT NULL,
    sign_count INT UNSIGNED DEFAULT 0,
    device_name VARCHAR(255) DEFAULT 'Unknown Device',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_credential_id (credential_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Tabel Audit Logs
-- Mencatat setiap perubahan di tabel user
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    record_id BIGINT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    changed_by INT COMMENT 'User ID yang melakukan perubahan',
    ip_address VARCHAR(45),
    user_agent TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Seed Data: Admin default
-- Ganti email dan phone dengan data Anda
-- ============================================
INSERT INTO users (name, email, email_hash, phone, role) VALUES
('Administrator', 'admin@example.com', SHA2('admin@example.com', 512), '628123456789', 'admin')
ON DUPLICATE KEY UPDATE name = name;
