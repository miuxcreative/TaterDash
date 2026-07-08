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
$sigStmt = $pdo->prepare("SELECT * FROM td_signatures WHERE proposal_id = ? ORDER BY signed_at ASC LIMIT 1");
$sigStmt->execute([$id]);
$sig = $sigStmt->fetch();

// Check expiry
$expired = $p['expiry_date'] && $p['expiry_date'] < date('Y-m-d');

// Deliverables
$deliverables = json_decode($p['deliverables'] ?? '[]', true) ?: [];

// Package name
$package_name = null;
if ($p['package_id']) {
    $pkgStmt = $pdo->prepare("SELECT name FROM td_packages WHERE id = ?");
    $pkgStmt->execute([$p['package_id']]);
    $pkg = $pkgStmt->fetch();
    $package_name = $pkg['name'] ?? null;
}

$settings = get_settings($pdo);

// ── Image slots — picks a random photo from proposal/images/ each load.
// Naming/pairing is TODO; for now every photo is fair game for either slot.
function random_proposal_image(): ?string {
    $dir = __DIR__ . '/images';
    if (!is_dir($dir)) return null;
    $files = array_merge(
        glob($dir . '/*.jpg') ?: [],
        glob($dir . '/*.jpeg') ?: [],
        glob($dir . '/*.png') ?: [],
        glob($dir . '/*.webp') ?: []
    );
    if (empty($files)) return null;
    return 'images/' . basename($files[array_rand($files)]);
}

$IMG_HERO_PHOTO  = random_proposal_image() ?? 'https://miuxcreative.github.io/mallowfrenchie/images/proposal-hero.jpg';
$IMG_ABOUT_PHOTO = random_proposal_image() ?? 'https://miuxcreative.github.io/mallowfrenchie/images/proposal-about.jpg';

