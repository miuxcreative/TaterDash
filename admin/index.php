<?php
// ═══════════════════════════════════════════
// TaterDash — Admin Dashboard
// /public_html/taterdash-app/admin/index.php
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';

$pdo = db_connect();

$tab = isset($_GET['tab']) && $_GET['tab'] === 'proposals' ? 'proposals' : 'invoices';

// ── Invoice stats ──
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(total), 0) AS total_invoiced,
        COALESCE(SUM(CASE WHEN status IN ('draft','sent','viewed') THEN total ELSE 0 END), 0) AS outstanding,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) AS paid,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts
    FROM td_invoices
")->fetch();

// ── Invoice filters ──
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search        = isset($_GET['q'])      ? trim($_GET['q']) : '';
$where  = [];
$params = [];
if ($status_filter) { $where[] = 'status = ?'; $params[] = $status_filter; }
if ($search)        { $where[] = '(client_name LIKE ? OR invoice_num LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT * FROM td_invoices $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// ── Proposal data ──
$proposals = $pdo->query("SELECT p.*, pk.name AS package_name FROM td_proposals p LEFT JOIN td_packages pk ON p.package_id = pk.id ORDER BY p.created_at DESC")->fetchAll();
$prop_stats = $pdo->query("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN status IN ('draft','sent','viewed') THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END) AS signed_count,
        COALESCE(SUM(CASE WHEN status = 'signed' THEN total ELSE 0 END), 0) AS signed_value
    FROM td_proposals
")->fetch();

function fmt_money($n) { return '$' . number_format(floatval($n), 2); }
function fmt_date($d)  { return $d ? date('M j, Y', strtotime($d)) : '—'; }

$badge_map = [
    'draft'  => ['bg' => '#f0f0f0', 'color' => '#6b6b6b', 'label' => 'Draft'],
    'sent'   => ['bg' => '#e8f0fe', 'color' => '#1a56db', 'label' => 'Sent'],
    'viewed' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Viewed'],
    'paid'   => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'Paid'],
    'signed' => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'Signed'],
    'expired'=> ['bg' => '#f0f0f0', 'color' => '#9b9b9b', 'label' => 'Expired'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaterDash — Admin</title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
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
      --sidebar:   220px;
    }
    html, body { height: 100%; }
    body {
      font-family: 'Satoshi', sans-serif;
      background: #f4f4f4;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      display: flex;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar);
      background: var(--dark);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      min-height: 100vh;
      position: sticky;
      top: 0;
    }
    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 20px 18px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .sidebar-brand img { height: 26px; width: auto; opacity: 0.9; }
    .sidebar-brand-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--white);
      letter-spacing: -0.01em;
    }
    .sidebar-brand-name span {
      display: block;
      font-size: 9px;
      font-weight: 500;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--pink);
      opacity: 0.85;
      margin-top: 1px;
    }
    .nav-section {
      padding: 20px 0 0;
    }
    .nav-label {
      font-size: 9px;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.25);
      padding: 0 18px;
      margin-bottom: 4px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 500;
      color: rgba(255,255,255,0.55);
      text-decoration: none;
      border-left: 2px solid transparent;
      transition: all 0.15s;
    }
    .nav-item:hover { color: var(--white); background: rgba(255,255,255,0.05); }
    .nav-item.active { color: var(--white); border-left-color: var(--pink); background: rgba(224,77,128,0.1); }
    .nav-icon { font-size: 14px; }
    .sidebar-footer {
      margin-top: auto;
      padding: 16px 18px;
      border-top: 1px solid rgba(255,255,255,0.07);
      font-size: 11px;
      color: rgba(255,255,255,0.2);
    }

    /* ── MAIN ── */
    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      overflow: hidden;
    }
    .topbar {
      background: var(--white);
      border-bottom: 1px solid var(--border);
      padding: 0 32px;
      height: 52px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .topbar-title { font-size: 15px; font-weight: 700; letter-spacing: -0.02em; }
    .content { padding: 28px 32px; flex: 1; }

    /* ── STAT CARDS ── */
    .stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px 20px;
    }
    .stat-label {
      font-size: 9px;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--ink-light);
      margin-bottom: 8px;
    }
    .stat-value {
      font-size: 24px;
      font-weight: 700;
      letter-spacing: -0.03em;
      color: var(--pink);
    }
    .stat-sub {
      font-size: 11px;
      color: var(--ink-light);
      margin-top: 3px;
    }

    /* ── TOOLBAR ── */
    .toolbar {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
    }
    .search-wrap {
      position: relative;
      flex: 1;
      max-width: 320px;
    }
    .search-wrap input {
      font-family: 'Satoshi', sans-serif;
      font-size: 13px;
      color: var(--ink);
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px 8px 34px;
      width: 100%;
      outline: none;
    }
    .search-wrap input:focus { border-color: var(--pink); }
    .search-icon {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--ink-light);
      font-size: 14px;
      pointer-events: none;
    }
    .filter-select {
      font-family: 'Satoshi', sans-serif;
      font-size: 13px;
      color: var(--ink);
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px;
      outline: none;
      cursor: pointer;
    }
    .filter-select:focus { border-color: var(--pink); }
    .btn {
      font-family: 'Satoshi', sans-serif;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 9px 20px;
      border-radius: 999px;
      border: none;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .btn-primary { background: var(--pink); color: var(--white); }
    .btn-primary:hover { background: #c83870; }
    .ml-auto { margin-left: auto; }

    /* ── TABLE ── */
    .table-wrap {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: visible;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    thead th {
      background: #fafafa;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-light);
      padding: 11px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    thead th.r { text-align: right; }
    tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #fdfafa; }
    tbody td { padding: 13px 16px; vertical-align: middle; }
    tbody td.r { text-align: right; font-weight: 600; }
    .inv-num {
      font-weight: 700;
      color: var(--pink);
      text-decoration: none;
      font-size: 13px;
    }
    .inv-num:hover { text-decoration: underline; }
    .client-name { font-weight: 600; color: var(--ink); }
    .client-email { font-size: 11.5px; color: var(--ink-light); margin-top: 1px; }

    /* ── STATUS BADGE ── */
    .badge {
      display: inline-block;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      padding: 3px 8px;
      border-radius: 999px;
    }

    /* ── ROW ACTIONS ── */
    .actions-cell { position: relative; text-align: right; }
    .menu-btn {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--ink-light);
      font-size: 18px;
      padding: 4px 8px;
      border-radius: 6px;
      line-height: 1;
    }
    .menu-btn:hover { background: var(--blush); color: var(--ink); }
    .dropdown {
      display: none;
      position: absolute;
      right: 8px;
      top: 100%;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 10px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.10);
      z-index: 100;
      min-width: 160px;
      padding: 6px 0;
    }
    .dropdown.open { display: block; }
    .dropdown a,
    .dropdown button {
      display: block;
      width: 100%;
      text-align: left;
      padding: 8px 14px;
      font-family: 'Satoshi', sans-serif;
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
      text-decoration: none;
      background: none;
      border: none;
      cursor: pointer;
    }
    .dropdown a:hover,
    .dropdown button:hover { background: var(--blush); color: var(--pink); }
    .dropdown .danger:hover { background: #fef2f2; color: #dc2626; }
    .dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }

    .empty-state {
      text-align: center;
      padding: 56px 32px;
      color: var(--ink-light);
    }
    .empty-state p { font-size: 14px; margin-bottom: 16px; }

    /* ── TABS ── */
    .tab-bar {
      display: flex;
      gap: 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 24px;
      background: var(--white);
      border-radius: 12px 12px 0 0;
      padding: 0 4px;
    }
    .tab-btn {
      font-family: 'Satoshi', sans-serif;
      font-size: 12px; font-weight: 700; letter-spacing: 0.06em;
      text-transform: uppercase; color: var(--ink-light);
      padding: 13px 20px; border: none; background: none;
      border-bottom: 2px solid transparent; margin-bottom: -1px;
      cursor: pointer; transition: all 0.15s; text-decoration: none;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .tab-btn:hover { color: var(--ink); }
    .tab-btn.active { color: var(--pink); border-bottom-color: var(--pink); }
    .tab-count { background: var(--blush); border-radius: 999px; padding: 1px 7px; font-size: 10px; color: var(--ink-mid); font-weight: 700; }

    /* ── MODALS ── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(17,17,17,0.45);
      backdrop-filter: blur(3px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .td-modal {
      background: var(--white);
      border-radius: 0 0 20px 20px;
      box-shadow: 0 2px 0 #e8e0e0, 0 16px 56px rgba(0,0,0,0.15);
      width: 100%;
      max-width: 360px;
      overflow: hidden;
      animation: modalIn 0.18s ease;
    }
    @keyframes modalIn {
      from { opacity: 0; transform: translateY(-8px) scale(0.98); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-bar { height: 3px; background: linear-gradient(90deg, var(--pink), var(--card-rose)); }
    .modal-body { padding: 24px 24px 20px; }
    .modal-icon {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; margin-bottom: 14px;
    }
    .modal-icon.danger { background: #fdf0f4; }
    .modal-icon.info   { background: var(--blush); }
    .modal-title { font-size: 15px; font-weight: 700; letter-spacing: -0.02em; color: var(--ink); margin-bottom: 5px; }
    .modal-msg   { font-size: 13px; color: var(--ink-mid); line-height: 1.55; }
    .modal-actions { display: flex; gap: 8px; padding: 0 24px 22px; }
    .modal-actions.single .mbtn { flex: 1; }
    .mbtn {
      font-family: 'Satoshi', sans-serif;
      font-size: 11px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 11px 18px; border-radius: 999px; border: none;
      cursor: pointer; flex: 1; transition: all 0.15s;
    }
    .mbtn-cancel  { background: #f5f0f0; color: var(--ink-mid); }
    .mbtn-cancel:hover { background: #ece5e5; color: var(--ink); }
    .mbtn-danger  { background: var(--pink); color: var(--white); }
    .mbtn-danger:hover { background: #c83870; }
    .mbtn-primary { background: var(--dark); color: var(--white); }
    .mbtn-primary:hover { background: #333; }

    /* ── TOAST ── */
    .td-toast {
      position: fixed; bottom: 24px; left: 50%;
      transform: translateX(-50%) translateY(8px);
      background: var(--dark); color: var(--white);
      border-radius: 14px; padding: 13px 18px;
      display: flex; align-items: flex-start; gap: 11px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      z-index: 1100; min-width: 220px; max-width: 340px;
      opacity: 0; transition: all 0.2s ease;
      pointer-events: none;
    }
    .td-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
    .toast-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pink); margin-top: 4px; flex-shrink: 0; }
    .toast-text { font-size: 13px; line-height: 1.5; color: rgba(255,255,255,0.85); flex: 1; }
    .toast-text strong { display: block; color: #fff; font-weight: 600; margin-bottom: 1px; }
    .toast-close { background: none; border: none; color: rgba(255,255,255,0.3); cursor: pointer; font-size: 15px; padding: 0; line-height: 1; }
    .toast-close:hover { color: rgba(255,255,255,0.7); }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png"
         alt="" onerror="this.style.display='none'">
    <div class="sidebar-brand-name">
      TaterDash
      <span>MallowFrenchie</span>
    </div>
  </div>
  <div class="nav-section">
    <div class="nav-label">Create</div>
    <a class="nav-item" href="/taterdash-app/taterdash/new-invoice.html">
      <span class="nav-icon">📄</span> New Invoice
    </a>
    <a class="nav-item" href="/taterdash-app/admin/new-proposal.php">
      <span class="nav-icon">📋</span> New Proposal
    </a>
    <div class="nav-label" style="margin-top:16px;">Manage</div>
    <a class="nav-item active" href="/taterdash-app/admin/">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a class="nav-item" href="/taterdash-app/admin/?view=invoices">
      <span class="nav-icon">🧾</span> All Invoices
    </a>
    <a class="nav-item" href="/taterdash-app/admin/?view=clients">
      <span class="nav-icon">👥</span> Clients
    </a>
  </div>
  <div class="sidebar-footer">TaterDash v1.0</div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">Dashboard</div>
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:12px;color:var(--ink-mid);">Hi, <?= htmlspecialchars($_SESSION['td_user']) ?></span>
      <a class="btn btn-primary" href="/taterdash-app/admin/new-proposal.php" style="background:#555;">+ New Proposal</a>
      <a class="btn btn-primary" href="/taterdash-app/taterdash/new-invoice.html">+ New Invoice</a>
      <a href="/taterdash-app/taterdash/logout.php" style="font-size:12px;color:var(--ink-mid);text-decoration:none;padding:8px 12px;border:1px solid var(--border);border-radius:999px;">Log out</a>
    </div>
  </div>

  <div class="content">

    <!-- STAT CARDS -->
    <?php if ($tab === 'invoices'): ?>
    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">Total Invoiced</div>
        <div class="stat-value"><?= fmt_money($stats['total_invoiced']) ?></div>
        <div class="stat-sub"><?= $stats['total_count'] ?> invoice<?= $stats['total_count'] != 1 ? 's' : '' ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value"><?= fmt_money($stats['outstanding']) ?></div>
        <div class="stat-sub">Draft, sent &amp; viewed</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Paid</div>
        <div class="stat-value" style="color:#166534;"><?= fmt_money($stats['paid']) ?></div>
        <div class="stat-sub">Collected</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Drafts</div>
        <div class="stat-value" style="color:var(--ink-mid);"><?= intval($stats['drafts']) ?></div>
        <div class="stat-sub">Not sent yet</div>
      </div>
    </div>
    <?php else: ?>
    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">Total Proposals</div>
        <div class="stat-value" style="color:var(--ink);"><?= intval($prop_stats['total_count']) ?></div>
        <div class="stat-sub">All time</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Open</div>
        <div class="stat-value"><?= intval($prop_stats['open_count']) ?></div>
        <div class="stat-sub">Draft, sent &amp; viewed</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Signed</div>
        <div class="stat-value" style="color:#166534;"><?= intval($prop_stats['signed_count']) ?></div>
        <div class="stat-sub">Accepted</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Signed Value</div>
        <div class="stat-value" style="color:#166534;"><?= fmt_money($prop_stats['signed_value']) ?></div>
        <div class="stat-sub">Revenue from signed</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABS + TABLE WRAPPER -->
    <div class="table-wrap">
      <div class="tab-bar">
        <a class="tab-btn <?= $tab === 'invoices' ? 'active' : '' ?>" href="/taterdash-app/admin/?tab=invoices">
          🧾 Invoices <span class="tab-count"><?= $stats['total_count'] ?></span>
        </a>
        <a class="tab-btn <?= $tab === 'proposals' ? 'active' : '' ?>" href="/taterdash-app/admin/?tab=proposals">
          📋 Proposals <span class="tab-count"><?= $prop_stats['total_count'] ?></span>
        </a>
      </div>

      <?php if ($tab === 'invoices'): ?>
      <!-- INVOICE TOOLBAR -->
      <form method="GET" class="toolbar" style="padding:0 16px 12px;">
        <input type="hidden" name="tab" value="invoices">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" placeholder="Search client or invoice #" value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="draft"  <?= $status_filter === 'draft'  ? 'selected' : '' ?>>Draft</option>
          <option value="sent"   <?= $status_filter === 'sent'   ? 'selected' : '' ?>>Sent</option>
          <option value="viewed" <?= $status_filter === 'viewed' ? 'selected' : '' ?>>Viewed</option>
          <option value="paid"   <?= $status_filter === 'paid'   ? 'selected' : '' ?>>Paid</option>
        </select>
        <button type="submit" class="btn btn-primary" style="padding:9px 16px;">Search</button>
        <?php if ($search || $status_filter): ?>
          <a href="/taterdash-app/admin/" style="font-size:12px;color:var(--ink-mid);text-decoration:none;">✕ Clear</a>
        <?php endif; ?>
      </form>

      <?php if (empty($invoices)): ?>
        <div class="empty-state">
          <p>No invoices found.</p>
          <a class="btn btn-primary" href="/taterdash-app/taterdash/new-invoice.html">+ Create your first invoice</a>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Invoice #</th>
            <th>Client</th>
            <th>Issue Date</th>
            <th>Due Date</th>
            <th>Status</th>
            <th class="r">Amount</th>
            <th class="r">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv):
            $b = $badge_map[$inv['status']] ?? $badge_map['draft'];
          ?>
          <tr>
            <td><a class="inv-num" href="/invoice/?id=<?= $inv['id'] ?>" target="_blank"><?= htmlspecialchars($inv['invoice_num']) ?></a></td>
            <td>
              <div class="client-name"><?= htmlspecialchars($inv['client_name']) ?></div>
              <div class="client-email"><?= htmlspecialchars($inv['client_email']) ?></div>
            </td>
            <td><?= fmt_date($inv['issue_date']) ?></td>
            <td><?= fmt_date($inv['due_date']) ?></td>
            <td><span class="badge" style="background:<?= $b['bg'] ?>;color:<?= $b['color'] ?>;"><?= $b['label'] ?></span></td>
            <td class="r"><?= fmt_money($inv['total']) ?></td>
            <td class="actions-cell">
              <button class="menu-btn" onclick="toggleMenu(<?= $inv['id'] ?>, event)">⋯</button>
              <div class="dropdown" id="menu-<?= $inv['id'] ?>">
                <a href="/invoice/?id=<?= $inv['id'] ?>" target="_blank">Open invoice</a>
                <a href="/taterdash-app/admin/edit-invoice.php?id=<?= $inv['id'] ?>">Edit</a>
                <a href="#" onclick="copyLink(<?= $inv['id'] ?>, '<?= SITE_URL ?>/invoice/?id=<?= $inv['id'] ?>'); return false;">Copy link</a>
                <?php if ($inv['status'] !== 'paid'): ?>
                <div class="dropdown-divider"></div>
                <button onclick="markPaid(<?= $inv['id'] ?>)">Mark as paid</button>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <button class="danger" onclick="deleteInvoice(<?= $inv['id'] ?>)">Delete</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php else: /* proposals tab */ ?>
      <?php if (empty($proposals)): ?>
        <div class="empty-state">
          <p>No proposals yet.</p>
          <a class="btn btn-primary" href="/taterdash-app/admin/new-proposal.php">+ Create your first proposal</a>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Proposal #</th>
            <th>Client</th>
            <th>Campaign</th>
            <th>Package</th>
            <th>Status</th>
            <th class="r">Value</th>
            <th class="r">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($proposals as $prop):
            $status = $prop['expiry_date'] && $prop['expiry_date'] < date('Y-m-d') && $prop['status'] !== 'signed' ? 'expired' : $prop['status'];
            $b = $badge_map[$status] ?? $badge_map['draft'];
          ?>
          <tr>
            <td>
              <a class="inv-num" href="<?= SITE_URL ?>/proposal/?id=<?= $prop['id'] ?>" target="_blank">
                <?= htmlspecialchars($prop['proposal_num']) ?>
              </a>
            </td>
            <td>
              <div class="client-name"><?= htmlspecialchars($prop['client_name']) ?></div>
              <div class="client-email"><?= htmlspecialchars($prop['client_email']) ?></div>
            </td>
            <td style="font-size:12px;color:var(--ink-mid);"><?= htmlspecialchars($prop['campaign_name'] ?? '—') ?></td>
            <td style="font-size:12px;color:var(--ink-mid);"><?= htmlspecialchars($prop['package_name'] ?? 'Custom') ?></td>
            <td><span class="badge" style="background:<?= $b['bg'] ?>;color:<?= $b['color'] ?>;"><?= $b['label'] ?></span></td>
            <td class="r"><?= fmt_money($prop['total']) ?></td>
            <td class="actions-cell">
              <button class="menu-btn" onclick="toggleMenu('p<?= $prop['id'] ?>', event)">⋯</button>
              <div class="dropdown" id="menu-p<?= $prop['id'] ?>">
                <a href="<?= SITE_URL ?>/proposal/?id=<?= $prop['id'] ?>" target="_blank">Open proposal</a>
                <a href="/taterdash-app/admin/edit-proposal.php?id=<?= $prop['id'] ?>">Edit</a>
                <a href="#" onclick="copyPropLink('<?= SITE_URL ?>/proposal/?id=<?= $prop['id'] ?>'); return false;">Copy link</a>
                <div class="dropdown-divider"></div>
                <button class="danger" onclick="deleteProposal(<?= $prop['id'] ?>)">Delete</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <?php endif; ?>

    </div>

  </div>
</div>

<!-- Delete confirm modal -->
<div class="modal-overlay" id="modal-delete">
  <div class="td-modal">
    <div class="modal-bar"></div>
    <div class="modal-body">
      <div class="modal-icon danger">🗑️</div>
      <div class="modal-title">Delete invoice?</div>
      <div class="modal-msg">This cannot be undone. The invoice and all its line items will be permanently removed.</div>
    </div>
    <div class="modal-actions">
      <button class="mbtn mbtn-cancel" onclick="closeModal('modal-delete')">Cancel</button>
      <button class="mbtn mbtn-danger" id="modal-delete-confirm">Delete</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="td-toast" id="td-toast">
  <div class="toast-dot"></div>
  <div class="toast-text" id="td-toast-text"><strong id="td-toast-title"></strong><span id="td-toast-sub"></span></div>
  <button class="toast-close" onclick="hideToast()">✕</button>
</div>

<script>
  function toggleMenu(id, e) {
    e.stopPropagation();
    document.querySelectorAll('.dropdown').forEach(d => {
      if (d.id !== 'menu-' + id) d.classList.remove('open');
    });
    document.getElementById('menu-' + id).classList.toggle('open');
  }
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('open'));
  });

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }
  document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
  });

  async function copyLink(id, url) {
    await navigator.clipboard.writeText(url);
    await fetch('/taterdash-app/taterdash/update-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ invoice_id: id, status: 'sent' })
    });
    showToast('Link copied', 'Invoice marked as Sent');
    setTimeout(() => location.reload(), 1400);
  }

  async function markPaid(id) {
    await fetch('/taterdash-app/taterdash/update-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ invoice_id: id, status: 'paid' })
    });
    showToast('Marked as Paid', '');
    setTimeout(() => location.reload(), 1200);
  }

  function copyPropLink(url) {
    navigator.clipboard.writeText(url);
    showToast('Link copied', 'Share this with your client');
  }

  function deleteProposal(id) {
    const overlay = document.getElementById('modal-delete');
    overlay.classList.add('open');
    document.getElementById('modal-delete-confirm').onclick = async () => {
      overlay.classList.remove('open');
      await fetch('/taterdash-app/taterdash/delete-proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: id })
      });
      location.reload();
    };
  }

  function deleteInvoice(id) {
    const overlay = document.getElementById('modal-delete');
    overlay.classList.add('open');
    document.getElementById('modal-delete-confirm').onclick = async () => {
      overlay.classList.remove('open');
      await fetch('/taterdash-app/taterdash/update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_id: id, status: '_delete' })
      });
      location.reload();
    };
  }

  let toastTimer;
  function showToast(title, sub) {
    document.getElementById('td-toast-title').textContent = title;
    document.getElementById('td-toast-sub').textContent = sub;
    const t = document.getElementById('td-toast');
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
  }
  function hideToast() {
    clearTimeout(toastTimer);
    document.getElementById('td-toast').classList.remove('show');
  }
</script>
</body>
</html>
