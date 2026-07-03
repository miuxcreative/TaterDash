<?php
// ═══════════════════════════════════════════
// TaterDash — Add / Edit Client
// POST: { id?, company, contact_name?, email, phone?, notes? }
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'), true);

$id           = intval($data['id'] ?? 0) ?: null;
$company      = trim($data['company']      ?? '');
$contact_name = trim($data['contact_name']  ?? '');
$email        = trim($data['email']        ?? '');
$phone        = trim($data['phone']         ?? '');
$notes        = trim($data['notes']         ?? '');

if (!$company || !$email) {
    echo json_encode(['success' => false, 'error' => 'Company and email are required']);
    exit;
}

$pdo = db_connect();

try {
    if ($id) {
        $pdo->prepare("
            UPDATE td_clients SET company=?, contact_name=?, email=?, phone=?, notes=?
            WHERE id=?
        ")->execute([$company, $contact_name ?: null, $email, $phone ?: null, $notes ?: null, $id]);
    } else {
        $existing = $pdo->prepare("SELECT id FROM td_clients WHERE email = ?");
        $existing->execute([$email]);
        if ($row = $existing->fetch()) {
            $id = $row['id'];
            $pdo->prepare("
                UPDATE td_clients SET company=?, contact_name=?, phone=?, notes=?
                WHERE id=?
            ")->execute([$company, $contact_name ?: null, $phone ?: null, $notes ?: null, $id]);
        } else {
            $pdo->prepare("
                INSERT INTO td_clients (company, contact_name, email, phone, notes)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$company, $contact_name ?: null, $email, $phone ?: null, $notes ?: null]);
            $id = $pdo->lastInsertId();
        }
    }
    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
