<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if (is_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ungültige Anfrage.';
    } elseif (attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        redirect('/admin/dashboard.php');
    } else {
        $error = 'Benutzername oder Passwort falsch.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin-Login – Proimprove</title>
<link rel="stylesheet" href="/admin/css/admin.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">
    <span class="logo-pro">Pro</span><span class="logo-improve">improve</span>
  </div>
  <h1 class="login-title">Admin-Login</h1>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif ?>
  <form method="POST" action="/admin/login.php">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>Benutzername</label>
      <input type="text" name="username" autofocus autocomplete="username" required>
    </div>
    <div class="form-group">
      <label>Passwort</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary btn-full" type="submit">Anmelden</button>
  </form>
  <p style="text-align:center;margin-top:20px"><a href="/" style="color:#64748b;font-size:.85rem">← Zurück zur Website</a></p>
</div>
</body>
</html>
