-- Migration 004: Seed supported languages
-- Created: 2026-04-13

CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(5) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    native_name VARCHAR(50) NOT NULL,
    flag VARCHAR(10) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, native_name, flag, sort_order) VALUES
('en', 'English',      'English',      '🇬🇧', 1),
('ru', 'Russian',      'Русский',      '🇷🇺', 2),
('es', 'Spanish',      'Español',      '🇪🇸', 3),
('ar', 'Arabic',       'العربية',       '🇸🇦', 4),
('pt', 'Portuguese',   'Português',    '🇧🇷', 5),
('tr', 'Turkish',      'Türkçe',       '🇹🇷', 6),
('hi', 'Hindi',        'हिन्दी',         '🇮🇳', 7),
('uz', 'Uzbek',        'O\'zbek',      '🇺🇿', 8),
('az', 'Azerbaijani',  'Azerbaycan',   '🇦🇿', 9),
('tg', 'Tajik',        'Тоҷикӣ',       '🇹🇯', 10),
('ko', 'Korean',       '한국어',         '🇰🇷', 11);
