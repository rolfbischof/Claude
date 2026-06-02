<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals(csrf_token(), $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function flash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = db_get('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1', [$key]);
        $cache[$key] = $row ? $row['value'] : $default;
    }
    return $cache[$key];
}

function save_setting(string $key, string $value): void {
    db_run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
}

function section_get(string $key): array {
    return db_get('SELECT * FROM sections WHERE section_key = ? LIMIT 1', [$key]) ?? [];
}

function allowed_image(string $mime): bool {
    return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
