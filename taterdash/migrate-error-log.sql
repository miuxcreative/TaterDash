-- ═══════════════════════════════════════════
-- TaterDash — Error Log Migration
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════

CREATE TABLE IF NOT EXISTS td_error_log (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    context      VARCHAR(100)    NOT NULL,   -- e.g. 'save-invoice', 'sign-proposal'
    message      TEXT            NOT NULL,
    request_data TEXT            NULL,       -- JSON snapshot of the incoming request (for debugging)
    is_resolved  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_created (created_at DESC),
    INDEX idx_unresolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
