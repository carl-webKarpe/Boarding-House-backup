<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_name(BH_SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => isHttps(),
        'use_strict_mode' => true,
        'gc_maxlifetime' => 1800,
    ]);
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): bool {
    return is_string($token) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
