-- ═══════════════════════════════════════════
-- TaterDash — Media Kit Stats + Proposal/Invoice Defaults Settings
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- td_settings already exists (from migrate-settings.sql). This adds
-- updated_at and seeds new keys without touching the existing
-- company_name / company_email / company_address rows.
--
-- NOTE: if updated_at already exists, the ALTER below will error with
-- "Duplicate column name 'updated_at'" — that's safe to ignore, same as
-- the pdf_path duplicate-column error from the last migration.

ALTER TABLE td_settings ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

INSERT INTO td_settings (setting_key, setting_value) VALUES
    ('stat_followers',            '67.8K'),
    ('stat_impressions',          '130K+'),
    ('stat_audience_age',         '79%'),
    ('stat_audience_age_label',   'ages 25–44'),
    ('stat_audience_women',       '63%'),
    ('stat_audience_men',         '37%'),
    ('stat_partnerships',         '500+'),
    ('contact_email',             'mallowfrenchie@gmail.com'),
    ('instagram_handle',          '@mallowfrenchie'),
    ('payment_terms_days',        '14'),
    ('proposal_validity_days',    '30'),
    ('deposit_percent',           '50'),
    ('about_blurb',               '3x Forbes featured French Bulldog in Miami who loves staycations, hugs ''n kisses, and being pampered! Usually on a quest for snacks, with a happy smile and sweet personality. Loves working with pet friendly brands and sharing fun finds with followers.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
