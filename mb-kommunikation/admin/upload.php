<?php
/**
 * mb Kommunikation + Events
 * AJAX file upload handler – returns JSON only, no HTML layout.
 *
 * Accepted:  POST multipart/form-data
 *   file        – the uploaded image file (required)
 *   csrf_token  – CSRF token from the session
 *   type        – context hint: 'events' | 'references' | 'pages' | anything else → 'general'
 *
 * Response JSON (success):
 *   {"success": true, "url": "/uploads/events/abc123.jpg", "filename": "abc123.jpg"}
 *
 * Response JSON (error):
 *   {"success": false, "error": "human-readable error message"}
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';

// ── Always respond with JSON ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Inline helper so we do not need functions.php ─────────────────────────────
function uploadJsonOut(array $payload, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Only handle POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadJsonOut(['success' => false, 'error' => 'Nur POST-Anfragen sind erlaubt.'], 405);
}

// ── Role check: editor+ required ─────────────────────────────────────────────
// requireRole() redirects on failure; we need JSON. Use hasRole() directly.
if (!$auth->isLoggedIn()) {
    uploadJsonOut(['success' => false, 'error' => 'Nicht angemeldet.'], 401);
}
if (!$auth->hasRole('editor')) {
    uploadJsonOut(['success' => false, 'error' => 'Zugriff verweigert. Mindestrolle: editor.'], 403);
}

// ── CSRF check ────────────────────────────────────────────────────────────────
// Accept token from POST field or X-CSRF-Token header (for fetch() callers).
$submittedToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!$auth->validateCsrfToken($submittedToken)) {
    uploadJsonOut(['success' => false, 'error' => 'Ungültiges Sicherheitstoken (CSRF).'], 403);
}

// ── Verify a file was submitted ───────────────────────────────────────────────
if (!isset($_FILES['file'])) {
    uploadJsonOut(['success' => false, 'error' => 'Kein Datei-Feld im Request vorhanden.'], 400);
}

$file = $_FILES['file'];

// ── PHP upload error codes ────────────────────────────────────────────────────
if ($file['error'] !== UPLOAD_ERR_OK) {
    $phpErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Die Datei überschreitet das PHP-Serverlimit (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'Die Datei überschreitet das Formularlimit (MAX_FILE_SIZE).',
        UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei ausgewählt.',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis auf dem Server vorhanden.',
        UPLOAD_ERR_CANT_WRITE => 'Die Datei konnte nicht auf den Server geschrieben werden.',
        UPLOAD_ERR_EXTENSION  => 'Der Upload wurde durch eine PHP-Erweiterung abgebrochen.',
    ];
    $errMsg = $phpErrors[$file['error']] ?? 'Unbekannter Upload-Fehler (Code ' . $file['error'] . ').';
    uploadJsonOut(['success' => false, 'error' => $errMsg], 400);
}

// ── Size check ────────────────────────────────────────────────────────────────
if ($file['size'] > MAX_UPLOAD_SIZE) {
    uploadJsonOut([
        'success' => false,
        'error'   => 'Die Datei ist zu gross. Maximal erlaubt: ' . MAX_UPLOAD_SIZE_LABEL . '.',
    ], 413);
}

if ($file['size'] === 0) {
    uploadJsonOut(['success' => false, 'error' => 'Die hochgeladene Datei ist leer.'], 400);
}

// ── MIME type validation (inspects file content via libmagic, not HTTP header) ─
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if ($mimeType === false || !array_key_exists($mimeType, $allowedMimeTypes)) {
    uploadJsonOut([
        'success' => false,
        'error'   => 'Ungültiger Dateityp. Erlaubt: JPEG, PNG, GIF, WebP.',
    ], 415);
}

// Derive extension from the detected MIME type (ignore client-supplied filename)
$extension = $allowedMimeTypes[$mimeType];

// ── Additional image integrity check (getimagesize reads image headers) ───────
if (@getimagesize($file['tmp_name']) === false) {
    uploadJsonOut(['success' => false, 'error' => 'Die Datei ist keine gültige Bilddatei.'], 415);
}

// ── Determine upload sub-directory ───────────────────────────────────────────
$allowedSubdirs = ['events', 'references', 'pages'];
$requestedType  = trim($_POST['type'] ?? '');
$subdir         = in_array($requestedType, $allowedSubdirs, true) ? $requestedType : 'general';

// ── Build destination path ────────────────────────────────────────────────────
$destDir = rtrim(UPLOAD_PATH, '/') . '/' . $subdir . '/';

if (!is_dir($destDir)) {
    if (!@mkdir($destDir, 0755, true)) {
        error_log('[upload.php] mkdir failed: ' . $destDir);
        uploadJsonOut(['success' => false, 'error' => 'Upload-Verzeichnis konnte nicht erstellt werden.'], 500);
    }
}

// ── Generate a unique filename using uniqid() ─────────────────────────────────
$filename = uniqid('', true) . '.' . $extension;
$destPath = $destDir . $filename;

// Guard against the extremely unlikely collision
$attempts = 0;
while (file_exists($destPath) && $attempts < 10) {
    $filename = uniqid('', true) . '.' . $extension;
    $destPath = $destDir . $filename;
    $attempts++;
}

// ── Move the temp file to its permanent location ──────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log('[upload.php] move_uploaded_file failed: tmp=' . $file['tmp_name'] . ' dest=' . $destPath);
    uploadJsonOut(['success' => false, 'error' => 'Die Datei konnte nicht gespeichert werden.'], 500);
}

// ── Set restrictive file permissions ─────────────────────────────────────────
@chmod($destPath, 0644);

// ── Build root-relative public URL ───────────────────────────────────────────
// Use a root-relative path (not SITE_URL-based) so it works on HTTP and HTTPS alike.
$publicUrl = '/uploads/' . $subdir . '/' . $filename;

// ── Rotate CSRF token for the next request ───────────────────────────────────
// generateCsrfToken / getCsrfToken will produce a fresh token stored in the session.
// The calling page should refresh its token from the response or a subsequent request.
// (Auth::validateCsrfToken does not auto-rotate; rotation happens on requireCsrf/verifyCsrf.)

// ── Success response ──────────────────────────────────────────────────────────
uploadJsonOut([
    'success'  => true,
    'url'      => $publicUrl,
    'filename' => $filename,
]);
