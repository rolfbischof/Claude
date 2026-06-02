<?php
/**
 * mb Kommunikation + Events
 * Global helper functions
 *
 * Covers: sanitization, redirects, date formatting, text truncation,
 * slug generation, settings lookup, file upload/delete, email sending,
 * pagination helpers, flash message rendering.
 *
 * Include once at the top of each page / controller:
 *   require_once __DIR__ . '/includes/functions.php';
 */

declare(strict_types=1);

if (!defined('DB_HOST')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/db.php';
}

// ====================================================================
// OUTPUT ESCAPING
// ====================================================================

/**
 * HTML-escape a string for safe output inside HTML documents.
 * Short alias: e($value)
 *
 * @param  string|int|float|null $value
 * @return string  HTML-safe string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ====================================================================
// INPUT SANITIZATION
// ====================================================================

/**
 * Strip HTML tags and trim whitespace from a string.
 * Use for plain-text fields like names, subjects, addresses.
 *
 * @param  string $value  Raw user input
 * @return string  Sanitized string
 */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

/**
 * Alias of sanitize() for legacy code.
 */
function sanitize_input(string $value): string
{
    return sanitize($value);
}

/**
 * Sanitize an integer value (keeps numeric sign).
 */
function sanitize_int(mixed $value): int
{
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Validate and sanitize an e-mail address.
 * Returns the sanitized address or an empty string on failure.
 */
function sanitize_email(string $email): string
{
    $email = trim(strtolower($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Sanitize a URL.
 * Returns the sanitized URL or an empty string on failure.
 */
function sanitize_url(string $url): string
{
    $url = trim($url);
    return filter_var($url, FILTER_VALIDATE_URL) ? filter_var($url, FILTER_SANITIZE_URL) : '';
}

// ====================================================================
// REDIRECT
// ====================================================================

/**
 * Issue an HTTP redirect and terminate the script.
 *
 * @param  string $url   Target URL (absolute or root-relative)
 * @param  int    $code  HTTP status code (default: 302 Found)
 * @return never
 */
function redirect(string $url, int $code = 302): never
{
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

/**
 * Redirect back to the HTTP_REFERER, or fall back to a default URL.
 */
function redirect_back(string $fallback = '/'): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($referer ?: $fallback);
}

// ====================================================================
// DATE & TIME FORMATTING
// ====================================================================

/**
 * Format a date string in Swiss German style.
 *
 * @param  string $date    Any string parseable by DateTimeImmutable
 * @param  string $format  PHP date() format (default: 'd.m.Y')
 * @return string  Formatted date or empty string on invalid input
 */
function format_date(string $date, string $format = 'd.m.Y'): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        return (new DateTimeImmutable($date))->format($format);
    } catch (Throwable) {
        return $date;
    }
}

/**
 * Format a date + time string.
 */
function format_datetime(string $date, string $format = 'd.m.Y H:i'): string
{
    return format_date($date, $format);
}

/**
 * Return a relative time description in German (e.g. "vor 3 Stunden").
 */
function time_ago(string $date): string
{
    try {
        $then = new DateTimeImmutable($date);
        $now  = new DateTimeImmutable();
        $diff = $now->diff($then);

        if ($diff->y > 0) return 'vor ' . $diff->y . ' ' . ($diff->y === 1 ? 'Jahr' : 'Jahren');
        if ($diff->m > 0) return 'vor ' . $diff->m . ' ' . ($diff->m === 1 ? 'Monat' : 'Monaten');
        if ($diff->d > 6) return 'vor ' . (int) floor($diff->d / 7) . ' ' . (floor($diff->d / 7) === 1.0 ? 'Woche' : 'Wochen');
        if ($diff->d > 0) return 'vor ' . $diff->d . ' ' . ($diff->d === 1 ? 'Tag' : 'Tagen');
        if ($diff->h > 0) return 'vor ' . $diff->h . ' ' . ($diff->h === 1 ? 'Stunde' : 'Stunden');
        if ($diff->i > 0) return 'vor ' . $diff->i . ' ' . ($diff->i === 1 ? 'Minute' : 'Minuten');
        return 'gerade eben';
    } catch (Throwable) {
        return format_date($date);
    }
}

// ====================================================================
// TEXT HELPERS
// ====================================================================

/**
 * Truncate text to a maximum character length, appending a suffix.
 * Strips HTML tags first so the count is on visible text only.
 *
 * @param  string $text    Input text (may contain HTML)
 * @param  int    $length  Maximum character length
 * @param  string $suffix  Appended when text is truncated (default: ellipsis)
 * @return string
 */
function truncate_text(string $text, int $length = 150, string $suffix = '…'): string
{
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? $text;

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

/**
 * Alias of truncate_text() for backward compatibility.
 */
function truncate(string $text, int $length = 150, string $suffix = '…'): string
{
    return truncate_text($text, $length, $suffix);
}

/**
 * Convert plain text to safe HTML, preserving line breaks.
 */
function nl2br_safe(string $text): string
{
    return nl2br(e($text));
}

// ====================================================================
// SLUG GENERATION
// ====================================================================

/**
 * Convert an arbitrary string to a URL-safe slug.
 * Handles German umlauts (ae/oe/ue/ss).
 *
 * @param  string $text  Source text (e.g. page title)
 * @return string  Lowercase slug with hyphens
 */
function generate_slug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    // German umlaut transliteration
    $text = strtr($text, [
        'ae' => 'ae', 'oe' => 'oe', 'ue' => 'ue',
        // actual umlaut characters
        "\xc3\xa4" => 'ae',   // ä
        "\xc3\xb6" => 'oe',   // oe
        "\xc3\xbc" => 'ue',   // ue
        "\xc3\x9f" => 'ss',   // ss
        "\xc3\x84" => 'ae',   // Ae
        "\xc3\x96" => 'oe',   // Oe
        "\xc3\x9c" => 'ue',   // Ue
    ]);
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text) ?? $text;
    $text = preg_replace('/[\s\-]+/', '-', trim($text)) ?? $text;
    return trim($text, '-');
}

/**
 * Alias of generate_slug().
 */
function slugify(string $text): string
{
    return generate_slug($text);
}

/**
 * Ensure a slug is unique within a database table.
 * Appends -2, -3, … if necessary.
 *
 * @param  string   $slug        Desired slug
 * @param  string   $table       DB table to check (e.g. 'pages')
 * @param  int|null $excludeId   Row ID to exclude (use when editing)
 * @return string   Unique slug
 */
function unique_slug(string $slug, string $table, ?int $excludeId = null): string
{
    $db      = Database::getInstance();
    $base    = $slug;
    $counter = 1;
    $sql     = $excludeId
        ? "SELECT COUNT(*) FROM `{$table}` WHERE `slug` = ? AND `id` != ?"
        : "SELECT COUNT(*) FROM `{$table}` WHERE `slug` = ?";

    while (true) {
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $count  = (int) $db->fetchColumn($sql, $params);
        if ($count === 0) {
            break;
        }
        $counter++;
        $slug = $base . '-' . $counter;
    }

    return $slug;
}

// ====================================================================
// SETTINGS
// ====================================================================

/**
 * Get a site setting value from the `settings` table.
 * Results are cached in a static variable per request.
 *
 * @param  string $key      Setting key
 * @param  string $default  Fallback value if key is not found
 * @return string
 */
function get_setting(string $key, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        try {
            $rows  = Database::getInstance()->fetchAll(
                'SELECT `key`, `value` FROM `settings`'
            );
            $cache = [];
            foreach ($rows as $row) {
                $cache[$row['key']] = (string) $row['value'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }

    return $cache[$key] ?? $default;
}

/**
 * Load all settings as a flat key => value array.
 */
function get_settings(): array
{
    // Warm the cache by calling get_setting for a dummy key,
    // then access the static variable via a known key
    get_setting('__warm__');
    // Return a fresh fetch to avoid exposing the private static
    try {
        $rows   = Database::getInstance()->fetchAll('SELECT `key`, `value` FROM `settings`');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    } catch (Throwable) {
        return [];
    }
}

// ====================================================================
// FILE UPLOAD
// ====================================================================

/**
 * Handle a file upload from $_FILES.
 *
 * @param  array  $file         Entry from $_FILES (e.g. $_FILES['image'])
 * @param  string $destination  Subdirectory within UPLOAD_PATH (e.g. 'events')
 * @param  array  $allowedExts  Allowed file extensions (lowercase, no dot)
 * @param  int    $maxSize      Max allowed size in bytes (default: MAX_UPLOAD_SIZE)
 * @return array{success: bool, filename: string|null, error: string|null}
 */
function upload_file(
    array  $file,
    string $destination  = '',
    array  $allowedExts  = [],
    int    $maxSize      = MAX_UPLOAD_SIZE
): array {
    if ($allowedExts === []) {
        $allowedExts = ALLOWED_IMAGE_EXTS;
    }

    // Basic validation
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Datei ist zu gross (PHP-Limit).',
            UPLOAD_ERR_FORM_SIZE  => 'Datei ueberschreitet das Formular-Limit.',
            UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewaehlt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Kein temporaeres Verzeichnis vorhanden.',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht gespeichert werden.',
        ];
        $msg = $phpErrors[$file['error'] ?? 0] ?? 'Unbekannter Upload-Fehler.';
        return ['success' => false, 'filename' => null, 'error' => $msg];
    }

    // Size check
    if ($file['size'] > $maxSize) {
        return [
            'success'  => false,
            'filename' => null,
            'error'    => 'Datei ist zu gross. Maximum: ' . MAX_UPLOAD_SIZE_LABEL . '.',
        ];
    }

    // Extension check
    $originalName = basename($file['name']);
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return [
            'success'  => false,
            'filename' => null,
            'error'    => 'Ungueltige Dateiendung. Erlaubt: ' . implode(', ', $allowedExts) . '.',
        ];
    }

    // MIME type check for images
    if (in_array($ext, ALLOWED_IMAGE_EXTS, true)) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Ungueiltiger Dateityp (MIME-Pruefung fehlgeschlagen).',
            ];
        }
    }

    // Build safe destination path
    $destDir = rtrim(UPLOAD_PATH, '/') . '/';
    if ($destination !== '') {
        $destDir .= trim($destination, '/') . '/';
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        return [
            'success'  => false,
            'filename' => null,
            'error'    => 'Upload-Verzeichnis konnte nicht erstellt werden.',
        ];
    }

    // Generate a collision-free filename
    $safeBase = preg_replace('/[^a-z0-9\-_]/', '', strtolower(pathinfo($originalName, PATHINFO_FILENAME)));
    $safeBase = $safeBase ?: 'upload';
    $filename = $safeBase . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $destDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return [
            'success'  => false,
            'filename' => null,
            'error'    => 'Datei konnte nicht verschoben werden.',
        ];
    }

    // Return relative path from UPLOAD_PATH root (for storing in DB)
    $relPath = ($destination ? trim($destination, '/') . '/' : '') . $filename;

    return ['success' => true, 'filename' => $relPath, 'error' => null];
}

