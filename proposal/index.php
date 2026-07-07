<?php
// ═══════════════════════════════════════════
// TaterDash — Client Proposal View
// Upload to: /public_html/proposal/index.php
// ═══════════════════════════════════════════

require_once __DIR__ . '/../taterdash-app/taterdash/config.php';
$pdo = db_connect();

$token = trim($_GET['t'] ?? '');
$id    = intval($_GET['id'] ?? 0);

// ── Fetch proposal: token is the primary lookup; id fallback is for links sent before tokens existed ──
// TODO: remove id fallback after launch
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE token = ?");
    $stmt->execute([$token]);
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE id = ?");
    $stmt->execute([$id]);
} else {
    http_response_code(404);
    die('Not found');
}
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('Proposal not found'); }
$id = $p['id'];

// Mark as viewed
if ($p['status'] === 'sent') {
    $pdo->prepare("UPDATE td_proposals SET status = 'viewed', updated_at = NOW() WHERE id = ?")->execute([$id]);
    $p['status'] = 'viewed';
}

// Check signature
$sig = null;
$sigStmt = $pdo->prepare("SELECT * FROM td_signatures WHERE proposal_id = ? ORDER BY signed_at ASC LIMIT 1");
$sigStmt->execute([$id]);
$sig = $sigStmt->fetch();

// Check expiry
$expired = $p['expiry_date'] && $p['expiry_date'] < date('Y-m-d');

// Deliverables
$deliverables = json_decode($p['deliverables'] ?? '[]', true) ?: [];

// Partners
$partner_industries = json_decode($p['partner_industries'] ?? '[]', true) ?: [];
$partners_by_industry = [];
if (!empty($partner_industries)) {
    $placeholders = implode(',', array_fill(0, count($partner_industries), '?'));
    $pStmt = $pdo->prepare("SELECT * FROM td_partners WHERE industry IN ($placeholders) AND is_active = 1 ORDER BY industry, sort_order");
    $pStmt->execute($partner_industries);
    foreach ($pStmt->fetchAll() as $partner) {
        $partners_by_industry[$partner['industry']][] = $partner;
    }
}

// Package name
$package_name = null;
if ($p['package_id']) {
    $pkgStmt = $pdo->prepare("SELECT name FROM td_packages WHERE id = ?");
    $pkgStmt->execute([$p['package_id']]);
    $pkg = $pkgStmt->fetch();
    $package_name = $pkg['name'] ?? null;
}

