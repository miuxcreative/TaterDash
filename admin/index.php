<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';

$pdo  = db_connect();
$year = (int) date('Y');
$user = $_SESSION['td_user'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_sent = (float) $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM (
        SELECT total FROM td_invoices  WHERE status!='draft' AND YEAR(created_at)=$year
        UNION ALL
        SELECT total FROM td_proposals WHERE status!='draft' AND YEAR(created_at)=$year
    ) t")->fetchColumn();

$invoices_paid = (float) $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM td_invoices
    WHERE status='paid' AND YEAR(created_at)=$year")->fetchColumn();

$proposals_signed = (float) $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM td_proposals
    WHERE status IN ('signed','accepted') AND YEAR(created_at)=$year")->fetchColumn();

$outstanding = (float) $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM (
        SELECT total FROM td_invoices  WHERE status IN ('sent','viewed') AND YEAR(created_at)=$year
        UNION ALL
        SELECT total FROM td_proposals WHERE status IN ('sent','viewed') AND YEAR(created_at)=$year
    ) t")->fetchColumn();

// ── Monthly chart ─────────────────────────────────────────────────────────────
$inv_sent = $inv_paid = $prop_sent = $prop_signed = array_fill(0, 12, 0);

foreach ($pdo->query("SELECT MONTH(created_at) m, status, SUM(total) total FROM td_invoices WHERE YEAR(created_at)=$year GROUP BY m,status")->fetchAll() as $r) {
    $i = (int)$r['m']-1;
    if ($r['status']!=='draft')  { $inv_sent[$i] += (float)$r['total']; }
    if ($r['status']==='paid')   { $inv_paid[$i] += (float)$r['total']; }
}
foreach ($pdo->query("SELECT MONTH(created_at) m, status, SUM(total) total FROM td_proposals WHERE YEAR(created_at)=$year GROUP BY m,status")->fetchAll() as $r) {
    $i = (int)$r['m']-1;
    if ($r['status']!=='draft')                      { $prop_sent[$i]   += (float)$r['total']; }
    if (in_array($r['status'],['signed','accepted'])) { $prop_signed[$i] += (float)$r['total']; }
}
$chart_data = json_encode(compact('inv_sent','inv_paid','prop_sent','prop_signed'));

// ── Bell notifications ────────────────────────────────────────────────────────
$unread_count = 0;
$bell_notifications = [];
try {
    $unread_count       = (int) $pdo->query("SELECT COUNT(*) FROM td_activity WHERE is_read=0")->fetchColumn();
    $bell_notifications = $pdo->query("SELECT * FROM td_activity ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── Activity ──────────────────────────────────────────────────────────────────
$activity = $pdo->query("
    SELECT 'invoice' row_type, id, client_name, invoice_num ref_num,
           invoice_num sub_label, created_at, status, total
    FROM td_invoices
    UNION ALL
    SELECT 'proposal' row_type, id, client_name, proposal_num ref_num,
           COALESCE(campaign_name,'') sub_label, created_at, status, total
    FROM td_proposals
    ORDER BY created_at DESC")->fetchAll();

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt($n): string { return '$'.number_format((float)$n, 0); }

function status_badge(string $s): string {
    $map = [
        'draft'    => ['#f5e5e5','#b0b0b0'],
        'sent'     => ['#faf0f0','#6b6b6b'],
        'viewed'   => ['#f2d0dc','#191919'],
        'paid'     => ['#e04d80','#ffffff'],
        'signed'   => ['#e04d80','#ffffff'],
        'accepted' => ['#e04d80','#ffffff'],
        'declined' => ['#f5e5e5','#b0b0b0'],
    ];
    [$bg,$color] = $map[$s] ?? ['#f5e5e5','#b0b0b0'];
    $label = ucfirst($s==='accepted'?'signed':$s);
    return "<span class=\"badge\" style=\"background:$bg;color:$color\">$label</span>";
}

function bell_chip(string $event): string {
    $map = [
        'created'       => ['#f0f0f0','#555','Created'],
        'sent'          => ['#faf0f0','#6b6b6b','Sent'],
        'viewed'        => ['#f2d0dc','#191919','Viewed'],
        'paid'          => ['#e04d80','#fff','Paid'],
        'signed'        => ['#e04d80','#fff','Signed'],
        'deleted'       => ['#f5e5e5','#b0b0b0','Deleted'],
        'from_proposal' => ['#c4dde8','#191919','Created'],
    ];
    [$bg,$col,$label] = $map[$event] ?? ['#f0f0f0','#555',ucfirst($event)];
    return "<span style=\"display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:$bg;color:$col;margin-right:4px\">$label</span>";
}

function bell_sentence(array $n): string {
    $et  = $n['entity_type'];
    $num = htmlspecialchars($n['entity_num']);
    $nm  = htmlspecialchars($n['entity_name']);
    switch ($n['event_type']) {
        case 'created':       return ucfirst($et)." $num created";
        case 'sent':          return ucfirst($et)." $num sent to client";
        case 'viewed':        return ucfirst($et)." $num viewed by client";
        case 'paid':          return "Invoice $num marked as paid";
        case 'signed':        return "Proposal $num signed by $nm";
        case 'deleted':       return ucfirst($et)." $num deleted";
        case 'from_proposal': return "Invoice $num created from proposal";
        default:              return ucfirst($et)." $num";
    }
}

function bell_time(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff/60).' min ago';
    if ($diff < 86400) return floor($diff/3600).' hr ago';
    if ($diff < 172800) return 'Yesterday, '.date('g:i A', strtotime($dt));
    return date('M j, g:i A', strtotime($dt));
}

function row_actions(array $row): string {
    $type  = $row['row_type'];
    $id    = (int)$row['id'];
    $st    = $row['status'];
    $dd    = '';

    if ($type === 'invoice') {
        $open = '/invoice/?id='.$id;
        $edit = '/taterdash-app/admin/edit-invoice.php?id='.$id;
        $copy = htmlspecialchars(SITE_URL.'/invoice/?id='.$id, ENT_QUOTES);
        if ($st === 'draft') {
            $dd .= '<a class="dd-item" href="'.$edit.'">Edit</a>';
            $dd .= '<button class="dd-item" onclick="copyLink(\''.$copy.'\',\'invoice\','.$id.',\'draft\')">Copy link</button>';
            $dd .= '<button class="dd-item dd-pink dd-last" onclick="confirmDelete(\'invoice\','.$id.')">Delete</button>';
        } elseif (in_array($st,['sent','viewed'])) {
            $dd .= '<a class="dd-item" href="'.$open.'" target="_blank">Open invoice</a>';
            $dd .= '<button class="dd-item" onclick="copyLink(\''.$copy.'\',\'invoice\','.$id.',\''.$st.'\')">Copy link</button>';
            $dd .= '<button class="dd-item" onclick="markPaid('.$id.')">Mark as paid</button>';
            $dd .= '<a class="dd-item dd-last" href="'.$edit.'">Edit</a>';
        } else {
            $dd .= '<a class="dd-item dd-last" href="'.$open.'" target="_blank">Open invoice</a>';
        }
    } else {
        $open = '/proposal/?id='.$id;
        $edit = '/taterdash-app/admin/edit-proposal.php?id='.$id;
        $copy = htmlspecialchars(SITE_URL.'/proposal/?id='.$id, ENT_QUOTES);
        if ($st === 'draft') {
            $dd .= '<a class="dd-item" href="'.$edit.'">Edit</a>';
            $dd .= '<button class="dd-item" onclick="copyLink(\''.$copy.'\',\'proposal\','.$id.',\'draft\')">Copy link</button>';
            $dd .= '<button class="dd-item dd-pink dd-last" onclick="confirmDelete(\'proposal\','.$id.')">Delete</button>';
        } elseif (in_array($st,['sent','viewed'])) {
            $dd .= '<a class="dd-item" href="'.$open.'" target="_blank">Open proposal</a>';
            $dd .= '<button class="dd-item" onclick="copyLink(\''.$copy.'\',\'proposal\','.$id.',\''.$st.'\')">Copy link</button>';
            $dd .= '<a class="dd-item dd-last" href="'.$edit.'">Edit</a>';
        } elseif (in_array($st,['signed','accepted'])) {
            $dd .= '<a class="dd-item" href="'.$open.'" target="_blank">Open proposal</a>';
            $dd .= '<button class="dd-item dd-pink dd-last" onclick="createInvoiceFromProposal('.$id.')">Create invoice</button>';
        } else {
            $dd .= '<a class="dd-item dd-last" href="'.$open.'" target="_blank">Open proposal</a>';
        }
    }
    return '<div class="action-wrap"><button class="three-dot" onclick="toggleDropdown(event,this)">⋯</button><div class="dropdown-menu">'.$dd.'</div></div>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TaterDash — Dashboard</title>
<link rel="preconnect" href="https://api.fontshare.com">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/introjs.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/intro.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Satoshi', sans-serif;
    background: #f5f5f5;
    color: #191919;
    -webkit-font-smoothing: antialiased;
}

/* ── Sidebar ─────────────────────────────────── */
.sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: 240px;
    background: #111111;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 300;
}
.sidebar-brand {
    padding: 24px 20px 16px;
    flex-shrink: 0;
}
.sidebar-logo {
    height: 40px;
    width: auto;
    display: block;
    margin-bottom: 12px;
}
.sidebar-name { font-size: 15px; font-weight: 700; color: #ffffff; }
.sidebar-sub  { font-size: 9px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: #e04d80; margin-top: 3px; }

.nav-label {
    font-size: 9px; font-weight: 500; letter-spacing: 0.16em;
    text-transform: uppercase; color: #6b6b6b;
    padding: 0 20px; margin-top: 24px;
    display: block; margin-bottom: 4px;
}
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 20px; font-size: 13px; font-weight: 400;
    color: rgba(255,255,255,0.6); text-decoration: none;
    border-left: 2px solid transparent;
    transition: background .12s, color .12s;
}
.nav-item:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.9); }
.nav-item.active { color: #ffffff; border-left-color: #e04d80; background: rgba(255,255,255,0.05); }
.nav-item i { font-size: 16px; }

.sidebar-spacer { flex: 1; }
.sidebar-divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 12px 0 0; }
.sidebar-profile { padding: 14px 20px 22px; }
.sidebar-profile-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: #f2d0dc; color: #e04d80;
    font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    text-transform: uppercase; flex-shrink: 0;
}
.profile-name  { font-size: 13px; font-weight: 500; color: #ffffff; }
.profile-link  { display: block; font-size: 12px; color: #6b6b6b; text-decoration: none; padding: 4px 0; transition: color .12s; }
.profile-link:hover { color: rgba(255,255,255,0.6); }

/* ── Topbar ──────────────────────────────────── */
.topbar {
    position: fixed;
    top: 0; left: 240px; right: 0;
    height: 52px;
    background: #ffffff;
    border-bottom: 1px solid #e8e8e8;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 32px;
    z-index: 200;
}
.topbar-title { font-size: 18px; font-weight: 700; color: #191919; }
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.help-btn {
    padding: 7px 16px; border-radius: 999px; border: none;
    background: #f2d0dc; color: #191919;
    font-family: inherit; font-size: 9px; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    cursor: pointer; transition: opacity .15s;
}
.help-btn:hover { opacity: .8; }
.tb-ghost {
    font-size: 12px; font-weight: 500; color: #6b6b6b;
    text-decoration: none; padding: 7px 12px;
    border-radius: 999px; transition: background .12s;
}
.tb-ghost:hover { background: #f5f5f5; }

/* ── Main ────────────────────────────────────── */
.main {
    margin-left: 240px;
    padding: 52px 0 0;
}
.main-inner { padding: 32px; }

/* ── Dash header ─────────────────────────────── */
.dash-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px;
}
.dash-title { font-size: 28px; font-weight: 300; color: #191919; }
.dash-logo  { height: 52px; width: auto; }

/* ── Stats ───────────────────────────────────── */
.stats-row {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 16px; margin-bottom: 24px;
}
.stat-card {
    background: #faf0f0; border-radius: 12px; padding: 20px 24px;
}
.stat-label {
    font-size: 10px; font-weight: 600; letter-spacing: 0.14em;
    text-transform: uppercase; color: #b0b0b0; margin-bottom: 8px;
}
.stat-value { font-size: 28px; font-weight: 700; color: #191919; font-variant-numeric: tabular-nums; }
.stat-value--pink { color: #e04d80; }
.stat-sub   { font-size: 12px; color: #6b6b6b; margin-top: 4px; }

/* ── Chart section ───────────────────────────── */
.chart-section {
    background: #ffffff; border: 1px solid #e8e8e8;
    border-radius: 12px; padding: 24px; margin-bottom: 24px;
}
.chart-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
}
.chart-title  { font-size: 14px; font-weight: 700; }
.chart-legend { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.legend-item  { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b6b6b; }
.legend-dot   { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.chart-wrap   { height: 200px; position: relative; }

/* ── Filter tabs ─────────────────────────────── */
.filter-tabs { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; }
.filter-sub  { display: none; align-items: center; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }
.filter-sub.visible { display: flex; }
.filter-tab, .filter-sub-tab {
    padding: 6px 16px; border-radius: 999px; border: 1px solid #e8e8e8;
    background: #ffffff; font-family: inherit; font-size: 12px; font-weight: 600;
    color: #6b6b6b; cursor: pointer; transition: all .12s;
}
.filter-tab:hover, .filter-sub-tab:hover { border-color: #191919; color: #191919; }
.filter-tab.active    { background: #111111; color: #ffffff; border-color: #111111; }
.filter-sub-tab.active { background: #e04d80; color: #ffffff; border-color: #e04d80; }

/* ── Activity table ──────────────────────────── */
.table-wrap {
    background: #ffffff; border: 1px solid #e8e8e8;
    border-radius: 12px; overflow: visible;
}
.activity-table { width: 100%; border-collapse: collapse; }
.activity-table th {
    padding: 12px 16px; text-align: left;
    font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: #b0b0b0;
    border-bottom: 1px solid #e8e8e8; white-space: nowrap;
}
.activity-table td {
    padding: 14px 16px; font-size: 13px;
    border-bottom: 1px solid #f0f0f0; vertical-align: middle;
}
.activity-table tr:last-child td { border-bottom: none; }
.activity-table tr.activity-row:hover td { background: #fafafa; }

/* type pill */
.type-pill {
    display: inline-flex; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600; letter-spacing: 0.04em; white-space: nowrap;
}
.type-pill--invoice  { background: #c4dde8; color: #191919; }
.type-pill--proposal { background: #f2d0dc; color: #191919; }

/* client */
.client-name { font-weight: 600; color: #191919; }
.client-sub  { font-size: 11px; color: #b0b0b0; margin-top: 2px; }

/* ref num */
.ref-num { font-size: 12px; font-weight: 600; color: #6b6b6b; font-variant-numeric: tabular-nums; }

/* date */
.date-cell { color: #6b6b6b; font-size: 13px; white-space: nowrap; }

/* badge */
.badge {
    display: inline-flex; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}

/* amount */
.amount-cell { font-variant-numeric: tabular-nums; font-weight: 600; text-align: right; white-space: nowrap; color: #191919; }
.amount-cell.amount-pink { color: #e04d80; }

/* three-dot dropdown */
.actions-cell { text-align: right; white-space: nowrap; position: relative; }
.action-wrap  { position: relative; display: inline-block; }
.three-dot {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1px solid #e8e8e8; background: #ffffff;
    font-size: 18px; line-height: 1; color: #6b6b6b;
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    transition: background .12s, border-color .12s;
}
.three-dot:hover { background: #f5f5f5; border-color: #d0d0d0; }
.dropdown-menu {
    display: none; position: absolute; right: 0; top: 36px;
    background: #ffffff; border: 1px solid #e8e8e8; border-radius: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08); min-width: 160px; z-index: 100;
    overflow: hidden;
}
.dropdown-menu.open { display: block; }
.dd-item {
    display: block; width: 100%; padding: 10px 16px;
    font-family: inherit; font-size: 13px; font-weight: 400; color: #191919;
    border: none; background: none; text-align: left; text-decoration: none;
    border-bottom: 1px solid #f5e5e5; cursor: pointer; transition: background .1s;
}
.dd-item:hover    { background: #faf0f0; }
.dd-item.dd-pink  { color: #e04d80; }
.dd-item.dd-last  { border-bottom: none; }

.empty-row td { text-align: center; padding: 40px; color: #b0b0b0; font-size: 14px; }

/* ── Modal ───────────────────────────────────── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.4); z-index: 500;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
    background: #ffffff; border-radius: 16px; overflow: hidden;
    width: 380px; max-width: 90vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.15);
}
.modal-bar         { height: 4px; background: #e04d80; }
.modal-body        { padding: 28px; }
.modal-title       { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
.modal-msg         { font-size: 14px; color: #6b6b6b; line-height: 1.55; }
.modal-footer      { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 28px 24px; }
.modal-btn {
    padding: 9px 20px; border-radius: 999px;
    font-family: inherit; font-size: 13px; font-weight: 700;
    cursor: pointer; border: none; transition: opacity .15s;
}
.modal-btn:hover        { opacity: .85; }
.modal-btn--cancel { background: #f5e5e5; color: #6b6b6b; }
.modal-btn--danger { background: #e04d80; color: #ffffff; }

/* ── Toast ───────────────────────────────────── */
.toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #111111; color: #ffffff;
    padding: 12px 22px; border-radius: 999px;
    font-size: 14px; font-weight: 600;
    opacity: 0; pointer-events: none;
    transition: opacity .25s, transform .25s; z-index: 600; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ── Bell ────────────────────────────────────── */
.bell-wrap { position: relative; }
.bell-btn {
    width: 36px; height: 36px; border-radius: 8px;
    border: 1px solid #e8e8e8; background: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: #444; transition: background .12s; position: relative;
}
.bell-btn:hover { background: #f5f5f5; }
.bell-btn i { font-size: 16px; }
.bell-badge-dot {
    position: absolute; top: -4px; right: -4px;
    min-width: 16px; height: 16px; padding: 0 4px;
    background: #e04d80; color: #fff; border-radius: 999px;
    font-size: 9px; font-weight: 700; border: 2px solid #fff;
    display: flex; align-items: center; justify-content: center; line-height: 1;
}
.bell-dropdown {
    display: none; position: absolute; top: 44px; right: 0;
    width: 320px; background: #fff; border: 1px solid #e8e8e8;
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.1);
    z-index: 250; overflow: hidden;
}
.bell-dropdown.open { display: block; }
.bell-dd-hdr { padding: 14px 16px 0; display: flex; align-items: center; justify-content: space-between; }
.bell-dd-title { font-size: 13px; font-weight: 700; color: #111; }
.bell-mark-all { font-size: 11px; color: #e04d80; background: none; border: none; cursor: pointer; font-family: inherit; font-weight: 500; padding: 0; }
.bell-tabs { display: flex; padding: 10px 16px 0; border-bottom: 1px solid #f0f0f0; }
.bell-tab-btn { font-size: 12px; font-weight: 500; color: #9b9b9b; padding: 6px 12px 8px; cursor: pointer; border: none; border-bottom: 2px solid transparent; background: none; font-family: inherit; }
.bell-tab-btn.active { color: #111; border-bottom-color: #e04d80; font-weight: 700; }
.bell-items { max-height: 300px; overflow-y: auto; }
.bell-item { display: flex; gap: 10px; padding: 11px 16px; border-bottom: 1px solid #f5f5f5; transition: background .1s; }
.bell-item:hover { background: #faf0f0; }
.bell-item.unread { background: #fdf4f7; }
.bell-item.unread:hover { background: #f8e8ef; }
.bell-dot { width: 7px; height: 7px; border-radius: 50%; background: #e04d80; margin-top: 5px; flex-shrink: 0; }
.bell-dot.read { background: transparent; }
.bell-item-body { flex: 1; min-width: 0; }
.bell-item-text { font-size: 12px; color: #333; line-height: 1.45; margin-bottom: 3px; }
.bell-item-time { font-size: 11px; color: #9b9b9b; }
.bell-dd-footer { padding: 10px 16px; text-align: center; border-top: 1px solid #f0f0f0; }
.bell-dd-footer a { font-size: 12px; color: #e04d80; text-decoration: none; font-weight: 500; }

/* ── Intro.js overrides ──────────────────────── */
.introjs-tooltip { font-family: 'Satoshi', sans-serif !important; border-radius: 12px !important; }
.introjs-button  { font-family: 'Satoshi', sans-serif !important; border-radius: 999px !important; text-shadow: none !important; }
.introjs-nextbutton, .introjs-donebutton { background: #e04d80 !important; border-color: #e04d80 !important; color: #fff !important; }
.introjs-progressbar { background: #e04d80 !important; }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-brand">
        <img class="sidebar-logo" src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png" alt="" onerror="this.style.display='none'">
        <div class="sidebar-name">TaterDash</div>
        <div class="sidebar-sub">MallowFrenchie</div>
    </div>

    <span class="nav-label">Create</span>
    <a class="nav-item" href="/taterdash-app/taterdash/new-invoice.html"
       data-intro="Create a new invoice — fill in client details, add deliverables, and get a link to send." data-step="1">
        <i class="ti ti-file-invoice"></i> New Invoice
    </a>
    <a class="nav-item" href="/taterdash-app/admin/new-proposal.php"
       data-intro="Send a proposal first — client reviews the package and signs directly from the link." data-step="2">
        <i class="ti ti-file-text"></i> New Proposal
    </a>

    <span class="nav-label">Manage</span>
    <a class="nav-item active" href="/taterdash-app/admin/">
        <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a class="nav-item" href="/taterdash-app/admin/?view=all">
        <i class="ti ti-list"></i> All Activity
    </a>
    <a class="nav-item" href="/taterdash-app/admin/?view=clients">
        <i class="ti ti-users"></i> Clients
    </a>

    <div class="sidebar-spacer"></div>
    <hr class="sidebar-divider">
    <div class="sidebar-profile">
        <div class="sidebar-profile-row">
            <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($user, 0, 1))) ?></div>
            <div class="profile-name"><?= htmlspecialchars($user) ?></div>
        </div>
        <a class="profile-link" href="/taterdash-app/admin/settings.php">⚙ Settings</a>
        <a class="profile-link" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</nav>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-actions">
        <button class="help-btn" onclick="startTour()">? Help</button>

        <div class="bell-wrap" id="bellWrap">
            <button class="bell-btn" onclick="toggleBell(event)" id="bellBtn">
                <i class="ti ti-bell"></i>
                <span class="bell-badge-dot" id="bellBadge" style="<?= $unread_count > 0 ? '' : 'display:none' ?>">
                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                </span>
            </button>
            <div class="bell-dropdown" id="bellDropdown">
                <div class="bell-dd-hdr">
                    <div class="bell-dd-title">Notifications</div>
                    <button class="bell-mark-all" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="bell-tabs">
                    <button class="bell-tab-btn active" onclick="filterBell('all',this)">All</button>
                    <button class="bell-tab-btn" onclick="filterBell('unread',this)">Unread<?php if ($unread_count > 0): ?> <span style="background:#e04d80;color:#fff;font-size:9px;padding:1px 5px;border-radius:99px;margin-left:2px"><?= $unread_count ?></span><?php endif; ?></button>
                </div>
                <div class="bell-items" id="bellItems">
                    <?php if (empty($bell_notifications)): ?>
                    <div style="padding:24px;text-align:center;font-size:13px;color:#9b9b9b;">No activity yet</div>
                    <?php else: foreach ($bell_notifications as $n):
                        $isUnread = !(bool)$n['is_read'];
                    ?>
                    <div class="bell-item <?= $isUnread ? 'unread' : '' ?>" data-unread="<?= $isUnread ? '1' : '0' ?>">
                        <div class="bell-dot <?= $isUnread ? '' : 'read' ?>"></div>
                        <div class="bell-item-body">
                            <div class="bell-item-text"><?= bell_chip($n['event_type']) ?><?= bell_sentence($n) ?></div>
                            <div class="bell-item-time"><?= bell_time($n['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="bell-dd-footer">
                    <a href="/taterdash-app/admin/?view=all">View all activity →</a>
                </div>
            </div>
        </div>

        <a class="tb-ghost" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</div>

<!-- Main -->
<div class="main">
<div class="main-inner">

    <!-- Dash header -->
    <div class="dash-header">
        <h1 class="dash-title">Dashboard</h1>
        <img class="dash-logo" src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png" alt="" onerror="this.style.display='none'">
    </div>

    <!-- Stats -->
    <div class="stats-row"
         data-intro="Your year at a glance — updates automatically as invoices are paid and proposals are signed." data-step="3">
        <div class="stat-card">
            <div class="stat-label">Total Sent</div>
            <div class="stat-value"><?= fmt($total_sent) ?></div>
            <div class="stat-sub"><?= $year ?> total</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Invoices Paid</div>
            <div class="stat-value stat-value--pink"><?= fmt($invoices_paid) ?></div>
            <div class="stat-sub">Collected</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Proposals Signed</div>
            <div class="stat-value stat-value--pink"><?= fmt($proposals_signed) ?></div>
            <div class="stat-sub">Deals closed</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Outstanding</div>
            <div class="stat-value"><?= fmt($outstanding) ?></div>
            <div class="stat-sub">Awaiting payment</div>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-section"
         data-intro="Monthly breakdown — faint colors are sent, solid colors are paid or signed." data-step="4">
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

    <!-- Table header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <div></div>
        <button onclick="location.reload()" style="display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;border:1px solid #e8e8e8;background:#fff;font-family:inherit;font-size:12px;font-weight:600;color:#6b6b6b;cursor:pointer;transition:all .12s" onmouseover="this.style.borderColor='#191919';this.style.color='#191919'" onmouseout="this.style.borderColor='#e8e8e8';this.style.color='#6b6b6b'"><i class="ti ti-refresh" style="font-size:14px"></i> Refresh</button>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs"
         data-intro="Use filters to focus — pick Invoices or Proposals for a sub-filter by status." data-step="5">
        <button class="filter-tab active" data-cat="all"       onclick="setCat('all')">All</button>
        <button class="filter-tab"        data-cat="invoices"  onclick="setCat('invoices')">Invoices</button>
        <button class="filter-tab"        data-cat="proposals" onclick="setCat('proposals')">Proposals</button>
        <button class="filter-tab"        data-cat="drafts"    onclick="setCat('drafts')">Drafts</button>
    </div>
    <div class="filter-sub" id="subTabs">
        <button class="filter-sub-tab active" data-sub="all"    onclick="setSub('all')">All</button>
        <button class="filter-sub-tab"        data-sub="active" onclick="setSub('active')">Active</button>
        <span id="subPaid"  ><button class="filter-sub-tab" data-sub="paid"   onclick="setSub('paid')">Paid</button></span>
        <span id="subSigned"><button class="filter-sub-tab" data-sub="signed" onclick="setSub('signed')">Signed</button></span>
    </div>

    <!-- Table -->
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
<?php else: foreach ($activity as $i => $row):
    $s    = $row['status'];
    $type = $row['row_type'];
    $date = date('M j, Y', strtotime($row['created_at']));
    $amtCls = in_array($s, ['paid','signed','accepted']) ? 'amount-cell amount-pink' : 'amount-cell';
    $first = ($i === 0);
?>
                <tr class="activity-row" data-type="<?= $type ?>" data-status="<?= htmlspecialchars($s) ?>" data-id="<?= (int)$row['id'] ?>">
                    <td><span class="type-pill type-pill--<?= $type ?>"><?= ucfirst($type) ?></span></td>
                    <td>
                        <div class="client-name"><?= htmlspecialchars($row['client_name']) ?></div>
                        <?php if ($row['sub_label'] && $row['sub_label'] !== $row['ref_num']): ?>
                        <div class="client-sub"><?= htmlspecialchars($row['sub_label']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="ref-num"><?= htmlspecialchars($row['ref_num']) ?></span></td>
                    <td><span class="date-cell"><?= $date ?></span></td>
                    <td <?= $first ? 'data-intro="Status updates automatically — sent when you copy the link, viewed when client opens it." data-step="6"' : '' ?>>
                        <?= status_badge($s) ?>
                    </td>
                    <td class="<?= $amtCls ?>"><?= fmt((float)$row['total']) ?></td>
                    <td class="actions-cell" <?= $first ? 'data-intro="Click ⋯ for actions. Signed proposals show Create Invoice — one click pre-fills everything." data-step="7"' : '' ?>>
                        <?= row_actions($row) ?>
                    </td>
                </tr>
<?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.main-inner -->
</div><!-- /.main -->

<!-- Delete modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-bar"></div>
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
    new Chart(document.getElementById('monthlyChart').getContext('2d'), {
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
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' $' + c.parsed.y.toLocaleString() } }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false },
                     ticks: { font: { family: 'Satoshi, sans-serif', size: 11 }, color: '#aaa' } },
                y: { grid: { color: '#f0f0f0' }, border: { display: false },
                     ticks: { font: { family: 'Satoshi, sans-serif', size: 11 }, color: '#bbb',
                              callback: v => v === 0 ? '' : (v >= 1000 ? '$' + Math.round(v/1000) + 'k' : '$' + v) } }
            }
        }
    });
})();

// Two-level filter
let _cat = 'all', _sub = 'all';

function applyFilter() {
    document.querySelectorAll('.activity-row').forEach(row => {
        const t = row.dataset.type, s = row.dataset.status;
        let show = true;
        // Category
        if (_cat === 'invoices')  show = t === 'invoice';
        else if (_cat === 'proposals') show = t === 'proposal';
        else if (_cat === 'drafts')    show = s === 'draft';
        // Sub-filter (only when a category is selected)
        if (show && _cat !== 'all' && _cat !== 'drafts') {
            if (_sub === 'active') show = ['sent','viewed'].includes(s);
            else if (_sub === 'paid')   show = s === 'paid';
            else if (_sub === 'signed') show = ['signed','accepted'].includes(s);
        }
        row.style.display = show ? '' : 'none';
    });
}

function setCat(cat) {
    _cat = cat; _sub = 'all';
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('[data-cat="' + cat + '"]').classList.add('active');
    // Show/hide sub row and its paid vs signed button
    const sub = document.getElementById('subTabs');
    if (cat === 'invoices' || cat === 'proposals') {
        sub.classList.add('visible');
        document.getElementById('subPaid').style.display   = cat === 'invoices'  ? '' : 'none';
        document.getElementById('subSigned').style.display = cat === 'proposals' ? '' : 'none';
    } else {
        sub.classList.remove('visible');
    }
    // Reset sub-tab active state
    document.querySelectorAll('.filter-sub-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('[data-sub="all"]').classList.add('active');
    applyFilter();
}

function setSub(sub) {
    _sub = sub;
    document.querySelectorAll('.filter-sub-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('[data-sub="' + sub + '"]').classList.add('active');
    applyFilter();
}

setCat('all');

// Dropdown
function toggleDropdown(e, btn) {
    e.stopPropagation();
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('open');
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    if (!isOpen) menu.classList.add('open');
}
document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
});

// Delete
let _delType = null, _delId = null;
function confirmDelete(type, id) {
    _delType = type; _delId = id;
    document.getElementById('deleteModalMsg').textContent =
        'Are you sure you want to delete this ' + type + '? This cannot be undone.';
    document.getElementById('deleteModalConfirm').onclick = executeDelete;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }
document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target===this) closeDeleteModal(); });

async function executeDelete() {
    closeDeleteModal();
    const url  = _delType==='invoice' ? '/taterdash-app/taterdash/update-status.php' : '/taterdash-app/taterdash/delete-proposal.php';
    const body = _delType==='invoice' ? JSON.stringify({invoice_id:_delId,status:'_delete'}) : JSON.stringify({proposal_id:_delId});
    try {
        const d = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body}).then(r=>r.json());
        if (d.success) { showToast('Deleted.'); setTimeout(()=>location.reload(),800); }
        else showToast('Error: '+(d.error||'Delete failed.'));
    } catch(e) { showToast('Network error.'); }
}

// Mark paid
async function markPaid(id) {
    try {
        const d = await fetch('/taterdash-app/taterdash/update-status.php',{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({invoice_id:id,status:'paid'})
        }).then(r=>r.json());
        if (d.success) { showToast('Marked as paid!'); setTimeout(()=>location.reload(),800); }
        else showToast('Error: '+(d.error||'Failed.'));
    } catch(e) { showToast('Network error.'); }
}

// Copy link — also marks as sent if currently draft
async function copyLink(url, type, id, currentStatus) {
    try {
        await navigator.clipboard.writeText(url);
        showToast('Link copied!');
    } catch(e) {
        showToast('Copy failed.');
        return;
    }
    if (currentStatus === 'draft') {
        try {
            const body = type === 'invoice'
                ? {invoice_id: id, status: 'sent'}
                : {type: 'proposal', id: id, status: 'sent'};
            const d = await fetch('/taterdash-app/taterdash/update-status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            }).then(r => r.json());
            if (d.success) updateRowBadge(type, id, 'sent');
        } catch(e) {}
    }
}

function updateRowBadge(type, id, status) {
    const row = document.querySelector(`.activity-row[data-type="${type}"][data-id="${id}"]`);
    if (!row) return;
    row.dataset.status = status;
    const colors = {
        draft:    ['#f5e5e5','#b0b0b0'],
        sent:     ['#faf0f0','#6b6b6b'],
        viewed:   ['#f2d0dc','#191919'],
        paid:     ['#e04d80','#ffffff'],
        signed:   ['#e04d80','#ffffff'],
        accepted: ['#e04d80','#ffffff'],
    };
    const [bg, color] = colors[status] || ['#f5e5e5','#b0b0b0'];
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    const badge = row.querySelector('.badge');
    if (badge) { badge.style.background = bg; badge.style.color = color; badge.textContent = label; }
}

// Create invoice from proposal
async function createInvoiceFromProposal(id) {
    try {
        const d = await fetch('/taterdash-app/taterdash/create-invoice-from-proposal.php',{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({proposal_id:id})
        }).then(r=>r.json());
        if (d.success) {
            showToast('Invoice '+d.invoice_num+' created!');
            setTimeout(()=>{ window.location.href=d.edit_url; },900);
        } else showToast('Error: '+(d.error||'Failed.'));
    } catch(e) { showToast('Network error.'); }
}

// Bell
function toggleBell(e) {
    e.stopPropagation();
    const dd = document.getElementById('bellDropdown');
    dd.classList.toggle('open');
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
}
document.addEventListener('click', (e) => {
    const wrap = document.getElementById('bellWrap');
    if (wrap && !wrap.contains(e.target)) document.getElementById('bellDropdown').classList.remove('open');
});

function filterBell(filter, btn) {
    document.querySelectorAll('.bell-tab-btn').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.bell-item').forEach(item => {
        item.style.display = (filter === 'unread' && item.dataset.unread === '0') ? 'none' : '';
    });
}

async function markAllRead() {
    try {
        await fetch('/taterdash-app/taterdash/mark-read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({all: true})
        });
        document.querySelectorAll('.bell-item').forEach(item => {
            item.classList.remove('unread'); item.dataset.unread = '0';
            const dot = item.querySelector('.bell-dot');
            if (dot) dot.classList.add('read');
        });
        const badge = document.getElementById('bellBadge');
        if (badge) badge.style.display = 'none';
    } catch(e) {}
}

// Help tour
function startTour() {
    introJs().setOptions({
        nextLabel:'Next →', prevLabel:'← Back', doneLabel:'Got it',
        showProgress:true, showBullets:false, exitOnOverlayClick:true,
    }).start();
}

// Toast
let _tt;
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg; el.classList.add('show');
    clearTimeout(_tt); _tt = setTimeout(()=>el.classList.remove('show'),2800);
}
</script>
</body>
</html>