/**
 * Delete an uploaded file by its relative path (as stored in the DB).
 *
 * @param  string $relativePath  Path relative to UPLOAD_PATH (e.g. 'events/foo.jpg')
 * @return bool   true on success or if file did not exist
 */
function delete_file(string $relativePath): bool
{
    if (empty($relativePath)) {
        return true;
    }

    $fullPath = rtrim(UPLOAD_PATH, '/') . '/' . ltrim($relativePath, '/');

    if (!file_exists($fullPath)) {
        return true;  // already gone
    }

    // Safety: only delete files inside UPLOAD_PATH
    $realUpload = realpath(UPLOAD_PATH);
    $realFile   = realpath($fullPath);

    if ($realUpload === false || $realFile === false) {
        return false;
    }
    if (!str_starts_with($realFile, $realUpload)) {
        return false;  // path traversal attempt
    }

    return unlink($fullPath);
}

/**
 * Convert a DB-stored relative upload path to a full web URL.
 *
 * @param  string $path     Relative path (e.g. 'events/foo.jpg') or full URL
 * @param  string $default  Fallback URL if $path is empty
 * @return string  Absolute URL
 */
function upload_url(string $path, string $default = ''): string
{
    if (empty($path)) {
        return $default;
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return rtrim(UPLOAD_URL, '/') . '/' . ltrim($path, '/');
}

// ====================================================================
// EMAIL
// ====================================================================

/**
 * Send an HTML e-mail using PHP's built-in mail() function.
 * Works on Hoststar.ch shared hosting without additional SMTP config.
 *
 * @param  string $to       Recipient e-mail address
 * @param  string $subject  E-mail subject (UTF-8)
 * @param  string $body     HTML body (UTF-8)
 * @param  string $replyTo  Optional Reply-To address
 * @return bool  true if mail() accepted the message
 */
function send_email(
    string $to,
    string $subject,
    string $body,
    string $replyTo = ''
): bool {
    $from     = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;
    $replyTo  = $replyTo ?: MAIL_REPLY_TO;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedBody    = base64_encode($body);

    return @mail($to, $encodedSubject, $encodedBody, $headers);
}

/**
 * Build an HTML confirmation e-mail body (sent to the contact form submitter).
 *
 * @param  string $name         Recipient's name
 * @param  string $subjectLine  Subject of the original inquiry
 * @return string  Complete HTML e-mail body
 */
function build_confirmation_email(string $name, string $subjectLine): string
{
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'mb Kommunikation + Events';
    $year     = date('Y');
    $nameHtml = e($name);
    $subjHtml = e($subjectLine);

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ihre Nachricht – {$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:system-ui,-apple-system,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="600" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:600px;width:100%;">
        <!-- Header -->
        <tr><td style="background:#1e3a5f;padding:32px 40px;text-align:center;">
          <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;letter-spacing:.5px;">
            {$siteName}
          </h1>
          <p style="color:#7fafd4;margin:6px 0 0;font-size:13px;">Hochdorf, Schweiz</p>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:40px;">
          <p style="color:#1e3a5f;font-size:18px;font-weight:700;margin:0 0 16px;">
            Vielen Dank, {$nameHtml}!
          </p>
          <p style="color:#4b5563;line-height:1.7;margin:0 0 16px;">
            Ihre Nachricht zum Thema <strong>{$subjHtml}</strong> ist bei uns eingegangen.
            Wir melden uns so schnell wie moeglich bei Ihnen.
          </p>
          <p style="color:#4b5563;line-height:1.7;margin:0 0 24px;">
            Freundliche Gruesse,<br>
            <strong>Manuela Bischof</strong><br>
            mb Kommunikation + Events
          </p>
          <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
          <p style="color:#9ca3af;font-size:12px;margin:0;text-align:center;">
            &copy; {$year} mb Kommunikation + Events &bull; Hochdorf, Schweiz
          </p>
        </td></tr>
        <!-- Footer accent -->
        <tr><td style="background:#e94560;height:4px;"></td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Build an HTML notification e-mail body (sent to site admin on new contact).
 *
 * @param  array $data  Keys: name, email, phone, subject, message
 * @return string  Complete HTML e-mail body
 */
function build_admin_notification_email(array $data): string
{
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'mb Kommunikation + Events';
    $year     = date('Y');
    $name     = e($data['name']    ?? '');
    $email    = e($data['email']   ?? '');
    $phone    = e($data['phone']   ?? '-');
    $subject  = e($data['subject'] ?? '-');
    $message  = nl2br(e($data['message'] ?? ''));

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Neue Kontaktanfrage – {$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:system-ui,-apple-system,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="600" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:600px;width:100%;">
        <!-- Header -->
        <tr><td style="background:#e94560;padding:24px 40px;">
          <h2 style="color:#ffffff;margin:0;font-size:18px;font-weight:700;">
            Neue Kontaktanfrage
          </h2>
          <p style="color:#fca5a5;margin:4px 0 0;font-size:13px;">{$siteName}</p>
        </td></tr>
        <!-- Details table -->
        <tr><td style="padding:32px 40px;">
          <table width="100%" cellpadding="0" cellspacing="0"
                 style="border-collapse:collapse;">
            <tr style="border-bottom:1px solid #f3f4f6;">
              <td style="color:#6b7280;font-size:12px;text-transform:uppercase;
                         letter-spacing:.5px;padding:10px 0;width:110px;">Name</td>
              <td style="color:#111827;font-weight:600;font-size:14px;padding:10px 0;">{$name}</td>
            </tr>
            <tr style="border-bottom:1px solid #f3f4f6;">
              <td style="color:#6b7280;font-size:12px;text-transform:uppercase;
                         letter-spacing:.5px;padding:10px 0;">E-Mail</td>
              <td style="color:#111827;font-size:14px;padding:10px 0;">
                <a href="mailto:{$email}" style="color:#1e3a5f;">{$email}</a>
              </td>
            </tr>
            <tr style="border-bottom:1px solid #f3f4f6;">
              <td style="color:#6b7280;font-size:12px;text-transform:uppercase;
                         letter-spacing:.5px;padding:10px 0;">Telefon</td>
              <td style="color:#111827;font-size:14px;padding:10px 0;">{$phone}</td>
            </tr>
            <tr>
              <td style="color:#6b7280;font-size:12px;text-transform:uppercase;
                         letter-spacing:.5px;padding:10px 0;">Betreff</td>
              <td style="color:#111827;font-weight:600;font-size:14px;padding:10px 0;">{$subject}</td>
            </tr>
          </table>
          <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">
          <p style="color:#374151;font-size:14px;line-height:1.7;margin:0 0 24px;">
            {$message}
          </p>
          <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">
          <p style="color:#9ca3af;font-size:11px;margin:0;text-align:center;">
            &copy; {$year} {$siteName} &bull; Diese E-Mail wurde automatisch generiert.
          </p>
        </td></tr>
        <!-- Footer accent -->
        <tr><td style="background:#1e3a5f;height:4px;"></td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// ====================================================================
// PAGINATION
// ====================================================================

/**
 * Calculate pagination metadata from total items and current page.
 *
 * @param  int $totalItems   Total number of records
 * @param  int $currentPage  Current page number (1-based)
 * @param  int $perPage      Items per page
 * @return array{
 *   total: int,
 *   per_page: int,
 *   current_page: int,
 *   last_page: int,
 *   offset: int,
 *   has_prev: bool,
 *   has_next: bool,
 *   prev_page: int,
 *   next_page: int
 * }
 */
function paginate(int $totalItems, int $currentPage = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $currentPage = max(1, $currentPage);
    $perPage     = max(1, $perPage);
    $lastPage    = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;
    $currentPage = min($currentPage, $lastPage);
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'        => $totalItems,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'last_page'    => $lastPage,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $lastPage,
        'prev_page'    => max(1, $currentPage - 1),
        'next_page'    => min($lastPage, $currentPage + 1),
    ];
}

/**
 * Render a Bootstrap-5-compatible pagination nav HTML string.
 *
 * @param  array  $pag      Result from paginate()
 * @param  string $baseUrl  URL prefix (e.g. '/events?page='); page number appended directly
 * @param  int    $window   How many page links to show on each side of the current page
 * @return string  HTML <nav> element or empty string if only one page
 */
function pagination_html(array $pag, string $baseUrl, int $window = 2): string
{
    if ($pag['last_page'] <= 1) {
        return '';
    }

    $current  = $pag['current_page'];
    $last     = $pag['last_page'];
    $html     = '<nav aria-label="Seitennavigation"><ul class="pagination justify-content-center">';

    // Previous
    if ($pag['has_prev']) {
        $html .= '<li class="page-item">'
               . '<a class="page-link" href="' . e($baseUrl . $pag['prev_page']) . '" aria-label="Vorige">'
               . '&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }

    // Page numbers
    $start = max(1, $current - $window);
    $end   = min($last, $current + $window);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . '1') . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }

    for ($p = $start; $p <= $end; $p++) {
        $active = $p === $current ? ' active" aria-current="page' : '';
        $html  .= '<li class="page-item' . $active . '">'
                . '<a class="page-link" href="' . e($baseUrl . $p) . '">' . $p . '</a></li>';
    }

    if ($end < $last) {
        if ($end < $last - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e($baseUrl . $last) . '">' . $last . '</a></li>';
    }

    // Next
    if ($pag['has_next']) {
        $html .= '<li class="page-item">'
               . '<a class="page-link" href="' . e($baseUrl . $pag['next_page']) . '" aria-label="Naechste">'
               . '&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

// ====================================================================
// FLASH MESSAGES
// ====================================================================

/**
 * Store a flash message in the session (displayed once on the next request).
 *
 * @param string $message  Message text
 * @param string $type     'success' | 'error' | 'warning' | 'info'
 */
function set_flash(string $message, string $type = 'info'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
}

/**
 * Retrieve and clear any pending flash message.
 * Returns null if none is stored.
 *
 * @return array{message: string, type: string}|null
 */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }
    $flash = [
        'message' => $_SESSION['flash_message'],
        'type'    => $_SESSION['flash_type'] ?? 'info',
    ];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return $flash;
}

/**
 * Render a Bootstrap 5 alert for the pending flash message.
 * Outputs nothing if no flash message exists.
 *
 * @param  bool $autoDismiss  If true, adds JS to auto-dismiss after 4 seconds
 * @return string  HTML alert div, or empty string
 */
function render_flash(bool $autoDismiss = true): string
{
    $flash = get_flash();
    if ($flash === null) {
        return '';
    }

    // Map internal type to Bootstrap alert class
    $typeMap = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];
    $alertClass = $typeMap[$flash['type']] ?? 'info';
    $id         = 'flash-' . uniqid();
    $auto       = $autoDismiss
        ? "<script>setTimeout(()=>{ var el=document.getElementById('{$id}'); if(el) el.style.display='none'; }, 4000);</script>"
        : '';

    return '<div id="' . $id . '" class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">'
         . e($flash['message'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schliessen"></button>'
         . '</div>' . $auto;
}

// ====================================================================
// CSRF (procedural wrappers, delegate to session directly)
// ====================================================================

/**
 * Get or generate the CSRF token for the current session.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $length = defined('CSRF_TOKEN_LENGTH') ? CSRF_TOKEN_LENGTH : 32;
        $_SESSION['csrf_token'] = bin2hex(random_bytes($length));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field.
 */
function csrf_field(string $fieldName = 'csrf_token'): string
{
    return '<input type="hidden" name="' . e($fieldName) . '" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a submitted CSRF token (constant-time compare).
 */
function verify_csrf(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $stored = $_SESSION['csrf_token'] ?? '';
    return $stored !== '' && hash_equals($stored, $token);
}

// ====================================================================
// MISC UTILITIES
// ====================================================================

/**
 * Return the current full URL (scheme + host + request URI).
 */
function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

/**
 * Format a byte count as a human-readable string (KB / MB / GB).
 */
function format_bytes(int $bytes, int $precision = 2): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 ** 2) {
        return round($bytes / 1024, $precision) . ' KB';
    }
    if ($bytes < 1024 ** 3) {
        return round($bytes / 1024 ** 2, $precision) . ' MB';
    }
    return round($bytes / 1024 ** 3, $precision) . ' GB';
}

/**
 * Generate a cryptographically secure random token (hex string).
 *
 * @param  int $bytes  Number of random bytes (token length = 2 * $bytes)
 * @return string
 */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Check if the current request is an AJAX (XMLHttpRequest) call.
 */
function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send a JSON response and terminate.
 *
 * @param  mixed $data     Data to encode as JSON
 * @param  int   $status   HTTP status code
 * @return never
 */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Return a placeholder image URL if $path is empty.
 */
function img_src(string $path, string $placeholder = ''): string
{
    if (!empty($path)) {
        return upload_url($path);
    }
    return $placeholder ?: SITE_URL . '/assets/images/placeholder.jpg';
}
