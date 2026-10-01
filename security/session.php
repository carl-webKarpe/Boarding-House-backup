<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(BH_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function regenerateSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function requireLogin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        http_response_code(302);
        header('Location: ../html/loginform.html');
        exit;
    }
}

function requireRole(array $allowedRoles): void {
    requireLogin();
    $role = $_SESSION['role'] ?? 'user';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function logoutUser(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
