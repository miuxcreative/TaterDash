<?php
// ═══════════════════════════════════════════
// TaterDash — Get Activity (paginated, for All Activity page "Load older")
// GET: ?offset=N&limit=N
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$offset = max(0, intval($_GET['offset'] ?? 0));
$limit  = min(100, max(1, intval($_GET['limit'] ?? 50)));

$pdo = db_connect();

$stmt = $pdo->prepare("SELECT * FROM td_activity ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$total = (int) $pdo->query("SELECT COUNT(*) FROM td_activity")->fetchColumn();

echo json_encode(['success' => true, 'events' => $rows, 'total' => $total]);
