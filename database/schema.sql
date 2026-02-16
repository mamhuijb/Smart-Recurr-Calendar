-- SmartRecur Calendar - MariaDB Schema
-- Run this file to initialize the database:
--   mysql -u root -p smartrecur < database/schema.sql

CREATE DATABASE IF NOT EXISTS smartrecur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartrecur;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin user (password: change_me_on_first_login)
INSERT INTO users (username, password_hash) VALUES
    ('admin', '$2y$12$LJ3m4ys3Gql.YnGBiHZ5cuXwMz0JbMHOXlRP1mVIi3bGJMsU5FUPW')
ON DUPLICATE KEY UPDATE username = username;

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    id VARCHAR(36) PRIMARY KEY,
    company VARCHAR(255) NOT NULL DEFAULT '',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    address TEXT DEFAULT NULL,
    postcode VARCHAR(20) DEFAULT '',
    syncro_id VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company),
    INDEX idx_syncro_id (syncro_id)
) ENGINE=InnoDB;

-- ============================================================
-- ASSETS (linked to customers)
-- ============================================================
CREATE TABLE IF NOT EXISTS assets (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SERVICES
-- ============================================================
CREATE TABLE IF NOT EXISTS services (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('RECURRING', 'ONE_TIME') NOT NULL DEFAULT 'RECURRING',
    default_duration_min INT NOT NULL DEFAULT 60,
    default_location ENUM('REMOTE', 'ON_SITE') NOT NULL DEFAULT 'ON_SITE',
    color VARCHAR(7) NOT NULL DEFAULT '#4F46E5',
    create_ticket TINYINT(1) NOT NULL DEFAULT 0,
    email_template_subject VARCHAR(500) DEFAULT NULL,
    email_template_body TEXT DEFAULT NULL,
    reminder_days JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TECHNICIANS
-- ============================================================
CREATE TABLE IF NOT EXISTS technicians (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT '',
    color VARCHAR(7) NOT NULL DEFAULT '#10B981',
    skills JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- EVENTS (appointments)
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    customer_id VARCHAR(36) NOT NULL,
    service_id VARCHAR(36) NOT NULL,
    technician_id VARCHAR(36) DEFAULT NULL,
    asset_id VARCHAR(36) DEFAULT NULL,
    syncro_ticket_id VARCHAR(50) DEFAULT NULL,
    location_type ENUM('REMOTE', 'ON_SITE') NOT NULL DEFAULT 'ON_SITE',
    description TEXT DEFAULT NULL,
    recurrence_rule TEXT DEFAULT NULL,
    generated_dates JSON NOT NULL,
    status ENUM('SCHEDULED', 'COMPLETED', 'MISSED') NOT NULL DEFAULT 'SCHEDULED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_service (service_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- SETTINGS (key-value store)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
    ('branding', JSON_OBJECT('logoUrl', '', 'primaryColorHex', '#4f46e5', 'themeMode', 'dark')),
    ('security', JSON_OBJECT('twoFactorEnabled', false, 'twoFactorSecret', '')),
    ('reminders', JSON_OBJECT('days', JSON_ARRAY(14, 7, 1))),
    ('holidays', JSON_ARRAY('2025-01-01', '2025-04-27', '2025-12-25', '2025-12-26')),
    ('manualClosures', JSON_ARRAY()),
    ('businessHours', JSON_OBJECT('start', '09:00', 'end', '17:00', 'closedDays', JSON_ARRAY(0))),
    ('preferredMailMethod', '"auto"'),
    ('templates', JSON_OBJECT('reminder', JSON_OBJECT(
        'subject', 'Appointment: {service_name} - {date}',
        'body', 'Hi {customer_name},\n\nWe have scheduled a technician ({tech_name}) for {service_name} on {date}.\nLocation: {location_type}\n\nPlease click here to confirm or reschedule: {link}\n\nMet vriendelijke groet,\n{company_name}'
    )))
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================================
-- EMAIL LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS email_logs (
    id VARCHAR(36) PRIMARY KEY,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    method VARCHAR(20) NOT NULL DEFAULT 'smtp',
    error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- INTEGRATION CONFIGS
-- ============================================================
CREATE TABLE IF NOT EXISTS integration_configs (
    id VARCHAR(50) PRIMARY KEY,
    config JSON NOT NULL,
    is_connected TINYINT(1) NOT NULL DEFAULT 0,
    last_checked TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default integration entries
INSERT INTO integration_configs (id, config) VALUES
    ('office365', JSON_OBJECT('clientId', '', 'clientSecret', '', 'tenantId', 'common', 'redirectUri', '', 'accessToken', '', 'userEmail', '')),
    ('syncro', JSON_OBJECT('apiKey', '', 'subdomain', '')),
    ('invoiceninja', JSON_OBJECT('apiKey', '', 'endpoint', 'https://app.invoiceninja.com')),
    ('zoho', JSON_OBJECT('apiKey', '', 'apiSecret', '', 'endpoint', 'https://www.zohoapis.com')),
    ('smtp', JSON_OBJECT('host', '', 'port', 587, 'username', '', 'password', '', 'fromEmail', '', 'fromName', 'SmartRecur', 'encryption', 'tls'))
ON DUPLICATE KEY UPDATE id = id;
