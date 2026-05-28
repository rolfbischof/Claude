<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function auth_required(string $loginUrl = '/admin/login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

function attempt_login(string $username, string $password): bool {
    require_once __DIR__ . '/db.php';
    $user = db_get('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1', [$username]);
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $user['id'];
        $_SESSION['admin_user'] = $username;
        return true;
    }
    return false;
}

function do_logout(): void {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

function current_user(): string {
    return $_SESSION['admin_user'] ?? '';
}
