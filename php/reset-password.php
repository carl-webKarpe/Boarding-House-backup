<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/validation.php';
require_once __DIR__ . '/../security/csrf.php';
require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../security/audit_log.php';

applySecurityHeaders();
startSecureSession();

$message = '';
$error = '';
$token = sanitizeText($_GET['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $token = sanitizeText($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $validation = validatePasswordValue($password);
            if (!$validation['valid']) {
                $error = $validation['message'];
            } else {
                try {
                    $pdo = getDb();
                    $tokenHash = hash('sha256', $token);
                    $stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token = :token AND used_at IS NULL ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute([':token' => $tokenHash]);
                    $reset = $stmt->fetch();
                    if (!$reset) {
                        $error = 'Invalid or expired reset token.';
                    } elseif (new DateTimeImmutable((string) $reset['expires_at']) < new DateTimeImmutable('now')) {
                        $error = 'This reset link has expired.';
                    } else {
                        $userId = (int) $reset['user_id'];
                        if (!changePassword($userId, $password)) {
                            $error = 'Password could not be updated.';
                        } else {
                            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')->execute([':user_id' => $userId]);
                            $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id')->execute([':id' => $userId]);
                            auditLog('password_reset', 'Password reset completed', $userId);
                            $message = 'Your password has been reset successfully. You can now log in.';
                        }
                    }
                } catch (Throwable $e) {
                    writeLog('Password reset failed: ' . $e->getMessage(), 'ERROR');
                    $error = 'Unable to reset password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { background: linear-gradient(135deg, #f8fafc 0%, #ecfeff 100%); color: #0f172a; font-family: Arial, sans-serif; }
    .card { border-radius: 24px; box-shadow: 0 25px 60px -20px rgba(15, 23, 42, 0.16); border: 1px solid rgba(15, 118, 110, 0.08); }
    .hero-pill { background: #ccfbf1; color: #0f766e; border-radius: 999px; padding: 0.4rem 0.8rem; display: inline-block; font-size: 0.82rem; font-weight: 700; }
    .btn-teal { background: #0f766e; color: #fff; border: none; }
    .btn-teal:hover { background: #115e59; color: #fff; }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card p-4 p-md-5">
          <div class="text-center mb-4">
            <span class="hero-pill">Create New Password</span>
            <h1 class="h3 mt-3 mb-2">Set a strong new password</h1>
            <p class="text-muted mb-0">Use a combination of letters, numbers, and symbols to keep your account secure.</p>
          </div>
          <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" class="mt-3">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="mb-3">
              <label for="password" class="form-label fw-semibold">New password</label>
              <input type="password" id="password" name="password" class="form-control rounded-3" placeholder="Enter a secure password" required />
            </div>
            <div class="mb-3">
              <label for="confirm_password" class="form-label fw-semibold">Confirm password</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control rounded-3" placeholder="Re-enter your password" required />
            </div>
            <button type="submit" class="btn btn-teal w-100 rounded-pill py-2">Reset password</button>
          </form>
          <div class="mt-3 text-center">
            <a href="../html/loginform.html" class="text-decoration-none fw-semibold">Back to login</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
