-- ═══════════════════════════════════════════
-- TaterDash — Editable Company Settings
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- company_address is intentionally NOT seeded here — enter the real
-- address by hand via /taterdash-app/admin/settings.php after this runs.
-- Keeps it out of git entirely (source, migrations, commit history).

CREATE TABLE IF NOT EXISTS td_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO td_settings (setting_key, setting_value) VALUES
    ('company_name',  'Mallow Frenchie'),
    ('company_email', 'mallowfrenchie@gmail.com')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
