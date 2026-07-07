<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo  = db_connect();
$user = $_SESSION['td_user'];

$clients = $pdo->query("
    SELECT c.*,
        (SELECT COALESCE(SUM(total),0) FROM td_invoices  WHERE client_id = c.id AND status = 'paid') AS invoiced_paid,
        (SELECT COALESCE(SUM(total),0) FROM td_proposals WHERE client_id = c.id AND status IN ('signed','accepted')) AS signed_total,
        (SELECT COUNT(*) FROM td_invoices  WHERE client_id = c.id) AS invoice_count,
        (SELECT COUNT(*) FROM td_proposals WHERE client_id = c.id) AS proposal_count,
        (SELECT COUNT(*) FROM td_invoices  WHERE client_id = c.id AND status IN ('sent','viewed')) AS invoice_active_count,
        (SELECT COUNT(*) FROM td_proposals WHERE client_id = c.id AND status IN ('sent','viewed')) AS proposal_active_count,
        GREATEST(
            COALESCE((SELECT MAX(created_at) FROM td_invoices  WHERE client_id = c.id), '1970-01-01'),
            COALESCE((SELECT MAX(created_at) FROM td_proposals WHERE client_id = c.id), '1970-01-01'),
            c.created_at
        ) AS last_activity_at
    FROM td_clients c
    ORDER BY c.company ASC
")->fetchAll();

const VIP_THRESHOLD = 2000;
const DORMANT_DAYS  = 90;

function fmt($n): string { return '$'.number_format((float)$n, 0); }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<title>Clients — TaterDash</title>
<link rel="preconnect" href="https://api.fontshare.com">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Satoshi', sans-serif; background: #f5f5f5; color: #191919; -webkit-font-smoothing: antialiased; }

.sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 240px; background: #111111; display: flex; flex-direction: column; overflow-y: auto; z-index: 300; }
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
.nav-profile { padding: 14px 20px 22px; }
.nav-profile-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.nav-avatar { width: 32px; height: 32px; border-radius: 50%; background: #f2d0dc; color: #e04d80; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; }
.nav-uname  { font-size: 13px; font-weight: 500; color: #ffffff; }
.nav-profile-link { display: block; font-size: 12px; color: #6b6b6b; text-decoration: none; padding: 4px 0; transition: color .12s; }
.nav-profile-link:hover { color: rgba(255,255,255,0.6); }

/* ── Topbar: see admin/partials/topbar.php for shared markup + styles ── */
.tb-primary { font-family: inherit; font-size: 12px; font-weight: 700; color: #fff; background: #e04d80; border: none; padding: 8px 16px; border-radius: 999px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.tb-primary:hover { opacity: .9; }

.main { margin-left: 240px; padding: 52px 0 0; }
.main-inner { padding: 32px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-title { font-size: 28px; font-weight: 300; color: #191919; }

/* ── Toolbar: search + filter chips ── */
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.search-box { position: relative; flex: 1; min-width: 220px; max-width: 320px; }
.search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #b0b0b0; font-size: 15px; }
.search-box input {
    width: 100%; font-family: inherit; font-size: 13px; color: #191919;
    background: #fff; border: 1px solid #e8e8e8; border-radius: 999px;
    padding: 9px 14px 9px 34px; outline: none; transition: border-color .15s;
}
.search-box input:focus { border-color: #e04d80; }
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-chip {
    padding: 7px 15px; border-radius: 999px; border: 1px solid #e8e8e8;
    background: #ffffff; font-family: inherit; font-size: 12px; font-weight: 600;
    color: #6b6b6b; cursor: pointer; transition: all .12s; white-space: nowrap;
}
.filter-chip:hover { border-color: #191919; color: #191919; }
.filter-chip.active { background: #111111; color: #ffffff; border-color: #111111; }

/* ── Table ── */
.table-wrap { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 12px; overflow: hidden; }
.clients-table { width: 100%; border-collapse: collapse; }
.clients-table th {
    padding: 12px 16px; text-align: left; font-size: 10px; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase; color: #b0b0b0;
    border-bottom: 1px solid #e8e8e8; white-space: nowrap;
}
.clients-table td { padding: 13px 16px; font-size: 13px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.clients-table tr:last-child td { border-bottom: none; }
.clients-table tr.client-row { cursor: pointer; transition: background .1s; }
.clients-table tr.client-row:hover td { background: #fafafa; }
.cell-name { display: flex; align-items: center; gap: 10px; }
.row-avatar { width: 30px; height: 30px; border-radius: 50%; background: #f2d0dc; color: #e04d80; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; }
.cell-company { font-weight: 600; color: #191919; }
.cell-contact { font-size: 11.5px; color: #b0b0b0; }
.cell-muted { color: #6b6b6b; }
.cell-amount { font-variant-numeric: tabular-nums; font-weight: 600; text-align: right; white-space: nowrap; }
.cell-amount--pink { color: #e04d80; }
.status-pill { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
.status-pill--active  { background: #f2d0dc; color: #191919; }
.status-pill--vip      { background: #e04d80; color: #ffffff; }
.status-pill--dormant  { background: #f5e5e5; color: #b0b0b0; }
.status-pill--new      { background: #f0f0f0; color: #6b6b6b; }

.empty-state { text-align: center; padding: 60px 20px; color: #b0b0b0; }
.empty-state i { font-size: 32px; margin-bottom: 12px; display: block; }

/* ── Side panel ── */
.panel-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.25); z-index: 500;
}
.panel-overlay.open { display: block; }
.side-panel {
    position: fixed; top: 0; right: 0; bottom: 0; width: 420px; max-width: 92vw;
    background: #ffffff; z-index: 501; box-shadow: -8px 0 40px rgba(0,0,0,.12);
    transform: translateX(100%); transition: transform .22s ease; overflow-y: auto;
    display: flex; flex-direction: column;
}
.side-panel.open { transform: translateX(0); }
.panel-header { padding: 24px 24px 0; display: flex; align-items: flex-start; justify-content: space-between; }
.panel-close { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f5f5f5; color: #6b6b6b; font-size: 16px; cursor: pointer; flex-shrink: 0; }
.panel-close:hover { background: #eee; }
.panel-identity { display: flex; align-items: center; gap: 14px; padding: 8px 24px 20px; }
.panel-avatar { width: 48px; height: 48px; border-radius: 50%; background: #f2d0dc; color: #e04d80; font-size: 18px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; }
.panel-name { font-size: 17px; font-weight: 700; color: #191919; }
.panel-contact { font-size: 12.5px; color: #6b6b6b; margin-top: 2px; }

.panel-actions { display: flex; gap: 8px; padding: 0 24px 20px; }
.panel-action-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
    font-family: inherit; font-size: 12px; font-weight: 700; padding: 10px 12px;
    border-radius: 999px; border: none; cursor: pointer; text-decoration: none; transition: opacity .15s;
}
.panel-action-btn:hover { opacity: .85; }
.panel-action-btn--invoice  { background: #191919; color: #fff; }
.panel-action-btn--proposal { background: #f2d0dc; color: #191919; }
.panel-action-btn--edit     { background: #f5e5e5; color: #6b6b6b; flex: 0 0 auto; padding: 10px 16px; }

.panel-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 1px; background: #f0f0f0; margin: 0 24px 20px; border-radius: 10px; overflow: hidden; }
.panel-stat { background: #faf0f0; padding: 12px 14px; }
.panel-stat-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #b0b0b0; }
.panel-stat-value { font-size: 16px; font-weight: 700; color: #191919; margin-top: 3px; font-variant-numeric: tabular-nums; }
.panel-stat-value--pink { color: #e04d80; }

.panel-section { padding: 0 24px 20px; }
.panel-section-title { font-size: 10px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #6b6b6b; margin-bottom: 10px; }
.panel-notes { font-size: 13px; color: #444; line-height: 1.55; background: #f9f9f9; border-radius: 8px; padding: 12px 14px; }
.panel-notes.empty { color: #b0b0b0; font-style: italic; }

.doc-list { display: flex; flex-direction: column; gap: 6px; }
.doc-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #f0f0f0; border-radius: 8px; text-decoration: none; color: inherit; transition: border-color .12s; }
.doc-row:hover { border-color: #e04d80; }
.doc-icon { width: 28px; height: 28px; border-radius: 7px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #6b6b6b; flex-shrink: 0; }
.doc-main { flex: 1; min-width: 0; }
.doc-num { font-size: 12.5px; font-weight: 600; color: #191919; }
.doc-date { font-size: 11px; color: #b0b0b0; }
.doc-right { text-align: right; flex-shrink: 0; }
.doc-amount { font-size: 12.5px; font-weight: 700; color: #191919; font-variant-numeric: tabular-nums; }
.doc-status { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-top: 3px; display: inline-block; }
.panel-loading, .panel-empty-docs { font-size: 12.5px; color: #b0b0b0; padding: 8px 0; }

/* Edit fields */
.panel-edit-fields { display: none; flex-direction: column; gap: 12px; padding: 0 24px 20px; }
.panel-edit-fields.open { display: flex; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6b6b6b; }
.field input, .field textarea {
    font-family: inherit; font-size: 13.5px; color: #191919;
    background: #fff; border: 1px solid #e8e8e8; border-radius: 8px;
    padding: 9px 12px; outline: none; transition: border-color .15s; width: 100%;
}
.field input:focus, .field textarea:focus { border-color: #e04d80; }
.panel-save-btn { background: #e04d80; color: #fff; border: none; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 700; padding: 10px 18px; cursor: pointer; align-self: flex-start; }
.panel-save-btn:hover { opacity: .9; }

/* Add-client modal (kept simpler, separate from edit-in-panel) */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 700; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #ffffff; border-radius: 16px; overflow: hidden; width: 420px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
.modal-bar { height: 4px; background: #e04d80; }
.modal-body { padding: 28px; }
.modal-title { font-size: 17px; font-weight: 700; margin-bottom: 20px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 0 28px 24px; }
.modal-btn { padding: 9px 20px; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.modal-btn:hover { opacity: .85; }
.modal-btn--cancel { background: #f5e5e5; color: #6b6b6b; }
.modal-btn--save { background: #e04d80; color: #ffffff; }

.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: #111111; color: #ffffff; padding: 12px 22px; border-radius: 999px; font-size: 14px; font-weight: 600; opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 900; white-space: nowrap; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>

<nav class="sidebar">
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
    <a class="nav-item" href="/taterdash-app/admin/all-activity.php"><i class="ti ti-list"></i> All Activity</a>
    <a class="nav-item active" href="/taterdash-app/admin/clients.php"><i class="ti ti-users"></i> Clients</a>
    <div class="nav-spacer"></div>
    <hr class="nav-divider">
    <div class="nav-profile">
        <div class="nav-profile-row">
            <div class="nav-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($user,0,1))) ?></div>
            <div class="nav-uname"><?= htmlspecialchars($user) ?></div>
        </div>
        <a class="nav-profile-link" href="/taterdash-app/admin/settings.php">⚙ Settings</a>
        <a class="nav-profile-link" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</nav>

<?php
$topbar_title = 'Clients';
$topbar_extra_actions = '<button class="tb-primary" onclick="openAddModal()"><i class="ti ti-plus"></i> Add Client</button>';
include __DIR__ . '/partials/topbar.php';
?>

<div class="main">
<div class="main-inner">
    <div class="page-header">
        <h1 class="page-title">Clients</h1>
    </div>

    <?php if (empty($clients)): ?>
    <div class="empty-state">
        <i class="ti ti-users"></i>
        No clients yet — they're added automatically when you create an invoice or proposal, or add one manually.
    </div>
    <?php else: ?>

    <div class="toolbar">
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" id="searchInput" placeholder="Search clients..." oninput="renderTable()">
        </div>
        <div class="filter-chips">
            <button class="filter-chip active" data-filter="all"     onclick="setFilter('all')">All</button>
            <button class="filter-chip"        data-filter="active"  onclick="setFilter('active')">Active</button>
            <button class="filter-chip"        data-filter="vip"     onclick="setFilter('vip')">VIP</button>
            <button class="filter-chip"        data-filter="dormant" onclick="setFilter('dormant')">Dormant</button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="clients-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th style="text-align:right">Paid</th>
                    <th style="text-align:right">Signed</th>
                    <th style="text-align:right">Docs</th>
                </tr>
            </thead>
            <tbody id="clientsBody"></tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Side panel -->
<div class="panel-overlay" id="panelOverlay" onclick="closePanel()"></div>
<div class="side-panel" id="sidePanel">
    <div class="panel-header">
        <div></div>
        <button class="panel-close" onclick="closePanel()">✕</button>
    </div>
    <div class="panel-identity">
        <div class="panel-avatar" id="p-avatar"></div>
        <div>
            <div class="panel-name" id="p-name"></div>
            <div class="panel-contact" id="p-contact"></div>
        </div>
    </div>
    <div class="panel-actions">
        <a class="panel-action-btn panel-action-btn--invoice" id="p-new-invoice" href="#"><i class="ti ti-file-invoice"></i> New Invoice</a>
        <a class="panel-action-btn panel-action-btn--proposal" id="p-new-proposal" href="#"><i class="ti ti-file-text"></i> New Proposal</a>
        <button class="panel-action-btn panel-action-btn--edit" onclick="toggleEdit()"><i class="ti ti-pencil"></i></button>
    </div>
    <div class="panel-stats">
        <div class="panel-stat"><div class="panel-stat-label">Paid</div><div class="panel-stat-value panel-stat-value--pink" id="p-paid"></div></div>
        <div class="panel-stat"><div class="panel-stat-label">Signed</div><div class="panel-stat-value panel-stat-value--pink" id="p-signed"></div></div>
        <div class="panel-stat"><div class="panel-stat-label">Docs</div><div class="panel-stat-value" id="p-docs"></div></div>
    </div>

    <div class="panel-edit-fields" id="editFields">
        <input type="hidden" id="e_id">
        <div class="field"><label>Company / Client Name *</label><input type="text" id="e_company"></div>
        <div class="field"><label>Contact Name</label><input type="text" id="e_contact"></div>
        <div class="field"><label>Email *</label><input type="email" id="e_email"></div>
        <div class="field"><label>Phone</label><input type="text" id="e_phone"></div>
        <div class="field"><label>Notes</label><textarea id="e_notes" rows="3"></textarea></div>
        <button class="panel-save-btn" onclick="saveEdit()">Save Changes</button>
    </div>

    <div class="panel-section" id="notesSection">
        <div class="panel-section-title">Notes</div>
        <div class="panel-notes" id="p-notes"></div>
    </div>

    <div class="panel-section">
        <div class="panel-section-title">Invoices</div>
        <div class="doc-list" id="p-invoices"><div class="panel-loading">Loading...</div></div>
    </div>
    <div class="panel-section">
        <div class="panel-section-title">Proposals</div>
        <div class="doc-list" id="p-proposals"></div>
    </div>
</div>

<!-- Add client modal -->
<div class="modal-overlay" id="clientModal">
    <div class="modal">
        <div class="modal-bar"></div>
        <div class="modal-body">
            <div class="modal-title">Add Client</div>
            <div class="field" style="margin-bottom:14px;"><label>Company / Client Name *</label><input type="text" id="c_company" placeholder="Airdog USA"></div>
            <div class="field" style="margin-bottom:14px;"><label>Contact Name</label><input type="text" id="c_contact" placeholder="Jane Smith"></div>
            <div class="field" style="margin-bottom:14px;"><label>Email *</label><input type="email" id="c_email" placeholder="contact@brand.com"></div>
            <div class="field" style="margin-bottom:14px;"><label>Phone</label><input type="text" id="c_phone" placeholder="(305) 555-0100"></div>
            <div class="field"><label>Notes</label><textarea id="c_notes" rows="3" placeholder="Anything worth remembering..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn--cancel" onclick="closeAddModal()">Cancel</button>
            <button class="modal-btn modal-btn--save" onclick="saveNewClient()">Save</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const VIP_THRESHOLD  = <?= VIP_THRESHOLD ?>;
const DORMANT_DAYS   = <?= DORMANT_DAYS ?>;
const ALL_CLIENTS    = <?= json_encode($clients) ?>;
let _filter = 'all';
let _activePanelId = null;

function clientStatus(c) {
    const paid   = parseFloat(c.invoiced_paid) + parseFloat(c.signed_total);
    const active = parseInt(c.invoice_active_count) + parseInt(c.proposal_active_count) > 0;
    const docs   = parseInt(c.invoice_count) + parseInt(c.proposal_count);
    const daysSince = (Date.now() - new Date(c.last_activity_at).getTime()) / 86400000;

    if (docs === 0) return { key: 'new', label: 'New' };
    if (active)      return { key: 'active', label: 'Active' };
    if (paid >= VIP_THRESHOLD) return { key: 'vip', label: 'VIP' };
    if (daysSince >= DORMANT_DAYS) return { key: 'dormant', label: 'Dormant' };
    return { key: 'new', label: 'Quiet' };
}

function fmtMoney(n) { return '$' + Math.round(parseFloat(n || 0)).toLocaleString('en-US'); }
function escHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function setFilter(f) {
    _filter = f;
    document.querySelectorAll('.filter-chip').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-filter="' + f + '"]').classList.add('active');
    renderTable();
}

function renderTable() {
    const q = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const body = document.getElementById('clientsBody');
    if (!body) return;

    const rows = ALL_CLIENTS.filter(c => {
        const status = clientStatus(c);
        if (_filter !== 'all' && status.key !== _filter) return false;
        if (!q) return true;
        return c.company.toLowerCase().includes(q)
            || (c.contact_name || '').toLowerCase().includes(q)
            || c.email.toLowerCase().includes(q);
    });

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#b0b0b0;">No clients match.</td></tr>';
        return;
    }

    body.innerHTML = rows.map(c => {
        const status = clientStatus(c);
        return `
        <tr class="client-row" onclick="openPanel(${c.id})">
            <td>
                <div class="cell-name">
                    <div class="row-avatar">${escHtml(c.company.charAt(0))}</div>
                    <div class="cell-company">${escHtml(c.company)}</div>
                </div>
            </td>
            <td class="cell-muted">
                ${escHtml(c.contact_name || c.email)}
                ${c.contact_name ? `<div class="cell-contact">${escHtml(c.email)}</div>` : ''}
            </td>
            <td><span class="status-pill status-pill--${status.key}">${status.label}</span></td>
            <td class="cell-amount cell-amount--pink">${fmtMoney(c.invoiced_paid)}</td>
            <td class="cell-amount cell-amount--pink">${fmtMoney(c.signed_total)}</td>
            <td class="cell-amount">${parseInt(c.invoice_count) + parseInt(c.proposal_count)}</td>
        </tr>`;
    }).join('');
}

renderTable();

// ── Side panel ──
function openPanel(id) {
    const c = ALL_CLIENTS.find(x => x.id === id);
    if (!c) return;
    _activePanelId = id;

    document.getElementById('p-avatar').textContent  = c.company.charAt(0).toUpperCase();
    document.getElementById('p-name').textContent    = c.company;
    document.getElementById('p-contact').textContent = c.contact_name ? (c.contact_name + ' · ' + c.email) : c.email;
    document.getElementById('p-paid').textContent    = fmtMoney(c.invoiced_paid);
    document.getElementById('p-signed').textContent  = fmtMoney(c.signed_total);
    document.getElementById('p-docs').textContent    = parseInt(c.invoice_count) + parseInt(c.proposal_count);

    const notesEl = document.getElementById('p-notes');
    if (c.notes) { notesEl.textContent = c.notes; notesEl.classList.remove('empty'); }
    else { notesEl.textContent = 'No notes yet.'; notesEl.classList.add('empty'); }

    const qs = new URLSearchParams({ client_id: c.id, client_name: c.company, client_email: c.email, contact_name: c.contact_name || '' }).toString();
    document.getElementById('p-new-invoice').href  = '/taterdash-app/taterdash/new-invoice.html?' + qs;
    document.getElementById('p-new-proposal').href = '/taterdash-app/admin/new-proposal.php?' + qs;

    // Reset edit fields
    document.getElementById('editFields').classList.remove('open');
    document.getElementById('e_id').value      = c.id;
    document.getElementById('e_company').value = c.company;
    document.getElementById('e_contact').value = c.contact_name || '';
    document.getElementById('e_email').value   = c.email;
    document.getElementById('e_phone').value   = c.phone || '';
    document.getElementById('e_notes').value   = c.notes || '';

    document.getElementById('p-invoices').innerHTML  = '<div class="panel-loading">Loading...</div>';
    document.getElementById('p-proposals').innerHTML = '';

    document.getElementById('panelOverlay').classList.add('open');
    document.getElementById('sidePanel').classList.add('open');

    fetch('/taterdash-app/taterdash/get-client-detail.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            renderDocs('p-invoices',  d.invoices,  'invoice_num',  '/invoice/?t=',  'ti-file-invoice');
            renderDocs('p-proposals', d.proposals, 'proposal_num', '/proposal/?t=', 'ti-file-text');
        })
        .catch(() => {
            document.getElementById('p-invoices').innerHTML = '<div class="panel-empty-docs">Couldn\'t load.</div>';
        });
}

function renderDocs(elId, docs, numField, urlPrefix, icon) {
    const el = document.getElementById(elId);
    if (!docs || !docs.length) { el.innerHTML = '<div class="panel-empty-docs">None yet.</div>'; return; }
    const badgeMap = {
        draft: ['#f5e5e5','#b0b0b0'], sent: ['#faf0f0','#6b6b6b'], viewed: ['#f2d0dc','#191919'],
        paid: ['#e04d80','#ffffff'], signed: ['#e04d80','#ffffff'], accepted: ['#e04d80','#ffffff'],
        declined: ['#f5e5e5','#b0b0b0']
    };
    el.innerHTML = docs.map(d => {
        const [bg,col] = badgeMap[d.status] || ['#f5e5e5','#b0b0b0'];
        const label = d.status === 'accepted' ? 'Signed' : d.status.charAt(0).toUpperCase() + d.status.slice(1);
        const date = new Date(d.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
        return `
        <a class="doc-row" href="${urlPrefix}${d.token}" target="_blank">
            <div class="doc-icon"><i class="ti ${icon}"></i></div>
            <div class="doc-main">
                <div class="doc-num">${escHtml(d[numField])}</div>
                <div class="doc-date">${date}</div>
            </div>
            <div class="doc-right">
                <div class="doc-amount">${fmtMoney(d.total)}</div>
                <div class="doc-status" style="background:${bg};color:${col}">${label}</div>
            </div>
        </a>`;
    }).join('');
}

function closePanel() {
    document.getElementById('panelOverlay').classList.remove('open');
    document.getElementById('sidePanel').classList.remove('open');
    _activePanelId = null;
}

function toggleEdit() {
    document.getElementById('editFields').classList.toggle('open');
}

async function saveEdit() {
    const payload = {
        id: document.getElementById('e_id').value,
        company: document.getElementById('e_company').value.trim(),
        contact_name: document.getElementById('e_contact').value.trim(),
        email: document.getElementById('e_email').value.trim(),
        phone: document.getElementById('e_phone').value.trim(),
        notes: document.getElementById('e_notes').value.trim(),
    };
    if (!payload.company || !payload.email) { showToast('Company and email are required.'); return; }
    try {
        const d = await fetch('/taterdash-app/taterdash/save-client.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        }).then(r => r.json());
        if (d.success) { showToast('Saved!'); setTimeout(() => location.reload(), 700); }
        else showToast('Error: ' + (d.error || 'Failed.'));
    } catch (e) { showToast('Network error.'); }
}

// ── Add client modal ──
function openAddModal() { document.getElementById('clientModal').classList.add('open'); }
function closeAddModal() { document.getElementById('clientModal').classList.remove('open'); }
document.getElementById('clientModal').addEventListener('click', function(e) { if (e.target === this) closeAddModal(); });

async function saveNewClient() {
    const payload = {
        company: document.getElementById('c_company').value.trim(),
        contact_name: document.getElementById('c_contact').value.trim(),
        email: document.getElementById('c_email').value.trim(),
        phone: document.getElementById('c_phone').value.trim(),
        notes: document.getElementById('c_notes').value.trim(),
    };
    if (!payload.company || !payload.email) { showToast('Company and email are required.'); return; }
    try {
        const d = await fetch('/taterdash-app/taterdash/save-client.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        }).then(r => r.json());
        if (d.success) { showToast('Client added!'); setTimeout(() => location.reload(), 700); }
        else showToast('Error: ' + (d.error || 'Failed.'));
    } catch (e) { showToast('Network error.'); }
}

let _tt;
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg; el.classList.add('show');
    clearTimeout(_tt); _tt = setTimeout(() => el.classList.remove('show'), 2800);
}
</script>
</body>
</html>
