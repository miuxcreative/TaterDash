<?php
session_start();
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['td_user'])) {
    header('Location: /taterdash-app/admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (attempt_login($username, $password)) {
        header('Location: /taterdash-app/admin/');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaterDash — Login</title>
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700,800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --ink:     #191919;
      --ink-mid: #6b6b6b;
      --white:   #ffffff;
      --blush:   #faf0f0;
      --pink:    #e04d80;
      --dark:    #111111;
      --border:  #e8e8e8;
    }
    html, body {
      height: 100%;
      font-family: 'Satoshi', sans-serif;
      background: var(--blush);
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
    }
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 24px;
    }
    .card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 48px 40px 40px;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 8px 40px rgba(0,0,0,0.07);
    }
    .brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 32px;
    }
    .brand img {
      height: 48px;
      width: auto;
      margin-bottom: 14px;
    }
    .brand-name {
      font-size: 18px;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--dark);
    }
    .brand-sub {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--pink);
      margin-top: 3px;
    }
    .field {
      display: flex;
      flex-direction: column;
      gap: 5px;
      margin-bottom: 14px;
    }
    .field label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-mid);
    }
    .field input {
      font-family: 'Satoshi', sans-serif;
      font-size: 14px;
      color: var(--ink);
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 11px 14px;
      outline: none;
      transition: border-color 0.15s;
      width: 100%;
    }
    .field input:focus { border-color: var(--pink); }
    .error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      font-size: 13px;
      font-weight: 500;
      padding: 10px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
    }
    .btn {
      font-family: 'Satoshi', sans-serif;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      background: var(--pink);
      color: var(--white);
      border: none;
      border-radius: 999px;
      padding: 13px;
      width: 100%;
      cursor: pointer;
      margin-top: 8px;
      transition: background 0.15s;
    }
    .btn:hover { background: #c83870; }
    .footer {
      text-align: center;
      margin-top: 28px;
      font-size: 11px;
      color: var(--ink-mid);
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <img src="https://miuxcreative.github.io/mallowfrenchie/images/MallowFrenchieLogoImage.png"
           alt="Mallow Frenchie"
           onerror="this.style.display='none'">
      <div class="brand-name">TaterDash</div>
      <div class="brand-sub">MallowFrenchie</div>
    </div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" autocomplete="username" autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password">
      </div>
      <button class="btn" type="submit">Sign In</button>
    </form>

    <div class="footer">TaterDash v1.0 · MallowFrenchie</div>
  </div>
</body>
</html>
