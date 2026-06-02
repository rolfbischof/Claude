<?php
/**
 * mb Kommunikation + Events – Configuration Template
 * Copy this file to config.php and adjust values for your environment.
 */

declare(strict_types=1);

// ── Database (Hoststar.ch) ─────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'mb_kommunikation');     // Your DB name
define('DB_USER',    'mb_user');              // Your DB user
define('DB_PASS',    'CHANGE_THIS');          // Your DB password
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    3306);

// ── Site ──────────────────────────────────────────────────────────
define('SITE_URL',   'https://www.mb-kommunikation-events.ch');
define('SITE_NAME',  'mb Kommunikation + Events');
define('ADMIN_URL',  SITE_URL . '/admin');

// ── Paths ─────────────────────────────────────────────────────────
define('ROOT_PATH',     dirname(__DIR__));
define('CONFIG_PATH',   __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOAD_PATH',   ROOT_PATH . '/uploads/');
define('BACKUP_PATH',   ROOT_PATH . '/backup/files/');
define('LOG_PATH',      ROOT_PATH . '/logs/');
define('ASSETS_PATH',   ROOT_PATH . '/assets/');
define('UPLOAD_URL',    SITE_URL . '/uploads/');

// ── File uploads ──────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE',       10 * 1024 * 1024);
define('MAX_UPLOAD_SIZE_LABEL', '10 MB');
define('ALLOWED_IMAGE_TYPES',   ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTS',    ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOC_TYPES',     ['application/pdf']);
define('ALLOWED_DOC_EXTS',      ['pdf']);

// ── Session ───────────────────────────────────────────────────────
define('SESSION_NAME',      'mb_session');
define('SESSION_LIFETIME',  3600 * 8);
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_COST',     12);

// ── Pagination ────────────────────────────────────────────────────
define('ITEMS_PER_PAGE',       12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// ── Email ─────────────────────────────────────────────────────────
define('MAIL_FROM',      'noreply@mb-kommunikation-events.ch');
define('MAIL_FROM_NAME', 'mb Kommunikation + Events');
define('MAIL_REPLY_TO',  'info@mb-kommunikation-events.ch');

// ── Optional: Supabase cloud backup ───────────────────────────────
// define('SUPABASE_URL', 'https://igxngxzibeozcqehxkpr.supabase.co');
// define('SUPABASE_KEY', 'YOUR_ANON_KEY_HERE');

// ── Timezone ──────────────────────────────────────────────────────
date_default_timezone_set('Europe/Zurich');

// ── Error reporting ───────────────────────────────────────────────
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . 'error.log');
define('APP_ENV', $env);

// ── Auto-create directories ───────────────────────────────────────
foreach ([UPLOAD_PATH, BACKUP_PATH, LOG_PATH, UPLOAD_PATH.'events/', UPLOAD_PATH.'references/', UPLOAD_PATH.'pages/'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
