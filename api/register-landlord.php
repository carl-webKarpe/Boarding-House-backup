<?php

declare(strict_types=1);

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/auth.php';

applySecurityHeaders();
startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = $_POST;
$csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken((string) $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$result = registerUser([
    'role' => 'landlord',
    'email' => (string) ($input['gmail'] ?? $input['email'] ?? ''),
    'username' => (string) ($input['username'] ?? ''),
    'password' => (string) ($input['password'] ?? ''),
    'confirmPassword' => (string) ($input['confirmPassword'] ?? ''),
]);

echo json_encode($result);
