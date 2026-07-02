<?php
// ═══════════════════════════════════════════
// TaterDash — Update Proposal
// /taterdash-app/taterdash/update-proposal.php
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

$proposal_id        = intval($data['proposal_id']  ?? 0);
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

if (!$proposal_id || !$client_name || !$client_email) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $pdo = db_connect();

    $check = $pdo->prepare("SELECT id FROM td_proposals WHERE id = ?");
    $check->execute([$proposal_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Proposal not found']);
        exit;
    }

    $pdo->prepare("
        UPDATE td_proposals SET
            client_name        = ?,
            client_email       = ?,
            campaign_name      = ?,
            platform           = ?,
            campaign_start     = ?,
            campaign_end       = ?,
            package_id         = ?,
            deliverables       = ?,
            total              = ?,
            notes              = ?,
            partner_industries = ?,
            expiry_date        = ?,
            updated_at         = NOW()
        WHERE id = ?
    ")->execute([
        $client_name, $client_email, $campaign_name, $platform,
        $campaign_start, $campaign_end, $package_id, $deliverables,
        $total, $notes, $partner_industries, $expiry_date,
        $proposal_id
    ]);

    echo json_encode([
        'success' => true,
        'url'     => SITE_URL . '/proposal/?id=' . $proposal_id,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
