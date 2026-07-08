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

define('RESEND_API_KEY',  'YOUR_RESEND_API_KEY_HERE');
define('MAIL_FROM',       'Mallow Frenchie <hello@mallowfrenchie.com>');
define('NOTIFY_EMAIL',    'mallowfrenchie@gmail.com');

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
    $prefix = 'INV-' . $year . '-%';
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_num, '-', -1) AS UNSIGNED)) FROM td_invoices WHERE invoice_num LIKE ?");
    $stmt->execute([$prefix]);
    $max = intval($stmt->fetchColumn());
    return 'INV-' . $year . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

function generate_proposal_num($pdo) {
    $year = date('Y');
    $prefix = 'PROP-' . $year . '-%';
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(proposal_num, '-', -1) AS UNSIGNED)) FROM td_proposals WHERE proposal_num LIKE ?");
    $stmt->execute([$prefix]);
    $max = intval($stmt->fetchColumn());
    return 'PROP-' . $year . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

function log_event(PDO $pdo, string $event_type, string $entity_type, int $entity_id, string $entity_num, string $entity_name, ?float $amount = null): void {
    $pdo->prepare("
        INSERT INTO td_activity (event_type, entity_type, entity_id, entity_num, entity_name, amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$event_type, $entity_type, $entity_id, $entity_num, $entity_name, $amount]);
}

function log_php_error(PDO $pdo, string $context, Throwable $e, array $requestData = []): void {
    try {
        $pdo->prepare("
            INSERT INTO td_error_log (context, message, request_data)
            VALUES (?, ?, ?)
        ")->execute([$context, $e->getMessage(), json_encode($requestData)]);
    } catch (Throwable $ignored) {
        // Never let logging failure mask the original error response.
    }
}

function generate_slug($client_name) {
    $slug = strtolower(trim($client_name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug . '-' . date('Y-m');
}

function generate_token() {
    return bin2hex(random_bytes(16));
}

function get_settings(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $values = [
        'company_name'             => 'Mallow Frenchie',
        'company_email'            => 'mallowfrenchie@gmail.com',
        'company_address'          => '',
        'stat_followers'           => '',
        'stat_impressions'         => '',
        'stat_audience_age'        => '',
        'stat_audience_age_label'  => '',
        'stat_audience_women'      => '',
        'stat_audience_men'        => '',
        'stat_partnerships'        => '',
        'contact_email'            => 'mallowfrenchie@gmail.com',
        'instagram_handle'         => '@mallowfrenchie',
        'payment_terms_days'       => '14',
        'proposal_validity_days'   => '30',
        'deposit_percent'          => '50',
        'about_blurb'              => '',
    ];
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM td_settings")->fetchAll();
        foreach ($rows as $row) {
            $values[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // td_settings may not exist yet on an unmigrated install — fall back to defaults.
    }
    $cache = $values;
    return $cache;
}

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $settings = get_settings($pdo);
    return $settings[$key] ?? $default;
}

function send_email(string $to, string $subject, string $html, ?string $attachmentPath = null, ?string $attachmentName = null): bool {
    $payload = [
        'from'    => MAIL_FROM,
        'to'      => $to,
        'subject' => $subject,
        'html'    => $html,
    ];
    if ($attachmentPath && is_readable($attachmentPath)) {
        $payload['attachments'] = [[
            'filename' => $attachmentName ?: basename($attachmentPath),
            'content'  => base64_encode(file_get_contents($attachmentPath)),
        ]];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 15,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode < 200 || $httpCode >= 300) {
        try {
            $pdo = db_connect();
            log_php_error($pdo, 'send_email', new Exception($curlError ?: "Resend API returned HTTP $httpCode: $response"), ['to' => $to, 'subject' => $subject]);
        } catch (Throwable $ignored) {
            // Never let logging failure mask the original error, or throw from send_email().
        }
        return false;
    }
    return true;
}
