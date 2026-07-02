<?php
session_start();
if (empty($_SESSION['td_user'])) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data        = json_decode(file_get_contents('php://input'), true);
$proposal_id = intval($data['proposal_id'] ?? 0);

if (!$proposal_id) {
    echo json_encode(['success' => false, 'error' => 'Missing proposal_id']);
    exit;
}

try {
    $pdo = db_connect();

    $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE id = ? AND status IN ('signed','accepted')");
    $stmt->execute([$proposal_id]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        echo json_encode(['success' => false, 'error' => 'Proposal not found or not yet signed']);
        exit;
    }

    $invoice_num = generate_invoice_num($pdo);
    $notes       = $proposal['campaign_name'] ?: $proposal['proposal_num'];

    $pdo->prepare("
        INSERT INTO td_invoices (client_name, client_email, invoice_num, issue_date, due_date, notes, total, status, created_at)
        VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, 'draft', NOW())
    ")->execute([
        $proposal['client_name'],
        $proposal['client_email'],
        $invoice_num,
        $notes,
        $proposal['total'],
    ]);

    $invoice_id = (int) $pdo->lastInsertId();

    $description = $proposal['campaign_name'] ?: 'Campaign';
    $line_note   = 'Created from proposal ' . $proposal['proposal_num'];

    $pdo->prepare("
        INSERT INTO td_line_items (invoice_id, description, note, quantity, unit_price)
        VALUES (?, ?, ?, 1, ?)
    ")->execute([
        $invoice_id,
        $description,
        $line_note,
        $proposal['total'],
    ]);

    echo json_encode([
        'success'     => true,
        'invoice_id'  => $invoice_id,
        'invoice_num' => $invoice_num,
        'edit_url'    => '/taterdash-app/admin/edit-invoice.php?id=' . $invoice_id,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
