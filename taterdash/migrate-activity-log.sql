-- ═══════════════════════════════════════════
-- TaterDash — Activity Log Migration
-- Run once on Hostinger via phpMyAdmin
-- ═══════════════════════════════════════════

CREATE TABLE IF NOT EXISTS td_activity (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    event_type  ENUM('created','sent','viewed','paid','signed','deleted','from_proposal')
                                NOT NULL,
    entity_type ENUM('invoice','proposal')
                                NOT NULL,
    entity_id   INT UNSIGNED    NOT NULL,
    entity_num  VARCHAR(20)     NOT NULL,
    entity_name VARCHAR(255)    NOT NULL DEFAULT '',
    amount      DECIMAL(10,2)   NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_created (created_at DESC),
    INDEX idx_unread  (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