function he($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
function fmtD($d) {
    if (!$d) return '—';
    return date('F j, Y', strtotime($d));
}
function fmtMoney($n) { return '$' . number_format(floatval($n), 0); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Partnership Proposal — MallowFrenchie × <?= he($p['client_name']) ?></title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --pink:      #e04d80;
      --card-rose: #f2d0dc;
      --dark:      #111111;
      --blush:     #faf0f0;
      --ink:       #191919;
      --ink-mid:   #6b6b6b;
      --border:    #e8e8e8;
      --white:     #ffffff;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Satoshi', sans-serif;
      background: #f0f0f0;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
    }

    /* ── HERO ── */
    .hero {
      background: var(--dark);
      position: relative;
      overflow: hidden;
      padding: 60px 24px 52px;
      text-align: center;
    }
    .hero-bg {
      position: absolute; inset: 0;
      background: url('https://mallowfrenchie.com/images/mallow-hero.jpg') center/cover no-repeat;
      opacity: 0.18;
    }
    .hero-content { position: relative; z-index: 1; }
    .hero-logo {
      height: 64px; width: auto; margin: 0 auto 20px;
      filter: brightness(0) invert(1);
    }
    .hero-logo-text {
      font-size: 28px; font-weight: 800; color: var(--white);
      letter-spacing: -0.03em; margin-bottom: 8px;
    }
    .hero-tag {
      font-size: 11px; font-weight: 600; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--pink); margin-bottom: 28px;
    }
    .hero-divider { width: 40px; height: 2px; background: var(--pink); margin: 0 auto 24px; }
    .hero-for {
      font-size: 11px; font-weight: 600; letter-spacing: 0.14em;
      text-transform: uppercase; color: rgba(255,255,255,0.45); margin-bottom: 8px;
    }
    .hero-client {
      font-size: 30px; font-weight: 800; color: var(--white);
      letter-spacing: -0.03em; margin-bottom: 6px;
    }
    .hero-campaign {
      font-size: 14px; color: rgba(255,255,255,0.55); margin-bottom: 24px;
    }
    .hero-meta { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
    .hero-meta-item {
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
      border-radius: 999px; padding: 5px 14px;
      font-size: 11px; color: rgba(255,255,255,0.5);
    }
    .hero-meta-item strong { color: var(--white); }

    /* ── STATS BAR ── */
    .stats-bar {
      background: linear-gradient(90deg, var(--pink), #c83870);
      padding: 20px 24px;
    }
    .stats-inner {
      max-width: 720px; margin: 0 auto;
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 0; text-align: center;
    }
    .stat-item { border-right: 1px solid rgba(255,255,255,0.2); }
    .stat-item:last-child { border-right: none; }
    .stat-val { font-size: 22px; font-weight: 800; color: white; letter-spacing: -0.03em; }
    .stat-lbl { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.75); letter-spacing: 0.08em; margin-top: 2px; text-transform: uppercase; }

    /* ── FEATURED IN ── */
    .featured-bar {
      background: var(--white); border-bottom: 1px solid var(--border);
      padding: 14px 24px;
    }
    .featured-inner {
      max-width: 720px; margin: 0 auto;
      display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    }
    .featured-label {
      font-size: 9px; font-weight: 700; letter-spacing: 0.2em;
      text-transform: uppercase; color: var(--ink-mid); flex-shrink: 0;
    }
    .featured-divider { width: 1px; height: 16px; background: var(--border); }
    .featured-name {
      font-size: 13px; font-weight: 700; color: var(--ink-mid);
      letter-spacing: -0.01em; opacity: 0.6;
    }

    /* ── CONTENT ── */
    .content { max-width: 720px; margin: 0 auto; padding: 40px 24px 80px; }

    /* section cards */
    .section-card {
      background: var(--white); border-radius: 0 0 16px 16px;
      box-shadow: 0 1px 0 var(--border), 0 4px 20px rgba(0,0,0,0.05);
      margin-bottom: 20px; overflow: hidden;
    }
    .card-bar { height: 3px; background: linear-gradient(90deg, var(--pink), var(--card-rose)); }
    .card-body { padding: 24px 28px; }
    .card-title {
      font-size: 11px; font-weight: 700; letter-spacing: 0.14em;
      text-transform: uppercase; color: var(--pink); margin-bottom: 14px;
    }

    /* campaign note */
    .campaign-note {
      font-size: 15px; line-height: 1.7; color: var(--ink);
      font-weight: 400;
    }
    .campaign-note strong { font-weight: 700; }

    /* package */
    .pkg-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
    .pkg-title { font-size: 20px; font-weight: 800; color: var(--ink); letter-spacing: -0.02em; }
    .pkg-platform { font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--pink); background: #fdf0f4; padding: 4px 12px; border-radius: 999px; }
    .deliverables-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
    .deliverable-item { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: var(--ink); line-height: 1.5; }
    .del-check { width: 18px; height: 18px; background: var(--pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; color: white; flex-shrink: 0; margin-top: 2px; }
    .campaign-dates { display: flex; gap: 24px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); flex-wrap: wrap; }
    .date-item { font-size: 12px; color: var(--ink-mid); }
    .date-item strong { display: block; font-size: 13px; color: var(--ink); font-weight: 700; }

    /* investment */
    .investment-amount {
      font-size: 44px; font-weight: 800; color: var(--ink);
      letter-spacing: -0.04em; margin-bottom: 6px;
    }
    .investment-sub { font-size: 13px; color: var(--ink-mid); }

    /* partners */
    .industry-section { margin-bottom: 24px; }
    .industry-section:last-child { margin-bottom: 0; }
    .industry-label {
      font-size: 9px; font-weight: 700; letter-spacing: 0.16em;
      text-transform: uppercase; color: var(--ink-mid);
      margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
    }
    .logo-grid { display: flex; flex-wrap: wrap; gap: 12px; }
    .logo-item {
      background: var(--blush); border: 1px solid var(--border);
      border-radius: 10px; padding: 12px 18px;
      display: flex; align-items: center; justify-content: center;
      min-width: 90px; min-height: 50px;
    }
    .logo-item img { max-height: 28px; max-width: 80px; object-fit: contain; filter: grayscale(1); opacity: 0.7; }
    .logo-item .logo-name { font-size: 11px; font-weight: 700; color: var(--ink-mid); }

    /* terms */
    .terms-list { display: flex; flex-direction: column; gap: 8px; }
    .term-item { font-size: 13px; color: var(--ink-mid); line-height: 1.55; padding-left: 14px; position: relative; }
    .term-item::before { content: "·"; position: absolute; left: 0; color: var(--pink); font-weight: 900; }

    /* expiry notice */
    .expiry-notice {
      background: #fef3c7; border: 1px solid #fcd34d; border-radius: 10px;
      padding: 12px 16px; margin-bottom: 20px;
      font-size: 13px; color: #92400e;
    }

    /* ── SIGNATURE ── */
    .sig-card {
      background: var(--white); border-radius: 0 0 20px 20px;
      box-shadow: 0 1px 0 var(--border), 0 8px 40px rgba(0,0,0,0.08);
      overflow: hidden; margin-bottom: 40px;
    }
    .sig-bar { height: 3px; background: linear-gradient(90deg, var(--pink), var(--card-rose)); }
    .sig-body { padding: 32px 28px; }
    .sig-title { font-size: 20px; font-weight: 800; color: var(--ink); letter-spacing: -0.02em; margin-bottom: 6px; }
    .sig-sub { font-size: 13px; color: var(--ink-mid); margin-bottom: 24px; line-height: 1.5; }
    .sig-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
    .sig-label { font-size: 10px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-mid); }
    .sig-input {
      font-family: 'Satoshi', sans-serif; font-size: 15px; font-weight: 600;
      color: var(--ink); background: var(--blush);
      border: 1.5px solid var(--border); border-radius: 10px;
      padding: 12px 16px; outline: none; width: 100%;
      transition: border-color 0.15s;
    }
    .sig-input:focus { border-color: var(--pink); background: white; }
    .sig-agree { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 22px; cursor: pointer; }
    .sig-agree input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--pink); flex-shrink: 0; margin-top: 2px; cursor: pointer; }
    .sig-agree-text { font-size: 13px; color: var(--ink-mid); line-height: 1.5; }
    .sig-btn {
      font-family: 'Satoshi', sans-serif; font-size: 13px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
      background: var(--pink); color: white; border: none;
      border-radius: 999px; padding: 14px 32px; cursor: pointer;
      width: 100%; transition: background 0.15s;
    }
    .sig-btn:hover { background: #c83870; }
    .sig-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .sig-error { display: none; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; font-size: 13px; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
    .sig-error.show { display: block; }

    /* signed state */
    .signed-state { text-align: center; padding: 32px 28px; }
    .signed-icon { font-size: 36px; margin-bottom: 12px; }
    .signed-title { font-size: 20px; font-weight: 800; color: #166534; letter-spacing: -0.02em; margin-bottom: 6px; }
    .signed-sub { font-size: 13px; color: var(--ink-mid); margin-bottom: 24px; line-height: 1.5; }
    .signed-meta { background: var(--blush); border-radius: 10px; padding: 14px 18px; font-size: 12px; color: var(--ink-mid); margin-bottom: 20px; text-align: left; }
    .signed-meta strong { color: var(--ink); }
    .pdf-btn {
      font-family: 'Satoshi', sans-serif; font-size: 12px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
      background: var(--dark); color: white; border: none;
      border-radius: 999px; padding: 12px 28px; cursor: pointer;
      transition: background 0.15s;
    }
    .pdf-btn:hover { background: #333; }

    /* expired state */
    .expired-card { background: var(--white); border-radius: 16px; padding: 40px 28px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 40px; }
    .expired-title { font-size: 20px; font-weight: 800; color: var(--ink); margin-bottom: 8px; }
    .expired-sub { font-size: 14px; color: var(--ink-mid); }

    /* ── FOOTER ── */
    .footer { background: var(--dark); padding: 24px; text-align: center; }
    .footer span { font-size: 11px; color: rgba(255,255,255,0.25); }

    /* ── PRINT ── */
    @media print {
      .sig-card, .sig-btn, .pdf-btn { display: none !important; }
      .hero-bg { display: none; }
      body { background: white; }
      .section-card { box-shadow: none; border: 1px solid var(--border); }
    }
  </style>
</head>
<body>

<!-- ── HERO ── -->
<div class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <img class="hero-logo"
      src="https://mallowfrenchie.com/images/MallowFrenchieLogoImage.png"
      alt="MallowFrenchie"
      onerror="this.style.display='none'">
    <div class="hero-logo-text">MallowFrenchie</div>
    <div class="hero-tag">South Florida's Most Influential French Bulldog 🐾</div>
    <div class="hero-divider"></div>
    <div class="hero-for">Partnership Proposal for</div>
    <div class="hero-client"><?= he($p['client_name']) ?></div>
    <?php if ($p['campaign_name']): ?>
    <div class="hero-campaign"><?= he($p['campaign_name']) ?></div>
    <?php endif; ?>
    <div class="hero-meta">
      <span class="hero-meta-item"><strong><?= he($p['proposal_num']) ?></strong></span>
      <span class="hero-meta-item">Prepared <?= fmtD($p['issue_date']) ?></span>
      <?php if ($p['expiry_date']): ?>
      <span class="hero-meta-item">Expires <?= fmtD($p['expiry_date']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── STATS BAR ── -->
<div class="stats-bar">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-val">67.8K</div>
      <div class="stat-lbl">Followers</div>
    </div>
    <div class="stat-item">
      <div class="stat-val">130K+</div>
      <div class="stat-lbl">Monthly Impressions</div>
    </div>
    <div class="stat-item">
      <div class="stat-val">63%</div>
      <div class="stat-lbl">Female Audience</div>
    </div>
    <div class="stat-item">
      <div class="stat-val">79%</div>
      <div class="stat-lbl">Ages 25–44</div>
    </div>
  </div>
</div>

<!-- ── FEATURED IN ── -->
<div class="featured-bar">
  <div class="featured-inner">
    <span class="featured-label">As seen in</span>
    <div class="featured-divider"></div>
    <span class="featured-name">Forbes</span>
    <span class="featured-name">New Times</span>
    <span class="featured-name">Time Out Miami</span>
  </div>
</div>

<!-- ── CONTENT ── -->
<div class="content">

  <?php if ($p['expiry_date'] && $p['expiry_date'] <= date('Y-m-d') && $p['expiry_date'] !== date('Y-m-d')): ?>
  <div class="expiry-notice">
    ⏰ This proposal expired on <?= fmtD($p['expiry_date']) ?>. Please reach out to Gina to request an updated proposal.
  </div>
  <?php endif; ?>

  <!-- Campaign Overview -->
  <?php if ($p['notes']): ?>
  <div class="section-card">
    <div class="card-bar"></div>
    <div class="card-body">
      <div class="card-title">Campaign Overview</div>
      <div class="campaign-note"><?= nl2br(he($p['notes'])) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Package + Deliverables -->
  <div class="section-card">
    <div class="card-bar"></div>
    <div class="card-body">
      <div class="card-title">What's Included</div>
      <div class="pkg-header">
        <div class="pkg-title"><?= he($package_name ?? 'Custom Package') ?></div>
        <?php if ($p['platform']): ?>
        <span class="pkg-platform"><?= he($p['platform']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($deliverables)): ?>
      <ul class="deliverables-list">
        <?php foreach ($deliverables as $d): ?>
        <li class="deliverable-item">
          <span class="del-check">✓</span>
          <span><?= he($d) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($p['campaign_start'] || $p['campaign_end']): ?>
      <div class="campaign-dates">
        <?php if ($p['campaign_start']): ?>
        <div class="date-item">Campaign Start<strong><?= fmtD($p['campaign_start']) ?></strong></div>
        <?php endif; ?>
        <?php if ($p['campaign_end']): ?>
        <div class="date-item">Campaign End<strong><?= fmtD($p['campaign_end']) ?></strong></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Investment -->
  <div class="section-card">
    <div class="card-bar"></div>
    <div class="card-body">
      <div class="card-title">Investment</div>
      <div class="investment-amount"><?= fmtMoney($p['total']) ?></div>
      <div class="investment-sub">Total partnership investment · 50% deposit due upon signing</div>
    </div>
  </div>

  <!-- Previous Partnerships -->
  <?php if (!empty($partners_by_industry)): ?>
  <div class="section-card">
    <div class="card-bar"></div>
    <div class="card-body">
      <div class="card-title">Previous Partnerships</div>
      <?php foreach ($partners_by_industry as $industry => $logos): ?>
      <div class="industry-section">
        <div class="industry-label"><?= he($industry) ?></div>
        <div class="logo-grid">
          <?php foreach ($logos as $logo): ?>
          <div class="logo-item">
            <img src="<?= he($logo['logo_url']) ?>" alt="<?= he($logo['name']) ?>"
              onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span class="logo-name" style="display:none"><?= he($logo['name']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Terms -->
  <div class="section-card">
    <div class="card-bar"></div>
    <div class="card-body">
      <div class="card-title">Terms</div>
      <div class="terms-list">
        <div class="term-item">Content will be delivered within 7 business days of the campaign start date unless otherwise agreed upon.</div>
        <div class="term-item">Client receives unlimited usage rights for all content created during this partnership.</div>
        <div class="term-item">One round of revisions is included. Additional revisions billed at $150/hr.</div>
        <div class="term-item">A 50% deposit is required to confirm the partnership. Remaining balance due upon content delivery.</div>
        <div class="term-item">Payment accepted via bank transfer or credit card. Net 7 payment terms.</div>
        <div class="term-item">Content approval window: client has 48 hours to review and approve. Silence = approval.</div>
        <div class="term-item">MallowFrenchie retains the right to repost and archive all co-created content.</div>
      </div>
    </div>
  </div>

  <!-- Signature Block -->
  <?php if ($expired && !$sig): ?>
  <div class="expired-card">
    <div style="font-size:28px;margin-bottom:12px;">⏰</div>
    <div class="expired-title">This proposal has expired</div>
    <div class="expired-sub">Please contact Gina at <a href="mailto:mallowfrenchie@gmail.com" style="color:var(--pink);">mallowfrenchie@gmail.com</a> to request an updated proposal.</div>
  </div>

  <?php elseif ($sig): ?>
  <div class="sig-card">
    <div class="sig-bar"></div>
    <div class="signed-state">
      <div class="signed-icon">✅</div>
      <div class="signed-title">Proposal Accepted</div>
      <div class="signed-sub">Thank you, <?= he($sig['signer_name']) ?>! We're excited to work together on this campaign.</div>
      <div class="signed-meta">
        Signed by <strong><?= he($sig['signer_name']) ?></strong>
        on <strong><?= date('F j, Y \a\t g:i A', strtotime($sig['signed_at'])) ?></strong>
      </div>
      <button class="pdf-btn" onclick="window.print()">Download PDF</button>
    </div>
  </div>

  <?php else: ?>
  <div class="sig-card" id="sig-block">
    <div class="sig-bar"></div>
    <div class="sig-body">
      <div class="sig-title">Accept this Proposal</div>
      <div class="sig-sub">By signing below you confirm your acceptance of this proposal and agree to the terms outlined above.</div>
      <div class="sig-error" id="sig-error"></div>
      <div class="sig-field">
        <label class="sig-label">Your Full Name *</label>
        <input class="sig-input" type="text" id="signer-name" placeholder="Jane Smith" autocomplete="name">
      </div>
      <label class="sig-agree">
        <input type="checkbox" id="sig-agree-check">
        <span class="sig-agree-text">I have read and agree to the terms of this proposal. I understand that a 50% deposit is required to confirm this partnership.</span>
      </label>
      <button class="sig-btn" id="sig-btn" onclick="signProposal()">Accept &amp; Sign</button>
    </div>
  </div>
  <?php endif; ?>

</div>

<div class="footer">
  <span>MallowFrenchie · G Space Agency LLC · Palmetto Bay, FL · @mallowfrenchie</span>
</div>

<script>
  const PROPOSAL_ID = <?= intval($id) ?>;

  async function signProposal() {
    const name    = document.getElementById('signer-name').value.trim();
    const agreed  = document.getElementById('sig-agree-check').checked;
    const errEl   = document.getElementById('sig-error');

    errEl.classList.remove('show');

    if (!name) {
      errEl.textContent = 'Please enter your full name.';
      errEl.classList.add('show');
      return;
    }
    if (!agreed) {
      errEl.textContent = 'Please check the agreement box to continue.';
      errEl.classList.add('show');
      return;
    }

    const btn = document.getElementById('sig-btn');
    btn.disabled = true;
    btn.textContent = 'Signing...';

    try {
      const res  = await fetch('/taterdash-app/taterdash/sign-proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proposal_id: PROPOSAL_ID, signer_name: name })
      });
      const data = await res.json();

      if (data.success) {
        fire_emailjs(name, data.signed_at);
        // Replace signature block with signed state
        document.getElementById('sig-block').innerHTML = `
          <div class="sig-bar"></div>
          <div class="signed-state">
            <div class="signed-icon">✅</div>
            <div class="signed-title">Proposal Accepted</div>
            <div class="signed-sub">Thank you, ${escHtml(name)}! We're excited to work together on this campaign.</div>
            <div class="signed-meta">
              Signed by <strong>${escHtml(name)}</strong>
              on <strong>${new Date(data.signed_at).toLocaleString('en-US', {month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})}</strong>
            </div>
            <button class="pdf-btn" onclick="window.print()">Download PDF</button>
          </div>
        `;
      } else {
        errEl.textContent = data.error || 'An error occurred. Please try again.';
        errEl.classList.add('show');
        btn.disabled = false;
        btn.textContent = 'Accept & Sign';
      }
    } catch (e) {
      errEl.textContent = 'Connection error. Please try again.';
      errEl.classList.add('show');
      btn.disabled = false;
      btn.textContent = 'Accept & Sign';
    }
  }

  function fire_emailjs(signerName, signedAt) {
    // TODO: wire EmailJS when account is set up
    console.log('[EmailJS] Proposal signed by', signerName, 'at', signedAt);
    console.log('[EmailJS] Send confirmation to client + notify Gina');
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
</script>
</body>
</html>
