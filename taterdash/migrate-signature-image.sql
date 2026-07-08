-- ═══════════════════════════════════════════
-- TaterDash — Signature Canvas Image
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- Stores the draw-to-sign canvas as a base64 PNG data URL. MEDIUMTEXT
-- (up to 16MB) is comfortably enough for a signature-pad PNG.

ALTER TABLE td_signatures ADD COLUMN signature_image MEDIUMTEXT NULL AFTER signer_email;
