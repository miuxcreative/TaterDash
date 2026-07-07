-- ═══════════════════════════════════════════
-- TaterDash — Unguessable Public Link Tokens
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- NOTE: Hostinger's MariaDB version is not confirmed to support RANDOM_BYTES()
-- (added in MariaDB 10.10 / MySQL 8.0.28). This migration uses the MD5(...)
-- fallback for the one-time backfill so it works on older MariaDB too.
-- New rows going forward get a proper random token from PHP's random_bytes()
-- via generate_token() in config.php — this fallback only touches existing rows.

ALTER TABLE td_invoices  ADD COLUMN token VARCHAR(32) UNIQUE AFTER invoice_num;
ALTER TABLE td_proposals ADD COLUMN token VARCHAR(32) UNIQUE AFTER proposal_num;

UPDATE td_invoices  SET token = MD5(CONCAT(id, '-inv-', RAND(), '-', NOW(6))) WHERE token IS NULL;
UPDATE td_proposals SET token = MD5(CONCAT(id, '-prop-', RAND(), '-', NOW(6))) WHERE token IS NULL;
