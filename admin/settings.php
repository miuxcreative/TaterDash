<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
$username = $_SESSION['td_user'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — TaterDash</title>
<link rel="preconnect" href="https://api.fontshare.com">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
<style>
:root {
    --pink:       #e04d80;
    --blush:      #faf0f0;
    --blush-dark: #f5e5e5;
    --card-rose:  #f2d0dc;
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
body { font-family: 'Satoshi', sans-serif; background: var(--blush); color: var(--ink); min-height: 100vh; }

.topbar {
    position: fixed; top: 0; left: 0; right: 0;
    height: var(--topbar-h); background: var(--ink);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px; z-index: 200;
}
.topbar-logo { font-size: 15px; font-weight: 700; color: var(--white); letter-spacing: .02em; }
.topbar-logo span { color: var(--pink); }
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.tb-btn {
    display: inline-flex; align-items: center; padding: 8px 16px;
    border-radius: 999px; font-family: inherit; font-size: 13px; font-weight: 600;
    cursor: pointer; text-decoration: none; border: none; transition: opacity .15s;
}
.tb-btn:hover { opacity: .85; }
.tb-btn--ghost { background: rgba(255,255,255,.1); color: var(--white); }

.layout { display: flex; margin-top: var(--topbar-h); min-height: calc(100vh - var(--topbar-h)); }

.sidebar {
    position: fixed; top: var(--topbar-h); left: 0; bottom: 0;
    width: var(--sidebar-w); background: var(--white);
    border-right: 1px solid var(--border);
    padding: 24px 0 0; overflow-y: auto;
    display: flex; flex-direction: column;
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
.sidebar-spacer { flex: 1; }
.sidebar-profile {
    border-top: 1px solid var(--border);
    padding: 16px 20px;
    background: var(--white);
}
.profile-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--card-rose); color: var(--pink);
    font-size: 14px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; text-transform: uppercase;
}
.profile-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.profile-link {
    display: block; font-size: 12px; font-weight: 500;
    color: var(--ink-light); text-decoration: none;
    padding: 5px 0; transition: color .12s;
}
.profile-link:hover { color: var(--pink); }

.main { margin-left: var(--sidebar-w); flex: 1; padding: 32px; max-width: 800px; }
.page-title { font-size: 22px; font-weight: 700; margin-bottom: 28px; }

.coming-soon {
    background: var(--white); border-radius: var(--radius);
    padding: 48px 40px; text-align: center;
}
.coming-soon-icon { font-size: 36px; margin-bottom: 16px; }
.coming-soon-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
.coming-soon-sub   { font-size: 14px; color: var(--ink-light); }
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
    <nav class="sidebar">
        <div class="nav-group">
            <span class="nav-label">Create</span>
            <a class="nav-link" href="/taterdash-app/taterdash/new-invoice.html"><span class="nav-icon">🧾</span> New Invoice</a>
            <a class="nav-link" href="/taterdash-app/admin/new-proposal.php"><span class="nav-icon">📋</span> New Proposal</a>
        </div>
        <div class="nav-group">
            <span class="nav-label">Manage</span>
            <a class="nav-link" href="/taterdash-app/admin/"><span class="nav-icon">📊</span> Dashboard</a>
        </div>
        <div class="sidebar-spacer"></div>
        <div class="sidebar-profile">
            <div class="profile-row">
                <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($username, 0, 1))) ?></div>
                <div class="profile-name"><?= htmlspecialchars($username) ?></div>
            </div>
            <a class="profile-link active" href="/taterdash-app/admin/settings.php">⚙ Settings</a>
        </div>
    </nav>

    <main class="main">
        <h1 class="page-title">Settings</h1>
        <div class="coming-soon">
            <div class="coming-soon-icon">⚙️</div>
            <div class="coming-soon-title">Coming soon</div>
            <div class="coming-soon-sub">Settings will live here — account, branding, notifications.</div>
        </div>
    </main>
</div>

</body>
</html>
