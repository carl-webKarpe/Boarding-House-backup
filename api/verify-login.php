<?php

declare(strict_types=1);

/**
 * Step 2 of the Super Admin login (the emailed code).
 *
 *   GET  api/verify-login.php                      { email (masked), resend_in }
 *   POST api/verify-login.php  { code }            check the code -> logged in
 *   POST api/verify-login.php  { action: resend }  email a new code
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../security/auth.php';

apiBootstrap();

if (requestMethod() === 'GET') {
    $pending = pendingTwoFactor();
    if (!$pending) {
        jsonError('Your login expired. Please log in again.', 440);
    }
    jsonResponse([
        'email' => $pending['email'],
        'resend_in' => max(0, BH_2FA_RESEND_SECONDS - (time() - (int) $pending['last_sent'])),
        'code_minutes' => BH_2FA_CODE_MINUTES,
    ]);
}

requireMethod('POST');
requireCsrf();
$body = requestBody();

$result = ($body['action'] ?? '') === 'resend'
    ? resendTwoFactorCode()
    : verifyTwoFactorCode((string) ($body['code'] ?? ''));

if (!$result['success']) {
    jsonError($result['message'], $result['status'] ?? 400);
}

if (($body['action'] ?? '') === 'resend') {
    jsonResponse(['resend_in' => BH_2FA_RESEND_SECONDS], $result['message']);
}
jsonResponse(['name' => $result['name'], 'role' => $result['role'], 'redirect' => $result['redirect']], 'Verified. Welcome back!');
