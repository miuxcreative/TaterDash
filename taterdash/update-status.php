<?php
// ═══════════════════════════════════════════
// TaterDash — Update Status (invoices + proposals)
// POST: { type?, id|invoice_id, status }
// ═══════════════════════════════════════════

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$data   = json_decode(file_get_contents('php://input'), true);
$type   = $data['type']   ?? 'invoice';
$id     = intval($data['id'] ?? $data['invoice_id'] ?? 0);
$status = $data['status'] ?? '';

$invoice_allowed  = ['sent', 'viewed', 'paid', '_delete'];
$proposal_allowed = ['sent', 'viewed'];
$allowed = $type === 'proposal' ? $proposal_allowed : $invoice_allowed;

if (!$id || !in_array($status, $allowed)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid parameters']));
}

$pdo = db_connect();

if ($type === 'proposal') {
    $stmt = $pdo->prepare("SELECT proposal_num, client_name FROM td_proposals WHERE id = ?");
    $stmt->execute([$id]);
    $proposal = $stmt->fetch();
    if (!$proposal) {
        http_response_code(404);
        die(json_encode(['error' => 'Proposal not found']));
    }
    // Guard: only advance — never demote status
    $guard = $status === 'sent' ? "AND status = 'draft'" : "AND status = 'sent'";
    $pdo->prepare("UPDATE td_proposals SET status = ?, updated_at = NOW() WHERE id = ? $guard")
        ->execute([$status, $id]);
    log_event($pdo, $status, 'proposal', $id, $proposal['proposal_num'], $proposal['client_name']);
    echo json_encode(['success' => true, 'id' => $id, 'status' => $status]);

} else {
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
}
