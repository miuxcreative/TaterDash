-- ═══════════════════════════════════════════
-- TaterDash — Add 'resent' + 'emailed' activity events
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════
-- td_activity.event_type is an ENUM (see migrate-activity-log.sql), so new
-- values must be added explicitly or inserts using them will fail.
-- 'resent' is used by send-document.php when a document is emailed again
-- after it already left draft status. 'emailed' has no call site yet —
-- added for forward compatibility per the task spec, not currently logged.

ALTER TABLE td_activity
  MODIFY COLUMN event_type ENUM('created','sent','viewed','paid','signed','deleted','from_proposal','resent','emailed') NOT NULL;
