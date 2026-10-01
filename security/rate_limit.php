<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * File-based fixed-window counters.
 *
 * Only FAILED attempts are counted, and the login limit is keyed on
 * IP + email. Students on the same campus Wi-Fi share one public IP, so a
 * per-IP limit that also counted successful logins would lock out a whole
 * school after a handful of logins.
 */
function rateLimitFile(string $scope, string $identifier): string {
    if (!is_dir(BH_RATE_LIMIT_FILE_DIR)) {
        @mkdir(BH_RATE_LIMIT_FILE_DIR, 0750, true);
    }

    return BH_RATE_LIMIT_FILE_DIR . '/ratelimit_' . preg_replace('/[^a-z0-9_]/i', '', $scope) . '_' . hash('sha256', $identifier) . '.json';
}

function readRateLimit(string $file, int $windowMinutes): array {
    $now = time();
    $data = ['attempts' => 0, 'window_start' => $now];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if (($now - (int) ($data['window_start'] ?? $now)) > ($windowMinutes * 60)) {
        $data = ['attempts' => 0, 'window_start' => $now];
    }

    return $data;
}

function isRateLimited(string $scope, string $identifier, int $maxAttempts, int $windowMinutes): bool {
    $data = readRateLimit(rateLimitFile($scope, $identifier), $windowMinutes);
    return (int) $data['attempts'] >= $maxAttempts;
}

function hitRateLimit(string $scope, string $identifier, int $windowMinutes): void {
    $file = rateLimitFile($scope, $identifier);
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    $data = readRateLimit($file, $windowMinutes);
    $data['attempts'] = (int) $data['attempts'] + 1;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, (string) json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function clearRateLimit(string $scope, string $identifier): void {
    $file = rateLimitFile($scope, $identifier);
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Counts a failed login against the account so it is locked after
 * BH_MAX_LOGIN_ATTEMPTS failures for BH_LOCKOUT_MINUTES.
 */
function recordFailedLogin(string $email): void {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id, failed_login_attempts, locked_until FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return;
        }

        $previousAttempts = (int) $user['failed_login_attempts'];
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= time()) {
            // The previous lock has expired, so start counting again.
            $previousAttempts = 0;
        }
        $newAttempts = $previousAttempts + 1;
        $lockedUntil = null;
        if ($newAttempts >= BH_MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = (new DateTimeImmutable('+' . BH_LOCKOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id');
        $stmt->execute([
            ':attempts' => $newAttempts,
            ':locked_until' => $lockedUntil,
            ':id' => $user['id'],
        ]);
    } catch (Throwable $e) {
        writeLog('Failed-login persistence failed: ' . $e->getMessage(), 'ERROR');
    }
}
