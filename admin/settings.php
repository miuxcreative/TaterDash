<?php
session_start();
if (empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/taterdash/login.php');
    exit;
}
require_once __DIR__ . '/../taterdash/config.php';
$pdo  = db_connect();
$user = $_SESSION['td_user'];
$settings = get_settings($pdo);
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

.settings-wrap { max-width: 640px; display: flex; flex-direction: column; gap: 20px; }
.settings-card {
    background: #ffffff; border: 1px solid #e8e8e8;
    border-radius: 12px; padding: 32px;
}
.settings-card-title { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
.settings-card-sub   { font-size: 13px; color: #6b6b6b; margin-bottom: 24px; }
.field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 16px; }
.field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.field-group.triple { grid-template-columns: 1fr 1fr 1fr; }
.field-group .field { margin-bottom: 0; }
.field label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6b6b6b;
}
.field input, .field textarea {
    font-family: inherit; font-size: 13.5px; color: #191919;
    background: #fff; border: 1px solid #e8e8e8; border-radius: 8px;
    padding: 9px 12px; outline: none; transition: border-color .15s; width: 100%;
}
.field input:focus, .field textarea:focus { border-color: #e04d80; }
.field textarea { resize: vertical; min-height: 64px; }
.field-hint { font-size: 11px; color: #b0b0b0; }
.save-btn {
    font-family: inherit; font-size: 12px; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    background: #e04d80; color: #fff; border: none;
    border-radius: 999px; padding: 11px 24px; cursor: pointer; transition: opacity .15s;
}
.save-btn:hover { opacity: .9; }
.save-btn-sticky { align-self: flex-start; }
.toast {
    position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px);
    background: #111111; color: #ffffff; padding: 12px 22px; border-radius: 999px;
    font-size: 14px; font-weight: 600; opacity: 0; pointer-events: none;
    transition: opacity .25s, transform .25s; z-index: 600; white-space: nowrap;
}
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
    <div class="settings-wrap">

        <div class="settings-card">
            <div class="settings-card-title">Company Info</div>
            <div class="settings-card-sub">Shown on invoices, proposals, and the signed-proposal PDF.</div>

            <div class="field">
                <label>Company Name</label>
                <input type="text" id="company_name" value="<?= htmlspecialchars($settings['company_name']) ?>">
            </div>
            <div class="field">
                <label>Company Email</label>
                <input type="email" id="company_email" value="<?= htmlspecialchars($settings['company_email']) ?>">
            </div>
            <div class="field">
                <label>Company Address</label>
                <textarea id="company_address" rows="3"><?= htmlspecialchars($settings['company_address']) ?></textarea>
                <div class="field-hint">Used for "Bill From" and check-payment instructions on invoices.</div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-title">Media Kit Stats</div>
            <div class="settings-card-sub">Shown in the stats bar on every proposal.</div>

            <div class="field-group">
                <div class="field">
                    <label>Followers</label>
                    <input type="text" id="stat_followers" value="<?= htmlspecialchars($settings['stat_followers']) ?>">
                </div>
                <div class="field">
                    <label>Monthly Impressions</label>
                    <input type="text" id="stat_impressions" value="<?= htmlspecialchars($settings['stat_impressions']) ?>">
                </div>
            </div>
            <div class="field-group">
                <div class="field">
                    <label>Audience Age %</label>
                    <input type="text" id="stat_audience_age" value="<?= htmlspecialchars($settings['stat_audience_age']) ?>">
                </div>
                <div class="field">
                    <label>Audience Age Label</label>
                    <input type="text" id="stat_audience_age_label" value="<?= htmlspecialchars($settings['stat_audience_age_label']) ?>">
                </div>
            </div>
            <div class="field-group triple">
                <div class="field">
                    <label>Women %</label>
                    <input type="text" id="stat_audience_women" value="<?= htmlspecialchars($settings['stat_audience_women']) ?>">
                </div>
                <div class="field">
                    <label>Men %</label>
                    <input type="text" id="stat_audience_men" value="<?= htmlspecialchars($settings['stat_audience_men']) ?>">
                </div>
                <div class="field">
                    <label>Partnerships</label>
                    <input type="text" id="stat_partnerships" value="<?= htmlspecialchars($settings['stat_partnerships']) ?>">
                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-title">Contact &amp; Brand</div>
            <div class="settings-card-sub">Public-facing contact info and bio.</div>

            <div class="field-group">
                <div class="field">
                    <label>Contact Email</label>
                    <input type="email" id="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>">
                </div>
                <div class="field">
                    <label>Instagram Handle</label>
                    <input type="text" id="instagram_handle" value="<?= htmlspecialchars($settings['instagram_handle']) ?>">
                </div>
            </div>
            <div class="field">
                <label>About Blurb</label>
                <textarea id="about_blurb" rows="4"><?= htmlspecialchars($settings['about_blurb']) ?></textarea>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-title">Documents</div>
            <div class="settings-card-sub">Defaults applied when creating new invoices and proposals.</div>

            <div class="field-group triple">
                <div class="field">
                    <label>Payment Terms (days)</label>
                    <input type="number" id="payment_terms_days" min="0" value="<?= htmlspecialchars($settings['payment_terms_days']) ?>">
                </div>
                <div class="field">
                    <label>Proposal Validity (days)</label>
                    <input type="number" id="proposal_validity_days" min="0" value="<?= htmlspecialchars($settings['proposal_validity_days']) ?>">
                </div>
                <div class="field">
                    <label>Deposit %</label>
                    <input type="number" id="deposit_percent" min="0" max="100" value="<?= htmlspecialchars($settings['deposit_percent']) ?>">
                </div>
            </div>
        </div>

        <button class="save-btn save-btn-sticky" id="saveBtn" onclick="saveSettings()">Save Changes</button>
    </div>
</div>
</div>

<div class="toast" id="toast"></div>

<script>
const SETTINGS_FIELDS = [
    'company_name', 'company_email', 'company_address',
    'stat_followers', 'stat_impressions', 'stat_audience_age', 'stat_audience_age_label',
    'stat_audience_women', 'stat_audience_men', 'stat_partnerships',
    'contact_email', 'instagram_handle', 'about_blurb',
    'payment_terms_days', 'proposal_validity_days', 'deposit_percent',
];

async function saveSettings() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    try {
        const payload = {};
        SETTINGS_FIELDS.forEach(key => {
            payload[key] = document.getElementById(key).value.trim();
        });
        const d = await fetch('/taterdash-app/taterdash/save-settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(r => r.json());
        if (d.success) showToast('Saved!');
        else showToast('Error: ' + (d.error || 'Failed.'));
    } catch (e) {
        showToast('Network error.');
    }
    btn.disabled = false;
    btn.textContent = 'Save Changes';
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
