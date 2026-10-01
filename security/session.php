<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/csrf.php';

/**
 * The only place a session is started, so every page uses the same
 * cookie settings (HttpOnly, SameSite=Lax, Secure on HTTPS).
 */
function startSecureSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_name(BH_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) (BH_SESSION_IDLE_MINUTES * 60));
    session_start();

    // Log out sessions that have been idle for too long.
    $now = time();
    if (!empty($_SESSION['user_id']) && isset($_SESSION['last_activity'])
        && ($now - (int) $_SESSION['last_activity']) > BH_SESSION_IDLE_MINUTES * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = $now;
}

function regenerateSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int {
    startSecureSession();
    return !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserRole(): ?string {
    startSecureSession();
    return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
}

/**
 * Builds a URL to a project file from the current script's folder, so
 * redirects work no matter which sub-folder the project is installed in.
 */
function appUrl(string $path): string {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = trim(dirname($script), '/');
    $scriptFile = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $root = str_replace('\\', '/', (string) realpath(BH_SYSTEM_ROOT));

    $depth = 0;
    if ($scriptFile !== '' && $root !== '' && str_starts_with($scriptFile, $root . '/')) {
        $relative = substr(dirname($scriptFile), strlen($root));
        $depth = $relative === '' ? 0 : substr_count(trim($relative, '/'), '/') + 1;
    } elseif ($scriptDir !== '') {
        $depth = 1;
    }

    return str_repeat('../', $depth) . ltrim($path, '/');
}

function redirectTo(string $path): never {
    header('Location: ' . appUrl($path));
    exit;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirectTo('html/loginform.html');
    }
}

/**
 * Page guard. Users without one of the allowed roles are sent back to their
 * own home page instead of seeing an error.
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    $role = currentUserRole();
    if (!in_array($role, $allowedRoles, true)) {
        redirectTo(homePathForRole($role));
    }
}

function logoutUser(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
