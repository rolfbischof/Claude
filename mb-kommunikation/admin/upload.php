<?php
/**
 * mb Kommunikation + Events – Admin AJAX File Upload Handler
 * Returns JSON: {success: true, url: '...', filename: '...'}
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Always return JSON
header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt.']);
    exit;
}

// Require editor+ role
$auth->requireLogin('/login.php');
if (!$auth->hasRole('editor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']);
    exit;
}

// Verify CSRF
$token = $_POST['csrf_token'] ?? '';
if (!$auth->verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültiger CSRF-Token.']);
    exit;
}

// Check file upload exists
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Datei zu gross (server limit).',
        UPLOAD_ERR_FORM_SIZE  => 'Datei zu gross (form limit).',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei empfangen.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt.',
        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden.',
        UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Erweiterung blockiert.',
    ];
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg  = $uploadErrors[$errCode] ?? 'Upload-Fehler (Code '.$errCode.').';
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

$file = $_FILES['file'];

// ── Validate MIME type via finfo (not just the browser-reported type) ──────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];

if (!array_key_exists($mimeType, $allowedMimes)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Ungültiger Dateityp: ' . $mimeType . '. Erlaubt: JPG, PNG, GIF, WebP, SVG.',
    ]);
    exit;
}

// ── Validate file extension ────────────────────────────────────────────────
$originalName = $file['name'];
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

if (!in_array($ext, $allowedExts)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Ungültige Dateiendung. Erlaubt: ' . implode(', ', $allowedExts) . '.',
    ]);
    exit;
}

// Use extension derived from MIME (safer than user-provided)
$safeExt = $allowedMimes[$mimeType];

// ── Validate file size ────────────────────────────────────────────────────
if ($file['size'] > MAX_UPLOAD_SIZE) {
    echo json_encode([
        'success' => false,
        'error'   => 'Datei zu gross. Maximum: ' . MAX_UPLOAD_SIZE_LABEL . '.',
    ]);
    exit;
}

// ── Determine upload subdirectory ─────────────────────────────────────────
$typeMap = [
    'events'     => 'events',
    'references' => 'references',
    'pages'      => 'pages',
    'general'    => 'general',
];
$requestedType = $_POST['type'] ?? 'general';
$subDir        = $typeMap[$requestedType] ?? 'general';

$uploadDir = UPLOAD_PATH . $subDir . '/';
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Upload-Verzeichnis konnte nicht erstellt werden.']);
        exit;
    }
}

// ── Generate unique safe filename ─────────────────────────────────────────
$baseName    = pathinfo($originalName, PATHINFO_FILENAME);
$safeBase    = preg_replace('/[^a-z0-9_-]/', '-', strtolower($baseName));
$safeBase    = preg_replace('/-+/', '-', trim($safeBase, '-'));
$safeBase    = $safeBase ?: 'upload';
$uniqueSlug  = substr($safeBase, 0, 40) . '_' . bin2hex(random_bytes(4));
$filename    = $uniqueSlug . '.' . $safeExt;
$destination = $uploadDir . $filename;

// ── Additional security: validate image content (except SVG) ──────────────
if ($mimeType !== 'image/svg+xml') {
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        echo json_encode(['success' => false, 'error' => 'Ungültige Bilddatei.']);
        exit;
    }
}

// SVG: strip potentially dangerous content
if ($mimeType === 'image/svg+xml') {
    $svgContent = file_get_contents($file['tmp_name']);
    // Remove script tags and event attributes
    $svgContent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svgContent);
    $svgContent = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $svgContent);
    if (file_put_contents($destination, $svgContent) === false) {
        echo json_encode(['success' => false, 'error' => 'SVG konnte nicht gespeichert werden.']);
        exit;
    }
} else {
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden.']);
        exit;
    }
}

// ── Build public URL ──────────────────────────────────────────────────────
$url = UPLOAD_URL . $subDir . '/' . $filename;

echo json_encode([
    'success'  => true,
    'url'      => $url,
    'filename' => $filename,
    'size'     => $file['size'],
    'type'     => $mimeType,
]);
