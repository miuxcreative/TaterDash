<?php
// ═══════════════════════════════════════════
// TaterDash — Database Configuration EXAMPLE
// Copy this file to config.php
// Fill in your real values in config.php
// NEVER commit config.php to git
// ═══════════════════════════════════════════

define('DB_HOST',    'localhost');
define('DB_NAME',    'u335521326_TaterDash_db');
define('DB_USER',    'u335521326_taterdash_user');
define('DB_PASS',    'YOUR_PASSWORD_HERE');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',        'https://mallowfrenchie.com');
define('ADMIN_PASSWORD',  'YOUR_ADMIN_PASSWORD_HERE');
define('STRIPE_PAYMENT_URL', '');

function db_connect() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
    }
}

function generate_invoice_num($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM td_invoices WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn();
    return 'INV-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

function generate_slug($client_name) {
    $slug = strtolower(trim($client_name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug . '-' . date('Y-m');
}
