<?php
// ═══════════════════════════════════════════
// TaterDash — Shared Admin Mobile Layer
//
// Included by admin/partials/topbar.php, so every admin page picks it up
// without touching eight duplicated stylesheets.
//
// Admin CSS is copy-pasted per page (each page declares its own .sidebar/.nav
// and .main rules with the same 240px / 280px values). Rather than add a
// breakpoint to all of them, this file overrides the layout once, below 900px:
//   - the fixed sidebar becomes an off-canvas drawer behind a hamburger
//   - .main / .layout lose their left margin and collapse to one column
//   - multi-column grids stack
//   - wide tables get a real horizontal scroll container instead of clipping
//
// Loaded after each page's own stylesheet, so plain overrides win on source order;
// !important is used only where a page rule is more specific.
// ═══════════════════════════════════════════
?>
<style>
/* ── Hamburger + backdrop: hidden until the drawer breakpoint ── */
.td-nav-toggle {
    display: none;
    width: 36px; height: 36px; margin-right: 12px;
    border: 1px solid #e8e8e8; border-radius: 8px; background: #fff;
    align-items: center; justify-content: center;
    cursor: pointer; color: #444; font-family: inherit; padding: 0;
    flex-shrink: 0;
}
.td-nav-toggle:hover { background: #f5f5f5; }
.td-nav-toggle i { font-size: 18px; }
.td-nav-backdrop { display: none; }

@media (max-width: 900px) {

    /* ── Off-canvas drawer ───────────────────── */
    .sidebar, .nav {
        width: 264px !important;
        transform: translateX(-100%);
        transition: transform .24s ease;
        z-index: 500 !important;
    }
    body.td-nav-open .sidebar,
    body.td-nav-open .nav { transform: translateX(0); }

    .td-nav-backdrop {
        display: block;
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 450;
        opacity: 0; pointer-events: none;
        transition: opacity .24s ease;
    }
    body.td-nav-open .td-nav-backdrop { opacity: 1; pointer-events: auto; }
    body.td-nav-open { overflow: hidden; }

    .td-nav-toggle { display: inline-flex; }

    /* ── Reclaim the sidebar gutter ───────────── */
    .topbar { left: 0 !important; padding: 0 14px !important; }
    .topbar-title { font-size: 16px; }
    .topbar-actions { gap: 6px; }
    .help-btn { display: none; }          /* guided tour is desktop-only */
    .tb-ghost { padding: 7px 8px; font-size: 11px; }

    .main { margin-left: 0 !important; }
    .main-inner { padding: 20px 16px !important; }

    /* Form pages (.layout) scroll as one page instead of two inner panes */
    .layout {
        margin-left: 0 !important;
        grid-template-columns: 1fr !important;
        height: auto !important;
        overflow: visible !important;
    }
    .form-panel, .preview-panel {
        overflow-y: visible !important;
        height: auto !important;
        padding: 24px 16px !important;
    }
    .preview-panel { border-left: none !important; border-top: 1px solid #e8e8e8; }

    /* ── Stack multi-column grids ─────────────── */
    .stats-row   { grid-template-columns: repeat(2,1fr) !important; gap: 12px !important; }
    .panel-stats { grid-template-columns: 1fr !important; }
    .field-group,
    .field-group.single,
    .field-group.triple { grid-template-columns: 1fr !important; }
    .pkg-grid    { grid-template-columns: 1fr !important; }
    .p-parties   { grid-template-columns: 1fr !important; }
    /* description full width, qty + price share the row below it */
    .line-item-row { grid-template-columns: 1fr 1fr !important; }
    .line-item-row > :first-child { grid-column: 1 / -1; }

    /* ── Tables: scroll instead of clip ───────── */
    .td-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .table-wrap { overflow: visible !important; }
    .td-table-scroll > table { min-width: 560px; }

    /* Wide dropdowns would otherwise hang off the right edge */
    .bell-dropdown { width: calc(100vw - 28px); right: -6px; }

    /* ── Touch targets ───────────────────────── */
    .btn, .btn-primary, .btn-ghost, button, .nav-item { min-height: 40px; }
    /* iOS zooms the whole page when a focused input is under 16px. Pages set
       this via `.field input` (0,1,1), so a bare `input` selector loses. */
    input, select, textarea,
    .field input, .field select, .field textarea { font-size: 16px; }
}
</style>

<div class="td-nav-backdrop" id="tdNavBackdrop"></div>

<script>
// The topbar partial is included near the top of <body>, so page content
// (tables in particular) has not been parsed yet — defer until it has.
(function () {
    function init() {
    var body = document.body;

    function closeNav() { body.classList.remove('td-nav-open'); }

    var toggle   = document.getElementById('tdNavToggle');
    var backdrop = document.getElementById('tdNavBackdrop');

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            body.classList.toggle('td-nav-open');
        });
    }
    if (backdrop) backdrop.addEventListener('click', closeNav);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNav();
    });

    // Tapping a nav link should close the drawer as the new page loads.
    var drawer = document.querySelector('.sidebar, .nav');
    if (drawer) {
        drawer.addEventListener('click', function (e) {
            if (e.target.closest('a')) closeNav();
        });
    }

    // Any table not already in a scroll container gets one, so wide tables
    // scroll horizontally instead of being clipped by an overflow:hidden card.
    document.querySelectorAll('table').forEach(function (t) {
        if (t.closest('.td-table-scroll')) return;
        var wrap = document.createElement('div');
        wrap.className = 'td-table-scroll';
        t.parentNode.insertBefore(wrap, t);
        wrap.appendChild(t);
    });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
