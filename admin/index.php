<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';

$pdo  = db_connect();
$year = (int) date('Y');

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_sent = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM (
        SELECT total FROM td_invoices  WHERE status != 'draft' AND YEAR(created_at) = $year
        UNION ALL
        SELECT total FROM td_proposals WHERE status != 'draft' AND YEAR(created_at) = $year
    ) t")->fetchColumn();

$invoices_paid = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM td_invoices
    WHERE status = 'paid' AND YEAR(created_at) = $year")->fetchColumn();

$proposals_signed = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM td_proposals
    WHERE status IN ('signed','accepted') AND YEAR(created_at) = $year")->fetchColumn();

$outstanding = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM (
        SELECT total FROM td_invoices  WHERE status IN ('sent','viewed') AND YEAR(created_at) = $year
        UNION ALL
        SELECT total FROM td_proposals WHERE status IN ('sent','viewed') AND YEAR(created_at) = $year
    ) t")->fetchColumn();

// ── Monthly chart data ────────────────────────────────────────────────────────
$inv_sent    = array_fill(0, 12, 0);
$inv_paid    = array_fill(0, 12, 0);
$prop_sent   = array_fill(0, 12, 0);
$prop_signed = array_fill(0, 12, 0);

foreach ($pdo->query("
    SELECT MONTH(created_at) AS m, status, SUM(total) AS total
    FROM td_invoices WHERE YEAR(created_at) = $year
    GROUP BY MONTH(created_at), status")->fetchAll() as $r) {
    $i = (int)$r['m'] - 1;
    if ($r['status'] !== 'draft') { $inv_sent[$i] += (float)$r['total']; }
    if ($r['status'] === 'paid')  { $inv_paid[$i] += (float)$r['total']; }
}
foreach ($pdo->query("
    SELECT MONTH(created_at) AS m, status, SUM(total) AS total
    FROM td_proposals WHERE YEAR(created_at) = $year
    GROUP BY MONTH(created_at), status")->fetchAll() as $r) {
    $i = (int)$r['m'] - 1;
    if ($r['status'] !== 'draft')                      { $prop_sent[$i]   += (float)$r['total']; }
    if (in_array($r['status'], ['signed','accepted'])) { $prop_signed[$i] += (float)$r['total']; }
}

$chart_data = json_encode([
    'inv_sent'    => $inv_sent,
    'inv_paid'    => $inv_paid,
    'prop_sent'   => $prop_sent,
    'prop_signed' => $prop_signed,
]);

// ── Activity (invoices + proposals unified) ───────────────────────────────────
$activity = $pdo->query("
    SELECT 'invoice' AS row_type, id, client_name, invoice_num AS ref_num,
           invoice_num AS sub_label, created_at, status, total
    FROM td_invoices
    UNION ALL
    SELECT 'proposal' AS row_type, id, client_name, proposal_num AS ref_num,
           COALESCE(campaign_name,'') AS sub_label, created_at, status, total
    FROM td_proposals
    ORDER BY created_at DESC")->fetchAll();

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt_money(float $n): string {
    return '$' . number_format($n, 0);
}

function status_badge(string $status): string {
    $map = [
        'draft'    => 'badge--draft',
        'sent'     => 'badge--sent',
        'viewed'   => 'badge--viewed',
        'paid'     => 'badge--paid',
        'signed'   => 'badge--signed',
        'accepted' => 'badge--signed',
    ];
    $cls   = $map[$status] ?? 'badge--draft';
    $label = ucfirst($status === 'accepted' ? 'signed' : $status);
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function row_actions(array $row): string {
    $type   = $row['row_type'];
    $id     = (int) $row['id'];
    $status = $row['status'];
    $html   = '';

    if ($type === 'invoice') {
        $open_url = '/invoice/?id=' . $id;
        $edit_url = '/taterdash-app/admin/edit-invoice.php?id=' . $id;
        $copy_url = SITE_URL . '/invoice/?id=' . $id;
        if ($status === 'draft') {
            $html .= '<a class="act-btn" href="' . $edit_url . '">Edit</a>';
            $html .= '<button class="act-btn danger" onclick="confirmDelete(\'invoice\',' . $id . ')">Delete</button>';
        } elseif (in_array($status, ['sent','viewed'])) {
            $html .= '<a class="act-btn" href="' . $open_url . '" target="_blank">Open</a>';
            $html .= '<button class="act-btn" onclick="copyLink(\'' . htmlspecialchars($copy_url, ENT_QUOTES) . '\')">Copy link</button>';
            $html .= '<button class="act-btn" onclick="markPaid(' . $id . ')">Mark paid</button>';
        } else {
            $html .= '<a class="act-btn" href="' . $open_url . '" target="_blank">Open</a>';
        }
    } else {
        $open_url = '/proposal/?id=' . $id;
        $edit_url = '/taterdash-app/admin/edit-proposal.php?id=' . $id;
        $copy_url = SITE_URL . '/proposal/?id=' . $id;
        if ($status === 'draft') {
            $html .= '<a class="act-btn" href="' . $edit_url . '">Edit</a>';
            $html .= '<button class="act-btn danger" onclick="confirmDelete(\'proposal\',' . $id . ')">Delete</button>';
        } elseif (in_array($status, ['sent','viewed'])) {
            $html .= '<a class="act-btn" href="' . $open_url . '" target="_blank">Open</a>';
            $html .= '<button class="act-btn" onclick="copyLink(\'' . htmlspecialchars($copy_url, ENT_QUOTES) . '\')">Copy link</button>';
        } elseif (in_array($status, ['signed','accepted'])) {
            $html .= '<a class="act-btn" href="' . $open_url . '" target="_blank">Open</a>';
            $html .= '<button class="act-btn create-inv" onclick="createInvoiceFromProposal(' . $id . ')">Create Invoice</button>';
        } else {
            $html .= '<a class="act-btn" href="' . $open_url . '" target="_blank">Open</a>';
        }
    }
    return $html;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaterDash</title>
<link rel="preconnect" href="https://api.fontshare.com">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root {
    --pink:       #e04d80;
    --blush:      #faf0f0;
    --blush-dark: #f5e5e5;
    --card-rose:  #f2d0dc;
    --card-sand:  #e6d5b8;
    --card-sky:   #c4dde8;
    --ink:        #111111;
    --ink-mid:    #555555;
    --ink-light:  #999999;
    --border:     #e8e8e8;
    --white:      #ffffff;
    --sidebar-w:  220px;
    --topbar-h:   60px;
    --radius:     16px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Satoshi', sans-serif;
    background: var(--blush);
    color: var(--ink);
    min-height: 100vh;
}

/* Topbar */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    background: var(--ink);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 200;
}
.topbar-logo { font-size: 15px; font-weight: 700; color: var(--white); letter-spacing: .02em; }
.topbar-logo span { color: var(--pink); }
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.tb-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 999px;
    font-family: inherit; font-size: 13px; font-weight: 600;
    cursor: pointer; text-decoration: none; border: none;
    transition: opacity .15s;
}
.tb-btn:hover { opacity: .85; }
.tb-btn--pink  { background: var(--pink);               color: var(--white); }
.tb-btn--ghost { background: rgba(255,255,255,.1);      color: var(--white); }

/* Layout */
.layout { display: flex; margin-top: var(--topbar-h); min-height: calc(100vh - var(--topbar-h)); }

/* Sidebar */
.sidebar {
    position: fixed;
    top: var(--topbar-h); left: 0; bottom: 0;
    width: var(--sidebar-w);
    background: var(--white);
    border-right: 1px solid var(--border);
    padding: 24px 0;
    overflow-y: auto;
}
.nav-group { margin-bottom: 24px; }
.nav-label {
    font-size: 10px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--ink-light);
    padding: 0 20px; margin-bottom: 6px; display: block;
}
.nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 20px; font-size: 14px; font-weight: 500;
    color: var(--ink-mid); text-decoration: none;
    transition: background .12s, color .12s;
}
.nav-link:hover, .nav-link.active { background: var(--blush); color: var(--pink); }
.nav-icon { font-size: 15px; }

/* Main */
.main {
    margin-left: var(--sidebar-w);
    flex: 1; padding: 32px;
    max-width: 1200px;
}
.page-header { display: flex; align-items: baseline; gap: 12px; margin-bottom: 28px; }
.page-title  { font-size: 22px; font-weight: 700; }
.page-sub    { font-size: 13px; color: var(--ink-light); }

/* Stats row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card { background: var(--white); border-radius: var(--radius); overflow: hidden; }
.stat-bar  { height: 4px; }
.stat-body { padding: 18px 20px 20px; }
.stat-label {
    font-size: 11px; font-weight: 600; letter-spacing: .06em;
    text-transform: uppercase; color: var(--ink-mid); margin-bottom: 10px;
}
.stat-value { font-size: 28px; font-weight: 700; font-variant-numeric: tabular-nums; }
.stat-value--pink { color: var(--pink); }

/* Chart */
.chart-section {
    background: var(--white);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 28px;
}
.chart-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
}
.chart-title { font-size: 15px; font-weight: 700; }
.chart-legend { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.legend-item  { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: var(--ink-mid); }
.legend-dot   { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.chart-wrap   { height: 200px; position: relative; }

/* Filter tabs */
.filter-tabs { display: flex; align-items: center; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }
.filter-tab {
    padding: 6px 14px;
    border: 1.5px solid var(--border);
    border-radius: 999px;
    background: none;
    font-family: inherit; font-size: 12px; font-weight: 600;
    color: var(--ink-mid); cursor: pointer;
    transition: all .12s;
}
.filter-tab:hover  { border-color: var(--pink); color: var(--pink); }
.filter-tab.active { background: var(--ink); color: var(--white); border-color: var(--ink); }

/* Activity table */
.table-wrap { background: var(--white); border-radius: var(--radius); overflow: hidden; }
.activity-table { width: 100%; border-collapse: collapse; }
.activity-table th {
    padding: 12px 16px; text-align: left;
    font-size: 11px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--ink-light);
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.activity-table td {
    padding: 14px 16px; font-size: 13.5px;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.activity-table tr:last-child td { border-bottom: none; }
.activity-table tr.activity-row:hover td { background: var(--blush); }

.type-pill {
    display: inline-flex; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: .04em; white-space: nowrap;
}
.type-pill--invoice  { background: var(--card-sky);  color: var(--ink); }
.type-pill--proposal { background: var(--card-rose); color: var(--ink); }

.client-name { font-weight: 600; }
.client-sub  { font-size: 11px; color: var(--ink-light); margin-top: 2px; }

.ref-num {
    font-size: 12px; font-weight: 600; color: var(--ink-mid);
    font-variant-numeric: tabular-nums;
}

.date-cell  { color: var(--ink-mid); font-size: 13px; white-space: nowrap; }

.badge {
    display: inline-flex; padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: .04em; white-space: nowrap;
}
.badge--draft  { background: var(--blush-dark); color: var(--ink-mid); }
.badge--sent   { background: var(--blush);      color: var(--ink-mid); }
.badge--viewed { background: var(--card-sand);  color: var(--ink); }
.badge--paid   { background: var(--pink);        color: var(--white); }
.badge--signed { background: var(--ink);         color: var(--white); }

.amount-cell { font-variant-numeric: tabular-nums; font-weight: 600; text-align: right; white-space: nowrap; }
.actions-cell { white-space: nowrap; text-align: right; }

.act-btn {
    display: inline-flex; align-items: center;
    padding: 5px 12px; border-radius: 999px;
    font-family: inherit; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1.5px solid var(--border);
    background: none; color: var(--ink-mid); text-decoration: none;
    margin-left: 4px; transition: all .12s;
}
.act-btn:first-child { margin-left: 0; }
.act-btn:hover       { border-color: var(--pink); color: var(--pink); }
.act-btn.danger:hover { border-color: #c0392b; color: #c0392b; }
.act-btn.create-inv  { background: var(--pink); border-color: var(--pink); color: var(--white); }
.act-btn.create-inv:hover { opacity: .85; }

.empty-row td { text-align: center; padding: 32px; color: var(--ink-light); font-size: 14px; }

/* Modal */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 500;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--white); border-radius: var(--radius);
    overflow: hidden; width: 380px; max-width: 90vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.15);
}
.modal-bar         { height: 5px; }
.modal-bar--danger { background: #c0392b; }
.modal-body   { padding: 28px; }
.modal-title  { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
.modal-msg    { font-size: 14px; color: var(--ink-mid); line-height: 1.55; }
.modal-footer {
    display: flex; justify-content: flex-end;
    gap: 8px; padding: 16px 28px 24px;
}
.modal-btn {
    padding: 9px 20px; border-radius: 999px;
    font-family: inherit; font-size: 13px; font-weight: 700;
    cursor: pointer; border: none; transition: opacity .15s;
}
.modal-btn:hover        { opacity: .85; }
.modal-btn--cancel      { background: var(--blush-dark); color: var(--ink-mid); }
.modal-btn--danger      { background: #c0392b;           color: var(--white); }

/* Toast */
.toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--ink); color: var(--white);
    padding: 12px 22px; border-radius: 999px;
    font-size: 14px; font-weight: 600;
    opacity: 0; pointer-events: none;
    transition: opacity .25s, transform .25s;
    z-index: 600; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-logo">Tater<span>Dash</span></div>
    <div class="topbar-actions">
        <a class="tb-btn tb-btn--ghost" href="/taterdash-app/taterdash/new-invoice.html">+ Invoice</a>
        <a class="tb-btn tb-btn--pink"  href="/taterdash-app/admin/new-proposal.php">+ Proposal</a>
        <a class="tb-btn tb-btn--ghost" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</div>

<div class="layout">
    <nav class="sidebar">
        <div class="nav-group">
            <span class="nav-label">Create</span>
            <a class="nav-link" href="/taterdash-app/taterdash/new-invoice.html"><span class="nav-icon">🧾</span> New Invoice</a>
            <a class="nav-link" href="/taterdash-app/admin/new-proposal.php"><span class="nav-icon">📋</span> New Proposal</a>
        </div>
        <div class="nav-group">
            <span class="nav-label">Manage</span>
            <a class="nav-link active" href="/taterdash-app/admin/"><span class="nav-icon">📊</span> Dashboard</a>
        </div>
    </nav>

    <main class="main">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <span class="page-sub"><?= date('F Y') ?></span>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-bar" style="background:var(--card-sand)"></div>
                <div class="stat-body">
                    <div class="stat-label">Total Sent</div>
                    <div class="stat-value"><?= fmt_money($total_sent) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-bar" style="background:var(--pink)"></div>
                <div class="stat-body">
                    <div class="stat-label">Invoices Paid</div>
                    <div class="stat-value stat-value--pink"><?= fmt_money($invoices_paid) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-bar" style="background:var(--ink)"></div>
                <div class="stat-body">
                    <div class="stat-label">Proposals Signed</div>
                    <div class="stat-value stat-value--pink"><?= fmt_money($proposals_signed) ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-bar" style="background:var(--card-sky)"></div>
                <div class="stat-body">
                    <div class="stat-label">Outstanding</div>
                    <div class="stat-value"><?= fmt_money($outstanding) ?></div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="chart-section">
            <div class="chart-header">
                <div class="chart-title">Monthly Overview <?= $year ?></div>
                <div class="chart-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#f2d0dc"></div>Invoice Sent</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#e04d80"></div>Invoice Paid</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#e6d5b8"></div>Proposal Sent</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#191919"></div>Proposal Signed</div>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Filter + Table -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all"       onclick="filterTable('all')">All</button>
            <button class="filter-tab"        data-filter="active"    onclick="filterTable('active')">Active</button>
            <button class="filter-tab"        data-filter="invoices"  onclick="filterTable('invoices')">Invoices</button>
            <button class="filter-tab"        data-filter="proposals" onclick="filterTable('proposals')">Proposals</button>
            <button class="filter-tab"        data-filter="paid"      onclick="filterTable('paid')">Paid / Signed</button>
            <button class="filter-tab"        data-filter="drafts"    onclick="filterTable('drafts')">Drafts</button>
        </div>

        <div class="table-wrap">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Client</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th style="text-align:right">Amount</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody id="activityBody">
<?php if (empty($activity)): ?>
                    <tr class="empty-row"><td colspan="7">No activity yet — create your first invoice or proposal.</td></tr>
<?php else: foreach ($activity as $row):
    $s    = htmlspecialchars($row['status']);
    $type = $row['row_type'];
    $date = date('M j, Y', strtotime($row['created_at']));
?>
                    <tr class="activity-row" data-type="<?= $type ?>" data-status="<?= $s ?>">
                        <td><span class="type-pill type-pill--<?= $type ?>"><?= ucfirst($type) ?></span></td>
                        <td>
                            <div class="client-name"><?= htmlspecialchars($row['client_name']) ?></div>
                            <?php if ($row['sub_label'] && $row['sub_label'] !== $row['ref_num']): ?>
                            <div class="client-sub"><?= htmlspecialchars($row['sub_label']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="ref-num"><?= htmlspecialchars($row['ref_num']) ?></span></td>
                        <td><span class="date-cell"><?= $date ?></span></td>
                        <td><?= status_badge($row['status']) ?></td>
                        <td class="amount-cell"><?= fmt_money((float)$row['total']) ?></td>
                        <td class="actions-cell"><?= row_actions($row) ?></td>
                    </tr>
<?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Delete modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-bar modal-bar--danger"></div>
        <div class="modal-body">
            <div class="modal-title">Delete?</div>
            <div class="modal-msg" id="deleteModalMsg">This action cannot be undone.</div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn--cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="modal-btn modal-btn--danger" id="deleteModalConfirm">Delete</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CHART_DATA = <?= $chart_data ?>;

// Chart
(function () {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [
                { label: 'Invoice sent',    data: CHART_DATA.inv_sent,    backgroundColor: '#f2d0dc', borderRadius: 3 },
                { label: 'Invoice paid',    data: CHART_DATA.inv_paid,    backgroundColor: '#e04d80', borderRadius: 3 },
                { label: 'Proposal sent',   data: CHART_DATA.prop_sent,   backgroundColor: '#e6d5b8', borderRadius: 3 },
                { label: 'Proposal signed', data: CHART_DATA.prop_signed, backgroundColor: '#191919', borderRadius: 3 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' $' + c.parsed.y.toLocaleString() } }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { family: 'Satoshi, sans-serif', size: 11 }, color: '#aaa' }
                },
                y: {
                    grid: { color: '#f0f0f0' },
                    border: { display: false },
                    ticks: {
                        font: { family: 'Satoshi, sans-serif', size: 11 },
                        color: '#bbb',
                        callback: v => v === 0 ? '' : (v >= 1000 ? '$' + Math.round(v / 1000) + 'k' : '$' + v)
                    }
                }
            }
        }
    });
})();

