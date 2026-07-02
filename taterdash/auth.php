<?php
// ═══════════════════════════════════════════
// TaterDash — Auth
// ═══════════════════════════════════════════

// Hardcoded users — passwords are bcrypt hashes
// To generate: <?php echo password_hash('yourpassword', PASSWORD_DEFAULT); ?>
$TATERDASH_USERS = [
    'gina' => '$2y$12$REPLACE_WITH_GINA_HASH',
    'miu'  => '$2y$12$REPLACE_WITH_MIU_HASH',
];

function is_logged_in(): bool {
    return !empty($_SESSION['td_user']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /taterdash-app/login.php');
        exit;
    }
}

function attempt_login(string $username, string $password): bool {
    global $TATERDASH_USERS;
    $username = strtolower(trim($username));
    if (!isset($TATERDASH_USERS[$username])) return false;
    if (!password_verify($password, $TATERDASH_USERS[$username])) return false;
    $_SESSION['td_user'] = $username;
    return true;
}
