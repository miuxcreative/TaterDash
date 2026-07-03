<?php
// ═══════════════════════════════════════════
// TaterDash — Get Clients (for autocomplete + clients list)
// ═══════════════════════════════════════════

session_start();
if (empty($_SESSION['td_user'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$pdo = db_connect();

$clients = $pdo->query("
    SELECT c.id, c.company, c.contact_name, c.email, c.phone, c.notes, c.created_at,
        (SELECT COALESCE(SUM(total),0) FROM td_invoices  WHERE client_id = c.id AND status = 'paid') AS invoiced_paid,
        (SELECT COALESCE(SUM(total),0) FROM td_proposals WHERE client_id = c.id AND status IN ('signed','accepted')) AS signed_total,
        (SELECT COUNT(*) FROM td_invoices  WHERE client_id = c.id) AS invoice_count,
        (SELECT COUNT(*) FROM td_proposals WHERE client_id = c.id) AS proposal_count,
        (SELECT COUNT(*) FROM td_invoices  WHERE client_id = c.id AND status IN ('sent','viewed')) AS invoice_active_count,
        (SELECT COUNT(*) FROM td_proposals WHERE client_id = c.id AND status IN ('sent','viewed')) AS proposal_active_count,
        GREATEST(
            COALESCE((SELECT MAX(created_at) FROM td_invoices  WHERE client_id = c.id), '1970-01-01'),
            COALESCE((SELECT MAX(created_at) FROM td_proposals WHERE client_id = c.id), '1970-01-01'),
            c.created_at
        ) AS last_activity_at
    FROM td_clients c
    ORDER BY c.company ASC
")->fetchAll();

echo json_encode(['success' => true, 'clients' => $clients]);
