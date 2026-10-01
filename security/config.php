<?php

declare(strict_types=1);

if (!defined('BH_SYSTEM_ROOT')) {
    define('BH_SYSTEM_ROOT', dirname(__DIR__));
}

if (!defined('BH_SYSTEM_SECURITY_DIR')) {
    define('BH_SYSTEM_SECURITY_DIR', __DIR__);
}

/**
 * Configuration values are read in this order:
 *   1. security/config.local.php (not committed; copy config.local.example.php)
 *   2. Environment variables (BH_DB_HOST, BH_DB_NAME, ...)
 *   3. The XAMPP-friendly defaults below.
 */
$bhLocalConfig = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $loaded = require __DIR__ . '/config.local.php';
    if (is_array($loaded)) {
        $bhLocalConfig = $loaded;
    }
}

function bhConfig(string $key, string $default): string {
    global $bhLocalConfig;
    if (isset($bhLocalConfig[$key]) && is_scalar($bhLocalConfig[$key])) {
        return (string) $bhLocalConfig[$key];
    }

    $env = getenv($key);
    return $env !== false ? $env : $default;
}

/**
 * Database configuration.
 */
define('BH_DB_HOST', bhConfig('BH_DB_HOST', '127.0.0.1'));
define('BH_DB_PORT', bhConfig('BH_DB_PORT', '3306'));
define('BH_DB_NAME', bhConfig('BH_DB_NAME', 'bhsystem'));
define('BH_DB_USER', bhConfig('BH_DB_USER', 'root'));
define('BH_DB_PASS', bhConfig('BH_DB_PASS', ''));
define('BH_DB_CHARSET', 'utf8mb4');

/**
 * Application settings.
 */
define('BH_APP_NAME', 'Boarding House Rental System');
define('BH_APP_DEBUG', bhConfig('BH_APP_DEBUG', '0') === '1');
define('BH_SESSION_NAME', 'BHSESSID');
define('BH_SESSION_IDLE_MINUTES', 30);
define('BH_STORAGE_DIR', BH_SYSTEM_ROOT . '/storage');
define('BH_LOG_FILE', BH_STORAGE_DIR . '/app.log');
define('BH_RATE_LIMIT_FILE_DIR', BH_STORAGE_DIR);
define('BH_DOCUMENT_DIR', BH_STORAGE_DIR . '/documents');
define('BH_UPLOAD_DIR', BH_SYSTEM_ROOT . '/uploads');
define('BH_UPLOAD_URL', 'uploads');

define('BH_PASSWORD_MIN_LENGTH', 8);

define('BH_MAX_LOGIN_ATTEMPTS', 5);
define('BH_LOCKOUT_MINUTES', 15);

define('BH_RESET_TOKEN_TTL_MINUTES', 60);

date_default_timezone_set(bhConfig('BH_TIMEZONE', 'Asia/Manila'));

if (!BH_APP_DEBUG) {
    ini_set('display_errors', '0');
}

function isHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

function getClientIp(): string {
    // Only REMOTE_ADDR is trustworthy; X-Forwarded-For and similar headers are
    // set by the client and would let attackers bypass login rate limiting.
    return !empty($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

function writeLog(string $message, string $level = 'INFO'): void {
    if (!is_dir(BH_STORAGE_DIR)) {
        @mkdir(BH_STORAGE_DIR, 0750, true);
    }

    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    @file_put_contents(BH_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
