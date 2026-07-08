<?php
// ═══════════════════════════════════════════
// TaterDash — Sign Proposal
// /taterdash-app/taterdash/sign-proposal.php
// ═══════════════════════════════════════════

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email-templates.php';
require_once __DIR__ . '/pdf-proposal.php';

$data = json_decode(file_get_contents('php://input'), true);

$proposal_id     = intval($data['proposal_id']  ?? 0);
$signer_name     = trim($data['signer_name']    ?? '');
$signer_email_in = trim($data['signer_email']   ?? '');
$signature_image = $data['signature_image']     ?? '';

if (!$proposal_id || !$signer_name) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Only accept a well-formed PNG data URL — anything else is stored as null rather than trusted as-is.
if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature_image)) {
    $signature_image = null;
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

    $ip           = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_direct    = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $signed_at    = date('Y-m-d H:i:s');
    $signer_email = filter_var($signer_email_in, FILTER_VALIDATE_EMAIL) ? $signer_email_in : $proposal['client_email'];

    $pdo->prepare("
        INSERT INTO td_signatures (proposal_id, signer_name, signer_email, signature_image, signed_at, ip_address, ip_direct, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$proposal_id, $signer_name, $signer_email, $signature_image, $signed_at, $ip, $ip_direct, $user_agent]);

    $pdo->prepare("UPDATE td_proposals SET status = 'signed' WHERE id = ?")
        ->execute([$proposal_id]);

    log_event($pdo, 'signed', 'proposal', $proposal_id, $proposal['proposal_num'], $proposal['client_name']);

    // Best-effort — render_proposal_pdf() and send_email() both log their own
    // failures and never throw, so neither can block the signature response.
    $pdfPath = render_proposal_pdf($pdo, $proposal_id);
    if ($pdfPath) {
        $pdo->prepare("UPDATE td_proposals SET pdf_path = ? WHERE id = ?")->execute([$pdfPath, $proposal_id]);
    }

    send_email($signer_email, 'Proposal Signed — ' . $proposal['proposal_num'], email_proposal_signed_client($proposal), $pdfPath);
    if (defined('NOTIFY_EMAIL') && NOTIFY_EMAIL) {
        send_email(NOTIFY_EMAIL, 'Proposal Signed by ' . $signer_name, email_proposal_signed_notify($proposal, $signer_name));
    }

    echo json_encode([
        'success'    => true,
        'signed_at'  => $signed_at,
        'signer'     => $signer_name,
    ]);
} catch (Exception $e) {
    log_php_error($pdo, 'sign-proposal', $e, $data);
    echo json_encode(['success' => false, 'error' => 'Something went wrong — it has been logged.']);
}
