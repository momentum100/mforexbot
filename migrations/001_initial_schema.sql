-- Migration 001: Initial schema
-- Created: 2026-04-13

CREATE TABLE IF NOT EXISTS bots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    webapp_url VARCHAR(255) NULL COMMENT 'Base URL for Telegram Mini Web App (e.g. https://trade.example.com/app/1/)',
    linked_channel VARCHAR(255) NULL COMMENT 'Channel username or ID (e.g. @mychannel or -1001234567890). Bot must be admin in channel.',
    support_link VARCHAR(255) NULL COMMENT 'Support contact link (e.g. https://t.me/support_user)',
    referral_url_template VARCHAR(500) NULL COMMENT 'Referral URL template with {user_id} placeholder (e.g. https://affiliate.com/ref?uid={user_id})',
    postback_secret VARCHAR(255) NULL COMMENT 'Shared secret for postback URL authentication',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    telegram_id BIGINT NOT NULL,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    bio TEXT NULL,
    lang_code VARCHAR(5) NOT NULL DEFAULT 'en',
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('new', 'registered', 'deposited') NOT NULL DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bot_telegram (bot_id, telegram_id),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NULL,
    `key` VARCHAR(255) NOT NULL,
    lang_code VARCHAR(5) NOT NULL,
    value TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bot_key_lang (bot_id, `key`, lang_code),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS currency_pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NULL COMMENT 'NULL = shared across all bots',
    symbol VARCHAR(20) NOT NULL COMMENT 'e.g. EUR/USD',
    type ENUM('forex', 'otc') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bot_pair (bot_id, symbol, type),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    max_uses INT NULL COMMENT 'NULL = unlimited',
    current_uses INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bot_code (bot_id, code),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
