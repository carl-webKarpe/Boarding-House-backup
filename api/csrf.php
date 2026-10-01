<?php

declare(strict_types=1);

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/session.php';

applySecurityHeaders();
startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

echo json_encode(['success' => true, 'csrf_token' => generateCsrfToken()]);
