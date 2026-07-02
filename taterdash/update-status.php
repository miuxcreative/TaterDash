<?php
// ═══════════════════════════════════════════
// TaterDash — Update Invoice Status
// POST: invoice_id, status
// ═══════════════════════════════════════════

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$data    = json_decode(file_get_contents('php://input'), true);
$id      = intval($data['invoice_id'] ?? 0);
$status  = $data['status'] ?? '';
$allowed = ['draft', 'sent', 'viewed', 'paid', '_delete'];

if (!$id || !in_array($status, $allowed)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid invoice_id or status']));
}

$pdo = db_connect();

$inv = $pdo->prepare("SELECT invoice_num, client_name, total FROM td_invoices WHERE id = ?");
$inv->execute([$id]);
$invoice = $inv->fetch();

if (!$invoice) {
    http_response_code(404);
    die(json_encode(['error' => 'Invoice not found']));
}

if ($status === '_delete') {
    log_event($pdo, 'deleted', 'invoice', $id, $invoice['invoice_num'], $invoice['client_name'], $invoice['total']);
    $pdo->prepare("DELETE FROM td_invoices WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'invoice_id' => $id, 'deleted' => true]);
} else {
    $pdo->prepare("UPDATE td_invoices SET status = ? WHERE id = ?")->execute([$status, $id]);
    log_event($pdo, $status, 'invoice', $id, $invoice['invoice_num'], $invoice['client_name'], $invoice['total']);
    echo json_encode(['success' => true, 'invoice_id' => $id, 'status' => $status]);
}
