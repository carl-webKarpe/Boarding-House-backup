<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/validation.php';
require_once __DIR__ . '/../security/csrf.php';
require_once __DIR__ . '/../security/audit_log.php';

applySecurityHeaders();
startSecureSession();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $validation = validateEmailValue($email);
        if (!$validation['valid']) {
            $error = $validation['message'];
        } else {
            try {
                $pdo = getDb();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $validation['value']]);
                $user = $stmt->fetch();

                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
                    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (:user_id, :token, :expires_at, NOW())');
                    $stmt->execute([
                        ':user_id' => (int) $user['id'],
                        ':token' => $tokenHash,
                        ':expires_at' => $expiresAt,
                    ]);

                    $scheme = isHttps() ? 'https' : 'http';
                    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
                    if (!in_array($port, [80, 443], true)) {
                        $host .= ':' . $port;
                    }
                    $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/php/forgot-password.php'))), '/');
                    $resetLink = $scheme . '://' . $host . $basePath . '/reset-password.php?token=' . urlencode($token);

                    // The link must only reach the account owner. Showing it on this page
                    // would let anyone reset any account just by typing its email.
                    // Until email sending is set up, the link is written to storage/app.log
                    // so the developer can test the flow locally.
                    writeLog('Password reset link for user #' . (int) $user['id'] . ': ' . $resetLink, 'INFO');
                    auditLog('password_reset_request', 'Password reset requested', (int) $user['id']);
                }
                $message = 'If the account exists, a password reset link has been sent to its email address.';
            } catch (Throwable $e) {
                writeLog('Password reset request failed: ' . $e->getMessage(), 'ERROR');
                $error = 'Unable to process your request right now.';
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
  <title>Forgot Password | Boarding House Rental System</title>
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
            <span class="hero-pill">Secure Account Recovery</span>
            <h1 class="h3 mt-3 mb-2">Reset your password</h1>
            <p class="text-muted mb-0">Enter your email address and we will prepare a secure password reset link for your account.</p>
          </div>

          <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" class="mt-3">
            <?php echo csrfInput(); ?>
            <div class="mb-3">
              <label for="email" class="form-label fw-semibold">Email address</label>
              <input type="email" id="email" name="email" class="form-control rounded-3" placeholder="you@example.com" required />
            </div>
            <button type="submit" class="btn btn-teal w-100 rounded-pill py-2">Send reset link</button>
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
