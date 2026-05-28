<?php
if (!defined('DB_HOST')) {
    $cfgPath = __DIR__ . '/../config.php';
    if (!file_exists($cfgPath)) {
        header('Location: /install.php');
        exit;
    }
    require_once $cfgPath;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . DB_NAME;
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
    }
    return $pdo;
}

function db_get(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_run(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}