function he($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
function fmtD($d) {
    if (!$d) return '—';
    return date('F j, Y', strtotime($d));
}
function fmtMoney($n) { return '$' . number_format(floatval($n), 0); }

$hero_title = $p['campaign_name'] ?: $p['client_name'];
$hero_sub   = $p['notes'] ?: ('A 3× Forbes-featured French Bulldog with ' . he($settings['stat_followers']) . ' devoted followers. Here\'s what we\'ll make together.');
$pkg_name   = $p['campaign_name'] ?: ($package_name ?? 'Custom Package');

// ── Signed-state / confirmation markup, shared between the fresh-sign JS swap and a returning-visitor page load ──
function render_signed_state($signerName, $signedAtIso) {
    $when = date('F j, Y \a\t g:i A', strtotime($signedAtIso));
    return '
      <div class="signed-state">
        <div class="signed-icon-box"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13l4 4L19 7" stroke="#e04d80" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div class="signed-title">Signed!</div>
        <div class="signed-sub">A copy is on its way to your inbox. Thank you, ' . he($signerName) . ' — we\'re excited to work together.</div>
        <div class="signed-meta">Signed by <strong>' . he($signerName) . '</strong> on <strong>' . he($when) . '</strong></div>
      </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <title>Partnership Proposal · Mallow × <?= he($p['client_name']) ?></title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:#191919; --ink-mid:#6b6b6b; --ink-light:#b0b0b0;
      --white:#ffffff; --blush:#faf0f0; --blush-dark:#f5e5e5;
      --pink:#e04d80; --card-rose:#f2d0dc; --card-sand:#e6d5b8;
      --card-sky:#c4dde8; --dark:#111111; --border:rgba(0,0,0,0.08);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { font-family:'Satoshi',sans-serif; background:var(--white); color:var(--ink); -webkit-font-smoothing:antialiased; }
    .wrap { max-width:1040px; margin:0 auto; padding:0 32px; }
    section { padding:96px 0; }

    .eyebrow { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--pink); }
    .title { font-size:clamp(32px,4vw,48px); font-weight:300; letter-spacing:-0.02em; line-height:1.1; margin-top:14px; }
    .title strong { font-weight:700; }
    .title em { font-style:italic; font-weight:800; }

    /* ===== HERO ===== */
    .hero { background:var(--blush); padding:0; }
    .hero-grid { display:grid; grid-template-columns:1.1fr 1fr; min-height:82vh; align-items:center; gap:56px; }
    .hero-copy { padding:80px 0; }
    .hero-name { font-size:clamp(52px,6vw,80px); font-weight:300; letter-spacing:-0.02em; line-height:1.05; margin-top:18px; }
    .hero-name em { font-style:italic; font-weight:800; }
    .hero-sub { font-size:17px; color:var(--ink-mid); line-height:1.7; margin-top:24px; max-width:44ch; }
    .hero-cta { display:inline-block; margin-top:36px; background:var(--card-rose); color:var(--ink); padding:16px 48px; border-radius:999px; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; text-decoration:none; transition:transform .15s ease; }
    .hero-cta:hover { transform:translateY(-2px); }
    .hero-photo { align-self:stretch; position:relative; background:var(--card-rose); border-radius:0 0 0 200px; min-height:520px; display:grid; place-items:center; font-size:120px; overflow:hidden; }
    .hero-photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }

    /* ===== STAT BAND (media-kit style) ===== */
    .stats { background:var(--white); padding:64px 0; border-bottom:1px solid var(--blush-dark); }
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:32px; text-align:center; }
    .stat-num { font-size:clamp(36px,4vw,52px); font-weight:800; letter-spacing:-0.02em; }
    .stat-label { font-size:12px; font-weight:500; letter-spacing:0.14em; text-transform:uppercase; color:var(--ink-mid); margin-top:6px; }

    /* ===== ABOUT ===== */
    .about-grid { display:grid; grid-template-columns:1fr 1.2fr; gap:64px; align-items:center; }
    .about-photo { height:380px; border-radius:999px; background:var(--card-sky); display:grid; place-items:center; font-size:80px; position:relative; overflow:hidden; }
    .about-photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .about-body { font-size:17px; line-height:1.8; color:var(--ink-mid); margin-top:20px; }
    .about-body b { color:var(--ink); }
    .press-strip { display:flex; gap:36px; margin-top:32px; align-items:center; font-weight:800; font-style:italic; font-size:18px; color:var(--ink-light); }

    /* ===== THE PARTNERSHIP (deliverables) ===== */
    .deliver { background:var(--blush); }
    .pkg-card { background:var(--card-rose); border-radius:20px; padding:44px 40px; margin-top:44px; display:grid; grid-template-columns:1.4fr 1fr; gap:48px; }
    .pkg-name { font-style:italic; font-weight:800; font-size:24px; }
    .pkg-list { list-style:none; margin-top:24px; }
    .pkg-list li { padding:12px 0; border-bottom:1px solid rgba(0,0,0,0.08); font-size:15px; display:flex; gap:12px; }
    .pkg-list li::before { content:'🐾'; font-size:13px; }
    .pkg-price-box { text-align:right; display:flex; flex-direction:column; justify-content:space-between; }
    .pkg-price-label { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--pink); }
    .pkg-price { font-size:clamp(44px,5vw,64px); font-weight:200; letter-spacing:-0.03em; margin-top:8px; }
    .pkg-valid { font-size:12px; color:var(--ink-mid); }

    /* ===== TIMELINE ===== */
    .tl-row { display:grid; grid-template-columns:200px 1fr auto; gap:40px; padding:28px 0; border-bottom:1px solid #e8e8e8; align-items:baseline; }
    .tl-row:first-of-type { border-top:1px solid #e8e8e8; margin-top:44px; }
    .tl-phase { font-size:18px; font-weight:700; }
    .tl-detail { font-size:14px; color:var(--ink-mid); line-height:1.6; }
    .tl-when { font-size:11px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--pink); white-space:nowrap; }

    /* ===== SIGNATURE (dark cover) ===== */
    .sign { background:var(--dark); color:var(--white); }
    .sign-grid { display:grid; grid-template-columns:1fr 1fr; gap:64px; align-items:start; }
    .sign .title { color:var(--white); }
    .sign-terms { font-size:14px; line-height:1.8; color:rgba(255,255,255,0.6); margin-top:24px; }
    .sign-form { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:36px; }
    .sf-label { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--pink); display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; margin-top:22px; }
    .sf-label:first-child { margin-top:0; }
    .sf-clear { font-size:10px; letter-spacing:0.05em; text-transform:none; color:rgba(255,255,255,0.4); background:none; border:none; cursor:pointer; font-family:inherit; text-decoration:underline; }
    .sf-clear:hover { color:rgba(255,255,255,0.7); }
    .sf-input { width:100%; background:transparent; border:none; border-bottom:1px solid rgba(255,255,255,0.25); color:var(--white); font-family:inherit; font-size:16px; padding:10px 2px; outline:none; }
    .sf-input:focus { border-bottom-color:var(--pink); }
    .sig-pad-wrap { margin-top:8px; border-radius:12px; overflow:hidden; border:1px solid rgba(255,255,255,0.25); }
    .sig-pad-canvas { display:block; width:100%; height:120px; background:#ffffff; cursor:crosshair; touch-action:none; }
    .sig-error { display:none; background:rgba(224,77,128,0.15); border:1px solid rgba(224,77,128,0.4); color:#ffb3c9; font-size:13px; padding:10px 14px; border-radius:8px; margin-top:16px; }
    .sig-error.show { display:block; }
    .btn-sign { display:block; width:100%; margin-top:28px; background:var(--pink); color:var(--white); border:none; padding:18px; border-radius:999px; font-family:inherit; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; transition:transform .15s ease; }
    .btn-sign:hover { transform:translateY(-2px); }
    .btn-sign:disabled { opacity:0.5; cursor:not-allowed; transform:none; }
    .sign-fine { font-size:12px; color:rgba(255,255,255,0.4); margin-top:16px; text-align:center; line-height:1.6; }

    /* signed / expired states inside the dark section */
    .signed-state { text-align:center; padding:8px 0 4px; }
    .signed-icon-box { width:56px; height:56px; border-radius:14px; background:var(--card-rose); display:flex; align-items:center; justify-content:center; margin:0 auto 14px; }
    .signed-title { font-size:22px; font-weight:800; letter-spacing:-0.02em; margin-bottom:8px; color:var(--white); }
    .signed-sub { font-size:14px; color:rgba(255,255,255,0.6); margin-bottom:20px; line-height:1.6; }
    .signed-meta { background:rgba(255,255,255,0.06); border-radius:10px; padding:14px 18px; font-size:12px; color:rgba(255,255,255,0.6); text-align:left; }
    .signed-meta strong { color:var(--white); }
    .expired-state { text-align:center; padding:8px 0 4px; }
    .expired-icon { font-size:32px; margin-bottom:12px; }
    .expired-title { font-size:20px; font-weight:800; color:var(--white); margin-bottom:8px; }
    .expired-sub { font-size:14px; color:rgba(255,255,255,0.6); line-height:1.6; }
    .expired-sub a { color:var(--card-rose); }

    /* ===== FOOTER ===== */
    .foot { background:var(--white); border-top:1px solid var(--border); padding:24px 48px; display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .foot b { font-weight:700; } .foot .mid { color:var(--ink-mid); } .foot .handle { font-weight:500; }

    @media (max-width: 860px) {
      section { padding:64px 0; }
      .hero-grid, .about-grid, .sign-grid, .pkg-card { grid-template-columns:1fr; }
      .hero-photo { border-radius:0; min-height:320px; order:-1; }
      .hero-copy { padding:48px 0 64px; }
      .stats-row { grid-template-columns:repeat(2,1fr); gap:40px 20px; }
      .pkg-price-box { text-align:left; margin-top:8px; }
      .tl-row { grid-template-columns:1fr; gap:6px; }
      .foot { flex-direction:column; gap:8px; padding:24px; }
    }

    @media print {
      .sign-form, .btn-sign, .hero-cta { display:none !important; }
    }
  </style>
</head>
<body>

<!-- HERO -->
<header class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <div class="eyebrow">Partnership proposal · <?= he($p['proposal_num']) ?></div>
      <h1 class="hero-name">Mallow <em>×</em><br><?= he($hero_title) ?></h1>
      <p class="hero-sub"><?= $hero_sub ?></p>
      <a class="hero-cta" href="#sign">Review &amp; sign</a>
    </div>
    <div class="hero-photo">
      <img src="<?= he($IMG_HERO_PHOTO) ?>" alt="" onerror="this.style.display='none'">
      <span>🐶</span>
    </div>
  </div>
</header>

<!-- STATS -->
<div class="stats">
  <div class="wrap stats-row">
    <div><div class="stat-num"><?= he($settings['stat_followers']) ?></div><div class="stat-label">Followers</div></div>
    <div><div class="stat-num"><?= he($settings['stat_impressions']) ?></div><div class="stat-label">Impressions / month</div></div>
    <div><div class="stat-num"><?= he($settings['stat_audience_age']) ?></div><div class="stat-label"><?= he($settings['stat_audience_age_label']) ?></div></div>
    <div><div class="stat-num"><?= he($settings['stat_partnerships']) ?></div><div class="stat-label">Brand partnerships</div></div>
  </div>
</div>

<!-- ABOUT -->
<section>
  <div class="wrap about-grid">
    <div class="about-photo">
      <img src="<?= he($IMG_ABOUT_PHOTO) ?>" alt="" onerror="this.style.display='none'">
      <span>📸</span>
    </div>
    <div>
      <div class="eyebrow">About me</div>
      <h2 class="title">Miami's most <em>bookable</em> frenchie.</h2>
      <p class="about-body"><?= nl2br(he($settings['about_blurb'])) ?></p>
      <div class="press-strip"><span>Forbes</span><span>New Times</span><span>TimeOut</span><span>AdWeek</span></div>
    </div>
  </div>
</section>

<!-- DELIVERABLES -->
<section class="deliver">
  <div class="wrap">
    <div class="eyebrow">The partnership</div>
    <h2 class="title">What <strong>you</strong> get.</h2>
    <div class="pkg-card">
      <div>
        <div class="pkg-name"><?= he($pkg_name) ?></div>
        <?php if (!empty($deliverables)): ?>
        <ul class="pkg-list">
          <?php foreach ($deliverables as $d): ?>
          <li><?= he($d) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="pkg-price-box">
        <div>
          <div class="pkg-price-label">Investment</div>
          <div class="pkg-price"><?= fmtMoney($p['total']) ?></div>
        </div>
        <?php if ($p['expiry_date']): ?>
        <div class="pkg-valid">Proposal valid through <?= fmtD($p['expiry_date']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- TIMELINE -->
<section>
  <div class="wrap">
    <div class="eyebrow">How it works</div>
    <h2 class="title">From <em>yes</em> to live.</h2>
    <div class="tl-row"><div class="tl-phase">Sign &amp; kickoff</div><div class="tl-detail">Sign below, receive your countersigned PDF instantly, and we schedule the shoot.</div><div class="tl-when">Day 0</div></div>
    <div class="tl-row"><div class="tl-phase">Content creation</div><div class="tl-detail">Shoot day at your property. Mallow brings the smile; we bring the crew.</div><div class="tl-when">Week 1–2</div></div>
    <div class="tl-row"><div class="tl-phase">Review &amp; publish</div><div class="tl-detail">You approve drafts, we post on the agreed schedule, HD files delivered.</div><div class="tl-when">Week 2–3</div></div>
  </div>
</section>

<!-- SIGNATURE -->
<section class="sign" id="sign">
  <div class="wrap sign-grid">
    <div>
      <div class="eyebrow">Make it official</div>
      <h2 class="title">Let's <strong>work</strong> together.</h2>
      <p class="sign-terms">By signing, you agree to the deliverables, investment, and timeline above. A <?= he($settings['deposit_percent']) ?>% deposit invoice is issued on signing; the balance is due on content delivery. You'll receive a signed PDF copy of this proposal by email the moment you sign.</p>
    </div>

    <?php if ($expired && !$sig): ?>
    <div class="sign-form">
      <div class="expired-state">
        <div class="expired-icon">⏰</div>
        <div class="expired-title">This proposal has expired</div>
        <div class="expired-sub">Please contact us at <a href="mailto:<?= he($settings['contact_email']) ?>"><?= he($settings['contact_email']) ?></a> to request an updated proposal.</div>
      </div>
    </div>

    <?php elseif ($sig): ?>
    <div class="sign-form" id="sig-block">
      <?= render_signed_state($sig['signer_name'], $sig['signed_at']) ?>
    </div>

    <?php else: ?>
    <form class="sign-form" id="sig-block" onsubmit="return false;">
      <div class="sig-error" id="sig-error"></div>
      <label class="sf-label" for="signer-name">Full name</label>
      <input class="sf-input" id="signer-name" type="text" placeholder="Your name" autocomplete="name">
      <label class="sf-label" for="signer-email">Email</label>
      <input class="sf-input" id="signer-email" type="email" placeholder="you@company.com" autocomplete="email" value="<?= he($p['client_email']) ?>">
      <label class="sf-label">Signature <button type="button" class="sf-clear" onclick="clearSignature()">Clear</button></label>
      <div class="sig-pad-wrap">
        <canvas class="sig-pad-canvas" id="sig-canvas"></canvas>
      </div>
      <button class="btn-sign" id="sig-btn" type="button" onclick="signProposal()">Sign proposal</button>
      <div class="sign-fine">Timestamped and legally binding · You'll get a PDF copy instantly</div>
    </form>
    <?php endif; ?>

  </div>
</section>

<footer class="foot">
  <span><b><?= he($settings['company_name']) ?></b></span>
  <span class="mid">Proposal by <?= he($settings['company_name']) ?> · Design by MIUX Creative</span>
  <span class="handle"><?= he($settings['instagram_handle']) ?></span>
</footer>

<script>
  const PROPOSAL_ID = <?= intval($id) ?>;

  // ── Signature canvas ──
  const canvas = document.getElementById('sig-canvas');
  let ctx, hasInk = false, drawing = false, lastX = 0, lastY = 0;

  function setupCanvas() {
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.strokeStyle = '#191919';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
  }

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const t = e.touches && e.touches[0];
    return { x: (t ? t.clientX : e.clientX) - rect.left, y: (t ? t.clientY : e.clientY) - rect.top };
  }
  function startDraw(e) {
    e.preventDefault();
    drawing = true;
    const p = getPos(e);
    lastX = p.x; lastY = p.y;
  }
  function draw(e) {
    if (!drawing) return;
    e.preventDefault();
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastX = p.x; lastY = p.y;
    hasInk = true;
  }
  function endDraw() { drawing = false; }

  function clearSignature() {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasInk = false;
  }

  if (canvas) {
    setupCanvas();
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', endDraw);
    window.addEventListener('resize', () => { setupCanvas(); hasInk = false; });
  }

  async function signProposal() {
    const name   = document.getElementById('signer-name').value.trim();
    const email  = document.getElementById('signer-email').value.trim();
    const errEl  = document.getElementById('sig-error');

    errEl.classList.remove('show');

    if (!name) {
      errEl.textContent = 'Please enter your full name.';
      errEl.classList.add('show');
      return;
    }
    if (!hasInk) {
      errEl.textContent = 'Please draw your signature above.';
      errEl.classList.add('show');
      return;
    }

    const btn = document.getElementById('sig-btn');
    btn.disabled = true;
    btn.textContent = 'Signing...';

    try {
      const res = await fetch('/taterdash-app/taterdash/sign-proposal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          proposal_id: PROPOSAL_ID,
          signer_name: name,
          signer_email: email,
          signature_image: canvas.toDataURL('image/png'),
        })
      });
      const data = await res.json();

      if (data.success) {
        document.getElementById('sig-block').innerHTML = `
          <div class="signed-state">
            <div class="signed-icon-box"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13l4 4L19 7" stroke="#e04d80" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            <div class="signed-title">Signed!</div>
            <div class="signed-sub">A copy is on its way to your inbox. Thank you, ${escHtml(name)} — we're excited to work together.</div>
            <div class="signed-meta">
              Signed by <strong>${escHtml(name)}</strong>
              on <strong>${new Date(data.signed_at).toLocaleString('en-US', {month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})}</strong>
            </div>
          </div>
        `;
      } else {
        errEl.textContent = data.error || 'An error occurred. Please try again.';
        errEl.classList.add('show');
        btn.disabled = false;
        btn.textContent = 'Sign proposal';
      }
    } catch (e) {
      errEl.textContent = 'Connection error. Please try again.';
      errEl.classList.add('show');
      btn.disabled = false;
      btn.textContent = 'Sign proposal';
    }
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
</script>
</body>
</html>
