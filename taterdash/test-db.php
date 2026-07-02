<?php
require_once __DIR__ . '/config.php';
try {
    $pdo = db_connect();
    echo 'DB connected OK';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
