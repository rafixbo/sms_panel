-- ============================================
-- SMS PORTAL DATABASE SCHEMA (v2.0)
-- ============================================

CREATE DATABASE IF NOT EXISTS sms_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sms_portal;

-- API Keys Table
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    owner_username VARCHAR(100) NOT NULL,
    total_points INT DEFAULT 100,
    used_points INT DEFAULT 0,
    daily_limit INT DEFAULT 50,
    monthly_limit INT DEFAULT 1000,
    daily_used INT DEFAULT 0,
    monthly_used INT DEFAULT 0,
    allowed_ips TEXT DEFAULT NULL COMMENT 'JSON array of allowed IPs, NULL=all',
    watermark TEXT DEFAULT NULL COMMENT 'Watermark message appended to SMS body',
    key_format VARCHAR(20) DEFAULT 'xxx-xxxxxx-xxxxxx' COMMENT 'Key format style',
    assigned_gateway_id INT DEFAULT NULL COMMENT 'Assigned gateway ID, NULL=default',
    status ENUM('active','suspended','expired') DEFAULT 'active',
    expires_at DATETIME DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    last_reset_date DATE DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SMS Logs Table
CREATE TABLE IF NOT EXISTS sms_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    key_owner VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_hash VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    gateway_used VARCHAR(100) DEFAULT NULL,
    gateway_id INT DEFAULT NULL,
    proxy_used VARCHAR(255) DEFAULT NULL,
    response_status INT DEFAULT NULL,
    response_body TEXT DEFAULT NULL,
    api_response_code INT DEFAULT NULL COMMENT 'Actual status code from API response body',
    points_deducted INT DEFAULT 1,
    status ENUM('success','failed','pending') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_phone (phone_number),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status),
    INDEX idx_gateway (gateway_used),
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin Sessions
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- System Logs
CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('INFO','WARNING','ERROR','SECURITY') DEFAULT 'INFO',
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (log_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Points Transactions
CREATE TABLE IF NOT EXISTS points_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    transaction_type ENUM('credit','debit','reset') DEFAULT 'debit',
    points INT NOT NULL,
    balance_after INT NOT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Gateways Table
CREATE TABLE IF NOT EXISTS gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Gateway display name e.g. A1, A2, HisabKhata',
    method ENUM('GET','POST','BOTH') DEFAULT 'POST' COMMENT 'HTTP method for this gateway',
    url VARCHAR(500) NOT NULL COMMENT 'Gateway API URL',
    headers JSON DEFAULT NULL COMMENT 'Custom headers as JSON object',
    body_type ENUM('json','form','query') DEFAULT 'json' COMMENT 'Body encoding type',
    body_template JSON DEFAULT NULL COMMENT 'Body template with placeholder vars',
    param_phone VARCHAR(100) DEFAULT 'phone' COMMENT 'Param name for phone number',
    param_message VARCHAR(100) DEFAULT 'hash' COMMENT 'Param name for SMS message',
    extra_params JSON DEFAULT NULL COMMENT 'Extra static params as JSON',
    response_check JSON DEFAULT NULL COMMENT 'How to check response: {"status_field":"status","success_values":["success",true,200],"code_field":"code","error_field":"response.message"}',
    success_http_codes JSON DEFAULT '[200,201,202]' COMMENT 'HTTP codes considered successful',
    timeout INT DEFAULT 15 COMMENT 'Request timeout in seconds',
    proxy_enabled TINYINT(1) DEFAULT 0 COMMENT 'Whether to use proxy for this gateway',
    priority INT DEFAULT 0 COMMENT 'Higher = preferred, 0=default',
    status ENUM('active','inactive') DEFAULT 'active',
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Is this the default gateway?',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

-- Proxies Table
CREATE TABLE IF NOT EXISTS proxies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proxy_url VARCHAR(500) NOT NULL COMMENT 'Proxy URL e.g. http://user:pass@ip:port',
    proxy_type ENUM('http','https','socks4','socks5') DEFAULT 'http',
    max_requests INT DEFAULT 1 COMMENT 'Max requests before rotating (0=unlimited until fail)',
    used_count INT DEFAULT 0 COMMENT 'How many times used in current cycle',
    total_used INT DEFAULT 0 COMMENT 'Total lifetime uses',
    fail_count INT DEFAULT 0 COMMENT 'Consecutive failures',
    max_fails INT DEFAULT 3 COMMENT 'Max consecutive fails before disabling',
    cooldown_minutes INT DEFAULT 0 COMMENT 'Minutes to wait after max_requests reached',
    cooldown_until DATETIME DEFAULT NULL COMMENT 'When cooldown expires',
    status ENUM('active','cooldown','disabled') DEFAULT 'active',
    last_used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (proxy_type)
) ENGINE=InnoDB;
