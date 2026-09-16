<?php
// ═══════════════════════════════════════════
// TaterDash — Create Stripe Checkout Session
// GET /taterdash-app/taterdash/create-checkout-session.php?t=TOKEN
//
// PUBLIC endpoint by necessity: the person paying is the client, who is not
// logged in. The invoice token IS the credential — the same one that gates the
// invoice page itself. Deliberately token-only, with no ?id= fallback: the
// fallback on invoice/index.php exists for links sent before tokens, and there
// are no pre-token payment links to honour.
//
// The amount is ALWAYS read from the database. Nothing about the price comes
// from the request, so a crafted URL cannot change what gets charged.
// ═══════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';

function pay_error(string $publicMessage, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Payment unavailable</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#faf0f0;color:#191919;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0;padding:24px;text-align:center}'
       . 'div{max-width:420px}a{color:#e04d80}</style></head><body><div>'
       . '<h1 style="font-weight:300">Payment unavailable</h1><p>' . htmlspecialchars($publicMessage, ENT_QUOTES) . '</p>'
       . '<p style="font-size:14px;color:#6b6b6b">Please get in touch and we will sort it out.</p>'
       . '</div></body></html>';
    exit;
}

if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    pay_error('That payment link is not valid.', 404);
}

if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '' || str_contains(STRIPE_SECRET_KEY, 'YOUR_KEY_HERE')) {
    pay_error('Card payment is not set up yet.', 503);
}

$pdo = db_connect();

try {
    $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE token = ?");
    $stmt->execute([$token]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        pay_error('That payment link is not valid.', 404);
    }
    if ($invoice['status'] === 'paid') {
        // Already settled — send them back to the invoice rather than charging twice.
        header('Location: ' . SITE_URL . '/invoice/?t=' . urlencode($token));
        exit;
    }

    // Charge the invoice total, as a single line item.
    //
    // Deliberately NOT itemised from td_line_items: those rows are not
    // guaranteed to sum to `total` (invoices created before the July
    // create-invoice-from-proposal fix have zeroed line items against a
    // non-zero total). Billing the itemisation would charge the wrong amount.
    // `total` is the figure the client actually agreed to and the figure the
    // invoice page shows as "total due", so that is what we charge.
    $amountCents = (int) round(((float) $invoice['total']) * 100);

    if ($amountCents < 50) {
        // Stripe's minimum charge is 50 cents; below that it would hard-fail.
        pay_error('This invoice total is too small to pay by card.', 422);
    }

    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    \Stripe\Stripe::setApiVersion('2024-06-20');

    $session = \Stripe\Checkout\Session::create([
        'mode'                 => 'payment',
        'client_reference_id'  => (string) $invoice['id'],
        'customer_email'       => $invoice['client_email'] ?: null,
        'line_items'           => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'usd',
                'unit_amount'  => $amountCents,
                'product_data' => [
                    'name'        => 'Invoice ' . $invoice['invoice_num'],
                    'description' => 'Mallow Frenchie — ' . $invoice['client_name'],
                ],
            ],
        ]],
        'metadata' => [
            'invoice_id'  => (string) $invoice['id'],
            'invoice_num' => $invoice['invoice_num'],
            'token'       => $token,
        ],
        // The webhook is the only thing that marks an invoice paid. These URLs
        // just control where the browser lands; they are attacker-reachable and
        // prove nothing about payment.
        'success_url' => SITE_URL . '/invoice/?t=' . urlencode($token) . '&checkout=done',
        'cancel_url'  => SITE_URL . '/invoice/?t=' . urlencode($token) . '&checkout=cancelled',
    ], [
        // Guards against a double-click creating two Checkout Sessions.
        'idempotency_key' => 'inv_' . $invoice['id'] . '_' . $amountCents . '_' . date('Ymd'),
    ]);

    // Record the session and move into the "payment processing" window. Guarded
    // so a stale request cannot pull a already-paid invoice backwards.
    $pdo->prepare("
        UPDATE td_invoices
           SET stripe_session_id = ?, status = 'payment_processing'
         WHERE id = ? AND status <> 'paid'
    ")->execute([$session->id, $invoice['id']]);

    header('Location: ' . $session->url, true, 303);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    log_php_error($pdo, 'create-checkout-session/stripe', $e, ['token' => substr($token, 0, 8) . '…']);
    pay_error('We could not start the payment just now.', 502);
} catch (Throwable $e) {
    log_php_error($pdo, 'create-checkout-session', $e, ['token' => substr($token, 0, 8) . '…']);
    pay_error('Something went wrong starting the payment.', 500);
}
