<?php
header('Content-Type: application/json; charset=utf-8');

$cfgPath = __DIR__ . '/../config.php';
if (!file_exists($cfgPath)) {
    echo json_encode(['ok' => false, 'msg' => 'Konfiguration fehlt.']);
    exit;
}

require_once $cfgPath;
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Methode nicht erlaubt.']);
    exit;
}

$vorname    = trim($_POST['vorname']    ?? '');
$nachname   = trim($_POST['nachname']   ?? '');
$email      = trim($_POST['email']      ?? '');
$unternehmen= trim($_POST['unternehmen']?? '');
$interesse  = trim($_POST['interesse']  ?? '');
$nachricht  = trim($_POST['nachricht']  ?? '');

if (!$vorname || !$nachname || !$email) {
    echo json_encode(['ok' => false, 'msg' => 'Bitte Name und E-Mail angeben.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Ungültige E-Mail-Adresse.']);
    exit;
}

// Basic rate limiting: max 3 submissions from same IP per hour
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';
$count = db_get('SELECT COUNT(*) c FROM contact_submissions WHERE ip_address=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$ip]);
if ($count && (int)$count['c'] >= 3) {
    echo json_encode(['ok' => false, 'msg' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.']);
    exit;
}

db_run('INSERT INTO contact_submissions (vorname,nachname,email,unternehmen,interesse,nachricht,ip_address)
        VALUES (?,?,?,?,?,?,?)',
    [$vorname, $nachname, $email, $unternehmen, $interesse, $nachricht, $ip]);

echo json_encode(['ok' => true, 'msg' => 'Vielen Dank! Wir melden uns innerhalb von 24 Stunden.']);
