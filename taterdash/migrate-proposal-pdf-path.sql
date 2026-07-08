-- ═══════════════════════════════════════════
-- TaterDash — Signed Proposal PDF Path
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════

ALTER TABLE td_proposals ADD COLUMN pdf_path VARCHAR(255) NULL AFTER token;
