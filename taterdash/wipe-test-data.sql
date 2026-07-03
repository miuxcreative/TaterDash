-- ═══════════════════════════════════════════
-- TaterDash — Wipe Test Data
-- Run manually in phpMyAdmin. DESTRUCTIVE — see warning below.
-- ═══════════════════════════════════════════
--
-- This deletes EVERY row currently in these tables — not a filtered "delete
-- only test rows" query. Everything in the live database right now (Nike,
-- Airdog, the "Test" client, PROP-2026-00x, INV-2026-00x, etc.) is demo/test
-- data created while building the app, so a full wipe is safe — but if you've
-- entered any real client or invoice you want to keep, export it first:
--   phpMyAdmin → select table → Export → CSV
--
-- Order matters — child tables (signatures, line items, activity, errors)
-- are cleared before their parent tables to respect foreign keys.

DELETE FROM td_signatures;
DELETE FROM td_line_items;
DELETE FROM td_invoices;
DELETE FROM td_proposals;
DELETE FROM td_clients;
DELETE FROM td_activity;
DELETE FROM td_error_log;

-- Reset auto-increment counters so new records start at 1 again
ALTER TABLE td_signatures  AUTO_INCREMENT = 1;
ALTER TABLE td_line_items  AUTO_INCREMENT = 1;
ALTER TABLE td_invoices    AUTO_INCREMENT = 1;
ALTER TABLE td_proposals   AUTO_INCREMENT = 1;
ALTER TABLE td_clients     AUTO_INCREMENT = 1;
ALTER TABLE td_activity    AUTO_INCREMENT = 1;
ALTER TABLE td_error_log   AUTO_INCREMENT = 1;
