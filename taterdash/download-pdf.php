<?php
// ═══════════════════════════════════════════
// TaterDash — Download Signed Proposal PDF
// GET: ?id=X
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die('Unauthorized');
}

require_once __DIR__ . '/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('Missing id');
}

$pdo = db_connect();
$stmt = $pdo->prepare("SELECT proposal_num, pdf_path FROM td_proposals WHERE id = ?");
$stmt->execute([$id]);
$proposal = $stmt->fetch();

if (!$proposal || !$proposal['pdf_path'] || !is_readable($proposal['pdf_path'])) {
    http_response_code(404);
    die('PDF not found');
}

$path     = $proposal['pdf_path'];
$filename = basename($path);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
