<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo  = db_connect();
$user = $_SESSION['td_user'];

const PAGE_SIZE = 50;

$events = $pdo->query("SELECT * FROM td_activity ORDER BY created_at DESC, id DESC LIMIT " . PAGE_SIZE)->fetchAll();
$total  = (int) $pdo->query("SELECT COUNT(*) FROM td_activity")->fetchColumn();

// Mark everything read — the user is now looking at the full feed
$pdo->exec("UPDATE td_activity SET is_read = 1 WHERE is_read = 0");

function event_chip(string $event): string {
    $map = [
        'created'       => ['#f0f0f0','#555','Created'],
        'sent'          => ['#faf0f0','#6b6b6b','Sent'],
        'viewed'        => ['#f2d0dc','#191919','Viewed'],
        'paid'          => ['#e04d80','#fff','Paid'],
        'signed'        => ['#e04d80','#fff','Signed'],
        'deleted'       => ['#f5e5e5','#b0b0b0','Deleted'],
        'from_proposal' => ['#c4dde8','#191919','Created'],
        'resent'        => ['#faf0f0','#6b6b6b','Resent'],
        'emailed'       => ['#faf0f0','#6b6b6b','Emailed'],
    ];
    [$bg,$col,$label] = $map[$event] ?? ['#f0f0f0','#555',ucfirst($event)];
    return "<span class=\"ev-chip\" style=\"background:$bg;color:$col\">$label</span>";
}

function event_sentence(array $n): string {
    $et   = $n['entity_type'];
    $num  = htmlspecialchars($n['entity_num']);
    $name = htmlspecialchars($n['entity_name']);
    $link = null;
    if ($n['event_type'] !== 'deleted') {
        $edit = $et === 'invoice' ? 'edit-invoice.php' : 'edit-proposal.php';
        $link = "/taterdash-app/admin/$edit?id=" . (int)$n['entity_id'];
    }
    $numHtml = $link ? "<a href=\"$link\">$num</a>" : $num;

    switch ($n['event_type']) {
        case 'created':       return ucfirst($et)." $numHtml created for $name";
        case 'sent':          return ucfirst($et)." $numHtml sent to $name";
        case 'viewed':        return ucfirst($et)." $numHtml viewed by $name";
        case 'paid':          return "Invoice $numHtml marked as paid";
        case 'signed':        return "Proposal $numHtml signed by $name";
        case 'deleted':       return ucfirst($et)." $num deleted";
        case 'from_proposal': return "Invoice $numHtml created from proposal for $name";
        case 'resent':        return ucfirst($et)." $numHtml resent to $name";
        case 'emailed':       return ucfirst($et)." $numHtml emailed to $name";
        default:              return ucfirst($et)." $numHtml";
    }
}

