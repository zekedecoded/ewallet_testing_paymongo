-- ============================================================
-- School E-Wallet System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS school_ewallet
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE school_ewallet;

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)    NOT NULL,
    student_id  VARCHAR(30)     UNIQUE,          -- NULL for merchants/admins
    role        ENUM('student','merchant','admin') NOT NULL DEFAULT 'student',
    email       VARCHAR(150)    UNIQUE,
    password    VARCHAR(255)    NOT NULL,
    avatar      VARCHAR(10)     DEFAULT NULL,    -- emoji avatar
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_role       (role)
) ENGINE=InnoDB;

-- ─── WALLETS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wallets (
    user_id     INT UNSIGNED PRIMARY KEY,
    balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── TRANSACTIONS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    description VARCHAR(255)  DEFAULT NULL,
    status      ENUM('success','failed','reversed') DEFAULT 'success',
    ref_code    VARCHAR(32)   UNIQUE,            -- unique txn reference
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created  (created_at),
    CONSTRAINT fk_txn_sender
        FOREIGN KEY (sender_id)   REFERENCES users(id),
    CONSTRAINT fk_txn_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── QR TOKENS (expiring, single-use) ───────────────────────
CREATE TABLE IF NOT EXISTS qr_tokens (
    token       VARCHAR(64)   PRIMARY KEY,
    merchant_id INT UNSIGNED  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    description VARCHAR(255)  DEFAULT NULL,
    used        TINYINT(1)    DEFAULT 0,
    expires_at  DATETIME      NOT NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merchant (merchant_id),
    CONSTRAINT fk_qr_merchant
        FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── SEED DATA ───────────────────────────────────────────────
-- Passwords are all "password123" hashed with bcrypt
INSERT INTO users (name, student_id, role, email, password, avatar) VALUES
('Admin User',    NULL,        'admin',    'admin@school.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/6RoFm', '🛡️'),
('Canteen Staff', NULL,        'merchant', 'canteen@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/6RoFm', '🍱'),
('Zeke Reyes',    'STU-2024-001','student', 'zeke@student.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/6RoFm', '⚡'),
('Ana Santos',    'STU-2024-002','student', 'ana@student.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/6RoFm', '🌸'),
('Ben Cruz',      'STU-2024-003','student', 'ben@student.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc/6RoFm', '🎯');

INSERT INTO wallets (user_id, balance) VALUES
(1, 0.00),
(2, 5000.00),
(3, 250.00),
(4, 180.50),
(5, 75.25);

-- ─── PAYMONGO TOP-UP REQUESTS ───────────────────────────────
CREATE TABLE IF NOT EXISTS topup_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    paymongo_link_id    VARCHAR(100) UNIQUE,
    paymongo_payment_id VARCHAR(100),
    checkout_url        TEXT,
    status              ENUM('pending','paid','failed','expired') DEFAULT 'pending',
    ref_code            VARCHAR(32) UNIQUE,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at             DATETIME,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    INDEX idx_link   (paymongo_link_id),
    CONSTRAINT fk_topup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
