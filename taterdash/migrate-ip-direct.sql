-- ═══════════════════════════════════════════
-- TaterDash — Signature Direct IP Column
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- ip_address already stores X-Forwarded-For (may be spoofed/proxy-supplied).
-- ip_direct stores REMOTE_ADDR unconditionally, alongside it.

ALTER TABLE td_signatures ADD COLUMN ip_direct VARCHAR(50) AFTER ip_address;
