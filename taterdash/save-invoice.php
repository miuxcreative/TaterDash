<?php
// ═══════════════════════════════════════════
// TaterDash — Save Invoice
// /public_html/taterdash/save-invoice.php
// ═══════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://mallowfrenchie.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid data']));
}

// ── Validate required fields ──
$required = ['client_name', 'client_email', 'issue_date', 'line_items'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Missing required field: $field"]));
    }
}

$pdo = db_connect();

try {
    $pdo->beginTransaction();

    // ── Save or find client ──
    $client_id = null;
    if (!empty($data['client_email'])) {
        $stmt = $pdo->prepare("SELECT id FROM td_clients WHERE email = ?");
        $stmt->execute([$data['client_email']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $client_id = $existing['id'];
            // Update client info
            $stmt = $pdo->prepare("UPDATE td_clients SET company = ?, contact_name = ? WHERE id = ?");
            $stmt->execute([$data['client_name'], $data['contact_name'] ?? null, $client_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO td_clients (company, contact_name, email) VALUES (?, ?, ?)");
            $stmt->execute([$data['client_name'], $data['contact_name'] ?? null, $data['client_email']]);
            $client_id = $pdo->lastInsertId();
        }
    }

    // ── Calculate totals ──
    $subtotal = 0;
    foreach ($data['line_items'] as $item) {
        $subtotal += floatval($item['quantity']) * floatval($item['unit_price']);
    }
    $total = $subtotal;

    // ── Generate invoice number and slug ──
    $invoice_num = generate_invoice_num($pdo);
    $slug        = generate_slug($data['client_name']);

    // ── Insert invoice ──
    $stmt = $pdo->prepare("
        INSERT INTO td_invoices
            (invoice_num, client_id, client_name, client_email, issue_date, due_date, status, subtotal, total, notes, stripe_link)
        VALUES
            (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_num,
        $client_id,
        $data['client_name'],
        $data['client_email'],
        $data['issue_date'],
        $data['due_date'] ?? null,
        $subtotal,
        $total,
        $data['notes'] ?? null,
        STRIPE_PAYMENT_URL ?: null
    ]);
    $invoice_id = $pdo->lastInsertId();

    // ── Insert line items ──
    $stmt = $pdo->prepare("
        INSERT INTO td_line_items (invoice_id, description, note, quantity, unit_price, total, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($data['line_items'] as $i => $item) {
        $line_total = floatval($item['quantity']) * floatval($item['unit_price']);
        $stmt->execute([
            $invoice_id,
            $item['description'],
            $item['note'] ?? null,
            floatval($item['quantity']),
            floatval($item['unit_price']),
            $line_total,
            $i
        ]);
    }

    $pdo->commit();

    log_event($pdo, 'created', 'invoice', $invoice_id, $invoice_num, $data['client_name'], $total);

    // ── Return success with client URL ──
    echo json_encode([
        'success'     => true,
        'invoice_id'  => $invoice_id,
        'invoice_num' => $invoice_num,
        'url'         => SITE_URL . '/invoice/?id=' . $invoice_id,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    log_php_error($pdo, 'save-invoice', $e, $data);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save invoice: ' . $e->getMessage()]);
}
