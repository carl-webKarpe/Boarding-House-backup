<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../security/auth.php';

apiBootstrap();
requireMethod('POST');
requireCsrf();

$input = requestBody();
$result = loginUser((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));

if (!$result['success']) {
    jsonError($result['message'], $result['status'] ?? 400);
}

jsonResponse([
    'username' => $result['username'],
    'name' => $result['name'],
    'role' => $result['role'],
    'redirect' => $result['redirect'],
], $result['message']);
