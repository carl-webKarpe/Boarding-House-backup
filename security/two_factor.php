<?php

declare(strict_types=1);

/**
 * Two-step login for the Super Admin: after the correct password, a 6-digit
 * code is emailed (Gmail, PHPMailer) and must be entered on
 * php/verify-login.php before the session is logged in.
 *
 *  - The code is stored only as a hash and expires after 10 minutes.
 *  - 5 wrong tries cancel the code; a new one can be requested.
 *  - "Resend" waits 60 seconds between emails, at most 5 emails per 15 minutes.
 *  - Until the code is entered the session is NOT logged in (no user_id/role).
 *  - While security/mail.local.php is not set up the step is skipped and a
 *    warning is written to storage/app.log, so nobody is locked out.
 */

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/rate_limit.php';

const BH_2FA_ROLES = [ROLE_SUPER_ADMIN];
const BH_2FA_CODE_MINUTES = 10;
const BH_2FA_PENDING_MINUTES = 15;
const BH_2FA_MAX_ATTEMPTS = 5;
const BH_2FA_RESEND_SECONDS = 60;
const BH_2FA_MAX_SENDS = 5;

function ensureLoginCodesTable(): void {
    // Created automatically, so an existing database needs no extra SQL script.
    getDb()->exec("CREATE TABLE IF NOT EXISTS login_codes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        used_at DATETIME NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_login_codes_user (user_id, created_at),
        CONSTRAINT fk_login_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Does this role have to enter an emailed code? */
function twoFactorRequiredFor(string $role): bool {
    if (!in_array($role, BH_2FA_ROLES, true)) {
        return false;
    }
    if (!mailReady()) {
        writeLog('Super Admin email verification is OFF: ' . mailProblem(), 'WARN');
        return false;
    }
    return true;
}

/** a***n@gmail.com */
function maskEmail(string $email): string {
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $shown = mb_strlen($name) <= 2 ? mb_substr($name, 0, 1) : mb_substr($name, 0, 1) . str_repeat('*', min(5, max(1, mb_strlen($name) - 2))) . mb_substr($name, -1);
    return $shown . '@' . $domain;
}

function pendingTwoFactor(): ?array {
    $pending = $_SESSION['pending_2fa'] ?? null;
    if (!is_array($pending) || (time() - (int) $pending['started']) > BH_2FA_PENDING_MINUTES * 60) {
        unset($_SESSION['pending_2fa']);
        return null;
    }
    return $pending;
}

/** Creates a new code, emails it and remembers the half-finished login in the session. */
function sendTwoFactorCode(array $user): array {
    ensureLoginCodesTable();
    $pdo = getDb();
    $userId = (int) $user['id'];

    $recent = $pdo->prepare('SELECT COUNT(*) FROM login_codes WHERE user_id = :u AND created_at > NOW() - INTERVAL ' . BH_2FA_PENDING_MINUTES . ' MINUTE');
    $recent->execute([':u' => $userId]);
    if ((int) $recent->fetchColumn() >= BH_2FA_MAX_SENDS) {
        return ['success' => false, 'status' => 429, 'message' => 'Too many codes were requested. Please wait 15 minutes and log in again.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE login_codes SET used_at = NOW() WHERE user_id = :u AND used_at IS NULL')->execute([':u' => $userId]);
    $pdo->prepare('INSERT INTO login_codes (user_id, code_hash, expires_at, ip_address) VALUES (:u, :h, NOW() + INTERVAL ' . BH_2FA_CODE_MINUTES . ' MINUTE, :ip)')
        ->execute([':u' => $userId, ':h' => password_hash($code, PASSWORD_DEFAULT), ':ip' => getClientIp()]);

    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: (string) $user['username'];
    $when = date('F j, Y g:i A');
    $ip = getClientIp();
    $minutes = BH_2FA_CODE_MINUTES;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1e293b">
  <h2 style="margin:0 0 8px;color:#15803d">Your login verification code</h2>
  <p>Hi {$safeName},</p>
  <p>Use this code to finish logging in to the <strong>Boarding House Rental System</strong> admin dashboard:</p>
  <p style="font-size:34px;font-weight:bold;letter-spacing:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;text-align:center;color:#14532d">{$code}</p>
  <p>The code expires in <strong>{$minutes} minutes</strong>. Never share it with anyone.</p>
  <p style="font-size:12px;color:#64748b">Requested on {$when} from IP {$ip}. If this was not you, change your password right away.</p>
</div>
HTML;
    $text = "Hi {$name},\n\nYour Boarding House Rental System login code is: {$code}\n\nIt expires in {$minutes} minutes. Never share it.\n"
        . "Requested on {$when} from IP {$ip}. If this was not you, change your password right away.";

    $sent = sendMail((string) $user['email'], $name, "{$code} is your login code", $html, $text);
    if (!$sent['success']) {
        return ['success' => false, 'status' => 503, 'message' => $sent['message']];
    }

    $_SESSION['pending_2fa'] = [
        'user_id' => $userId,
        'email' => maskEmail((string) $user['email']),
        'started' => $_SESSION['pending_2fa']['started'] ?? time(),
        'last_sent' => time(),
    ];
    auditLog('login_code_sent', 'Verification code emailed to ' . maskEmail((string) $user['email']), $userId, 'user', $userId);
    return ['success' => true, 'message' => 'We sent a 6-digit code to ' . maskEmail((string) $user['email']) . '.'];
}

/** Step 1, called by loginUser() after the correct password. */
function startTwoFactorLogin(array $user): array {
    // A fresh, not-logged-in session for the half-finished login.
    $_SESSION = [];
    session_regenerate_id(true);
    return sendTwoFactorCode($user);
}

function resendTwoFactorCode(): array {
    $pending = pendingTwoFactor();
    if (!$pending) {
        return ['success' => false, 'status' => 440, 'message' => 'Your login expired. Please log in again.'];
    }
    $wait = BH_2FA_RESEND_SECONDS - (time() - (int) $pending['last_sent']);
    if ($wait > 0) {
        return ['success' => false, 'status' => 429, 'message' => "Please wait {$wait} seconds before asking for a new code."];
    }
    return sendTwoFactorCode(twoFactorUser((int) $pending['user_id']));
}

function twoFactorUser(int $id): array {
    $stmt = getDb()->prepare('SELECT id, username, email, password_hash, role, first_name, last_name, status FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: [];
}

/** Step 2: checks the code; on success the session is logged in. */
function verifyTwoFactorCode(string $code): array {
    $pending = pendingTwoFactor();
    if (!$pending) {
        return ['success' => false, 'status' => 440, 'message' => 'Your login expired. Please log in again.'];
    }
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return ['success' => false, 'status' => 422, 'message' => 'Enter the 6-digit code from the email.'];
    }

    ensureLoginCodesTable();
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT id, code_hash, attempts, expires_at < NOW() AS expired FROM login_codes
        WHERE user_id = :u AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([':u' => $pending['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['success' => false, 'status' => 410, 'message' => 'This code is no longer valid. Press "Send a new code".'];
    }
    if ((int) $row['expired']) {
        return ['success' => false, 'status' => 410, 'message' => 'The code expired. Press "Send a new code".'];
    }
    if (!password_verify($code, (string) $row['code_hash'])) {
        $attempts = (int) $row['attempts'] + 1;
        $pdo->prepare('UPDATE login_codes SET attempts = :a, used_at = IF(:a2 >= :max, NOW(), NULL) WHERE id = :id')
            ->execute([':a' => $attempts, ':a2' => $attempts, ':max' => BH_2FA_MAX_ATTEMPTS, ':id' => $row['id']]);
        auditLog('login_code_failed', 'Wrong verification code entered', (int) $pending['user_id'], 'user', (int) $pending['user_id']);
        $left = BH_2FA_MAX_ATTEMPTS - $attempts;
        return ['success' => false, 'status' => 401, 'message' => $left > 0
            ? "Wrong code. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.'
            : 'Too many wrong codes. Press "Send a new code".'];
    }

    $pdo->prepare('UPDATE login_codes SET used_at = NOW() WHERE id = :id')->execute([':id' => $row['id']]);
    $user = twoFactorUser((int) $pending['user_id']);
    if (!$user || $user['status'] === 'disabled') {
        unset($_SESSION['pending_2fa']);
        return ['success' => false, 'status' => 403, 'message' => 'This account cannot log in. Please contact the administrator.'];
    }
    unset($_SESSION['pending_2fa']);
    return completeLogin($user, true);
}