function fmt_amount($n): string { return $n === null ? '' : '$'.number_format((float)$n, 0); }
function date_label(string $dt): string {
    $d = strtotime(date('Y-m-d', strtotime($dt)));
    $today = strtotime(date('Y-m-d'));
    $yest  = strtotime(date('Y-m-d', strtotime('-1 day')));
    if ($d === $today) return 'Today';
    if ($d === $yest)  return 'Yesterday';
    return date('l, F j, Y', $d);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<title>All Activity — TaterDash</title>
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

.main { margin-left: 240px; padding: 52px 0 0; }
.main-inner { padding: 32px; max-width: 760px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-title { font-size: 28px; font-weight: 300; color: #191919; }

.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
.filter-chip {
    padding: 7px 15px; border-radius: 999px; border: 1px solid #e8e8e8;
    background: #ffffff; font-family: inherit; font-size: 12px; font-weight: 600;
    color: #6b6b6b; cursor: pointer; transition: all .12s;
}
.filter-chip:hover { border-color: #191919; color: #191919; }
.filter-chip.active { background: #111111; color: #ffffff; border-color: #111111; }

.feed-wrap { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 12px; overflow: hidden; }
.date-group { }
.date-group + .date-group { border-top: 1px solid #f0f0f0; }
.date-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    color: #b0b0b0; padding: 16px 24px 6px; background: #fafafa;
}
.ev-row {
    display: flex; align-items: flex-start; gap: 12px; padding: 12px 24px;
    border-top: 1px solid #f5f5f5;
}
.date-group .ev-row:first-of-type { border-top: none; }
.ev-avatar {
    width: 28px; height: 28px; border-radius: 50%; background: #f2d0dc; color: #e04d80;
    font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
}
.ev-chip { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-right: 6px; vertical-align: middle; }
.ev-main { flex: 1; min-width: 0; }
.ev-sentence { font-size: 13px; color: #333; line-height: 1.5; }
.ev-sentence a { color: #e04d80; text-decoration: none; font-weight: 600; }
.ev-sentence a:hover { text-decoration: underline; }
.ev-meta { font-size: 11px; color: #b0b0b0; margin-top: 3px; }
.ev-amount { font-size: 12.5px; font-weight: 700; color: #191919; font-variant-numeric: tabular-nums; flex-shrink: 0; margin-top: 2px; }
.ev-time { font-size: 11.5px; color: #b0b0b0; flex-shrink: 0; margin-top: 2px; white-space: nowrap; }

.load-more-wrap { padding: 18px 24px; text-align: center; }
.load-more-btn {
    font-family: inherit; font-size: 12.5px; font-weight: 700; color: #6b6b6b;
    background: #f5f5f5; border: none; padding: 9px 20px; border-radius: 999px; cursor: pointer;
}
.load-more-btn:hover { background: #eee; }
.load-more-btn:disabled { opacity: .5; cursor: default; }

.empty-state { text-align: center; padding: 60px 20px; color: #b0b0b0; }
.empty-state i { font-size: 32px; margin-bottom: 12px; display: block; }
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
    <a class="nav-item active" href="/taterdash-app/admin/all-activity.php"><i class="ti ti-list"></i> All Activity</a>
    <a class="nav-item" href="/taterdash-app/admin/clients.php"><i class="ti ti-users"></i> Clients</a>
    <a class="nav-item" href="/taterdash-app/admin/errors.php"><i class="ti ti-alert-triangle"></i> Errors</a>
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

<?php $topbar_title = 'All Activity'; include __DIR__ . '/partials/topbar.php'; ?>

<div class="main">
<div class="main-inner">
    <div class="page-header">
        <h1 class="page-title">All Activity</h1>
    </div>

    <?php if (empty($events)): ?>
    <div class="empty-state">
        <i class="ti ti-history"></i>
        No activity yet — it'll show up here as invoices and proposals move through their lifecycle.
    </div>
    <?php else: ?>

    <div class="filter-chips">
        <button class="filter-chip active" data-filter="all"       onclick="setFilter('all')">All</button>
        <button class="filter-chip"        data-filter="invoice"   onclick="setFilter('invoice')">Invoices</button>
        <button class="filter-chip"        data-filter="proposal"  onclick="setFilter('proposal')">Proposals</button>
        <button class="filter-chip"        data-filter="month"     onclick="setFilter('month')">This Month</button>
    </div>

    <div class="feed-wrap" id="feedWrap"></div>
    <div class="load-more-wrap" id="loadMoreWrap" style="<?= $total > PAGE_SIZE ? '' : 'display:none' ?>">
        <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()">Load older</button>
    </div>
    <?php endif; ?>
</div>
</div>

<script>
const CURRENT_MONTH = new Date().toISOString().slice(0,7); // YYYY-MM
let ALL_EVENTS = <?= json_encode($events) ?>;
let _offset = ALL_EVENTS.length;
let _total  = <?= (int)$total ?>;
let _filter = 'all';

const BADGE = {
    created:       ['#f0f0f0','#555','Created'],
    sent:          ['#faf0f0','#6b6b6b','Sent'],
    viewed:        ['#f2d0dc','#191919','Viewed'],
    paid:          ['#e04d80','#fff','Paid'],
    signed:        ['#e04d80','#fff','Signed'],
    deleted:       ['#f5e5e5','#b0b0b0','Deleted'],
    from_proposal: ['#c4dde8','#191919','Created'],
    resent:        ['#faf0f0','#6b6b6b','Resent'],
    emailed:       ['#faf0f0','#6b6b6b','Emailed'],
};

function escHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function sentence(n) {
    const et = n.entity_type, num = escHtml(n.entity_num), name = escHtml(n.entity_name);
    let link = null;
    if (n.event_type !== 'deleted') {
        const edit = et === 'invoice' ? 'edit-invoice.php' : 'edit-proposal.php';
        link = '/taterdash-app/admin/' + edit + '?id=' + n.entity_id;
    }
    const numHtml = link ? `<a href="${link}">${num}</a>` : num;
    switch (n.event_type) {
        case 'created':       return `${cap(et)} ${numHtml} created for ${name}`;
        case 'sent':          return `${cap(et)} ${numHtml} sent to ${name}`;
        case 'viewed':        return `${cap(et)} ${numHtml} viewed by ${name}`;
        case 'paid':          return `Invoice ${numHtml} marked as paid`;
        case 'signed':        return `Proposal ${numHtml} signed by ${name}`;
        case 'deleted':       return `${cap(et)} ${num} deleted`;
        case 'from_proposal': return `Invoice ${numHtml} created from proposal for ${name}`;
        case 'resent':        return `${cap(et)} ${numHtml} resent to ${name}`;
        case 'emailed':       return `${cap(et)} ${numHtml} emailed to ${name}`;
        default:              return `${cap(et)} ${numHtml}`;
    }
}
function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function fmtAmount(n) { return (n === null || n === undefined) ? '' : '$' + Math.round(n).toLocaleString('en-US'); }
function fmtTime(dt) {
    return new Date(dt.replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}
function dateKey(dt) { return dt.slice(0,10); }
function dateLabel(dt) {
    const d = new Date(dt.slice(0,10) + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    const yest  = new Date(today); yest.setDate(yest.getDate() - 1);
    if (d.getTime() === today.getTime()) return 'Today';
    if (d.getTime() === yest.getTime())  return 'Yesterday';
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function matchesFilter(ev) {
    if (_filter === 'all') return true;
    if (_filter === 'invoice' || _filter === 'proposal') return ev.entity_type === _filter;
    if (_filter === 'month') return ev.created_at.slice(0,7) === CURRENT_MONTH;
    return true;
}

function renderFeed() {
    const wrap = document.getElementById('feedWrap');
    const visible = ALL_EVENTS.filter(matchesFilter);

    if (!visible.length) {
        wrap.innerHTML = '<div style="padding:40px;text-align:center;color:#b0b0b0;font-size:13px;">No activity matches this filter.</div>';
        return;
    }

    let html = '';
    let lastKey = null;
    visible.forEach(ev => {
        const key = dateKey(ev.created_at);
        if (key !== lastKey) {
            if (lastKey !== null) html += '</div>';
            html += `<div class="date-group"><div class="date-label">${dateLabel(ev.created_at)}</div>`;
            lastKey = key;
        }
        const [bg, col, label] = BADGE[ev.event_type] || ['#f0f0f0','#555', cap(ev.event_type)];
        html += `
        <div class="ev-row">
            <div class="ev-avatar">${escHtml((ev.entity_name || '?').charAt(0))}</div>
            <div class="ev-main">
                <div class="ev-sentence"><span class="ev-chip" style="background:${bg};color:${col}">${label}</span> ${sentence(ev)}</div>
                <div class="ev-meta">${cap(ev.entity_type)}${ev.amount ? ' · ' + fmtAmount(ev.amount) : ''}</div>
            </div>
            <div class="ev-time">${fmtTime(ev.created_at)}</div>
        </div>`;
    });
    html += '</div>';
    wrap.innerHTML = html;
}

function setFilter(f) {
    _filter = f;
    document.querySelectorAll('.filter-chip').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-filter="' + f + '"]').classList.add('active');
    renderFeed();
}

async function loadMore() {
    const btn = document.getElementById('loadMoreBtn');
    btn.disabled = true;
    btn.textContent = 'Loading...';
    try {
        const d = await fetch(`/taterdash-app/taterdash/get-activity.php?offset=${_offset}&limit=50`).then(r => r.json());
        if (d.success) {
            ALL_EVENTS = ALL_EVENTS.concat(d.events);
            _offset += d.events.length;
            _total = d.total;
            renderFeed();
        }
    } catch (e) {}
    if (_offset >= _total) {
        document.getElementById('loadMoreWrap').style.display = 'none';
    } else {
        btn.disabled = false;
        btn.textContent = 'Load older';
    }
}

renderFeed();
</script>
</body>
</html>
