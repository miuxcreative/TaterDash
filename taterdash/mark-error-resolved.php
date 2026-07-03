<?php
session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$pdo  = db_connect();

if (!empty($data['all'])) {
    $pdo->exec("UPDATE td_error_log SET is_resolved = 1 WHERE is_resolved = 0");
} else {
    $id = intval($data['id'] ?? 0);
    if (!$id) { die(json_encode(['success' => false, 'error' => 'Missing id'])); }
    $pdo->prepare("UPDATE td_error_log SET is_resolved = 1 WHERE id = ?")->execute([$id]);
}
echo json_encode(['success' => true]);
