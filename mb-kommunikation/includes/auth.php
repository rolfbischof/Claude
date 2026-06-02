<?php
/**
 * mb Kommunikation + Events
 * Auth class – session management, login/logout, CSRF tokens, role checks.
 *
 * Usage:
 *   require_once 'includes/auth.php';
 *   $auth = new Auth();
 *   $auth->requireRole('editor');           // redirects if not authorised
 *   if ($auth->login('admin', 'pass')) { }  // returns true / false
 *   echo $auth->csrfField();                // render hidden CSRF input
 *
 * Procedural compatibility shims (require_login, require_role) are
 * provided at the bottom of this file for legacy templates.
 */

declare(strict_types=1);

if (!defined('SESSION_NAME')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/db.php';
}

class Auth
{
    private Database $db;

    /**
     * Role hierarchy – higher value = more permissions.
     * Used by hasRole() to compare levels.
     */
    private const ROLE_HIERARCHY = [
        'viewer'     => 0,
        'editor'     => 1,
        'admin'      => 2,
        'superadmin' => 3,
    ];

    // ================================================================
    // CONSTRUCTOR
    // ================================================================

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    // ================================================================
    // SESSION MANAGEMENT
    // ================================================================

    /**
     * Start or resume the PHP session with secure cookie settings.
     * Rotates the session ID every 30 minutes to limit the window for
     * session-fixation attacks.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 28800;

        session_name(SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Rotate session ID periodically (every 30 minutes)
        if (!isset($_SESSION['_sess_created'])) {
            $_SESSION['_sess_created'] = time();
        } elseif (time() - $_SESSION['_sess_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_sess_created'] = time();
        }
    }

    /**
     * Completely destroy the current session and its cookie.
     */
    private function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ================================================================
    // LOGIN / LOGOUT
    // ================================================================

    /**
     * Authenticate a user by username or e-mail + password.
     *
     * Security notes:
     * - Runs password_verify even when the user is not found to prevent
     *   timing-based user-enumeration attacks.
     * - Automatically rehashes the stored hash if the bcrypt cost changed.
     * - Regenerates the PHP session ID on success (prevents session fixation).
     *
     * @param  string $usernameOrEmail  Username or e-mail address
     * @param  string $password         Plain-text password
     * @return bool   true on success, false on any failure
     */
    public function login(string $usernameOrEmail, string $password): bool
    {
        if (empty(trim($usernameOrEmail)) || empty($password)) {
            return false;
        }

        $user = $this->db->fetchOne(
            'SELECT * FROM `users`
              WHERE (`username` = ? OR `email` = ?)
                AND `active` = 1
              LIMIT 1',
            [$usernameOrEmail, $usernameOrEmail]
        );

        // Always run a hash comparison to resist timing attacks on unknown users
        $dummyHash = '$2y$12$invalidhashpaddingtomimicbcryptXXXXXXXXXXXXXXXXXXXXXXXX';
        $hash      = $user['password_hash'] ?? $dummyHash;

        if (!password_verify($password, $hash) || $user === null) {
            return false;
        }

        // Silently rehash if cost factor changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            $this->db->update('users', ['password_hash' => $newHash], ['id' => $user['id']]);
        }

        // Regenerate session ID on login (prevents session fixation)
        session_regenerate_id(true);
        $_SESSION['_sess_created'] = time();

        // Store only what is needed in the session – never the password hash
        $_SESSION['user'] = [
            'id'         => (int) $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name'  => $user['last_name']  ?? '',
            'role'       => $user['role'],
        ];

        // Legacy flat keys for procedural code still using $_SESSION['user_id']
        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
                                  ?: $user['username'];

        // Record the login timestamp
        $this->db->update(
            'users',
            ['last_login' => date('Y-m-d H:i:s')],
            ['id' => (int) $user['id']]
        );

