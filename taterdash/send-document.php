<?php
// ═══════════════════════════════════════════
// TaterDash — Send Document (invoice/proposal) via email
// POST: { type:'invoice'|'proposal', id, to_email, personal_note? }
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email-templates.php';

$data          = json_decode(file_get_contents('php://input'), true);
$type          = $data['type'] ?? '';
$id            = intval($data['id'] ?? 0);
$to_email      = trim($data['to_email'] ?? '');
$personal_note = trim($data['personal_note'] ?? '');

if (!$id || !in_array($type, ['invoice', 'proposal'], true) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid parameters']));
}

$pdo = db_connect();

try {
    if ($type === 'invoice') {
        $stmt = $pdo->prepare("SELECT * FROM td_invoices WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            http_response_code(404);
            die(json_encode(['success' => false, 'error' => 'Invoice not found']));
        }
        $url     = SITE_URL . '/invoice/?t=' . $record['token'];
        $html    = email_invoice_sent($record, $url);
        $subject = 'Invoice ' . $record['invoice_num'] . ' from Mallow Frenchie';
        $table   = 'td_invoices';
        $numCol  = 'invoice_num';
        $amount  = (float) $record['total'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM td_proposals WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            http_response_code(404);
            die(json_encode(['success' => false, 'error' => 'Proposal not found']));
        }
        $url     = SITE_URL . '/proposal/?t=' . $record['token'];
        $html    = email_proposal_sent($record, $url);
        $subject = 'Partnership Proposal from Mallow Frenchie';
        $table   = 'td_proposals';
        $numCol  = 'proposal_num';
        $amount  = null;
    }

    if ($personal_note !== '') {
        $html = '<p style="font-style:italic;color:#6b6b6b;font-size:13px;line-height:1.6;margin:0 0 18px;font-family:\'Satoshi\',Helvetica,Arial,sans-serif;">'
              . nl2br(htmlspecialchars($personal_note)) . '</p>' . $html;
    }

    if (!send_email($to_email, $subject, $html)) {
        http_response_code(502);
        die(json_encode(['success' => false, 'error' => 'Failed to send email']));
    }

    // ── Status transition: draft -> sent (guarded); otherwise log 'resent', no status change ──
    $newStatus = $record['status'];
    if ($record['status'] === 'draft') {
        $updatedAtSql = $type === 'proposal' ? ", updated_at = NOW()" : "";
        $upd = $pdo->prepare("UPDATE $table SET status = 'sent'$updatedAtSql WHERE id = ? AND status = 'draft'");
        $upd->execute([$id]);
        if ($upd->rowCount() > 0) {
            log_event($pdo, 'sent', $type, $id, $record[$numCol], $record['client_name'], $amount);
            $newStatus = 'sent';
        } else {
            // Lost the race to another request — treat as a resend, not a duplicate 'sent'.
            log_event($pdo, 'resent', $type, $id, $record[$numCol], $record['client_name'], $amount);
        }
    } else {
        log_event($pdo, 'resent', $type, $id, $record[$numCol], $record['client_name'], $amount);
    }

    echo json_encode(['success' => true, 'status' => $newStatus]);

} catch (Exception $e) {
    log_php_error($pdo, 'send-document', $e, $data);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong — it has been logged.']);
}
