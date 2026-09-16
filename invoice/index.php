<?php
// ═══════════════════════════════════════════
// TaterDash — Client Invoice View
// /public_html/invoice/index.php
// mallowfrenchie.com/invoice/?t=TOKEN (or ?id=1 for pre-token links)
// ═══════════════════════════════════════════

require_once __DIR__ . '/../taterdash-app/taterdash/config.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';
$id    = isset($_GET['id']) ? intval($_GET['id']) : 0;

$pdo = db_connect();

// ── Fetch invoice: token is the primary lookup; id fallback is for links sent before tokens existed ──
// TODO: remove id fallback after launch
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE token = ?");
    $stmt->execute([$token]);
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE id = ?");
    $stmt->execute([$id]);
} else {
    http_response_code(404);
    die('Invoice not found.');
}
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found.');
}
$id = $invoice['id'];

// ── Fetch line items ──
$stmt = $pdo->prepare("SELECT * FROM td_line_items WHERE invoice_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// ── Mark as viewed ──
if ($invoice['status'] === 'sent') {
    $pdo->prepare("UPDATE td_invoices SET status = 'viewed' WHERE id = ?")->execute([$id]);
    $invoice['status'] = 'viewed';
}

$settings = get_settings($pdo);

// ── Image slots — swap these once real photos/marks are supplied ──
$IMG_BRAND_MARK = 'https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png';

function he($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
function fmt_money_cents($n) { return '$' . number_format(floatval($n), 2); }
function fmt_money_whole($n) { return number_format(floatval($n), 0); }
function fmt_date($d)  { return $d ? date('F j, Y', strtotime($d)) : '—'; }

$is_paid = $invoice['status'] === 'paid';

// ── Stripe state ──────────────────────────────
// Every constant is guarded with defined(): the live config.php is edited by
// hand on the server and will not have the Stripe keys until someone adds
// them, so an unconfigured install must degrade to the old "not set up yet"
// button rather than fatal on a client-facing page.
$stripe_mode  = defined('STRIPE_MODE') ? STRIPE_MODE : 'test';
$stripe_ready = defined('STRIPE_SECRET_KEY')
             && STRIPE_SECRET_KEY !== ''
             && !str_contains(STRIPE_SECRET_KEY, 'YOUR_KEY_HERE')
             && !empty($invoice['token']);

// The Pay link always uses the invoice's own token, never the URL's — an
// invoice opened through the legacy ?id= link still needs a working button.
$pay_url = $stripe_ready
    ? (defined('APP_URL') ? APP_URL : SITE_URL . '/taterdash-app')
      . '/taterdash/create-checkout-session.php?t=' . urlencode($invoice['token'])
    : '';

$checkout_state = $_GET['checkout'] ?? '';

// Stripe's webhook usually lands before the client's browser gets back here,
// but not always. When they have just come from a completed Checkout and the
// invoice is not yet flipped, show a confirming state rather than inviting
// them to pay a second time.
$awaiting_confirmation = !$is_paid && $checkout_state === 'done';

$pay_status_label = $is_paid                 ? 'Paid — thank you!'
                  : ($awaiting_confirmation  ? 'Confirming payment…'
                  : 'Awaiting payment');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <title>Invoice <?= he($invoice['invoice_num']) ?> · Mallow Frenchie</title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:#191919; --ink-mid:#6b6b6b; --ink-light:#b0b0b0;
      --white:#ffffff; --blush:#faf0f0; --blush-dark:#f5e5e5;
      --pink:#e04d80; --card-rose:#f2d0dc; --card-sand:#e6d5b8;
      --card-sky:#c4dde8; --dark:#111111; --border:rgba(0,0,0,0.08);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Satoshi',sans-serif; background:var(--blush); color:var(--ink); -webkit-font-smoothing:antialiased; }

    .layout { display:grid; grid-template-columns: 1fr 420px; min-height:100vh; }

    /* ============ LEFT — THE INVOICE ============ */
    .doc-pane { padding:48px 56px; display:flex; justify-content:center; align-items:flex-start; }
    .doc { background:var(--white); border-radius:24px; max-width:720px; width:100%; overflow:hidden; box-shadow:0 24px 80px rgba(224,77,128,0.10); }

    .doc-head { padding:44px 52px 36px; display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--blush-dark); }
    .brand { display:flex; align-items:center; gap:14px; }
    .brand-mark { width:52px; height:52px; border-radius:50%; background:var(--card-rose); display:grid; place-items:center; overflow:hidden; flex-shrink:0; }
    .brand-mark img { width:100%; height:100%; object-fit:cover; }
    .brand-name { font-weight:700; font-size:17px; letter-spacing:-0.01em; }
    .brand-sub { font-size:12px; color:var(--ink-mid); font-weight:500; }
    .doc-meta { text-align:right; }
    .doc-type { font-style:italic; font-weight:800; font-size:26px; letter-spacing:-0.02em; }
    .doc-num { font-size:12px; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:var(--pink); margin-top:4px; }
    .doc-dates { font-size:12px; color:var(--ink-mid); margin-top:8px; line-height:1.6; }

    .doc-parties { display:grid; grid-template-columns:1fr 1fr; gap:24px; padding:32px 52px; background:var(--blush); }
    .party-label { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--pink); margin-bottom:8px; }
    .party-name { font-weight:700; font-size:15px; }
    .party-detail { font-size:13px; color:var(--ink-mid); line-height:1.6; margin-top:2px; }

    .doc-body { padding:36px 52px 44px; }
    .line-table { width:100%; border-collapse:collapse; }
    .line-table th { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--ink-light); text-align:left; padding-bottom:14px; border-bottom:1px solid var(--blush-dark); }
    .line-table th:last-child { text-align:right; }
    .line-table td { padding:18px 0; border-bottom:1px solid var(--blush-dark); font-size:14px; vertical-align:top; }
    .line-table td:last-child { text-align:right; font-weight:700; white-space:nowrap; }
    .li-name { font-weight:700; }
    .li-desc { font-size:13px; color:var(--ink-mid); margin-top:4px; line-height:1.55; }

    .totals { margin-top:28px; display:flex; justify-content:flex-end; }
    .totals-box { width:280px; }
    .tot-row { display:flex; justify-content:space-between; font-size:13px; color:var(--ink-mid); padding:6px 0; }
    .tot-due { display:flex; justify-content:space-between; align-items:baseline; margin-top:12px; padding-top:16px; border-top:2px solid var(--ink); }
    .tot-due-label { font-style:italic; font-weight:800; font-size:16px; }
    .tot-due-amt { font-weight:800; font-size:28px; letter-spacing:-0.02em; }

    .doc-notes { padding:0 52px 8px; font-size:13px; color:var(--ink-mid); line-height:1.6; }
    .doc-notes b { color:var(--ink); }

    .doc-foot { padding:24px 52px; border-top:1px solid var(--blush-dark); display:flex; justify-content:space-between; font-size:12px; color:var(--ink-mid); }
    .doc-foot b { color:var(--ink); font-weight:700; }

    /* ============ RIGHT — PAYMENT PANEL ============ */
    .pay-pane { background:var(--dark); color:var(--white); padding:56px 44px; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; }
    .pay-eyebrow { font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--pink); }
    .pay-title { font-size:clamp(28px,3vw,36px); font-weight:300; letter-spacing:-0.02em; line-height:1.1; margin-top:14px; }
    .pay-title strong { font-weight:700; }
    .pay-amount { margin-top:36px; }
    .pay-amount-label { font-size:12px; color:rgba(255,255,255,0.5); font-weight:500; letter-spacing:0.12em; text-transform:uppercase; }
    .pay-amount-num { font-size:56px; font-weight:200; letter-spacing:-0.03em; margin-top:6px; }
    .pay-amount-num sup { font-size:22px; font-weight:400; vertical-align:38px; margin-right:2px; }

    .pay-status { display:inline-flex; align-items:center; gap:8px; margin-top:20px; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:var(--card-sand); }
    .pay-status::before { content:''; width:8px; height:8px; border-radius:50%; background:var(--card-sand); }

    .pay-cta { margin-top:auto; }
    .btn-pay { display:block; width:100%; text-align:center; background:var(--pink); color:var(--white); border:none; padding:20px 48px; border-radius:999px; font-family:inherit; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; text-decoration:none; transition:transform .15s ease, box-shadow .15s ease; }
    .btn-pay:hover { transform:translateY(-2px); box-shadow:0 12px 32px rgba(224,77,128,0.4); }
    .pay-secure { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:16px; font-size:12px; color:rgba(255,255,255,0.45); }
    .pay-note { margin-top:28px; padding-top:24px; border-top:1px solid rgba(255,255,255,0.1); font-size:13px; line-height:1.7; color:rgba(255,255,255,0.6); }
    .pay-note b { color:var(--white); }
    .pay-contact { margin-top:20px; font-size:12px; color:rgba(255,255,255,0.45); }
    .btn-pay--waiting { background:var(--card-sand); color:var(--ink); pointer-events:none; }
    .pay-cancelled { margin-bottom:14px; padding:10px 14px; border-radius:10px; background:rgba(255,255,255,0.08); font-size:13px; color:rgba(255,255,255,0.75); text-align:center; }
    .test-banner { position:sticky; top:0; z-index:50; background:#7a5c00; color:#ffe9a3; font-size:12px; font-weight:600; letter-spacing:0.04em; text-align:center; padding:8px 14px; }
    .pay-contact a { color:var(--card-rose); text-decoration:none; }

    /* Paid state */
    .is-paid .btn-pay { background:var(--card-sand); color:var(--ink); pointer-events:none; }
    .is-paid .pay-status { color:#8fd6a4; } .is-paid .pay-status::before { background:#8fd6a4; }

    .doc-pane, .doc { min-width: 0; }
    .doc-body { overflow-x: auto; }

    @media (max-width: 980px) {
      .layout { grid-template-columns:1fr; }
      .pay-pane { position:static; height:auto; padding:44px 32px; }
      .pay-cta { margin-top:36px; }
      .doc-pane { padding:24px 16px; }
      .doc-head, .doc-parties, .doc-body, .doc-notes, .doc-foot { padding-left:28px; padding-right:28px; }
    }

    /* Phones: the 3-column line table can't fit, so each line item becomes a
       stacked card. Horizontal scroll was the old behaviour and it silently
       clipped Qty/Amount with no affordance to swipe. */
    @media (max-width: 600px) {
      .line-table, .line-table tbody, .line-table tr, .line-table td { display:block; width:100%; }
      .line-table thead { display:none; }
      .line-table tr { padding:18px 0; border-bottom:1px solid var(--blush-dark); }
      .line-table tr:first-child { padding-top:0; }
      .line-table td { padding:0; border:none; }
      .line-table td.li-meta { display:flex; justify-content:space-between; align-items:baseline; gap:16px; margin-top:10px; font-size:14px; }
      .line-table td.li-meta::before { content:attr(data-label); font-size:11px; font-weight:500; letter-spacing:0.18em; text-transform:uppercase; color:var(--ink-light); }
      .line-table td:last-child { text-align:right; }
      .totals-box { width:100%; }
    }

    @media (max-width: 480px) {
      .doc-head { flex-wrap: wrap; gap: 16px; }
      .doc-meta { text-align: left; }
      .doc-parties { grid-template-columns: 1fr; }
    }

    @media print {
      body { background: white; }
      .pay-pane { display: none; }
      .layout { grid-template-columns: 1fr; }
      .doc-pane { padding: 0; }
      .doc { box-shadow: none; max-width: 100%; }
    }
  </style>
</head>
<body class="<?= $is_paid ? 'is-paid' : '' ?>">
<?php if ($stripe_ready && $stripe_mode !== 'live'): ?>
<div class="test-banner">TEST MODE — no real money moves. Card payments here use Stripe test cards only.</div>
<?php endif; ?>
<div class="layout">

  <!-- LEFT: the invoice document -->
  <main class="doc-pane">
    <article class="doc">
      <header class="doc-head">
        <div class="brand">
          <div class="brand-mark"><img src="<?= he($IMG_BRAND_MARK) ?>" alt="" onerror="this.style.display='none'"></div>
          <div>
            <div class="brand-name"><?= he($settings['company_name']) ?></div>
            <div class="brand-sub"><?= he($settings['instagram_handle']) ?></div>
          </div>
        </div>
        <div class="doc-meta">
          <div class="doc-type">invoice</div>
          <div class="doc-num"><?= he($invoice['invoice_num']) ?></div>
          <div class="doc-dates">Issued <?= fmt_date($invoice['issue_date']) ?><br>Due <?= fmt_date($invoice['due_date']) ?></div>
        </div>
      </header>

      <section class="doc-parties">
        <div>
          <div class="party-label">Billed to</div>
          <div class="party-name"><?= he($invoice['client_name']) ?></div>
          <div class="party-detail"><?= he($invoice['client_email']) ?></div>
        </div>
        <div>
          <div class="party-label">From</div>
          <div class="party-name"><?= he($settings['company_name']) ?></div>
          <div class="party-detail">
            <?= he($settings['company_email']) ?><br>
            <?= he($settings['instagram_handle']) ?><?= $settings['stat_followers'] ? ' · ' . he($settings['stat_followers']) : '' ?>
          </div>
        </div>
      </section>

      <section class="doc-body">
        <table class="line-table">
          <thead><tr><th>Deliverable</th><th>Qty</th><th>Amount</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="li-name"><?= he($item['description']) ?></div>
                <?php if ($item['note']): ?>
                <div class="li-desc"><?= he($item['note']) ?></div>
                <?php endif; ?>
              </td>
              <td class="li-meta" data-label="Qty"><?= intval($item['quantity']) ?></td>
              <td class="li-meta" data-label="Amount"><?= fmt_money_cents($item['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="totals">
          <div class="totals-box">
            <div class="tot-row"><span>Subtotal</span><span><?= fmt_money_cents($invoice['subtotal']) ?></span></div>
            <div class="tot-row"><span>Discount</span><span>—</span></div>
            <div class="tot-due"><span class="tot-due-label">total due</span><span class="tot-due-amt"><?= fmt_money_cents($invoice['total']) ?></span></div>
          </div>
        </div>
      </section>

      <?php if ($invoice['notes']): ?>
      <div class="doc-notes"><b>Notes</b><br><?= nl2br(he($invoice['notes'])) ?></div>
      <?php endif; ?>

      <footer class="doc-foot">
        <span><b><?= he($settings['company_name']) ?></b> · Thank you for working with us! 🐾</span>
        <span><?= he($settings['instagram_handle']) ?></span>
      </footer>
    </article>
  </main>

  <!-- RIGHT: payment panel -->
  <aside class="pay-pane">
    <div>
      <div class="pay-eyebrow">Secure payment</div>
      <h1 class="pay-title">Ready when <strong>you</strong> are.</h1>
      <div class="pay-amount">
        <div class="pay-amount-label">Amount due</div>
        <div class="pay-amount-num"><sup>$</sup><?= fmt_money_whole($invoice['total']) ?></div>
      </div>
      <div class="pay-status"><?= he($pay_status_label) ?></div>
    </div>
    <div class="pay-cta">
      <?php if ($is_paid): ?>
      <span class="btn-pay">Paid ✓</span>
      <div class="pay-note"><b>This invoice has been paid.</b> Thank you! Get in touch any time if you need a copy for your records.</div>

      <?php elseif ($awaiting_confirmation): ?>
      <span class="btn-pay btn-pay--waiting">Confirming…</span>
      <div class="pay-note"><b>Your payment went through.</b> We're just waiting on the final confirmation from Stripe — this page will show as paid within a few moments. You can safely close this window; nothing else is needed from you.</div>

      <?php elseif ($stripe_ready): ?>
      <?php if ($checkout_state === 'cancelled'): ?>
      <div class="pay-cancelled">Payment cancelled — nothing was charged.</div>
      <?php endif; ?>
      <a href="<?= he($pay_url) ?>" class="btn-pay">Pay with card</a>
      <div class="pay-secure">🔒 Secured by Stripe · card details never touch our server</div>
      <div class="pay-note"><b>What happens next?</b> You'll be taken to Stripe's secure checkout. Once paid, this invoice updates automatically and Stripe emails you a receipt.</div>

      <?php elseif (defined('STRIPE_PAYMENT_URL') && STRIPE_PAYMENT_URL): ?>
      <?php /* Legacy single static link — only reachable on an install where the
              Checkout keys have not been added to config.php yet. */ ?>
      <a href="<?= he(STRIPE_PAYMENT_URL) ?>" class="btn-pay" target="_blank">Pay with card</a>
      <div class="pay-secure">🔒 Secured by Stripe · card details never touch our server</div>
      <div class="pay-note"><b>What happens next?</b> You'll be taken to Stripe's secure checkout.</div>

      <?php else: ?>
      <span class="btn-pay" style="opacity:.4;pointer-events:none;">Pay with card</span>
      <div class="pay-note"><b>Card payment isn't set up yet.</b> Reach out below and we'll send you payment instructions directly.</div>
      <?php endif; ?>
      <div class="pay-contact">Questions? <a href="mailto:<?= he($settings['contact_email']) ?>"><?= he($settings['contact_email']) ?></a></div>
    </div>
  </aside>

</div>
<?php if ($awaiting_confirmation): ?>
<script>
// Stripe's webhook normally beats the browser back here, but it can lag a
// second or two. Re-check a few times, then stop — an endless reload loop
// would be worse than a stale page if the webhook never arrives.
(function () {
    var KEY = 'td-confirm-tries';
    var tries = parseInt(sessionStorage.getItem(KEY) || '0', 10);
    if (tries >= 4) { sessionStorage.removeItem(KEY); return; }
    sessionStorage.setItem(KEY, tries + 1);
    setTimeout(function () { location.reload(); }, 4000);
})();
</script>
<?php endif; ?>
</body>
</html>