        return true;
    }

    /**
     * Log out the current user, destroy the session, and redirect.
     *
     * @param string $redirectTo  URL to send the user to after logout
     */
    public function logout(string $redirectTo = '/admin/login.php'): never
    {
        $this->destroySession();
        header('Location: ' . $redirectTo);
        exit;
    }

    // ================================================================
    // AUTHENTICATION CHECKS
    // ================================================================

    /**
     * Returns true if a user is currently logged in with a valid session.
     */
    public function isLoggedIn(): bool
    {
        // Support both new nested key and legacy flat key
        return (isset($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] > 0)
            || (isset($_SESSION['user_id'])    && (int) $_SESSION['user_id']    > 0);
    }

    /**
     * Require that the current visitor is logged in.
     * Redirects to the login page with a ?redirect= return URL otherwise.
     *
     * @param string $loginUrl  URL of the login page
     */
    public function requireLogin(string $loginUrl = '/admin/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . $loginUrl . ($returnUrl ? '?redirect=' . $returnUrl : ''));
            exit;
        }
    }

    /**
     * Require that the current user holds at least the given role level.
     * Shows a styled 403 page rather than a redirect loop on failure.
     *
     * @param string $minRole   Minimum required role key
     * @param string $loginUrl  Login page URL used by requireLogin()
     */
    public function requireRole(string $minRole, string $loginUrl = '/admin/login.php'): void
    {
        $this->requireLogin($loginUrl);

        if (!$this->hasRole($minRole)) {
            http_response_code(403);
            $adminUrl = defined('ADMIN_URL') ? ADMIN_URL : '/admin';
            echo '<!DOCTYPE html><html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Zugriff verweigert</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#f3f4f6;display:flex;
         align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{background:#fff;border-radius:12px;padding:48px;max-width:440px;
         text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);}
    h1{color:#1e3a5f;font-size:1.5rem;margin:0 0 12px;}
    p{color:#6b7280;line-height:1.6;}
    a{display:inline-block;margin-top:20px;padding:10px 24px;background:#1e3a5f;
      color:#fff;border-radius:6px;text-decoration:none;font-size:.875rem;}
    a:hover{background:#2a4f80;}
  </style>
</head>
<body>
  <div class="box">
    <h1>403 &#8211; Zugriff verweigert</h1>
    <p>Sie haben keine Berechtigung, diese Seite aufzurufen.<br>
       Bitte wenden Sie sich an einen Administrator.</p>
    <a href="' . htmlspecialchars($adminUrl) . '">Zur&uuml;ck zum Dashboard</a>
  </div>
</body>
</html>';
            exit;
        }
    }

    // ================================================================
    // ROLE CHECKS
    // ================================================================

    /**
     * Check if the logged-in user has at least the given role level.
     *
     * @param  string $minRole  Required minimum role key
     * @return bool
     */
    public function hasRole(string $minRole): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $userLevel = self::ROLE_HIERARCHY[$this->getRole()] ?? -1;
        $minLevel  = self::ROLE_HIERARCHY[$minRole]         ?? 999;

        return $userLevel >= $minLevel;
    }

    /** Returns true if the current user's role exactly matches $role. */
    public function isRole(string $role): bool
    {
        return $this->getRole() === $role;
    }

    // Convenience wrappers
    public function isViewer(): bool     { return $this->hasRole('viewer');     }
    public function isEditor(): bool     { return $this->hasRole('editor');     }
    public function isAdmin(): bool      { return $this->hasRole('admin');      }
    public function isSuperAdmin(): bool { return $this->hasRole('superadmin'); }

    // ================================================================
    // CURRENT USER ACCESSORS
    // ================================================================

    /** Return the full session user array, or null if not logged in. */
    public function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Return a single field from the session user array. */
    public function getUserField(string $field, mixed $default = null): mixed
    {
        return $_SESSION['user'][$field] ?? $default;
    }

    public function getUserId(): int
    {
        return (int) ($this->getUserField('id') ?? $_SESSION['user_id'] ?? 0);
    }

    public function getUsername(): string
    {
        return (string) ($this->getUserField('username') ?? '');
    }

    public function getEmail(): string
    {
        return (string) ($this->getUserField('email') ?? $_SESSION['user_email'] ?? '');
    }

    public function getRole(): string
    {
        return (string) ($this->getUserField('role') ?? $_SESSION['user_role'] ?? 'viewer');
    }

    /** Alias kept for backward compatibility. */
    public function getUserRole(): string
    {
        return $this->getRole();
    }

    /**
     * Return the user's display name.
     * Prefers "First Last", falls back to username, then "Benutzer".
     */
    public function getDisplayName(): string
    {
        $first = trim((string) $this->getUserField('first_name', ''));
        $last  = trim((string) $this->getUserField('last_name',  ''));
        $full  = trim($first . ' ' . $last);
        return $full ?: ($this->getUsername() ?: 'Benutzer');
    }

    /** Alias kept for backward compatibility. */
    public function getUserDisplayName(): string
    {
        return $this->getDisplayName();
    }

    /**
     * Fetch the full user row fresh from the database (not the session cache).
     * Returns null if not logged in or on DB error.
     */
    public function getCurrentUserFromDb(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        try {
            return $this->db->find('users', $this->getUserId());
        } catch (Throwable) {
            return null;
        }
    }

    // ================================================================
    // REGISTRATION
    // ================================================================

    /**
     * Register a new user account.
     *
     * @param  string $username
     * @param  string $email
     * @param  string $password   Plain-text password (min. 8 chars)
     * @param  string $firstName
     * @param  string $lastName
     * @param  string $role       One of: viewer | editor | admin | superadmin
     * @return int    New user ID
     *
     * @throws InvalidArgumentException  on validation failure
     * @throws RuntimeException          on duplicate username or e-mail
     */
    public function register(
        string $username,
        string $email,
        string $password,
        string $firstName = '',
        string $lastName  = '',
        string $role      = 'viewer'
    ): int {
        $username = trim($username);
        $email    = trim(strtolower($email));
        $role     = trim($role);

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new InvalidArgumentException('Benutzername muss 3-50 Zeichen lang sein.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) {
            throw new InvalidArgumentException(
                'Benutzername darf nur Buchstaben, Ziffern, Punkte, Bindestriche und Unterstriche enthalten.'
            );
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Ungueltige E-Mail-Adresse.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Passwort muss mindestens 8 Zeichen lang sein.');
        }
        if (!array_key_exists($role, self::ROLE_HIERARCHY)) {
            throw new InvalidArgumentException('Ungueltige Rolle: ' . $role);
        }

        if ($this->db->exists('users', ['username' => $username])) {
            throw new RuntimeException('Dieser Benutzername ist bereits vergeben.');
        }
        if ($this->db->exists('users', ['email' => $email])) {
            throw new RuntimeException('Diese E-Mail-Adresse ist bereits registriert.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        return $this->db->insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $hash,
            'first_name'    => trim($firstName),
            'last_name'     => trim($lastName),
            'role'          => $role,
            'active'        => 1,
        ]);
    }

    // ================================================================
    // PASSWORD MANAGEMENT
    // ================================================================

    /**
     * Change a user's password after verifying the current one.
     *
     * @throws InvalidArgumentException  if new password is too short
     * @throws RuntimeException          if current password is wrong
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Neues Passwort muss mindestens 8 Zeichen lang sein.');
        }

        $user = $this->db->find('users', $userId);
        if ($user === null) {
            throw new RuntimeException('Benutzer nicht gefunden.');
        }
        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new RuntimeException('Das aktuelle Passwort ist falsch.');
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $this->db->update('users', ['password_hash' => $newHash], ['id' => $userId]);
    }

    /**
     * Reset a user's password without checking the old one.
     * Intended for superadmin use only.
     *
     * @throws InvalidArgumentException
     */
    public function resetPassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Passwort muss mindestens 8 Zeichen lang sein.');
        }
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $this->db->update('users', ['password_hash' => $newHash], ['id' => $userId]);
    }

    /**
     * Hash a plain-text password with the configured bcrypt cost.
     * Useful for seeding scripts and CLI utilities.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    }

    // ================================================================
    // CSRF PROTECTION
    // ================================================================

    /**
     * Generate (or retrieve) the CSRF token for the current session.
     * The token is lazily created on first call.
     */
    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $length = defined('CSRF_TOKEN_LENGTH') ? CSRF_TOKEN_LENGTH : 32;
            $_SESSION['csrf_token'] = bin2hex(random_bytes($length));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted CSRF token using constant-time string comparison
     * to prevent timing-based attacks.
     */
    public function validateCsrfToken(string $submittedToken): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, $submittedToken);
    }

    /** Alias for backward compatibility. */
    public function verifyCsrfToken(string $token): bool
    {
        return $this->validateCsrfToken($token);
    }

    /**
     * Enforce a valid CSRF token from $_POST (or X-CSRF-Token header) or
     * terminate the request with HTTP 403.
     * Rotates the token after a successful check.
     * Handles both regular form POSTs and AJAX (JSON) requests.
     *
     * @param string $field  POST field name (default: 'csrf_token')
     */
    public function verifyCsrf(string $field = 'csrf_token'): void
    {
        $submitted = $_POST[$field] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->validateCsrfToken($submitted)) {
            if (!headers_sent()) {
                http_response_code(403);
            }

            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                      && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'CSRF-Token ungueltig.']);
            } else {
                die('Ungueltiges Sicherheitstoken. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
            }
            exit;
        }

        // Rotate token after successful use
        $length = defined('CSRF_TOKEN_LENGTH') ? CSRF_TOKEN_LENGTH : 32;
        $_SESSION['csrf_token'] = bin2hex(random_bytes($length));
    }

    /** Alias kept for backward compatibility. */
    public function requireCsrf(): void
    {
        $this->verifyCsrf('csrf_token');
    }

    /**
     * Render a hidden HTML input containing the CSRF token.
     * Insert inside every HTML form that modifies server state.
     *
     * @param  string $field  Input name attribute
     * @return string  Safe HTML string (does not echo – use echo $auth->csrfField())
     */
    public function csrfField(string $field = 'csrf_token'): string
    {
        $token = htmlspecialchars($this->getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name  = htmlspecialchars($field, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    // ================================================================
    // FLASH MESSAGES
    // ================================================================

    /**
     * Store a one-time flash message in the session.
     *
     * @param string $message  Message text
     * @param string $type     'success' | 'error' | 'warning' | 'info'
     */
    public function flash(string $message, string $type = 'info'): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
    }

    /**
     * Retrieve and clear the pending flash message.
     * Returns null if no flash message is stored.
     *
     * @return array{message: string, type: string}|null
     */
    public function getFlash(): ?array
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

    // ================================================================
    // USER MANAGEMENT (admin helpers)
    // ================================================================

    /**
     * Activate or deactivate a user account.
     *
     * @throws RuntimeException if the admin tries to deactivate their own account
     */
    public function setUserActive(int $userId, bool $active): void
    {
        if (!$active && $userId === $this->getUserId()) {
            throw new RuntimeException('Sie koennen Ihr eigenes Konto nicht deaktivieren.');
        }
        $this->db->update('users', ['active' => (int) $active], ['id' => $userId]);
    }

    /**
     * Change the role of any user account.
     *
     * @throws InvalidArgumentException on unknown role key
     */
    public function setUserRole(int $userId, string $role): void
    {
        if (!array_key_exists($role, self::ROLE_HIERARCHY)) {
            throw new InvalidArgumentException('Ungueltige Rolle: ' . $role);
        }
        $this->db->update('users', ['role' => $role], ['id' => $userId]);
    }

    /**
     * Delete a user account permanently.
     *
     * @throws RuntimeException if the admin tries to delete their own account
     */
    public function deleteUser(int $userId): void
    {
        if ($userId === $this->getUserId()) {
            throw new RuntimeException('Sie koennen Ihr eigenes Konto nicht loeschen.');
        }
        $this->db->delete('users', ['id' => $userId]);
    }

    /**
     * Return all available roles as key => German label pairs.
     *
     * @return array<string, string>
     */
    public function getAvailableRoles(): array
    {
        return [
            'viewer'     => 'Betrachter',
            'editor'     => 'Redakteur',
            'admin'      => 'Administrator',
            'superadmin' => 'Super-Administrator',
        ];
    }

    /**
     * Return the German display label for a role key.
     */
    public function getRoleLabel(string $role): string
    {
        return $this->getAvailableRoles()[$role] ?? $role;
    }
}

// ====================================================================
// Procedural compatibility shims
// Legacy scripts that call require_login() / require_role() directly
// continue to work without modification.
// ====================================================================

if (!function_exists('require_login')) {
    function require_login(string $redirect = '/admin/login.php'): void
    {
        (new Auth())->requireLogin($redirect);
    }
}

if (!function_exists('require_role')) {
    function require_role(string $minRole, string $redirect = '/admin/login.php'): void
    {
        (new Auth())->requireRole($minRole, $redirect);
    }
}

if (!function_exists('logout')) {
    function logout(string $redirect = '/admin/login.php'): never
    {
        (new Auth())->logout($redirect);
    }
}

// Global singleton instance – available as $auth in all included files
$auth = new Auth();
