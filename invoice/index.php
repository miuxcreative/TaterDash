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
}

$settings = get_settings($pdo);

// ── Format helpers ──
function fmt_money($n) { return '$' . number_format(floatval($n), 2); }
function fmt_date($d)  { return date('F j, Y', strtotime($d)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <link rel="apple-touch-icon" href="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png">
  <title>Invoice <?= htmlspecialchars($invoice['invoice_num']) ?> — Mallow Frenchie</title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@200,300,400,500,600,700,800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --ink:       #191919;
      --ink-mid:   #6b6b6b;
      --ink-light: #b0b0b0;
      --white:     #ffffff;
      --blush:     #faf0f0;
      --blush-dark:#f5e5e5;
      --pink:      #e04d80;
      --card-rose: #f2d0dc;
      --dark:      #111111;
      --border:    #e8e8e8;
    }
    html { font-size: 16px; }
    body {
      font-family: 'Satoshi', sans-serif;
      background: #e4e4e4;
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
      padding: 48px 20px;
    }
    .page {
      max-width: 740px;
      margin: 0 auto;
      background: var(--white);
      box-shadow: 0 8px 64px rgba(0,0,0,0.10);
    }
    .top-band { height: 4px; background: var(--card-rose); }

    /* HEADER */
    .hdr {
      padding: 44px 56px 32px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 1px solid var(--border);
    }
    .hdr-logo img { height: 80px; width: auto; display: block; }
    .hdr-logo-fallback { display: none; font-size: 17px; font-weight: 700; color: var(--ink); }
    .hdr-logo-fallback span { color: var(--pink); }
    .hdr-right { text-align: right; }
    .hdr-word { font-size: 48px; font-weight: 200; letter-spacing: -0.03em; color: var(--ink); line-height: 1.0; margin-bottom: 10px; }
    .hdr-dateline { font-size: 12.5px; color: var(--ink-mid); line-height: 1.75; }
    .hdr-dateline b { font-weight: 600; color: var(--ink); }
    .hdr-invnum { margin-top: 6px; font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--pink); }

    /* PARTIES */
    .parties {
      padding: 28px 56px 32px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
      border-bottom: 1px solid var(--border);
    }
    .pty-eyebrow { font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--pink); margin-bottom: 8px; }
    .pty-name { font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 3px; }
    .pty-detail { font-size: 12.5px; color: var(--ink-mid); line-height: 1.7; }

    /* TABLE */
    .items { padding: 28px 56px 0; }
    .items-wrap { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table thead th {
      background: var(--card-rose);
      font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
      color: var(--ink); padding: 12px 16px; text-align: left;
      border-right: 1px solid rgba(224,77,128,0.2);
      border-bottom: 1px solid rgba(224,77,128,0.2);
    }
    .items-table thead th:last-child { border-right: none; }
    .items-table thead th.r { text-align: right; }
    .items-table thead th.col-qty { width: 52px; }
    .items-table thead th.col-price { width: 100px; }
    .items-table thead th.col-total { width: 100px; }
    .items-table tbody td {
      padding: 15px 16px; font-size: 13.5px; color: var(--ink);
      vertical-align: top; background: var(--white);
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
    }
    .items-table tbody td:last-child { border-right: none; }
    .items-table tbody tr:last-child td { border-bottom: none; }
    .items-table tbody td.r { text-align: right; font-weight: 600; }
    .items-table tbody td.col-qty { color: var(--ink-mid); text-align: center; }
    .line-desc { font-weight: 600; }
    .line-note { font-size: 11.5px; color: var(--ink-mid); margin-top: 3px; line-height: 1.5; }

    /* TOTAL */
    .total-row { padding: 16px 56px 0; display: flex; justify-content: flex-end; }
    .total-box { display: flex; align-items: center; gap: 48px; border-top: 2px solid var(--ink); padding-top: 12px; min-width: 220px; }
    .total-label { font-size: 10px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--ink-mid); }
    .total-amount { font-size: 24px; font-weight: 700; color: var(--ink); letter-spacing: -0.02em; }

    /* PAYMENT */
    .payment { padding: 32px 56px 36px; display: flex; justify-content: space-between; align-items: flex-end; gap: 40px; border-top: 1px solid var(--border); margin-top: 28px; }
    .pay-eyebrow { font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--pink); margin-bottom: 12px; }
    .pay-methods { display: flex; flex-direction: column; gap: 5px; }
    .pay-row { font-size: 12.5px; color: var(--ink-mid); line-height: 1.5; }
    .pay-row b { font-weight: 600; color: var(--ink); margin-right: 5px; }
    .pay-note { font-size: 11px; color: var(--ink-light); margin-top: 10px; line-height: 1.6; }
    .pay-btn {
      display: inline-block; background: var(--card-rose); color: var(--ink);
      padding: 13px 32px; border-radius: 999px; font-size: 12px; font-weight: 700;
      letter-spacing: 0.1em; text-transform: uppercase; text-decoration: none;
      white-space: nowrap; flex-shrink: 0;
    }
    .pay-btn.disabled { opacity: 0.4; pointer-events: none; }

    /* FOOTER */
    .ftr { background: var(--dark); padding: 16px 56px; display: flex; justify-content: space-between; align-items: center; }
    .ftr-wordmark { font-size: 13px; font-weight: 700; color: var(--white); letter-spacing: -0.01em; }
    .ftr-mid { font-size: 11px; color: rgba(255,255,255,0.35); text-align: center; }
    .ftr-handle { font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.45); }

    @media print {
      body { background: white; padding: 0; }
      .page { box-shadow: none; }
      .pay-btn { display: none; }
      @page { margin: 0; size: letter; }
    }
  </style>
