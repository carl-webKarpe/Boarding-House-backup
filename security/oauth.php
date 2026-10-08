<?php

declare(strict_types=1);

/**
 * "Continue with Google / Facebook" (OAuth 2.0 authorization-code flow).
 *
 *   php/oauth.php?provider=google      -> sends the visitor to Google / Facebook
 *   php/oauth-callback.php             <- they come back here with a one-time code
 *
 * The code is exchanged for the person's verified email and name, server to
 * server (the visitor never sees the app secret). Then:
 *   - known Google/Facebook account          -> log in
 *   - existing account with the same email   -> link it and log in
 *   - new person                             -> create a Student (tenant) account
 * Landlords still register with the landlord form (they must upload IDs).
 * A Super Admin still has to enter the emailed code afterwards.
 *
 * Settings: security/oauth.local.php (copy security/oauth.local.example.php).
 */

require_once __DIR__ . '/auth.php';

const BH_OAUTH_CONFIG_FILE = __DIR__ . '/oauth.local.php';
const BH_OAUTH_STATE_MINUTES = 10;

const BH_OAUTH_PROVIDERS = [
    'google' => [
        'label' => 'Google',
        'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token',
        'profile' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'scope' => 'openid email profile',
    ],
    'facebook' => [
        'label' => 'Facebook',
        'authorize' => 'https://www.facebook.com/v19.0/dialog/oauth',
        'token' => 'https://graph.facebook.com/v19.0/oauth/access_token',
        'profile' => 'https://graph.facebook.com/v19.0/me',
        'scope' => 'email,public_profile',
    ],
];

function oauthConfig(): array {
    static $config = null;
    if ($config === null) {
        $loaded = is_file(BH_OAUTH_CONFIG_FILE) ? require BH_OAUTH_CONFIG_FILE : [];
        $config = is_array($loaded) ? $loaded : [];
    }
    return $config;
}

/** Client id + secret of a provider, or null when it is not set up. */
function oauthCredentials(string $provider): ?array {
    $c = oauthConfig()[$provider] ?? null;
    $id = trim((string) ($c['client_id'] ?? ''));
    $secret = trim((string) ($c['client_secret'] ?? ''));
    if ($id === '' || $secret === '' || str_contains($id, 'your-') || str_contains($secret, 'your-')) {
        return null;
    }
    return ['id' => $id, 'secret' => $secret];
}

/** The address Google/Facebook send the visitor back to (must be registered in their consoles). */
function oauthRedirectUri(): string {
    $base = rtrim((string) (oauthConfig()['base_url'] ?? ''), '/');
    if ($base === '') {
        $scheme = isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // This file lives in /security, the pages in /php: work out the project folder from the script path.
        $base = $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/php/x.php'))), '/');
    }
    return $base . '/php/oauth-callback.php';
}

function ensureSocialLoginsTable(): void {
    getDb()->exec("CREATE TABLE IF NOT EXISTS social_logins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        provider ENUM('google', 'facebook') NOT NULL,
        provider_user_id VARCHAR(191) NOT NULL,
        email VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        UNIQUE KEY uq_social_provider_user (provider, provider_user_id),
        KEY idx_social_user (user_id),
        CONSTRAINT fk_social_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Step 1: the URL of Google's / Facebook's sign-in page. Remembers a one-time state in the session. */
function oauthStart(string $provider): string {
    $p = BH_OAUTH_PROVIDERS[$provider] ?? null;
    $cred = oauthCredentials($provider);
    if (!$p || !$cred) {
        throw new RuntimeException(($p['label'] ?? 'This') . ' sign-in is not set up yet. Please log in with your email and password.');
    }
    $state = bin2hex(random_bytes(24));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['oauth'] = ['provider' => $provider, 'state' => $state, 'verifier' => $verifier, 'started' => time()];

    $params = [
        'client_id' => $cred['id'],
        'redirect_uri' => oauthRedirectUri(),
        'response_type' => 'code',
        'scope' => $p['scope'],
        'state' => $state,
    ];
    if ($provider === 'google') {
        // PKCE: even a stolen code is useless without this session's verifier.
        $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $params['code_challenge_method'] = 'S256';
        $params['prompt'] = 'select_account';
    }
    return $p['authorize'] . '?' . http_build_query($params);
}

/** HTTPS request to Google/Facebook. Returns the decoded JSON. */
function oauthHttp(string $method, string $url, array $data = []): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP curl extension is off. Enable extension=curl in php.ini.');
    }
    $ch = curl_init();
    if ($method === 'GET' && $data) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    // XAMPP's PHP often has no certificate list: use the one installed by Composer.
    if (is_file(BH_COMPOSER_AUTOLOAD)) {
        require_once BH_COMPOSER_AUTOLOAD;
        if (class_exists(\Composer\CaBundle\CaBundle::class)) {
            $bundle = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
            curl_setopt($ch, is_dir($bundle) ? CURLOPT_CAPATH : CURLOPT_CAINFO, $bundle);
        }
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Could not reach the sign-in service: ' . $error);
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json) || $status >= 400) {
        $reason = is_array($json) ? ($json['error_description'] ?? $json['error']['message'] ?? $json['error'] ?? 'error') : 'invalid response';
        throw new RuntimeException("Sign-in service answered HTTP {$status}: " . (is_string($reason) ? $reason : json_encode($reason)));
    }
    return $json;
}

