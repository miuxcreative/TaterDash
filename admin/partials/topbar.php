<?php
// ═══════════════════════════════════════════
// TaterDash — Shared Admin Topbar
// Source of truth: extracted verbatim from admin/index.php (Dashboard).
//
// Before including, the calling page must already have $pdo connected
// (session/auth check + db_connect() must run first). Optionally set:
//   $topbar_title          (string)  page name shown top-left, e.g. 'Clients'
//   $topbar_extra_actions  (string)  raw HTML for page-specific buttons,
//                                    rendered before the Help/Bell/Logout cluster
//   $topbar_sidebar_width  (string)  CSS length matching the page's sidebar
//                                    width, so the topbar starts in the right
//                                    place. Defaults to '240px' (Dashboard's
//                                    sidebar width). The 3-column form pages
//                                    (edit-invoice/edit-proposal/new-proposal)
//                                    use a 280px sidebar and must set this.
// ═══════════════════════════════════════════

if (!isset($pdo)) {
    http_response_code(403);
    die('Direct access not permitted.');
}

$topbar_title         = $topbar_title ?? '';
$topbar_extra_actions = $topbar_extra_actions ?? '';
$topbar_sidebar_width = $topbar_sidebar_width ?? '240px';

if (!function_exists('bell_chip')) {
    function bell_chip(string $event): string {
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
        return "<span style=\"display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:$bg;color:$col;margin-right:4px\">$label</span>";
    }
}

if (!function_exists('bell_sentence')) {
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
            case 'resent':        return ucfirst($et)." $num resent to client";
            case 'emailed':       return ucfirst($et)." $num emailed to client";
            default:              return ucfirst($et)." $num";
        }
    }
}

if (!function_exists('bell_time')) {
    function bell_time(string $dt): string {
        $diff = time() - strtotime($dt);
        if ($diff < 60)    return 'Just now';
        if ($diff < 3600)  return floor($diff/60).' min ago';
        if ($diff < 86400) return floor($diff/3600).' hr ago';
        if ($diff < 172800) return 'Yesterday, '.date('g:i A', strtotime($dt));
        return date('M j, g:i A', strtotime($dt));
    }
}

$topbar_unread_count       = 0;
$topbar_bell_notifications = [];
try {
    $topbar_unread_count       = (int) $pdo->query("SELECT COUNT(*) FROM td_activity WHERE is_read=0")->fetchColumn();
    $topbar_bell_notifications = $pdo->query("SELECT * FROM td_activity ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}
?>
<style>
.topbar {
    position: fixed;
    top: 0; left: <?= htmlspecialchars($topbar_sidebar_width) ?>; right: 0;
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
    background: none; border: none; cursor: pointer; font-family: inherit;
}
.tb-ghost:hover { background: #f5f5f5; }

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
</style>

<div class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($topbar_title) ?></div>
    <div class="topbar-actions">
        <?php if ($topbar_extra_actions !== ''): ?>
        <?= $topbar_extra_actions ?>
        <?php endif; ?>
        <button class="help-btn" onclick="startTour()">? Help</button>

        <div class="bell-wrap" id="bellWrap">
            <button class="bell-btn" onclick="toggleBell(event)" id="bellBtn">
                <i class="ti ti-bell"></i>
                <span class="bell-badge-dot" id="bellBadge" style="<?= $topbar_unread_count > 0 ? '' : 'display:none' ?>">
                    <?= $topbar_unread_count > 9 ? '9+' : $topbar_unread_count ?>
                </span>
            </button>
            <div class="bell-dropdown" id="bellDropdown">
                <div class="bell-dd-hdr">
                    <div class="bell-dd-title">Notifications</div>
                    <button class="bell-mark-all" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="bell-tabs">
                    <button class="bell-tab-btn active" onclick="filterBell('all',this)">All</button>
                    <button class="bell-tab-btn" onclick="filterBell('unread',this)">Unread<?php if ($topbar_unread_count > 0): ?> <span style="background:#e04d80;color:#fff;font-size:9px;padding:1px 5px;border-radius:99px;margin-left:2px"><?= $topbar_unread_count ?></span><?php endif; ?></button>
                </div>
                <div class="bell-items" id="bellItems">
                    <?php if (empty($topbar_bell_notifications)): ?>
                    <div style="padding:24px;text-align:center;font-size:13px;color:#9b9b9b;">No activity yet</div>
                    <?php else: foreach ($topbar_bell_notifications as $n):
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
                    <a href="/taterdash-app/admin/all-activity.php">View all activity →</a>
                </div>
            </div>
        </div>

        <a class="tb-ghost" href="/taterdash-app/taterdash/logout.php">Logout</a>
    </div>
</div>

<script>
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

// Help tour — only Dashboard currently defines a guided tour (data-step attrs +
// Intro.js loaded in its <head>). No-op gracefully on pages without one.
function startTour() {
    if (typeof introJs === 'undefined' || !document.querySelector('[data-step]')) return;
    introJs().setOptions({
        nextLabel:'Next →', prevLabel:'← Back', doneLabel:'Got it',
        showProgress:true, showBullets:false, exitOnOverlayClick:true,
    }).start();
}
</script>
