<?php
// ═══════════════════════════════════════════
// TaterDash — Stripe Webhook
// POST /taterdash-app/taterdash/stripe-webhook.php
//
// The ONLY thing in this codebase that may mark an invoice paid from a card
// payment. The browser returning to success_url proves nothing — anyone can
// visit that URL — so the redirect never touches status.
//
// Unauthenticated on purpose: Stripe's servers call this, not a logged-in user.
// Authenticity comes from the Stripe-Signature header, verified against
// STRIPE_WEBHOOK_SECRET. An unsigned or wrongly-signed request is rejected
// before anything is read from it.
// ═══════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/email-templates.php';

// Never render HTML here; Stripe reads status codes.
header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!defined('STRIPE_WEBHOOK_SECRET') || STRIPE_WEBHOOK_SECRET === ''
    || str_contains(STRIPE_WEBHOOK_SECRET, 'YOUR_WEBHOOK')) {
    http_response_code(503);
    exit('Webhook secret not configured');
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Wrong secret, replayed outside the tolerance window, or forged.
    http_response_code(400);
    exit('Invalid signature');
}

$pdo = db_connect();

// ── Idempotency ──────────────────────────────
// Stripe retries until it gets a 2xx, and may deliver the same event twice.
// The UNIQUE event_id turns a replay into a duplicate-key error, so the work
// below runs exactly once per event.
try {
    $pdo->prepare("
        INSERT INTO td_stripe_events (event_id, event_type, payload)
        VALUES (?, ?, ?)
    ")->execute([$event->id, $event->type, substr($payload, 0, 60000)]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {      // duplicate key — already handled
        http_response_code(200);
        exit('Duplicate event ignored');
    }
    log_php_error($pdo, 'stripe-webhook/ledger', $e, ['event' => $event->id]);
    http_response_code(500);
    exit('Ledger write failed');
}

/**
 * Resolve the invoice an event belongs to. metadata.invoice_id is what we set
 * when creating the session; session id is the fallback if metadata is missing.
 */
function resolve_invoice(PDO $pdo, $object): ?array {
    $invoiceId = (int) ($object->metadata->invoice_id ?? 0);
    if ($invoiceId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        if ($row = $stmt->fetch()) return $row;
    }
    if (!empty($object->id)) {
        $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE stripe_session_id = ?");
        $stmt->execute([$object->id]);
        if ($row = $stmt->fetch()) return $row;
    }
    return null;
}

try {
    switch ($event->type) {

        // ── Payment succeeded ────────────────
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            $session = $event->data->object;

            // 'completed' also fires for async methods that have not actually
            // settled yet; only 'paid' means the money is there.
            if (($session->payment_status ?? '') !== 'paid') {
                http_response_code(200);
                exit('Session not yet paid — ignoring');
            }

            $invoice = resolve_invoice($pdo, $session);
            if (!$invoice) {
                // 200, not 500: retrying will not make an unknown invoice appear.
                log_php_error($pdo, 'stripe-webhook/unknown-invoice',
                    new Exception('No invoice for session ' . $session->id), []);
                http_response_code(200);
                exit('No matching invoice');
            }

            // The status flip is the atomic claim. If another delivery already
            // marked it paid, rowCount() is 0 and we skip the side effects
            // rather than sending a second confirmation email.
            $upd = $pdo->prepare("
                UPDATE td_invoices
                   SET status = 'paid',
                       paid_at = NOW(),
                       stripe_payment_intent = ?
                 WHERE id = ? AND status <> 'paid'
            ");
            $upd->execute([$session->payment_intent ?? null, $invoice['id']]);

            $pdo->prepare("UPDATE td_stripe_events SET invoice_id = ? WHERE event_id = ?")
                ->execute([$invoice['id'], $event->id]);

            if ($upd->rowCount() === 0) {
                http_response_code(200);
                exit('Already paid');
            }

            log_event($pdo, 'paid', 'invoice', (int) $invoice['id'],
                $invoice['invoice_num'], $invoice['client_name'], (float) $invoice['total']);

            // Side effects must never fail the webhook — Stripe would retry a
            // non-2xx and we would redo work that already succeeded.
            try {
                $amount = '$' . number_format((float) $invoice['total'], 2);

                if (!empty($invoice['client_email'])) {
                    send_email(
                        $invoice['client_email'],
                        'Payment received — invoice ' . $invoice['invoice_num'],
                        email_invoice_paid_client($invoice)
                    );
                }
                if (defined('NOTIFY_EMAIL') && NOTIFY_EMAIL) {
                    send_email(
                        NOTIFY_EMAIL,
                        'Paid: ' . $invoice['invoice_num'] . ' — ' . $amount,
                        email_invoice_paid_notify($invoice)
                    );
                }
            } catch (Throwable $e) {
                log_php_error($pdo, 'stripe-webhook/notify', $e, ['invoice_id' => $invoice['id']]);
            }

            http_response_code(200);
            exit('Invoice marked paid');

        // ── Client abandoned or the session timed out ──
        case 'checkout.session.expired':
        case 'checkout.session.async_payment_failed':
            $session = $event->data->object;
            $invoice = resolve_invoice($pdo, $session);
            if ($invoice) {
                // Release the "payment processing" state so the invoice is
                // payable again. Never touches an invoice that reached 'paid'.
                $pdo->prepare("
                    UPDATE td_invoices
                       SET status = 'viewed', stripe_session_id = NULL
                     WHERE id = ? AND status = 'payment_processing'
                ")->execute([$invoice['id']]);

                $pdo->prepare("UPDATE td_stripe_events SET invoice_id = ? WHERE event_id = ?")
                    ->execute([$invoice['id'], $event->id]);
            }
            http_response_code(200);
            exit('Session released');

        default:
            http_response_code(200);
            exit('Event ignored');
    }

} catch (Throwable $e) {
    log_php_error($pdo, 'stripe-webhook', $e, ['event' => $event->id, 'type' => $event->type]);
    http_response_code(500);
    exit('Handler error');
}
