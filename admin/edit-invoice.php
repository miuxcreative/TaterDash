<?php
// ═══════════════════════════════════════════
// TaterDash — Edit Invoice
// /taterdash-app/admin/edit-invoice.php
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /taterdash-app/admin/');
    exit;
}

$pdo = db_connect();

$inv = $pdo->prepare("SELECT * FROM td_invoices WHERE id = ?");
$inv->execute([$id]);
$invoice = $inv->fetch();

if (!$invoice) {
    header('Location: /taterdash-app/admin/');
    exit;
}

$li = $pdo->prepare("SELECT * FROM td_line_items WHERE invoice_id = ? ORDER BY id ASC");
$li->execute([$id]);
$line_items = $li->fetchAll();

function he($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaterDash — Edit Invoice #<?= he($invoice['invoice_num']) ?></title>
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
      --blush-dark:#f5e5e5;
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

    /* ── TOP BAR ── */
    .topbar {
      background: var(--dark);
      padding: 0 32px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .topbar-logo { font-size: 15px; font-weight: 700; color: var(--white); letter-spacing: .02em; }
    .topbar-logo span { color: var(--pink); }
    .topbar-actions { display: flex; align-items: center; gap: 10px; }
    .tb-btn { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .15s; }
    .tb-btn:hover { opacity: .85; }
    .tb-btn--pink  { background: var(--pink); color: var(--white); }
    .tb-btn--ghost { background: rgba(255,255,255,.1); color: var(--white); }

    /* ── LAYOUT ── */
    .layout {
      display: grid;
      grid-template-columns: var(--sidebar) 1fr 380px;
      flex: 1;
      overflow: hidden;
      height: calc(100vh - 60px);
    }

    /* ── LEFT NAV (dark) ── */
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

    /* ── FORM PANEL ── */
    .form-panel { overflow-y: auto; padding: 32px 36px; background: #f8f8f8; }
    .form-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px; }
    .form-title { font-size: 22px; font-weight: 700; color: var(--ink); letter-spacing: -0.02em; }
    .invoice-badge {
      font-size: 10px; font-weight: 700; letter-spacing: 0.14em;
      text-transform: uppercase; color: var(--pink);
      background: #fdf0f4; border-radius: 999px;
      padding: 4px 12px; margin-top: 4px; display: inline-block;
    }
    .form-sub { font-size: 13px; color: var(--ink-mid); margin-bottom: 28px; }

    /* form elements */
    .field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .field-group.single { grid-template-columns: 1fr; }
    .field-group.triple { grid-template-columns: 1fr 1fr 1fr; }
    .field { display: flex; flex-direction: column; gap: 5px; }
    .field label {
      font-size: 10px; font-weight: 700; letter-spacing: 0.14em;
      text-transform: uppercase; color: var(--ink-mid);
    }
    .field input, .field textarea, .field select {
      font-family: 'Satoshi', sans-serif; font-size: 13.5px;
      color: var(--ink); background: var(--white);
      border: 1px solid var(--border); border-radius: 8px;
      padding: 9px 12px; outline: none;
      transition: border-color 0.15s; width: 100%;
    }
    .field input:focus, .field textarea:focus { border-color: var(--pink); }
    .field textarea { resize: vertical; min-height: 72px; }

    .section-divider {
      font-size: 10px; font-weight: 700; letter-spacing: 0.16em;
      text-transform: uppercase; color: var(--pink);
      margin: 24px 0 14px; padding-bottom: 8px;
      border-bottom: 1px solid var(--border);
    }

    /* line items */
    .line-items { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
    .line-item {
      background: var(--white); border: 1px solid var(--border);
      border-radius: 10px; padding: 14px; position: relative;
    }
    .line-item-row { display: grid; grid-template-columns: 1fr 80px 120px; gap: 8px; align-items: end; }
    .line-note-row { margin-top: 8px; }
    .remove-item {
      position: absolute; top: 10px; right: 10px;
      background: none; border: none; color: var(--ink-light);
      cursor: pointer; font-size: 14px; padding: 4px 6px;
      border-radius: 4px; line-height: 1;
    }
    .remove-item:hover { color: var(--pink); background: #fdf0f4; }

    @media (max-width: 768px) {
      .layout { grid-template-columns: 1fr; grid-template-rows: auto 1fr; height: auto; }
      .nav { display: none; }
      .preview-panel { display: none; }
      .form-panel { padding: 20px 16px; }
      .field-group { grid-template-columns: 1fr; }
      .field-group.triple { grid-template-columns: 1fr; }
      .line-item-row { grid-template-columns: 1fr 70px 100px; }
      .topbar { padding: 0 16px; }
    }

    .btn-add-item {
      display: flex; align-items: center; gap: 6px;
      background: none; border: 1px dashed var(--border);
      border-radius: 8px; padding: 10px 16px;
      font-family: 'Satoshi', sans-serif; font-size: 13px;
      font-weight: 500; color: var(--ink-mid);
      cursor: pointer; width: 100%; transition: all 0.15s;
    }
    .btn-add-item:hover { border-color: var(--pink); color: var(--pink); background: #fdf0f4; }

    .form-actions {
      display: flex; gap: 10px; margin-top: 28px;
      padding-top: 20px; border-top: 1px solid var(--border);
    }
    .btn {
      font-family: 'Satoshi', sans-serif; font-size: 12px; font-weight: 700;
      letter-spacing: 0.1em; text-transform: uppercase;
      padding: 12px 28px; border-radius: 999px; border: none;
      cursor: pointer; transition: all 0.15s;
    }
    .btn-primary { background: var(--pink); color: var(--white); }
    .btn-primary:hover { background: #c83870; }
    .btn-ghost { background: none; border: 1px solid var(--border); color: var(--ink-mid); }
    .btn-ghost:hover { border-color: var(--ink); color: var(--ink); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* ── PREVIEW PANEL ── */
    .preview-panel {
      background: #e0e0e0; overflow-y: auto;
      padding: 24px 20px; border-left: 1px solid var(--border);
    }
    .preview-label {
      font-size: 9px; font-weight: 700; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--ink-mid);
      margin-bottom: 16px; text-align: center;
    }
    .preview-doc {
      background: var(--white);
      box-shadow: 0 4px 24px rgba(0,0,0,0.12);
      transform-origin: top center; font-size: 9px;
    }
    .p-band { height: 3px; background: var(--card-rose); }
    .p-hdr { padding: 20px 24px 14px; display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border); }
    .p-logo { height: 36px; }
    .p-word { font-size: 22px; font-weight: 200; letter-spacing: -0.03em; line-height: 1; margin-bottom: 4px; }
    .p-date { font-size: 9px; color: var(--ink-mid); }
    .p-num  { font-size: 7px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--pink); margin-top: 3px; }
    .p-parties { padding: 12px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid var(--border); }
    .p-ey { font-size: 7px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--pink); margin-bottom: 4px; }
    .p-name { font-size: 10px; font-weight: 700; color: var(--ink); }
    .p-detail { font-size: 8.5px; color: var(--ink-mid); line-height: 1.6; }
    .p-items { padding: 0 24px; }
    .p-table { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
    .p-table th { background: var(--card-rose); font-size: 7px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 8px; text-align: left; border-right: 1px solid rgba(224,77,128,0.2); }
    .p-table th:last-child { border-right: none; text-align: right; }
    .p-table th.r { text-align: right; }
    .p-table td { padding: 7px 8px; font-size: 8.5px; border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); }
    .p-table td:last-child { border-right: none; text-align: right; font-weight: 600; }
    .p-table tr:last-child td { border-bottom: none; }
    .p-total { padding: 10px 24px; display: flex; justify-content: flex-end; }
    .p-total-box { display: flex; gap: 24px; align-items: center; border-top: 1.5px solid var(--ink); padding-top: 7px; min-width: 120px; }
    .p-total-label { font-size: 7px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-mid); }
    .p-total-amt { font-size: 14px; font-weight: 700; color: var(--ink); }
    .p-ftr { background: var(--dark); padding: 9px 24px; display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
    .p-ftr span { font-size: 8px; color: rgba(255,255,255,0.4); }

    /* success state */
    .success-banner {
      display: none; background: #eef5ee;
      border: 1px solid #6aad6a; border-radius: 10px;
      padding: 16px 20px; margin-bottom: 20px;
    }
    .success-banner.show { display: block; }
    .success-url { font-size: 13px; font-weight: 600; color: #2a4f2a; word-break: break-all; margin-top: 6px; }
    .copy-btn {
      font-family: 'Satoshi', sans-serif; font-size: 11px; font-weight: 600;
      background: #6aad6a; color: white; border: none;
      padding: 5px 14px; border-radius: 999px; cursor: pointer; margin-top: 8px;
    }

    /* ── MODALS ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(17,17,17,0.45); backdrop-filter: blur(3px);
      z-index: 1000; align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .td-modal {
      background: #fff; border-radius: 0 0 20px 20px;
      box-shadow: 0 2px 0 #e8e0e0, 0 16px 56px rgba(0,0,0,0.15);
      width: 100%; max-width: 360px; overflow: hidden;
      animation: modalIn 0.18s ease;
    }
    @keyframes modalIn {
      from { opacity: 0; transform: translateY(-8px) scale(0.98); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-bar { height: 3px; background: linear-gradient(90deg, #e04d80, #f2d0dc); }
    .modal-body { padding: 24px 24px 20px; }
    .modal-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 14px; }
    .modal-icon.info { background: #faf0f0; }
    .modal-title { font-size: 15px; font-weight: 700; letter-spacing: -0.02em; color: #191919; margin-bottom: 5px; }
    .modal-msg   { font-size: 13px; color: #6b6b6b; line-height: 1.55; }
    .modal-actions { display: flex; gap: 8px; padding: 0 24px 22px; }
    .mbtn {
      font-family: 'Satoshi', sans-serif; font-size: 11px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 11px 18px; border-radius: 999px; border: none;
      cursor: pointer; flex: 1; transition: all 0.15s;
    }
    .mbtn-primary { background: #111; color: #fff; }
    .mbtn-primary:hover { background: #333; }
  </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-logo">Tater<span>Dash</span></div>
  <div class="topbar-actions">
    <a class="tb-btn tb-btn--ghost" href="/taterdash-app/admin/">← Dashboard</a>
    <a class="tb-btn tb-btn--ghost" href="/taterdash-app/taterdash/logout.php">Logout</a>
  </div>
</div>

<div class="layout">

  <!-- LEFT NAV -->
  <nav class="nav">
    <div class="nav-brand">
      <img class="nav-brand-logo" src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png" alt="" onerror="this.style.display='none'">
      <div class="nav-brand-name">TaterDash</div>
      <div class="nav-brand-sub">MallowFrenchie</div>
    </div>
    <span class="nav-label">Create</span>
    <a class="nav-item" href="/taterdash-app/taterdash/new-invoice.html"><i class="ti ti-file-invoice"></i> New Invoice</a>
    <a class="nav-item" href="/taterdash-app/admin/new-proposal.php"><i class="ti ti-file-text"></i> New Proposal</a>
    <span class="nav-label">Manage</span>
    <a class="nav-item" href="/taterdash-app/admin/"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
    <a class="nav-item active" href="#"><i class="ti ti-file-pencil"></i> Edit Invoice</a>
    <a class="nav-item" href="/taterdash-app/admin/?view=all"><i class="ti ti-list"></i> All Activity</a>
    <a class="nav-item" href="/taterdash-app/admin/?view=clients"><i class="ti ti-users"></i> Clients</a>
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

  <!-- FORM PANEL -->
  <div class="form-panel">
    <div class="form-header">
      <div>
        <div class="form-title">Edit Invoice</div>
        <div class="invoice-badge"><?= he($invoice['invoice_num']) ?></div>
      </div>
    </div>
    <div class="form-sub" style="margin-top:10px;">Changes save over the existing invoice — the client link stays the same.</div>

    <div id="success-banner" class="success-banner">
      <div style="font-size:13px;font-weight:700;color:#2a4f2a;">✓ Invoice updated</div>
      <div class="success-url" id="success-url"></div>
      <button class="copy-btn" onclick="copyLink()">Copy Link</button>
    </div>

    <div class="section-divider">Client</div>
    <div class="field-group">
      <div class="field">
        <label>Company / Client Name *</label>
        <input type="text" id="client_name" value="<?= he($invoice['client_name']) ?>" oninput="updatePreview()">
      </div>
      <div class="field">
        <label>Contact Name</label>
        <input type="text" id="contact_name" value="<?= he($invoice['contact_name']) ?>" oninput="updatePreview()">
      </div>
    </div>
    <div class="field-group">
      <div class="field">
        <label>Client Email *</label>
        <input type="email" id="client_email" value="<?= he($invoice['client_email']) ?>" oninput="updatePreview()">
      </div>
    </div>

    <div class="section-divider">Invoice Details</div>
    <div class="field-group triple">
      <div class="field">
        <label>Issue Date *</label>
        <input type="date" id="issue_date" value="<?= he($invoice['issue_date']) ?>" oninput="updatePreview()">
      </div>
      <div class="field">
        <label>Due Date</label>
        <input type="date" id="due_date" value="<?= he($invoice['due_date'] ?? '') ?>" oninput="updatePreview()">
      </div>
    </div>

    <div class="section-divider">Line Items</div>
    <div class="line-items" id="line-items"></div>
    <button class="btn-add-item" onclick="addItem()">+ Add Line Item</button>

    <div class="section-divider">Notes</div>
    <div class="field-group single">
      <div class="field">
        <label>Notes (optional)</label>
        <textarea id="notes" oninput="updatePreview()"><?= he($invoice['notes']) ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" id="submit-btn" onclick="submitEdit()">Save Changes</button>
      <a href="/taterdash-app/admin/" class="btn btn-ghost" style="text-decoration:none;">Cancel</a>
    </div>
  </div>

  <!-- PREVIEW PANEL -->
  <div class="preview-panel">
    <div class="preview-label">Live Preview</div>
    <div class="preview-doc" id="preview-doc">
      <div class="p-band"></div>
      <div class="p-hdr">
        <img class="p-logo"
          src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png"
          alt="" onerror="this.style.display='none'">
        <div style="text-align:right;">
          <div class="p-word">invoice</div>
          <div class="p-date" id="p-date">Date: —</div>
          <div class="p-num"><?= he($invoice['invoice_num']) ?></div>
        </div>
      </div>
      <div class="p-parties">
        <div>
          <div class="p-ey">From</div>
          <div class="p-name">G Space Agency LLC</div>
          <div class="p-detail">mallowfrenchie@gmail.com<br>Palmetto Bay, FL 33157</div>
        </div>
        <div style="text-align:right;">
          <div class="p-ey">Bill To</div>
          <div class="p-name" id="p-client"><?= he($invoice['client_name']) ?></div>
          <div class="p-detail" id="p-email"><?= he($invoice['client_email']) ?></div>
        </div>
      </div>
      <div class="p-items">
        <table class="p-table">
          <thead>
            <tr>
              <th style="width:28px;">Qty</th>
              <th>Description</th>
              <th class="r" style="width:60px;">Total</th>
            </tr>
          </thead>
          <tbody id="p-items-body">
            <tr><td colspan="3" style="color:#b0b0b0;text-align:center;padding:12px;">Add line items to preview</td></tr>
          </tbody>
        </table>
      </div>
      <div class="p-total">
        <div class="p-total-box">
          <div class="p-total-label">Total</div>
          <div class="p-total-amt" id="p-total">$0.00</div>
        </div>
      </div>
      <div class="p-ftr">
        <span>MallowFrenchie</span>
        <span>@mallowfrenchie</span>
      </div>
    </div>
  </div>

</div><!-- end layout -->

<!-- Alert modal -->
<div class="modal-overlay" id="modal-alert">
  <div class="td-modal">
    <div class="modal-bar"></div>
    <div class="modal-body">
      <div class="modal-icon info">⚠️</div>
      <div class="modal-title" id="alert-title">Error</div>
      <div class="modal-msg" id="alert-msg"></div>
    </div>
    <div class="modal-actions">
      <button class="mbtn mbtn-primary" style="flex:1" onclick="closeAlert()">Got it</button>
    </div>
  </div>
</div>

<script>
  const INVOICE_ID = <?= intval($id) ?>;
  const SITE_URL   = '<?= SITE_URL ?>';

  // ── Seed existing line items from PHP ──
  const EXISTING_ITEMS = <?= json_encode(array_values($line_items)) ?>;

  let itemCount = 0;

  function addItem(desc, qty, price, note) {
    itemCount++;
    const eid = 'item_' + itemCount;
    const div = document.createElement('div');
    div.className = 'line-item';
    div.id = eid;
    div.innerHTML = `
      <button class="remove-item" onclick="removeItem('${eid}')" title="Remove">✕</button>
      <div class="line-item-row">
        <div class="field">
          <label>Description *</label>
          <input type="text" value="${escHtml(desc||'')}" placeholder="e.g. Sponsored Instagram Reel" oninput="updatePreview()" data-field="desc">
        </div>
        <div class="field">
          <label>Qty</label>
          <input type="number" value="${parseFloat(qty)||1}" min="1" step="1" oninput="updatePreview()" data-field="qty">
        </div>
        <div class="field">
          <label>Unit Price</label>
          <input type="number" value="${parseFloat(price)||0}" min="0" step="0.01" oninput="updatePreview()" data-field="price">
        </div>
      </div>
      <div class="line-note-row field">
        <label>Note (optional)</label>
        <input type="text" value="${escHtml(note||'')}" placeholder="Platform, campaign name, details..." oninput="updatePreview()" data-field="note">
      </div>
    `;
    document.getElementById('line-items').appendChild(div);
    updatePreview();
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function removeItem(id) {
    document.getElementById(id).remove();
    updatePreview();
  }

  function getItems() {
    const items = [];
    document.querySelectorAll('.line-item').forEach(el => {
      const desc  = el.querySelector('[data-field="desc"]').value.trim();
      const qty   = parseFloat(el.querySelector('[data-field="qty"]').value) || 1;
      const price = parseFloat(el.querySelector('[data-field="price"]').value) || 0;
      const note  = el.querySelector('[data-field="note"]').value.trim();
      if (desc) items.push({ description: desc, quantity: qty, unit_price: price, note, total: qty * price });
    });
    return items;
  }

  // ── Preview ──
  function fmt(n) { return '$' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
  function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function updatePreview() {
    document.getElementById('p-client').textContent = document.getElementById('client_name').value || 'Client Company';
    document.getElementById('p-email').textContent  = document.getElementById('client_email').value || 'contact@client.com';
    document.getElementById('p-date').textContent   = 'Date: ' + fmtDate(document.getElementById('issue_date').value);

    const items = getItems();
    const tbody = document.getElementById('p-items-body');
    if (items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="3" style="color:#b0b0b0;text-align:center;padding:12px;">Add line items to preview</td></tr>';
      document.getElementById('p-total').textContent = '$0.00';
      return;
    }
    let total = 0;
    tbody.innerHTML = items.map(it => {
      total += it.total;
      return `<tr><td>${it.quantity}</td><td>${escHtml(it.description)}</td><td>${fmt(it.total)}</td></tr>`;
    }).join('');
    document.getElementById('p-total').textContent = fmt(total);
  }

  // ── Submit ──
  async function submitEdit() {
    const client_name  = document.getElementById('client_name').value.trim();
    const client_email = document.getElementById('client_email').value.trim();
    const issue_date   = document.getElementById('issue_date').value;
    const items        = getItems();

    if (!client_name || !client_email || !issue_date || items.length === 0) {
      showAlert('Missing fields', 'Please fill in client name, email, date, and at least one line item.');
      return;
    }

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const payload = {
      invoice_id:   INVOICE_ID,
      client_name,
      contact_name: document.getElementById('contact_name').value.trim(),
      client_email,
      issue_date,
      due_date: document.getElementById('due_date').value || null,
      notes:    document.getElementById('notes').value.trim(),
      line_items: items
    };

    try {
      const res  = await fetch('/taterdash-app/taterdash/update-invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        const banner = document.getElementById('success-banner');
        document.getElementById('success-url').textContent = data.url;
        banner.classList.add('show');
        window.generatedUrl = data.url;
        banner.scrollIntoView({ behavior: 'smooth' });
        btn.textContent = '✓ Saved';
      } else {
        showAlert('Save failed', data.error || 'Unknown error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Save Changes';
      }
    } catch (e) {
      showAlert('Connection error', 'Could not reach the server. Check your connection and try again.');
      btn.disabled = false;
      btn.textContent = 'Save Changes';
    }
  }

  function copyLink() {
    if (window.generatedUrl) {
      navigator.clipboard.writeText(window.generatedUrl);
      const btn = document.querySelector('.copy-btn');
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy Link', 2000);
    }
  }

  // ── Modal ──
  function showAlert(title, msg) {
    document.getElementById('alert-title').textContent = title;
    document.getElementById('alert-msg').textContent = msg;
    document.getElementById('modal-alert').classList.add('open');
  }
  function closeAlert() {
    document.getElementById('modal-alert').classList.remove('open');
  }
  document.getElementById('modal-alert').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeAlert();
  });

  // ── Init: seed existing line items ──
  if (EXISTING_ITEMS.length > 0) {
    EXISTING_ITEMS.forEach(it => addItem(it.description, it.quantity, it.unit_price, it.note));
  } else {
    addItem();
  }
  updatePreview();
</script>
</body>
</html>
