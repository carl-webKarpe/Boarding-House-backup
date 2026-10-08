<?php

/**
 * "Continue with Google / Facebook" settings.
 *
 * Copy this file to security/oauth.local.php (ignored by Git, so the secrets
 * are never uploaded) and fill in the keys of the provider(s) you want.
 * A provider with empty keys simply shows "not set up yet" when clicked.
 *
 * In both consoles, add this redirect (callback) URL exactly:
 *   http://localhost:8000/php/oauth-callback.php          (start-server.bat)
 *   http://localhost/BHsystem/php/oauth-callback.php      (XAMPP Apache)
 *
 * GOOGLE  https://console.cloud.google.com/apis/credentials
 *   Create credentials > OAuth client ID > Web application
 *   Authorized redirect URIs: the URL above.  Copy the Client ID and Client secret.
 *
 * FACEBOOK  https://developers.facebook.com/apps
 *   Create app > "Authenticate and request data from users with Facebook Login"
 *   Facebook Login > Settings > Valid OAuth Redirect URIs: the URL above.
 *   App settings > Basic: copy the App ID and App secret.
 */

return [
    // Leave empty to detect it automatically (e.g. http://localhost:8000).
    'base_url' => '',

    'google' => [
        'client_id' => 'your-google-client-id.apps.googleusercontent.com',
        'client_secret' => 'your-google-client-secret',
    ],

    'facebook' => [
        'client_id' => 'your-facebook-app-id',
        'client_secret' => 'your-facebook-app-secret',
    ],
];
