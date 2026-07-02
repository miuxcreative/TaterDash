<?php
// ═══════════════════════════════════════════
// TaterDash — Auth
// ═══════════════════════════════════════════

// Hardcoded users — passwords are bcrypt hashes
// To generate: <?php echo password_hash('yourpassword', PASSWORD_DEFAULT); ?>
$TATERDASH_USERS = [
    'gina' => '$2y$12$P0BbLvV4Kb1BfE1m10dKyeaJ54c1hcIvGx00mxCwTbYXj9Uc4zPPW',
    'miu'  => '$2y$12$8uZO4fFoNTnjGoTI4KCan.EhKFrvRKDen2RF6HBOWA.I6IJlMwm6m',
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
