-- ═══════════════════════════════════════════
-- TaterDash — Stripe activation
-- Run once in phpMyAdmin > u335521326_TaterDash_db > SQL tab
--
-- Adds the 'payment_processing' status (designed in the UI since the
-- client-facing redesign but with no trigger until now: it's the window
-- between the client landing on Stripe Checkout and the webhook confirming
-- payment), plus the columns needed to tie an invoice to a Checkout Session.
-- ═══════════════════════════════════════════

ALTER TABLE td_invoices
  MODIFY status ENUM('draft','sent','viewed','payment_processing','paid')
  NOT NULL DEFAULT 'draft';

ALTER TABLE td_invoices
  ADD COLUMN stripe_session_id  VARCHAR(255) NULL AFTER stripe_link,
  ADD COLUMN stripe_payment_intent VARCHAR(255) NULL AFTER stripe_session_id,
  ADD COLUMN paid_at            DATETIME     NULL AFTER stripe_payment_intent;

-- Looked up by the webhook on every payment event, so it needs an index.
CREATE INDEX idx_td_invoices_stripe_session ON td_invoices (stripe_session_id);

-- Idempotency ledger: Stripe retries webhooks, and the same event can arrive
-- more than once. A UNIQUE event_id means a replay is a duplicate-key error
-- instead of a second "paid" log entry or a second confirmation email.
CREATE TABLE IF NOT EXISTS td_stripe_events (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  event_id     VARCHAR(255) NOT NULL UNIQUE,
  event_type   VARCHAR(100) NOT NULL,
  invoice_id   INT NULL,
  payload      TEXT,
  received_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