/** Step 2: exchanges the code for the person's profile: [id, email, email_verified, first_name, last_name]. */
function oauthProfile(string $provider, string $code, string $state): array {
    $saved = $_SESSION['oauth'] ?? null;
    unset($_SESSION['oauth']);
    if (!is_array($saved) || $saved['provider'] !== $provider || !hash_equals((string) $saved['state'], $state)
        || time() - (int) $saved['started'] > BH_OAUTH_STATE_MINUTES * 60) {
        throw new RuntimeException('The sign-in link expired. Please try again.');
    }
    $cred = oauthCredentials($provider);
    $p = BH_OAUTH_PROVIDERS[$provider];

    if ($provider === 'google') {
        $token = oauthHttp('POST', $p['token'], [
            'code' => $code, 'client_id' => $cred['id'], 'client_secret' => $cred['secret'],
            'redirect_uri' => oauthRedirectUri(), 'grant_type' => 'authorization_code', 'code_verifier' => $saved['verifier'],
        ]);
        $me = oauthHttp('GET', $p['profile'], ['access_token' => $token['access_token'] ?? '']);
        return [
            'id' => (string) ($me['sub'] ?? ''),
            'email' => strtolower((string) ($me['email'] ?? '')),
            'email_verified' => ($me['email_verified'] ?? false) === true || ($me['email_verified'] ?? '') === 'true',
            'first_name' => (string) ($me['given_name'] ?? ''),
            'last_name' => (string) ($me['family_name'] ?? ''),
        ];
    }

    $token = oauthHttp('GET', $p['token'], [
        'code' => $code, 'client_id' => $cred['id'], 'client_secret' => $cred['secret'], 'redirect_uri' => oauthRedirectUri(),
    ]);
    $access = (string) ($token['access_token'] ?? '');
    $me = oauthHttp('GET', $p['profile'], [
        'fields' => 'id,first_name,last_name,email',
        'access_token' => $access,
        'appsecret_proof' => hash_hmac('sha256', $access, $cred['secret']),
    ]);
    return [
        'id' => (string) ($me['id'] ?? ''),
        'email' => strtolower((string) ($me['email'] ?? '')),
        // Facebook only returns an email address the person has confirmed.
        'email_verified' => !empty($me['email']),
        'first_name' => (string) ($me['first_name'] ?? ''),
        'last_name' => (string) ($me['last_name'] ?? ''),
    ];
}

