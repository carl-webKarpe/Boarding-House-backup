<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

apiBootstrap();
requireMethod('GET');

$token = generateCsrfToken();
// Kept at the top level as well, for the existing registration scripts.
jsonResponse(['csrf_token' => $token], '', 200, ['csrf_token' => $token]);
