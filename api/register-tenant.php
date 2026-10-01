<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../security/auth.php';

apiBootstrap();
requireMethod('POST');
requireCsrf();

$result = registerAccount(ROLE_TENANT, $_POST, [
    'id_document' => $_FILES['idFile'] ?? null,
]);

if (!$result['success']) {
    jsonError($result['message'], $result['status'] ?? 400, $result['errors'] ?? []);
}

jsonResponse(null, $result['message'], 201);
