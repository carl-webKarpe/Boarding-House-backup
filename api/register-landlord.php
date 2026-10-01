<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../security/auth.php';

apiBootstrap();
requireMethod('POST');
requireCsrf();

$result = registerAccount(ROLE_LANDLORD, $_POST, [
    'government_id' => $_FILES['govIdFile'] ?? null,
    'selfie' => $_FILES['selfieFile'] ?? null,
    'business_permit' => $_FILES['permitFile'] ?? null,
    'proof_of_ownership' => $_FILES['ownershipFile'] ?? null,
]);

if (!$result['success']) {
    jsonError($result['message'], $result['status'] ?? 400, $result['errors'] ?? []);
}

jsonResponse(null, $result['message'], 201);
