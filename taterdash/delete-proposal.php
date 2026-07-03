<?php
session_start();
if (empty($_SESSION['td_user'])) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['proposal_id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'error'=>'Missing id']); exit; }
try {
    $pdo = db_connect();
    $prop = $pdo->prepare("SELECT proposal_num, client_name FROM td_proposals WHERE id = ?");
    $prop->execute([$id]);
    $proposal = $prop->fetch();
    if ($proposal) {
        log_event($pdo, 'deleted', 'proposal', $id, $proposal['proposal_num'], $proposal['client_name']);
    }
    $pdo->prepare("DELETE FROM td_signatures WHERE proposal_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM td_proposals WHERE id = ?")->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    log_php_error($pdo, 'delete-proposal', $e, $data);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
