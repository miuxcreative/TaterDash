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
        (SELECT COUNT(*) FROM td_proposals WHERE client_id = c.id) AS proposal_count
    FROM td_clients c
    ORDER BY c.company ASC
")->fetchAll();

function fmt($n): string { return '$'.number_format((float)$n, 0); }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
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

.topbar { position: fixed; top: 0; left: 240px; right: 0; height: 52px; background: #ffffff; border-bottom: 1px solid #e8e8e8; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 200; }
.topbar-title { font-size: 18px; font-weight: 700; color: #191919; }
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.tb-ghost { font-size: 12px; font-weight: 500; color: #6b6b6b; text-decoration: none; padding: 7px 12px; border-radius: 999px; transition: background .12s; }
.tb-ghost:hover { background: #f5f5f5; }
.tb-primary { font-family: inherit; font-size: 12px; font-weight: 700; color: #fff; background: #e04d80; border: none; padding: 8px 16px; border-radius: 999px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.tb-primary:hover { opacity: .9; }

.main { margin-left: 240px; padding: 52px 0 0; }
.main-inner { padding: 32px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-title { font-size: 28px; font-weight: 300; color: #191919; }

.clients-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.client-card { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 20px 22px; cursor: pointer; transition: border-color .12s; }
.client-card:hover { border-color: #e04d80; }
.client-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.client-name { font-size: 15px; font-weight: 700; color: #191919; }
.client-contact { font-size: 12px; color: #6b6b6b; margin-top: 2px; }
.client-avatar { width: 36px; height: 36px; border-radius: 50%; background: #f2d0dc; color: #e04d80; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; }
.client-stats { display: flex; gap: 20px; padding-top: 12px; border-top: 1px solid #f0f0f0; }
.client-stat-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #b0b0b0; }
.client-stat-value { font-size: 15px; font-weight: 700; color: #191919; font-variant-numeric: tabular-nums; margin-top: 2px; }
.client-stat-value--pink { color: #e04d80; }

.empty-state { text-align: center; padding: 60px 20px; color: #b0b0b0; }
.empty-state i { font-size: 32px; margin-bottom: 12px; display: block; }

/* Modal */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 500; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal { background: #ffffff; border-radius: 16px; overflow: hidden; width: 420px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
.modal-bar { height: 4px; background: #e04d80; }
.modal-body { padding: 28px; }
.modal-title { font-size: 17px; font-weight: 700; margin-bottom: 20px; }
.field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.field label { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6b6b6b; }
.field input, .field textarea {
    font-family: inherit; font-size: 13.5px; color: #191919;
    background: #fff; border: 1px solid #e8e8e8; border-radius: 8px;
    padding: 9px 12px; outline: none; transition: border-color .15s; width: 100%;
}
.field input:focus, .field textarea:focus { border-color: #e04d80; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 0 28px 24px; }
.modal-btn { padding: 9px 20px; border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.modal-btn:hover { opacity: .85; }
.modal-btn--cancel { background: #f5e5e5; color: #6b6b6b; }
.modal-btn--save { background: #e04d80; color: #ffffff; }

.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: #111111; color: #ffffff; padding: 12px 22px; border-radius: 999px; font-size: 14px; font-weight: 600; opacity: 0; pointer-events: none; transition: opacity .25s, transform .25s; z-index: 600; white-space: nowrap; }
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
    <a class="nav-item" href="/taterdash-app/admin/?view=all"><i class="ti ti-list"></i> All Activity</a>
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

<div class="topbar">
    <div class="topbar-title">Clients</div>
    <div class="topbar-actions">
        <button class="tb-primary" onclick="openClientModal()"><i class="ti ti-plus"></i> Add Client</button>
        <a class="tb-ghost" href="/taterdash-app/admin/">← Dashboard</a>
    </div>
</div>

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
    <div class="clients-grid">
        <?php foreach ($clients as $c): ?>
        <div class="client-card" onclick='openClientModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <div class="client-top">
                <div>
                    <div class="client-name"><?= htmlspecialchars($c['company']) ?></div>
                    <div class="client-contact"><?= htmlspecialchars($c['contact_name'] ?: $c['email']) ?></div>
                </div>
                <div class="client-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($c['company'],0,1))) ?></div>
            </div>
            <div class="client-stats">
                <div>
                    <div class="client-stat-label">Paid</div>
                    <div class="client-stat-value client-stat-value--pink"><?= fmt($c['invoiced_paid']) ?></div>
                </div>
                <div>
                    <div class="client-stat-label">Signed</div>
                    <div class="client-stat-value client-stat-value--pink"><?= fmt($c['signed_total']) ?></div>
                </div>
                <div>
                    <div class="client-stat-label">Docs</div>
                    <div class="client-stat-value"><?= (int)$c['invoice_count'] + (int)$c['proposal_count'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Client modal -->
<div class="modal-overlay" id="clientModal">
    <div class="modal">
        <div class="modal-bar"></div>
        <div class="modal-body">
            <div class="modal-title" id="modalTitle">Add Client</div>
            <input type="hidden" id="c_id">
            <div class="field">
                <label>Company / Client Name *</label>
                <input type="text" id="c_company" placeholder="Airdog USA">
            </div>
            <div class="field">
                <label>Contact Name</label>
                <input type="text" id="c_contact" placeholder="Jane Smith">
            </div>
            <div class="field">
                <label>Email *</label>
                <input type="email" id="c_email" placeholder="contact@brand.com">
            </div>
            <div class="field">
                <label>Phone</label>
                <input type="text" id="c_phone" placeholder="(305) 555-0100">
            </div>
            <div class="field">
                <label>Notes</label>
                <textarea id="c_notes" rows="3" placeholder="Anything worth remembering about this client..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn--cancel" onclick="closeClientModal()">Cancel</button>
            <button class="modal-btn modal-btn--save" onclick="saveClient()">Save</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function openClientModal(c) {
    document.getElementById('modalTitle').textContent = c ? 'Edit Client' : 'Add Client';
    document.getElementById('c_id').value      = c ? c.id : '';
    document.getElementById('c_company').value = c ? c.company : '';
    document.getElementById('c_contact').value = c ? (c.contact_name || '') : '';
    document.getElementById('c_email').value   = c ? c.email : '';
    document.getElementById('c_phone').value   = c ? (c.phone || '') : '';
    document.getElementById('c_notes').value   = c ? (c.notes || '') : '';
    document.getElementById('clientModal').classList.add('open');
}
function closeClientModal() { document.getElementById('clientModal').classList.remove('open'); }
document.getElementById('clientModal').addEventListener('click', function(e) { if (e.target === this) closeClientModal(); });

async function saveClient() {
    const payload = {
        id: document.getElementById('c_id').value || null,
        company: document.getElementById('c_company').value.trim(),
        contact_name: document.getElementById('c_contact').value.trim(),
        email: document.getElementById('c_email').value.trim(),
        phone: document.getElementById('c_phone').value.trim(),
        notes: document.getElementById('c_notes').value.trim(),
    };
    if (!payload.company || !payload.email) {
        showToast('Company and email are required.');
        return;
    }
    try {
        const d = await fetch('/taterdash-app/taterdash/save-client.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        }).then(r => r.json());
        if (d.success) { showToast('Saved!'); setTimeout(() => location.reload(), 700); }
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
