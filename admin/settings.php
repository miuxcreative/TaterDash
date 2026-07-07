<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo  = db_connect();
$user = $_SESSION['td_user'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
<title>Settings — TaterDash</title>
<link rel="preconnect" href="https://api.fontshare.com">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Satoshi', sans-serif; background: #f5f5f5; color: #191919; -webkit-font-smoothing: antialiased; }

.sidebar {
    position: fixed; top: 0; left: 0; bottom: 0; width: 240px;
    background: #111111; display: flex; flex-direction: column; overflow-y: auto; z-index: 300;
}
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
.main-inner { padding: 32px; }
.page-title { font-size: 28px; font-weight: 300; color: #191919; margin-bottom: 28px; }

.coming-soon {
    background: #ffffff; border: 1px solid #e8e8e8;
    border-radius: 12px; padding: 56px 40px; text-align: center;
}
.coming-soon-icon  { font-size: 32px; margin-bottom: 16px; }
.coming-soon-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
.coming-soon-sub   { font-size: 14px; color: #6b6b6b; }
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
    <a class="nav-item" href="/taterdash-app/admin/errors.php"><i class="ti ti-alert-triangle"></i> Errors</a>
    <div class="nav-spacer"></div>
    <hr class="nav-divider">
    <div class="nav-profile">
        <div class="nav-profile-row">
            <div class="nav-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($user,0,1))) ?></div>
            <div class="nav-uname"><?= htmlspecialchars($user) ?></div>
        </div>
        <a class="nav-profile-link active" href="/taterdash-app/admin/settings.php">⚙ Settings</a>
        <a class="nav-profile-link" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</nav>

<?php $topbar_title = 'Settings'; include __DIR__ . '/partials/topbar.php'; ?>

<div class="main">
<div class="main-inner">
    <h1 class="page-title">Settings</h1>
    <div class="coming-soon">
        <div class="coming-soon-icon">⚙️</div>
        <div class="coming-soon-title">Coming soon</div>
        <div class="coming-soon-sub">Settings will live here — account, branding, notifications.</div>
    </div>
</div>
</div>

</body>
</html>
