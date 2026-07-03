<?php
// ═══════════════════════════════════════════
// TaterDash — New Proposal
// /taterdash-app/admin/new-proposal.php
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo = db_connect();

$packages  = $pdo->query("SELECT * FROM td_packages WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$industries = $pdo->query("SELECT DISTINCT industry FROM td_partners WHERE is_active = 1 ORDER BY industry")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaterDash — New Proposal</title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --ink:       #191919;
      --ink-mid:   #6b6b6b;
      --ink-light: #b0b0b0;
      --white:     #ffffff;
      --blush:     #faf0f0;
      --pink:      #e04d80;
      --card-rose: #f2d0dc;
      --dark:      #111111;
      --border:    #e8e8e8;
      --sidebar:   280px;
    }
    html, body { height: 100%; }
    body {
      font-family: 'Satoshi', sans-serif;
      background: #f0f0f0;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      display: flex;
      flex-direction: column;
    }
    .topbar {
      background: var(--dark); padding: 0 32px; height: 60px;
      display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
    }
    .topbar-logo { font-size: 15px; font-weight: 700; color: var(--white); letter-spacing: .02em; }
    .topbar-logo span { color: var(--pink); }
    .topbar-actions { display: flex; align-items: center; gap: 10px; }
    .tb-btn { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .15s; }
    .tb-btn:hover { opacity: .85; }
    .tb-btn--pink  { background: var(--pink); color: var(--white); }
    .tb-btn--ghost { background: rgba(255,255,255,.1); color: var(--white); }
    .layout { display: grid; grid-template-columns: var(--sidebar) 1fr 340px; flex: 1; overflow: hidden; height: calc(100vh - 60px); }
    .nav { background: #111111; display: flex; flex-direction: column; overflow-y: auto; }
    .nav-brand { padding: 24px 20px 16px; flex-shrink: 0; }
    .nav-brand-logo { height: 40px; width: auto; display: block; margin-bottom: 12px; }
    .nav-brand-name { font-size: 15px; font-weight: 700; color: #ffffff; }
    .nav-brand-sub  { font-size: 9px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: #e04d80; margin-top: 3px; }
    .nav-label { font-size: 9px; font-weight: 500; letter-spacing: 0.16em; text-transform: uppercase; color: #6b6b6b; padding: 0 20px; margin-top: 24px; display: block; margin-bottom: 4px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; font-size: 13px; font-weight: 400; color: rgba(255,255,255,0.6); text-decoration: none; border-left: 2px solid transparent; transition: background .12s, color .12s; }
    .nav-item:hover  { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.9); }
    .nav-item.active { color: #ffffff; border-left-color: #e04d80; background: rgba(255,255,255,0.05); }
    .nav-item i { font-size: 16px; }
    .nav-spacer  { flex: 1; }
    .nav-divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 12px 0 0; }
    .nav-profile { padding: 14px 20px 20px; }
    .nav-profile-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .nav-avatar { width: 32px; height: 32px; border-radius: 50%; background: #f2d0dc; color: #e04d80; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; }
    .nav-uname  { font-size: 13px; font-weight: 500; color: #ffffff; }
    .nav-profile-link { display: block; font-size: 12px; color: #6b6b6b; text-decoration: none; padding: 4px 0; transition: color .12s; }
    .nav-profile-link:hover { color: rgba(255,255,255,0.6); }
    .form-panel { overflow-y: auto; padding: 32px 36px; background: #f8f8f8; }
    .form-title { font-size: 22px; font-weight: 700; color: var(--ink); letter-spacing: -0.02em; margin-bottom: 6px; }
    .form-sub { font-size: 13px; color: var(--ink-mid); margin-bottom: 28px; }
    .section-divider { font-size: 10px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--pink); margin: 24px 0 14px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
    .field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .field-group.single { grid-template-columns: 1fr; }
    .field-group.triple { grid-template-columns: 1fr 1fr 1fr; }
    .field { display: flex; flex-direction: column; gap: 5px; }
    .field label { font-size: 10px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-mid); }
    .field input, .field textarea, .field select {
      font-family: 'Satoshi', sans-serif; font-size: 13.5px; color: var(--ink);
      background: var(--white); border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; outline: none; transition: border-color 0.15s; width: 100%;
    }
    .field input:focus, .field textarea:focus, .field select:focus { border-color: var(--pink); }
    .field textarea { resize: vertical; min-height: 88px; }

    /* ── CLIENT AUTOCOMPLETE ── */
    .client-picker { position: relative; }
    .client-suggestions {
      display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
      background: var(--white); border: 1px solid var(--border); border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08); max-height: 220px; overflow-y: auto; z-index: 50;
    }
    .client-suggestions.open { display: block; }
    .client-suggestion { padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f5f5f5; }
    .client-suggestion:last-child { border-bottom: none; }
    .client-suggestion:hover, .client-suggestion.active { background: var(--blush); }
    .cs-name  { font-size: 13px; font-weight: 600; color: var(--ink); }
    .cs-email { font-size: 11px; color: var(--ink-mid); margin-top: 1px; }
    .cs-empty { padding: 9px 12px; font-size: 12px; color: var(--ink-mid); }

    /* ── PACKAGE CARDS ── */
    .pkg-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 12px; }
    .pkg-card {
      background: var(--white); border: 2px solid var(--border); border-radius: 12px;
      padding: 14px 16px; cursor: pointer; transition: all 0.15s; position: relative;
    }
    .pkg-card:hover { border-color: var(--card-rose); }
    .pkg-card.selected { border-color: var(--pink); background: #fdf0f4; }
    .pkg-card.selected .pkg-check { display: flex; }
    .pkg-check { display: none; position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; background: var(--pink); border-radius: 50%; align-items: center; justify-content: center; font-size: 11px; color: white; }
    .pkg-name { font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
    .pkg-desc { font-size: 11.5px; color: var(--ink-mid); line-height: 1.4; margin-bottom: 8px; }
    .pkg-price { font-size: 15px; font-weight: 700; color: var(--pink); }

    /* ── DELIVERABLES ── */
    .deliverable-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
    .deliverable-row { display: flex; gap: 8px; align-items: center; }
    .deliverable-row input {
      font-family: 'Satoshi', sans-serif; font-size: 13px; color: var(--ink);
      background: var(--white); border: 1px solid var(--border); border-radius: 8px;
      padding: 8px 12px; outline: none; flex: 1; transition: border-color 0.15s;
    }
    .deliverable-row input:focus { border-color: var(--pink); }
    .del-remove { background: none; border: none; color: var(--ink-light); cursor: pointer; font-size: 14px; padding: 6px; border-radius: 4px; flex-shrink: 0; line-height: 1; }
    .del-remove:hover { color: var(--pink); background: #fdf0f4; }
    .btn-add-del { display: flex; align-items: center; gap: 6px; background: none; border: 1px dashed var(--border); border-radius: 8px; padding: 8px 14px; font-family: 'Satoshi', sans-serif; font-size: 12px; font-weight: 500; color: var(--ink-mid); cursor: pointer; transition: all 0.15s; width: 100%; }
    .btn-add-del:hover { border-color: var(--pink); color: var(--pink); background: #fdf0f4; }

    /* ── INDUSTRIES ── */
    .industry-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .industry-chip { display: flex; align-items: center; gap: 6px; padding: 7px 14px; background: var(--white); border: 1px solid var(--border); border-radius: 999px; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--ink-mid); transition: all 0.15s; user-select: none; }
    .industry-chip input { display: none; }
    .industry-chip:has(input:checked) { background: #fdf0f4; border-color: var(--pink); color: var(--pink); font-weight: 700; }

    /* ── EXPIRY QUICK BUTTONS ── */
    .expiry-quick { display: flex; gap: 6px; margin-top: 6px; }
    .expiry-btn { background: var(--blush); border: 1px solid var(--border); border-radius: 999px; padding: 4px 12px; font-family: 'Satoshi', sans-serif; font-size: 11px; font-weight: 600; color: var(--ink-mid); cursor: pointer; transition: all 0.12s; }
    .expiry-btn:hover { background: var(--card-rose); border-color: var(--pink); color: var(--pink); }

    /* ── ACTIONS ── */
    .form-actions { display: flex; gap: 10px; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border); }
    .btn { font-family: 'Satoshi', sans-serif; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 12px 28px; border-radius: 999px; border: none; cursor: pointer; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-primary { background: var(--pink); color: var(--white); }
    .btn-primary:hover { background: #c83870; }
    .btn-ghost { background: none; border: 1px solid var(--border); color: var(--ink-mid); }
    .btn-ghost:hover { border-color: var(--ink); color: var(--ink); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* ── SUCCESS BANNER ── */
    .success-banner { display: none; background: #eef5ee; border: 1px solid #6aad6a; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; }
    .success-banner.show { display: block; }
    .success-url { font-size: 13px; font-weight: 600; color: #2a4f2a; word-break: break-all; margin-top: 6px; }
    .copy-btn { font-family: 'Satoshi', sans-serif; font-size: 11px; font-weight: 600; background: #6aad6a; color: white; border: none; padding: 5px 14px; border-radius: 999px; cursor: pointer; margin-top: 8px; }

    /* ── PREVIEW PANEL ── */
    .preview-panel { background: #e8e8e8; overflow-y: auto; padding: 24px 18px; border-left: 1px solid var(--border); }
    .preview-label { font-size: 9px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--ink-mid); margin-bottom: 14px; text-align: center; }
    .prev-doc { background: var(--white); box-shadow: 0 4px 24px rgba(0,0,0,0.12); font-size: 9px; }
    .prev-hero { background: var(--dark); padding: 16px 18px; text-align: center; }
    .prev-hero-logo { font-size: 10px; font-weight: 800; color: var(--white); letter-spacing: -0.01em; margin-bottom: 3px; }
    .prev-hero-tag { font-size: 7px; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
    .prev-hero-client { font-size: 8px; color: var(--pink); font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
    .prev-hero-campaign { font-size: 11px; font-weight: 700; color: var(--white); margin-top: 3px; }
    .prev-stats { background: var(--pink); padding: 8px 18px; display: flex; justify-content: space-around; }
    .prev-stat { text-align: center; }
    .prev-stat-val { font-size: 9px; font-weight: 800; color: white; }
    .prev-stat-lbl { font-size: 6px; color: rgba(255,255,255,0.75); margin-top: 1px; }
    .prev-section { padding: 12px 18px; border-bottom: 1px solid var(--border); }
    .prev-section:last-child { border-bottom: none; }
    .prev-sec-label { font-size: 7px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--pink); margin-bottom: 5px; }
    .prev-note { font-size: 8px; color: var(--ink-mid); line-height: 1.5; font-style: italic; }
    .prev-pkg-name { font-size: 10px; font-weight: 700; color: var(--ink); margin-bottom: 5px; }
    .prev-deliverable { font-size: 8px; color: var(--ink-mid); padding: 2px 0; }
    .prev-deliverable::before { content: "✓ "; color: var(--pink); }
    .prev-total { font-size: 16px; font-weight: 800; color: var(--pink); letter-spacing: -0.02em; }
    .prev-industry-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
    .prev-industry-tag { background: var(--blush); border-radius: 999px; padding: 2px 7px; font-size: 7px; color: var(--ink-mid); }
    .prev-expiry { font-size: 8px; color: var(--ink-light); }

    /* ── MODALS ── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(17,17,17,0.45); backdrop-filter: blur(3px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
    .modal-overlay.open { display: flex; }
    .td-modal { background: #fff; border-radius: 0 0 20px 20px; box-shadow: 0 2px 0 #e8e0e0, 0 16px 56px rgba(0,0,0,0.15); width: 100%; max-width: 360px; overflow: hidden; animation: modalIn 0.18s ease; }
    @keyframes modalIn { from { opacity:0; transform:translateY(-8px) scale(0.98); } to { opacity:1; transform:translateY(0) scale(1); } }
    .modal-bar { height: 3px; background: linear-gradient(90deg, var(--pink), var(--card-rose)); }
    .modal-body { padding: 24px 24px 20px; }
    .modal-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 14px; background: var(--blush); }
    .modal-title { font-size: 15px; font-weight: 700; letter-spacing: -0.02em; color: var(--ink); margin-bottom: 5px; }
    .modal-msg { font-size: 13px; color: var(--ink-mid); line-height: 1.55; }
    .modal-actions { padding: 0 24px 22px; }
    .mbtn { font-family: 'Satoshi', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 11px 18px; border-radius: 999px; border: none; cursor: pointer; width: 100%; transition: all 0.15s; background: var(--dark); color: white; }
    .mbtn:hover { background: #333; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="topbar-logo">Tater<span>Dash</span></div>
  <div class="topbar-actions">
    <a class="tb-btn tb-btn--ghost" href="/taterdash-app/admin/">← Dashboard</a>
    <a class="tb-btn tb-btn--ghost" href="/taterdash-app/taterdash/logout.php">Logout</a>
  </div>
</div>

<div class="layout">

  <!-- NAV -->
  <nav class="nav">
    <div class="nav-brand">
      <img class="nav-brand-logo" src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png" alt="" onerror="this.style.display='none'">
      <div class="nav-brand-name">TaterDash</div>
      <div class="nav-brand-sub">MallowFrenchie</div>
    </div>
    <span class="nav-label">Create</span>
    <a class="nav-item" href="/taterdash-app/taterdash/new-invoice.html"><i class="ti ti-file-invoice"></i> New Invoice</a>
    <a class="nav-item active" href="/taterdash-app/admin/new-proposal.php"><i class="ti ti-file-text"></i> New Proposal</a>
    <span class="nav-label">Manage</span>
    <a class="nav-item" href="/taterdash-app/admin/"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
    <a class="nav-item" href="/taterdash-app/admin/?view=all"><i class="ti ti-list"></i> All Activity</a>
    <a class="nav-item" href="/taterdash-app/admin/clients.php"><i class="ti ti-users"></i> Clients</a>
    <div class="nav-spacer"></div>
    <hr class="nav-divider">
    <div class="nav-profile">
      <div class="nav-profile-row">
        <div class="nav-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['td_user'],0,1))) ?></div>
        <div class="nav-uname"><?= htmlspecialchars($_SESSION['td_user']) ?></div>
      </div>
      <a class="nav-profile-link" href="/taterdash-app/admin/settings.php">⚙ Settings</a>
      <a class="nav-profile-link" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
  </nav>

  <!-- FORM -->
  <div class="form-panel">
    <div class="form-title">New Proposal</div>
    <div class="form-sub">Build a partnership proposal — pick a package, customize, and send the link.</div>

    <div id="success-banner" class="success-banner">
      <div style="font-size:13px;font-weight:700;color:#2a4f2a;">✓ Proposal created — link ready to send</div>
      <div class="success-url" id="success-url"></div>
      <button class="copy-btn" onclick="doCopyLink()">Copy Link</button>
    </div>

    <div class="section-divider">Client</div>
    <div class="field-group">
      <div class="field client-picker">
        <label>Company / Client Name *</label>
        <input type="text" id="client_name" placeholder="Airdog USA" autocomplete="off"
               oninput="onClientNameInput()" onfocus="onClientNameInput()">
        <div class="client-suggestions" id="clientSuggestions"></div>
      </div>
      <div class="field">
        <label>Client Email *</label>
        <input type="email" id="client_email" placeholder="contact@brand.com">
      </div>
    </div>

    <div class="section-divider">Campaign</div>
    <div class="field-group">
      <div class="field">
        <label>Campaign Name</label>
        <input type="text" id="campaign_name" placeholder="Airdog Summer 2026" oninput="updatePreview()">
      </div>
      <div class="field">
        <label>Platform</label>
        <select id="platform" onchange="updatePreview()">
          <option value="Instagram">Instagram</option>
          <option value="TikTok">TikTok</option>
          <option value="Both">Both</option>
        </select>
      </div>
    </div>
    <div class="field-group">
      <div class="field">
        <label>Campaign Start</label>
        <input type="date" id="campaign_start" oninput="updatePreview()">
      </div>
      <div class="field">
        <label>Campaign End</label>
        <input type="date" id="campaign_end" oninput="updatePreview()">
      </div>
    </div>

    <div class="section-divider">Package</div>
    <div class="pkg-grid">
      <?php foreach ($packages as $pkg): ?>
      <div class="pkg-card" id="pkg-card-<?= $pkg['id'] ?>" onclick="selectPackage(<?= $pkg['id'] ?>, <?= htmlspecialchars(json_encode($pkg['deliverables']), ENT_QUOTES) ?>, <?= $pkg['price'] ?>)">
        <div class="pkg-check">✓</div>
        <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
        <div class="pkg-desc"><?= htmlspecialchars($pkg['description']) ?></div>
        <div class="pkg-price">$<?= number_format($pkg['price'], 0) ?></div>
      </div>
      <?php endforeach; ?>
      <div class="pkg-card" id="pkg-card-0" onclick="selectPackage(0, '[]', 0)">
        <div class="pkg-check">✓</div>
        <div class="pkg-name">Custom</div>
        <div class="pkg-desc">Build your own package</div>
        <div class="pkg-price">—</div>
      </div>
    </div>

    <div class="section-divider">Deliverables</div>
    <div class="deliverable-list" id="deliverable-list"></div>
    <button class="btn-add-del" onclick="addDeliverable()">+ Add deliverable</button>

    <div class="section-divider">Investment</div>
    <div class="field-group single">
      <div class="field">
        <label>Total Investment ($)</label>
        <input type="number" id="total" value="0" min="0" step="100" oninput="updatePreview()">
      </div>
    </div>

    <div class="section-divider">Message to Client</div>
    <div class="field-group single">
      <div class="field">
        <label>Personal note</label>
        <textarea id="notes" placeholder="Hi [Name], I'm so excited about this opportunity to collaborate on your campaign..." oninput="updatePreview()"></textarea>
      </div>
    </div>

    <div class="section-divider">Partner Logos to Show</div>
    <div class="industry-grid">
      <?php foreach ($industries as $ind): ?>
      <label class="industry-chip">
        <input type="checkbox" name="industry" value="<?= htmlspecialchars($ind) ?>" onchange="updatePreview()">
        <?= htmlspecialchars($ind) ?>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="section-divider">Expiry</div>
    <div class="field-group single">
      <div class="field">
        <label>Proposal expires on</label>
        <input type="date" id="expiry_date" oninput="updatePreview()">
        <div class="expiry-quick">
          <button class="expiry-btn" onclick="setExpiry(30)">+30 days</button>
          <button class="expiry-btn" onclick="setExpiry(60)">+60 days</button>
          <button class="expiry-btn" onclick="setExpiry(90)">+90 days</button>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" id="submit-btn" onclick="submitProposal()">Create Proposal</button>
      <a href="/taterdash-app/admin/" class="btn btn-ghost">Cancel</a>
    </div>
  </div>

  <!-- PREVIEW -->
  <div class="preview-panel">
    <div class="preview-label">Preview</div>
    <div class="prev-doc">
      <div class="prev-hero">
        <div class="prev-hero-logo">🐾 MallowFrenchie</div>
        <div class="prev-hero-tag">Partnership Proposal</div>
        <div class="prev-hero-client" id="prev-client">Client Name</div>
        <div class="prev-hero-campaign" id="prev-campaign"></div>
      </div>
      <div class="prev-stats">
        <div class="prev-stat"><div class="prev-stat-val">67.8K</div><div class="prev-stat-lbl">Followers</div></div>
        <div class="prev-stat"><div class="prev-stat-val">130K+</div><div class="prev-stat-lbl">Impressions</div></div>
        <div class="prev-stat"><div class="prev-stat-val">63%</div><div class="prev-stat-lbl">Female</div></div>
        <div class="prev-stat"><div class="prev-stat-val">79%</div><div class="prev-stat-lbl">25-44</div></div>
      </div>
      <div class="prev-section">
        <div class="prev-sec-label">Message</div>
        <div class="prev-note" id="prev-note">Your personal note to the client will appear here.</div>
      </div>
      <div class="prev-section">
        <div class="prev-sec-label">Package</div>
        <div class="prev-pkg-name" id="prev-pkg-name">— Select a package —</div>
        <div id="prev-deliverables"></div>
      </div>
      <div class="prev-section">
        <div class="prev-sec-label">Investment</div>
        <div class="prev-total" id="prev-total">$0</div>
      </div>
      <div class="prev-section">
        <div class="prev-sec-label">Partner Industries</div>
        <div class="prev-industry-tags" id="prev-industries"><span style="color:var(--ink-light);font-size:8px;">None selected</span></div>
      </div>
      <div class="prev-section">
        <div class="prev-sec-label">Expiry</div>
        <div class="prev-expiry" id="prev-expiry">No expiry set</div>
      </div>
    </div>
  </div>

</div>

<!-- Alert modal -->
<div class="modal-overlay" id="modal-alert">
  <div class="td-modal">
    <div class="modal-bar"></div>
    <div class="modal-body">
      <div class="modal-icon">⚠️</div>
      <div class="modal-title" id="alert-title">Error</div>
      <div class="modal-msg" id="alert-msg"></div>
    </div>
    <div class="modal-actions">
      <button class="mbtn" onclick="closeAlert()">Got it</button>
    </div>
  </div>
</div>

<script>
  const PACKAGES_DATA = <?= json_encode(array_column($packages, null, 'id')) ?>;
  let selectedPackageId = null;
  let delCount = 0;
  let generatedUrl = null;

  // ── Client autocomplete ──
  let CLIENTS_DATA = [];
  fetch('/taterdash-app/taterdash/get-clients.php')
    .then(r => r.json())
    .then(d => { if (d.success) CLIENTS_DATA = d.clients; })
    .catch(() => {});

  let _currentMatches = [];

  function onClientNameInput() {
    const q   = document.getElementById('client_name').value.trim().toLowerCase();
    const box = document.getElementById('clientSuggestions');
    updatePreview();

    if (!q) { box.classList.remove('open'); box.innerHTML = ''; return; }

    _currentMatches = CLIENTS_DATA.filter(c =>
      c.company.toLowerCase().includes(q) || (c.contact_name || '').toLowerCase().includes(q)
    ).slice(0, 6);

    if (!_currentMatches.length) { box.classList.remove('open'); box.innerHTML = ''; return; }

    box.innerHTML = _currentMatches.map((c, i) => `
      <div class="client-suggestion" onclick="pickClient(${i})">
        <div class="cs-name">${escHtml(c.company)}</div>
        <div class="cs-email">${escHtml(c.email)}</div>
      </div>
    `).join('');
    box.classList.add('open');
  }

  function pickClient(i) {
    const c = _currentMatches[i];
    document.getElementById('client_name').value  = c.company;
    document.getElementById('client_email').value = c.email;
    document.getElementById('clientSuggestions').classList.remove('open');
    updatePreview();
  }

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.client-picker')) {
      document.getElementById('clientSuggestions').classList.remove('open');
    }
  });

  // ── Package selection ──
  function selectPackage(id, deliverablesJson, price) {
    // Deselect all
    document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('pkg-card-' + id).classList.add('selected');
    selectedPackageId = id || null;

    // Set price
    document.getElementById('total').value = price || 0;

    // Fill deliverables
    const deliverables = typeof deliverablesJson === 'string'
      ? JSON.parse(deliverablesJson)
      : (deliverablesJson || []);
    document.getElementById('deliverable-list').innerHTML = '';
    delCount = 0;
    if (deliverables.length > 0) {
      deliverables.forEach(d => addDeliverable(d));
    } else {
      addDeliverable();
    }

    // Update preview pkg name
    if (id && PACKAGES_DATA[id]) {
      document.getElementById('prev-pkg-name').textContent = PACKAGES_DATA[id].name;
    } else {
      document.getElementById('prev-pkg-name').textContent = 'Custom Package';
    }

    updatePreview();
  }

  // ── Deliverables editor ──
  function addDeliverable(text = '') {
    delCount++;
    const rid = 'del_' + delCount;
    const row = document.createElement('div');
    row.className = 'deliverable-row';
    row.id = rid;
    row.innerHTML = `
      <input type="text" value="${escHtml(text)}" placeholder="e.g. 1 in-feed reel (10-30 sec)" oninput="updatePreview()">
      <button class="del-remove" onclick="removeDeliverable('${rid}')" title="Remove">✕</button>
    `;
    document.getElementById('deliverable-list').appendChild(row);
    updatePreview();
  }

  function removeDeliverable(id) {
    document.getElementById(id)?.remove();
    updatePreview();
  }

  function getDeliverables() {
    return Array.from(document.querySelectorAll('.deliverable-row input'))
      .map(i => i.value.trim()).filter(Boolean);
  }

  // ── Expiry quick set ──
  function setExpiry(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    document.getElementById('expiry_date').value = d.toISOString().split('T')[0];
    updatePreview();
  }

  // ── Preview update ──
  function fmt(n) { return '$' + parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }
  function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function updatePreview() {
    document.getElementById('prev-client').textContent   = document.getElementById('client_name').value || 'Client Name';
    document.getElementById('prev-campaign').textContent = document.getElementById('campaign_name').value || '';
    document.getElementById('prev-note').textContent     = document.getElementById('notes').value || 'Your personal note to the client will appear here.';
    document.getElementById('prev-total').textContent    = fmt(document.getElementById('total').value);

    // Deliverables
    const dels = getDeliverables();
    const delEl = document.getElementById('prev-deliverables');
    delEl.innerHTML = dels.length
      ? dels.map(d => `<div class="prev-deliverable">${escHtml(d)}</div>`).join('')
      : '<div style="font-size:8px;color:var(--ink-light);">No deliverables yet</div>';

    // Industries
    const checked = Array.from(document.querySelectorAll('input[name="industry"]:checked')).map(i => i.value);
    const indEl = document.getElementById('prev-industries');
    indEl.innerHTML = checked.length
      ? checked.map(i => `<span class="prev-industry-tag">${escHtml(i)}</span>`).join('')
      : '<span style="color:var(--ink-light);font-size:8px;">None selected</span>';

    // Expiry
    const exp = document.getElementById('expiry_date').value;
    document.getElementById('prev-expiry').textContent = exp ? 'Expires ' + fmtDate(exp) : 'No expiry set';
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // ── Submit ──
  async function submitProposal() {
    const client_name  = document.getElementById('client_name').value.trim();
    const client_email = document.getElementById('client_email').value.trim();
    const deliverables = getDeliverables();

    if (!client_name || !client_email) {
      showAlert('Missing fields', 'Please fill in at least the client name and email.');
      return;
    }
    if (deliverables.length === 0) {
      showAlert('No deliverables', 'Add at least one deliverable before creating the proposal.');
      return;
    }

    const industries = Array.from(document.querySelectorAll('input[name="industry"]:checked')).map(i => i.value);
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Creating...';

    const payload = {
      client_name,
      client_email,
      campaign_name:      document.getElementById('campaign_name').value.trim(),
      platform:           document.getElementById('platform').value,
      campaign_start:     document.getElementById('campaign_start').value || null,
      campaign_end:       document.getElementById('campaign_end').value   || null,
      package_id:         selectedPackageId,
      deliverables,
      total:              parseFloat(document.getElementById('total').value) || 0,
      notes:              document.getElementById('notes').value.trim(),
      partner_industries: industries,
      expiry_date:        document.getElementById('expiry_date').value || null,
    };

    try {
      const res  = await fetch('/taterdash-app/taterdash/save-proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        generatedUrl = data.url;
        document.getElementById('success-url').textContent = data.url;
        const banner = document.getElementById('success-banner');
        banner.classList.add('show');
        banner.scrollIntoView({ behavior: 'smooth' });
        btn.textContent = '✓ Created';
      } else {
        showAlert('Save failed', data.error || 'Unknown error');
        btn.disabled = false;
        btn.textContent = 'Create Proposal';
      }
    } catch (e) {
      showAlert('Connection error', 'Could not reach the server.');
      btn.disabled = false;
      btn.textContent = 'Create Proposal';
    }
  }

  function doCopyLink() {
    if (generatedUrl) {
      navigator.clipboard.writeText(generatedUrl);
      const b = document.querySelector('.copy-btn');
      b.textContent = 'Copied!';
      setTimeout(() => b.textContent = 'Copy Link', 2000);
    }
  }

  // ── Alert modal ──
  function showAlert(title, msg) {
    document.getElementById('alert-title').textContent = title;
    document.getElementById('alert-msg').textContent   = msg;
    document.getElementById('modal-alert').classList.add('open');
  }
  function closeAlert() { document.getElementById('modal-alert').classList.remove('open'); }
  document.getElementById('modal-alert').addEventListener('click', e => { if (e.target === e.currentTarget) closeAlert(); });

  // ── Init ──
  addDeliverable();
  updatePreview();
</script>
</body>
</html>
