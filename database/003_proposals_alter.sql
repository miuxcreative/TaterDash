-- TaterDash — Migration 003: Add proposal columns
-- Run in phpMyAdmin > u335521326_TaterDash_db > SQL tab
-- NOTE: expiry_date already exists in td_proposals — skip the last line if you get a duplicate column error

ALTER TABLE td_proposals
  ADD COLUMN campaign_name     VARCHAR(255) AFTER client_email,
  ADD COLUMN platform          ENUM('Instagram','TikTok','Both') DEFAULT 'Instagram' AFTER campaign_name,
  ADD COLUMN campaign_start    DATE AFTER platform,
  ADD COLUMN campaign_end      DATE AFTER campaign_start,
  ADD COLUMN package_id        INT AFTER campaign_end,
  ADD COLUMN partner_industries TEXT AFTER package_id;

-- Only run this if expiry_date does NOT already exist in td_proposals:
-- ALTER TABLE td_proposals ADD COLUMN expiry_date DATE AFTER partner_industries;
