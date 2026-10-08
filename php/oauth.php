<?php

declare(strict_types=1);

/**
 * "Continue with Google / Facebook": sends the visitor to the provider's sign-in page.
 *   php/oauth.php?provider=google | facebook
 */

require_once __DIR__ . '/../security/oauth.php';

startSecureSession();
header('Cache-Control: no-store');

$provider = (string) ($_GET['provider'] ?? '');
if (isLoggedIn()) {
    redirectTo(homePathForRole(currentUserRole()));
}

try {
    header('Location: ' . oauthStart($provider), true, 302);
} catch (Throwable $e) {
    writeLog('Social sign-in start (' . $provider . '): ' . $e->getMessage(), 'WARN');
    redirectTo('html/loginform.html?oauth_error=' . rawurlencode($e->getMessage()));
}
exit;