</head>
<body>
<div class="page">
  <div class="top-band"></div>

  <div class="hdr">
    <div class="hdr-logo">
      <img src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png"
           alt="Mallow Frenchie"
           onerror="this.style.display='none';document.querySelector('.hdr-logo-fallback').style.display='block'">
      <div class="hdr-logo-fallback">Mallow<span>Frenchie</span></div>
    </div>
    <div class="hdr-right">
      <div class="hdr-word">invoice</div>
      <div class="hdr-dateline">
        <b>Date:</b> <?= fmt_date($invoice['issue_date']) ?>
        &nbsp;|&nbsp;
        <b>Due</b> upon receipt
      </div>
      <div class="hdr-invnum"><?= htmlspecialchars($invoice['invoice_num']) ?></div>
    </div>
  </div>

  <div class="parties">
    <div>
      <div class="pty-eyebrow">From</div>
      <div class="pty-name"><?= htmlspecialchars($settings['company_name']) ?></div>
      <div class="pty-detail">
        <?= htmlspecialchars($settings['company_email']) ?><br>
        <?= nl2br(htmlspecialchars($settings['company_address'])) ?>
      </div>
    </div>
    <div style="text-align:right;">
      <div class="pty-eyebrow">Bill To</div>
      <div class="pty-name"><?= htmlspecialchars($invoice['client_name']) ?></div>
      <div class="pty-detail"><?= htmlspecialchars($invoice['client_email']) ?></div>
    </div>
  </div>

  <div class="items">
    <div class="items-wrap">
      <table class="items-table">
        <thead>
          <tr>
            <th class="col-qty">Qty</th>
            <th>Description</th>
            <th class="col-price r">Price</th>
            <th class="col-total r">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td class="col-qty"><?= intval($item['quantity']) ?></td>
            <td>
              <div class="line-desc"><?= htmlspecialchars($item['description']) ?></div>
              <?php if ($item['note']): ?>
              <div class="line-note"><?= htmlspecialchars($item['note']) ?></div>
              <?php endif; ?>
            </td>
            <td class="r"><?= fmt_money($item['unit_price']) ?></td>
            <td class="r"><?= fmt_money($item['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="total-row">
    <div class="total-box">
      <div class="total-label">Total</div>
      <div class="total-amount"><?= fmt_money($invoice['total']) ?></div>
    </div>
  </div>

  <?php if ($invoice['notes']): ?>
  <div style="padding: 20px 56px 0;">
    <div class="pty-eyebrow" style="margin-bottom:6px;">Notes</div>
    <div style="font-size:13px; color:var(--ink-mid); line-height:1.65;"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
  </div>
  <?php endif; ?>

  <div class="payment">
    <div>
      <div class="pay-eyebrow">Payment</div>
      <div class="pay-methods">
        <div class="pay-row"><b>PayPal</b> mallowfrenchie@gmail.com</div>
        <div class="pay-row"><b>Zelle</b> 305-333-7905</div>
        <div class="pay-row"><b>Check</b> payable to <?= htmlspecialchars($settings['company_name']) ?><?= $settings['company_address'] ? ' · ' . htmlspecialchars($settings['company_address']) : '' ?></div>
      </div>
      <?php if (!STRIPE_PAYMENT_URL): ?>
      <div class="pay-note">Online payment via card coming soon.</div>
      <?php endif; ?>
    </div>
    <?php if (STRIPE_PAYMENT_URL): ?>
    <a href="<?= htmlspecialchars(STRIPE_PAYMENT_URL) ?>" class="pay-btn" target="_blank">Pay Now ↗</a>
    <?php else: ?>
    <span class="pay-btn disabled">Pay Now ↗</span>
    <?php endif; ?>
  </div>

  <div class="ftr">
    <div class="ftr-wordmark">MallowFrenchie</div>
    <div class="ftr-mid"><?= htmlspecialchars($settings['company_name']) ?> &middot; <?= htmlspecialchars($settings['company_email']) ?></div>
    <div class="ftr-handle">@mallowfrenchie</div>
  </div>
</div>
</body>
</html>
