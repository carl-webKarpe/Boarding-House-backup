<?php

declare(strict_types=1);

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/audit_log.php';
require_once __DIR__ . '/../security/sanitize.php';

applySecurityHeaders();
startSecureSession();

// Logging out changes state, so it must be a POST with the CSRF token.
// Otherwise any website could log users out with a simple link or image.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Your session expired. Please go back and try again.');
    }

    if (!empty($_SESSION['user_id'])) {
        auditLog('logout', 'User logged out: ' . ($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''), (int) $_SESSION['user_id'], 'user', (int) $_SESSION['user_id']);
    }
    logoutUser();
    redirectTo('html/loginform.html');
}

if (!isLoggedIn()) {
    redirectTo('html/loginform.html');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Log out | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
  <main class="container py-5" style="max-width: 28rem;">
    <div class="card border-0 shadow-sm p-4 text-center">
      <h1 class="h4">Log out?</h1>
      <p class="text-muted">You are signed in as <?php echo sanitizeForOutput($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''); ?>.</p>
      <form method="post" class="d-flex gap-2 justify-content-center">
        <?php echo csrfInput(); ?>
        <a href="<?php echo sanitizeForOutput(appUrl(homePathForRole(currentUserRole()))); ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-danger">Log out</button>
      </form>
    </div>
  </main>
</body>
</html>
