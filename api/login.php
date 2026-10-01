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

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$result = loginUser((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''), !empty($input['rememberMe']));
echo json_encode($result);
