<?php
// ═══════════════════════════════════════════
// TaterDash — Get Settings (for client-side previews, e.g. new-invoice.html)
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$pdo = db_connect();

echo json_encode(['success' => true, 'settings' => get_settings($pdo)]);
