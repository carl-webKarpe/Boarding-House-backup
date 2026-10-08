<?php

declare(strict_types=1);

/**
 * Google / Facebook send the visitor back here after they approve the sign-in.
 * Register this address in the Google Cloud and Meta developer consoles:
 *   http://localhost:8000/php/oauth-callback.php        (start-server.bat)
 *   http://localhost/BHsystem/php/oauth-callback.php    (XAMPP Apache)
 */

require_once __DIR__ . '/../security/oauth.php';

startSecureSession();
header('Cache-Control: no-store');

$fail = static function (string $message): never {
    redirectTo('html/loginform.html?oauth_error=' . rawurlencode($message));
};

$provider = (string) ($_SESSION['oauth']['provider'] ?? '');
if (!isset(BH_OAUTH_PROVIDERS[$provider])) {
    $fail('The sign-in link expired. Please try again.');
}
if (isset($_GET['error'])) {
    // The person pressed "Cancel" (or the provider refused).
    unset($_SESSION['oauth']);
    $fail(BH_OAUTH_PROVIDERS[$provider]['label'] . ' sign-in was cancelled.');
}

try {
    $profile = oauthProfile($provider, (string) ($_GET['code'] ?? ''), (string) ($_GET['state'] ?? ''));
    $result = oauthLogin($provider, $profile);
} catch (Throwable $e) {
    writeLog('Social sign-in (' . $provider . ') failed: ' . $e->getMessage(), 'ERROR');
    $fail(BH_OAUTH_PROVIDERS[$provider]['label'] . ' sign-in failed. Please try again or log in with your email and password.');
}

if (!$result['success']) {
    $fail($result['message']);
}
$target = $result['redirect'];
if (!empty($result['created'])) {
    $target .= (str_contains($target, '?') ? '&' : '?') . 'welcome=1';
}
redirectTo($target);