/** A free username like "juan.delacruz" / "juan.delacruz2" from the email. */
function oauthUsername(string $email): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9_.]/', '', explode('@', $email)[0]) ?: 'student');
    $base = substr($base, 0, 40);
    $stmt = getDb()->prepare('SELECT 1 FROM users WHERE username = :u');
    for ($i = 0; $i < 50; $i++) {
        $candidate = $i === 0 ? $base : $base . ($i + 1);
        $stmt->execute([':u' => $candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
    }
    return $base . bin2hex(random_bytes(3));
}

/**
 * Step 3: finds, links or creates the account, then logs in.
 * Returns ['success', 'message', 'redirect', 'created'].
 */
function oauthLogin(string $provider, array $profile): array {
    if ($profile['id'] === '') {
        return ['success' => false, 'message' => 'The sign-in service did not return your account. Please try again.'];
    }
    if ($profile['email'] === '' || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => BH_OAUTH_PROVIDERS[$provider]['label'] . ' did not share your email address. Allow the email permission, or log in with your email and password.'];
    }
    if (!$profile['email_verified']) {
        return ['success' => false, 'message' => 'Your ' . BH_OAUTH_PROVIDERS[$provider]['label'] . ' email address is not verified yet.'];
    }

    ensureSocialLoginsTable();
    $pdo = getDb();
    $select = 'SELECT id, username, email, password_hash, role, first_name, last_name, status FROM users';

    // 1. Already linked
    $stmt = $pdo->prepare($select . ' WHERE id = (SELECT user_id FROM social_logins WHERE provider = :p AND provider_user_id = :pid)');
    $stmt->execute([':p' => $provider, ':pid' => $profile['id']]);
    $user = $stmt->fetch();
    $created = false;

    // 2. Same email -> link
    if (!$user) {
        $stmt = $pdo->prepare($select . ' WHERE email = :email');
        $stmt->execute([':email' => $profile['email']]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('INSERT INTO social_logins (user_id, provider, provider_user_id, email) VALUES (:u, :p, :pid, :e)')
                ->execute([':u' => $user['id'], ':p' => $provider, ':pid' => $profile['id'], ':e' => $profile['email']]);
            auditLog('social_link', BH_OAUTH_PROVIDERS[$provider]['label'] . ' sign-in linked to the account', (int) $user['id'], 'user', (int) $user['id']);
        }
    }

    // 3. New student account
    if (!$user) {
        if (!settingEnabled('allow_tenant_registration')) {
            return ['success' => false, 'message' => 'Registration is currently closed. Please try again later.'];
        }
        $first = mb_substr(trim($profile['first_name']) ?: explode('@', $profile['email'])[0], 0, 80);
        $last = mb_substr(trim($profile['last_name']), 0, 80);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, status) VALUES (:u, :e, :h, 'tenant', :f, :l, 'active')")
            ->execute([':u' => oauthUsername($profile['email']), ':e' => $profile['email'],
                // No password: this account signs in with Google/Facebook (or "Forgot password" to set one).
                ':h' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), ':f' => $first, ':l' => $last]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO social_logins (user_id, provider, provider_user_id, email) VALUES (:u, :p, :pid, :e)')
            ->execute([':u' => $userId, ':p' => $provider, ':pid' => $profile['id'], ':e' => $profile['email']]);
        $pdo->commit();
        $created = true;
        $name = trim("{$first} {$last}");
        auditLog('register', 'New tenant registered with ' . BH_OAUTH_PROVIDERS[$provider]['label'] . ': ' . $name, $userId, 'user', $userId);
        notifyAdmins('user_registered', 'New user registered', $name . ' created a tenant account with ' . BH_OAUTH_PROVIDERS[$provider]['label'] . '.', '#/users?role=tenant');
        $stmt = $pdo->prepare($select . ' WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
    }

    if ($user['status'] === 'disabled') {
        return ['success' => false, 'message' => 'This account has been disabled. Please contact the administrator.'];
    }
    $pdo->prepare('UPDATE social_logins SET last_used_at = NOW() WHERE provider = :p AND provider_user_id = :pid')
        ->execute([':p' => $provider, ':pid' => $profile['id']]);

    // The Super Admin still needs the emailed code.
    if (twoFactorRequiredFor((string) $user['role'])) {
        $sent = startTwoFactorLogin($user);
        return $sent['success']
            ? ['success' => true, 'message' => $sent['message'], 'redirect' => 'php/verify-login.php', 'created' => false]
            : ['success' => false, 'message' => $sent['message']];
    }

    $result = completeLogin($user);
    return ['success' => true, 'message' => $result['message'], 'redirect' => $result['redirect'], 'created' => $created];
}
