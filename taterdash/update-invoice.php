<?php
// ═══════════════════════════════════════════
// TaterDash — Update Invoice
// /taterdash-app/taterdash/update-invoice.php
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$invoice_id  = intval($data['invoice_id'] ?? 0);
$client_name  = trim($data['client_name']  ?? '');
$contact_name = trim($data['contact_name'] ?? '');
$client_email = trim($data['client_email'] ?? '');
$issue_date   = $data['issue_date'] ?? null;
$due_date     = $data['due_date']   ?: null;
$notes        = trim($data['notes'] ?? '');
$line_items   = $data['line_items'] ?? [];

if (!$invoice_id || !$client_name || !$client_email || !$issue_date) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $pdo = db_connect();

    // Verify invoice exists
    $check = $pdo->prepare("SELECT id FROM td_invoices WHERE id = ?");
    $check->execute([$invoice_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    // Recalculate total
    $total = 0;
    foreach ($line_items as $item) {
        $total += floatval($item['quantity'] ?? 1) * floatval($item['unit_price'] ?? 0);
    }

    $pdo->beginTransaction();

    // Update invoice record
    $stmt = $pdo->prepare("
        UPDATE td_invoices SET
            client_name  = ?,
            client_email = ?,
            issue_date   = ?,
            due_date     = ?,
            notes        = ?,
            total        = ?
        WHERE id = ?
    ");
    $stmt->execute([$client_name, $client_email, $issue_date, $due_date, $notes, $total, $invoice_id]);

    // Replace line items
    $pdo->prepare("DELETE FROM td_line_items WHERE invoice_id = ?")->execute([$invoice_id]);

    $li = $pdo->prepare("
        INSERT INTO td_line_items (invoice_id, description, quantity, unit_price, note, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($line_items as $item) {
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $li->execute([$invoice_id, $item['description'], $qty, $price, $item['note'] ?? '', $qty * $price]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'url'     => SITE_URL . '/invoice/?id=' . $invoice_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_php_error($pdo, 'update-invoice', $e, $data);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
