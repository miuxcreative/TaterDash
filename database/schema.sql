-- TaterDash Database Schema
-- Run this in phpMyAdmin > u335521326_TaterDash_db > SQL tab
-- Reflects live schema as of 2026-07; migrations in taterdash/ are the change history.

-- ── CLIENTS ──────────────────────────────────────────
CREATE TABLE td_clients (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  company       VARCHAR(255) NOT NULL,
  contact_name  VARCHAR(255),
  email         VARCHAR(255) NOT NULL,
  phone         VARCHAR(50),
  notes         TEXT,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── INVOICES ─────────────────────────────────────────
CREATE TABLE td_invoices (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  invoice_num   VARCHAR(50) NOT NULL UNIQUE,
  token         VARCHAR(32) UNIQUE,
  client_id     INT,
  client_name   VARCHAR(255) NOT NULL,
  client_email  VARCHAR(255) NOT NULL,
  issue_date    DATE NOT NULL,
  due_date      DATE,
  status        ENUM('draft','sent','viewed','paid') DEFAULT 'draft',
  subtotal      DECIMAL(10,2) DEFAULT 0.00,
  total         DECIMAL(10,2) DEFAULT 0.00,
  notes         TEXT,
  stripe_link   VARCHAR(500),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES td_clients(id) ON DELETE SET NULL
);

-- ── LINE ITEMS ───────────────────────────────────────
CREATE TABLE td_line_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id    INT NOT NULL,
  description   VARCHAR(500) NOT NULL,
  note          VARCHAR(500),
  quantity      DECIMAL(10,2) DEFAULT 1,
  unit_price    DECIMAL(10,2) NOT NULL,
  total         DECIMAL(10,2) NOT NULL,
  sort_order    INT DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES td_invoices(id) ON DELETE CASCADE
);

-- ── PROPOSALS ────────────────────────────────────────
CREATE TABLE td_proposals (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  proposal_num    VARCHAR(50) NOT NULL UNIQUE,
  token           VARCHAR(32) UNIQUE,
  client_id       INT,
  client_name     VARCHAR(255) NOT NULL,
  client_email    VARCHAR(255) NOT NULL,
  issue_date      DATE NOT NULL,
  expiry_date     DATE,
  status          ENUM('draft','sent','viewed','signed','accepted','declined') DEFAULT 'draft',
  scope           TEXT,
  deliverables    TEXT,
  total           DECIMAL(10,2) DEFAULT 0.00,
  notes           TEXT,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES td_clients(id) ON DELETE SET NULL
);

-- ── SIGNATURES ───────────────────────────────────────
CREATE TABLE td_signatures (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id     INT NOT NULL,
  signer_name     VARCHAR(255) NOT NULL,
  signer_email    VARCHAR(255) NOT NULL,
  signed_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address      VARCHAR(50),
  ip_direct       VARCHAR(50),
  user_agent      TEXT,
  FOREIGN KEY (proposal_id) REFERENCES td_proposals(id) ON DELETE CASCADE
);
