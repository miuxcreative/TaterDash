-- TaterDash — Migration 002: Proposals
-- Run in phpMyAdmin > u335521326_TaterDash_db > SQL tab

CREATE TABLE td_packages (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  deliverables  TEXT,
  price         DECIMAL(10,2) NOT NULL,
  is_custom     TINYINT(1) DEFAULT 0,
  is_active     TINYINT(1) DEFAULT 1,
  sort_order    INT DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE td_partners (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(255) NOT NULL,
  logo_url      VARCHAR(500) NOT NULL,
  industry      VARCHAR(100) NOT NULL,
  sort_order    INT DEFAULT 0,
  is_active     TINYINT(1) DEFAULT 1
);

-- Seed packages
INSERT INTO td_packages (name, description, deliverables, price, sort_order) VALUES
('Package 1', '1 in-feed reel or carousel', '["1 in-feed reel (10-30 sec) or carousel (3+ images)","1 dedicated caption + tag to your IG","2 tagged stories + bio link (72hr)","Unlimited usage rights","Access to all HD files"]', 2100.00, 1),
('Package 2', '2 in-feed reels or carousels', '["2 in-feed reels or carousels","1 dedicated caption + tag to your IG","4 tagged stories + bio link","Unlimited usage rights","Access to all HD files"]', 3800.00, 2),
('Package 3', 'Full campaign package', '["3 in-feed reels + carousel posts","3 dedicated captions + tags to your IG","6 tagged stories + links to your website","Unlimited usage rights","Access to all HD files","Link in bio through duration of partnership"]', 4700.00, 3);

-- Seed partners by industry
INSERT INTO td_partners (name, logo_url, industry, sort_order) VALUES
('Toyota', 'https://mallowfrenchie.com/images/partners/toyota.png', 'Auto & Transport', 1),
('BMW', 'https://mallowfrenchie.com/images/partners/bmw.png', 'Auto & Transport', 2),
('MINI', 'https://mallowfrenchie.com/images/partners/mini.png', 'Auto & Transport', 3),
('Brightline', 'https://mallowfrenchie.com/images/partners/brightline.png', 'Auto & Transport', 4),
('Nobu', 'https://mallowfrenchie.com/images/partners/nobu.png', 'Hotels & Hospitality', 1),
('W Hotel', 'https://mallowfrenchie.com/images/partners/whotel.png', 'Hotels & Hospitality', 2),
('Kimpton', 'https://mallowfrenchie.com/images/partners/kimpton.png', 'Hotels & Hospitality', 3),
('Marriott', 'https://mallowfrenchie.com/images/partners/marriott.png', 'Hotels & Hospitality', 4),
('Hilton', 'https://mallowfrenchie.com/images/partners/hilton.png', 'Hotels & Hospitality', 5),
('Hyatt', 'https://mallowfrenchie.com/images/partners/hyatt.png', 'Hotels & Hospitality', 6),
('Eden Roc', 'https://mallowfrenchie.com/images/partners/edenroc.png', 'Hotels & Hospitality', 7),
('BarkBox', 'https://mallowfrenchie.com/images/partners/barkbox.png', 'Pet & Lifestyle', 1),
('NutraVet', 'https://mallowfrenchie.com/images/partners/nutravet.png', 'Pet & Lifestyle', 2),
('PetSupermarket', 'https://mallowfrenchie.com/images/partners/petsupermarket.png', 'Pet & Lifestyle', 3),
('Wag', 'https://mallowfrenchie.com/images/partners/wag.png', 'Pet & Lifestyle', 4),
('Disney', 'https://mallowfrenchie.com/images/partners/disney.png', 'Entertainment & Media', 1),
('Amazon', 'https://mallowfrenchie.com/images/partners/amazon.png', 'Entertainment & Media', 2),
('Miami Marlins', 'https://mallowfrenchie.com/images/partners/marlins.png', 'Entertainment & Media', 3),
('SOBEWFF', 'https://mallowfrenchie.com/images/partners/sobewff.png', 'Entertainment & Media', 4);
