<?php
// ═══════════════════════════════════════════
// TaterDash — Sign Proposal
// /taterdash-app/taterdash/sign-proposal.php
// ═══════════════════════════════════════════

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);

$proposal_id  = intval($data['proposal_id']  ?? 0);
$signer_name  = trim($data['signer_name']    ?? '');

if (!$proposal_id || !$signer_name) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $pdo = db_connect();

    // Fetch proposal
    $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE id = ?");
    $stmt->execute([$proposal_id]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        echo json_encode(['success' => false, 'error' => 'Proposal not found']);
        exit;
    }
    if ($proposal['status'] === 'signed') {
        echo json_encode(['success' => false, 'error' => 'Already signed']);
        exit;
    }
    if ($proposal['expiry_date'] && $proposal['expiry_date'] < date('Y-m-d')) {
        echo json_encode(['success' => false, 'error' => 'This proposal has expired']);
        exit;
    }

    $ip         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_direct  = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $signed_at  = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO td_signatures (proposal_id, signer_name, signer_email, signed_at, ip_address, ip_direct, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$proposal_id, $signer_name, $proposal['client_email'], $signed_at, $ip, $ip_direct, $user_agent]);

    $pdo->prepare("UPDATE td_proposals SET status = 'signed' WHERE id = ?")
        ->execute([$proposal_id]);

    log_event($pdo, 'signed', 'proposal', $proposal_id, $proposal['proposal_num'], $proposal['client_name']);

    echo json_encode([
        'success'    => true,
        'signed_at'  => $signed_at,
        'signer'     => $signer_name,
    ]);
} catch (Exception $e) {
    log_php_error($pdo, 'sign-proposal', $e, $data);
    echo json_encode(['success' => false, 'error' => 'Something went wrong — it has been logged.']);
}
