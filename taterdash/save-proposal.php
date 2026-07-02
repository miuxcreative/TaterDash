<?php
// ═══════════════════════════════════════════
// TaterDash — Save Proposal
// /taterdash-app/taterdash/save-proposal.php
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);

$client_name        = trim($data['client_name']    ?? '');
$client_email       = trim($data['client_email']   ?? '');
$campaign_name      = trim($data['campaign_name']  ?? '');
$platform           = $data['platform']            ?? 'Instagram';
$campaign_start     = $data['campaign_start']      ?: null;
$campaign_end       = $data['campaign_end']        ?: null;
$package_id         = intval($data['package_id']   ?? 0) ?: null;
$deliverables       = json_encode($data['deliverables']        ?? []);
$total              = floatval($data['total']      ?? 0);
$notes              = trim($data['notes']          ?? '');
$partner_industries = json_encode($data['partner_industries']  ?? []);
$expiry_date        = $data['expiry_date']         ?: null;
$issue_date         = date('Y-m-d');

if (!$client_name || !$client_email) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $pdo = db_connect();
    $proposal_num = generate_proposal_num($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO td_proposals
            (proposal_num, client_name, client_email, campaign_name, platform,
             campaign_start, campaign_end, package_id, deliverables, total,
             notes, partner_industries, expiry_date, issue_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([
        $proposal_num, $client_name, $client_email, $campaign_name, $platform,
        $campaign_start, $campaign_end, $package_id, $deliverables, $total,
        $notes, $partner_industries, $expiry_date, $issue_date
    ]);
    $proposal_id = $pdo->lastInsertId();

    echo json_encode([
        'success'      => true,
        'proposal_id'  => $proposal_id,
        'proposal_num' => $proposal_num,
        'url'          => SITE_URL . '/proposal/?id=' . $proposal_id,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