// Filter
function filterTable(filter) {
    document.querySelectorAll('.activity-row').forEach(row => {
        const type   = row.dataset.type;
        const status = row.dataset.status;
        let show;
        switch (filter) {
            case 'active':    show = ['sent','viewed'].includes(status); break;
            case 'invoices':  show = type === 'invoice'; break;
            case 'proposals': show = type === 'proposal'; break;
            case 'paid':      show = ['paid','signed','accepted'].includes(status); break;
            case 'drafts':    show = status === 'draft'; break;
            default:          show = true;
        }
        row.style.display = show ? '' : 'none';
    });
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('[data-filter="' + filter + '"]').classList.add('active');
}

// Delete modal
let _delType = null, _delId = null;

function confirmDelete(type, id) {
    _delType = type;
    _delId   = id;
    document.getElementById('deleteModalMsg').textContent =
        'Are you sure you want to delete this ' + type + '? This cannot be undone.';
    document.getElementById('deleteModalConfirm').onclick = executeDelete;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}
document.getElementById('deleteModal').addEventListener('click', function (e) {
    if (e.target === this) closeDeleteModal();
});

async function executeDelete() {
    closeDeleteModal();
    const url  = _delType === 'invoice'
        ? '/taterdash-app/taterdash/update-status.php'
        : '/taterdash-app/taterdash/delete-proposal.php';
    const body = _delType === 'invoice'
        ? JSON.stringify({ invoice_id: _delId, status: '_delete' })
        : JSON.stringify({ proposal_id: _delId });
    try {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
        const d = await r.json();
        if (d.success) { showToast('Deleted.'); setTimeout(() => location.reload(), 800); }
        else           { showToast('Error: ' + (d.error || 'Delete failed.')); }
    } catch (e) { showToast('Network error.'); }
}

// Mark paid
async function markPaid(id) {
    try {
        const r = await fetch('/taterdash-app/taterdash/update-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: id, status: 'paid' })
        });
        const d = await r.json();
        if (d.success) { showToast('Marked as paid!'); setTimeout(() => location.reload(), 800); }
        else           { showToast('Error: ' + (d.error || 'Failed.')); }
    } catch (e) { showToast('Network error.'); }
}

// Copy link
function copyLink(url) {
    navigator.clipboard.writeText(url)
        .then(() => showToast('Link copied!'))
        .catch(() => showToast('Copy failed.'));
}

// Create invoice from proposal
async function createInvoiceFromProposal(proposalId) {
    try {
        const r = await fetch('/taterdash-app/taterdash/create-invoice-from-proposal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ proposal_id: proposalId })
        });
        const d = await r.json();
        if (d.success) {
            showToast('Invoice ' + d.invoice_num + ' created!');
            setTimeout(() => { window.location.href = d.edit_url; }, 900);
        } else {
            showToast('Error: ' + (d.error || 'Failed to create invoice.'));
        }
    } catch (e) { showToast('Network error.'); }
}

// Toast
let _toastTimer;
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => el.classList.remove('show'), 2800);
}
</script>
</body>
</html>
