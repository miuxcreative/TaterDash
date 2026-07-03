<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo  = db_connect();
$user = $_SESSION['td_user'];

$errors = $pdo->query("SELECT * FROM td_error_log ORDER BY created_at DESC LIMIT 200")->fetchAll();
$unresolved_count = (int) $pdo->query("SELECT COUNT(*) FROM td_error_log WHERE is_resolved = 0")->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Errors — TaterDash</title>
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
.nav-item-badge { margin-left: auto; background: #e04d80; color: #fff; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 999px; }
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
.tb-ghost { font-size: 12px; font-weight: 500; color: #6b6b6b; text-decoration: none; padding: 7px 12px; border-radius: 999px; transition: background .12s; background: none; border: none; cursor: pointer; font-family: inherit; }
.tb-ghost:hover { background: #f5f5f5; }

.main { margin-left: 240px; padding: 52px 0 0; }
.main-inner { padding: 32px; max-width: 860px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-title { font-size: 28px; font-weight: 300; color: #191919; }

.err-list { display: flex; flex-direction: column; gap: 10px; }
.err-card { background: #ffffff; border: 1px solid #e8e8e8; border-radius: 12px; padding: 16px 18px; }
.err-card.resolved { opacity: .55; }
.err-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.err-context { font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; background: #f5e5e5; color: #b0b0b0; padding: 2px 9px; border-radius: 999px; display: inline-block; margin-bottom: 6px; }
.err-context.unresolved { background: #fdf0f4; color: #e04d80; }
.err-message { font-size: 13.5px; color: #191919; font-weight: 600; line-height: 1.4; }
.err-time { font-size: 11.5px; color: #b0b0b0; white-space: nowrap; flex-shrink: 0; }
.err-data { font-size: 11.5px; color: #6b6b6b; font-family: 'Menlo', monospace; background: #f9f9f9; border-radius: 6px; padding: 8px 10px; margin-top: 8px; white-space: pre-wrap; word-break: break-word; max-height: 100px; overflow-y: auto; }
.err-resolve-btn { font-family: inherit; font-size: 11.5px; font-weight: 700; color: #6b6b6b; background: #f5f5f5; border: none; padding: 6px 14px; border-radius: 999px; cursor: pointer; margin-top: 10px; }
.err-resolve-btn:hover { background: #eee; }

.empty-state { text-align: center; padding: 60px 20px; color: #b0b0b0; }
.empty-state i { font-size: 32px; margin-bottom: 12px; display: block; }
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
    <a class="nav-item" href="/taterdash-app/admin/all-activity.php"><i class="ti ti-list"></i> All Activity</a>
    <a class="nav-item" href="/taterdash-app/admin/clients.php"><i class="ti ti-users"></i> Clients</a>
    <a class="nav-item active" href="/taterdash-app/admin/errors.php">
        <i class="ti ti-alert-triangle"></i> Errors
        <?php if ($unresolved_count > 0): ?><span class="nav-item-badge"><?= $unresolved_count ?></span><?php endif; ?>
    </a>
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
    <div class="topbar-title">Errors</div>
    <div class="topbar-actions">
        <?php if ($unresolved_count > 0): ?>
        <button class="tb-ghost" onclick="resolveAll()">Mark all resolved</button>
        <?php endif; ?>
        <a class="tb-ghost" href="/taterdash-app/admin/">← Dashboard</a>
    </div>
</div>

<div class="main">
<div class="main-inner">
    <div class="page-header">
        <h1 class="page-title">Errors</h1>
    </div>

    <?php if (empty($errors)): ?>
    <div class="empty-state">
        <i class="ti ti-shield-check"></i>
        No errors logged — nice.
    </div>
    <?php else: ?>
    <div class="err-list">
        <?php foreach ($errors as $e): ?>
        <div class="err-card <?= $e['is_resolved'] ? 'resolved' : '' ?>" id="err-<?= $e['id'] ?>">
            <div class="err-top">
                <div>
                    <span class="err-context <?= $e['is_resolved'] ? '' : 'unresolved' ?>"><?= htmlspecialchars($e['context']) ?></span>
                    <div class="err-message"><?= htmlspecialchars($e['message']) ?></div>
                </div>
                <div class="err-time"><?= date('M j, Y g:i A', strtotime($e['created_at'])) ?></div>
            </div>
            <?php if ($e['request_data'] && $e['request_data'] !== 'null' && $e['request_data'] !== '[]'): ?>
            <div class="err-data"><?= htmlspecialchars($e['request_data']) ?></div>
            <?php endif; ?>
            <?php if (!$e['is_resolved']): ?>
            <button class="err-resolve-btn" onclick="resolveOne(<?= $e['id'] ?>)">Mark resolved</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<div class="toast" id="toast"></div>

<script>
async function resolveOne(id) {
    try {
        const d = await fetch('/taterdash-app/taterdash/mark-error-resolved.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id })
        }).then(r => r.json());
        if (d.success) {
            document.getElementById('err-' + id).classList.add('resolved');
            document.querySelector('#err-' + id + ' .err-context').classList.remove('unresolved');
            document.querySelector('#err-' + id + ' .err-resolve-btn')?.remove();
            showToast('Marked resolved.');
        }
    } catch (e) { showToast('Network error.'); }
}

async function resolveAll() {
    try {
        const d = await fetch('/taterdash-app/taterdash/mark-error-resolved.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ all: true })
        }).then(r => r.json());
        if (d.success) { showToast('All marked resolved.'); setTimeout(() => location.reload(), 700); }
    } catch (e) { showToast('Network error.'); }
}

let _tt;
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg; el.classList.add('show');
    clearTimeout(_tt); _tt = setTimeout(() => el.classList.remove('show'), 2600);
}
</script>
</body>
</html>
