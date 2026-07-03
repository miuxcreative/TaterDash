<?php
// ═══════════════════════════════════════════
// TaterDash — Get Client Detail (for side panel)
// GET: ?id=X
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { die(json_encode(['success' => false, 'error' => 'Missing id'])); }

$pdo = db_connect();

$stmt = $pdo->prepare("SELECT * FROM td_clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { die(json_encode(['success' => false, 'error' => 'Client not found'])); }

$invStmt = $pdo->prepare("SELECT id, invoice_num, status, total, created_at FROM td_invoices WHERE client_id = ? ORDER BY created_at DESC");
$invStmt->execute([$id]);
$invoices = $invStmt->fetchAll();

$propStmt = $pdo->prepare("SELECT id, proposal_num, campaign_name, status, total, created_at FROM td_proposals WHERE client_id = ? ORDER BY created_at DESC");
$propStmt->execute([$id]);
$proposals = $propStmt->fetchAll();

echo json_encode(['success' => true, 'client' => $client, 'invoices' => $invoices, 'proposals' => $proposals]);
