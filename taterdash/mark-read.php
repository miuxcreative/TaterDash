<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$data = json_decode(file_get_contents('php://input'), true);
$pdo  = db_connect();
if (!empty($data['all'])) {
    $pdo->prepare("UPDATE td_activity SET is_read=1 WHERE is_read=0")->execute();
} elseif (!empty($data['ids']) && is_array($data['ids'])) {
    $ids = array_map('intval', $data['ids']);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE td_activity SET is_read=1 WHERE id IN ($ph)")->execute($ids);
}
echo json_encode(['success' => true]);
