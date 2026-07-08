<?php
// ═══════════════════════════════════════════
// TaterDash — Save Settings
// POST: any subset of the keys in $allowed below
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$data    = json_decode(file_get_contents('php://input'), true);
$allowed = [
    'company_name', 'company_email', 'company_address',
    'stat_followers', 'stat_impressions', 'stat_audience_age', 'stat_audience_age_label',
    'stat_audience_women', 'stat_audience_men', 'stat_partnerships',
    'contact_email', 'instagram_handle',
    'payment_terms_days', 'proposal_validity_days', 'deposit_percent',
    'about_blurb',
];

$pdo = db_connect();

try {
    $stmt = $pdo->prepare("
        INSERT INTO td_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $stmt->execute([$key, trim((string)$data[$key])]);
        }
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    log_php_error($pdo, 'save-settings', $e, $data);
    echo json_encode(['success' => false, 'error' => 'Something went wrong — it has been logged.']);
}
