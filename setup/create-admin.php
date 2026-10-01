<?php

declare(strict_types=1);

/**
 * Creates the first administrator account.
 *
 * Browser:  http://localhost/BHsystem/setup/create-admin.php
 *           Works ONLY while the database has no admin account yet, and only
 *           from the same computer (localhost). After that it refuses to run.
 *
 * Command line (always allowed; can add more super admins):
 *   php setup/create-admin.php admin@example.com "Admin@12345" "First" "Last"
 */

require_once __DIR__ . '/../security/config.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/validation.php';
require_once __DIR__ . '/../security/sanitize.php';

function createSuperAdmin(string $email, string $password, string $first, string $last): array {
    $errors = [];
    $emailCheck = validateEmailValue($email);
    $passwordCheck = validatePasswordValue($password);
    if (!$emailCheck['valid']) {
        $errors[] = $emailCheck['message'];
    }
    if (!$passwordCheck['valid']) {
        $errors[] = $passwordCheck['message'];
    }
    if (trim($first) === '' || trim($last) === '') {
        $errors[] = 'First and last name are required.';
    }
    if ($errors) {
        return ['ok' => false, 'message' => implode(' ', $errors)];
    }

    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $emailCheck['value']]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => 'An account with this email already exists.'];
    }

    $base = 'admin_' . preg_replace('/[^a-z0-9]/', '', strtolower($first));
    $username = substr($base, 0, 14);
    $suffix = 1;
    $check = $pdo->prepare('SELECT id FROM users WHERE username = :u');
    while (true) {
        $check->execute([':u' => $username]);
        if (!$check->fetch()) {
            break;
        }
        $username = substr($base, 0, 14) . (++$suffix);
    }

    $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, status) VALUES (:u, :e, :p, 'super_admin', :f, :l, 'active')")
        ->execute([':u' => $username, ':e' => $emailCheck['value'], ':p' => password_hash($password, PASSWORD_DEFAULT), ':f' => trim($first), ':l' => trim($last)]);

    return ['ok' => true, 'message' => "Super admin created. Log in with {$emailCheck['value']}."];
}

function adminExists(): bool {
    return (int) getDb()->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'super_admin')")->fetchColumn() > 0;
}

if (PHP_SAPI === 'cli') {
    if ($argc < 5) {
        fwrite(STDERR, "Usage: php setup/create-admin.php <email> <password> <first name> <last name>\n");
        exit(1);
    }
    try {
        $result = createSuperAdmin($argv[1], $argv[2], $argv[3], $argv[4]);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Database error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    fwrite($result['ok'] ? STDOUT : STDERR, $result['message'] . "\n");
    exit($result['ok'] ? 0 : 1);
}

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
applySecurityHeaders();

$message = '';
$success = false;
$blocked = '';
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

try {
    if (!$isLocal) {
        $blocked = 'For security, this page only works on the computer running XAMPP (http://localhost).';
    } elseif (adminExists()) {
        $blocked = 'An administrator account already exists, so this setup page is locked. Log in, then add more administrators from Admin > Users.';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $message = 'Your session expired. Please try again.';
        } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
            $message = 'Passwords do not match.';
        } else {
            $result = createSuperAdmin((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['first_name'] ?? ''), (string) ($_POST['last_name'] ?? ''));
            $message = $result['message'];
            $success = $result['ok'];
        }
    }
} catch (DatabaseUnavailableException $e) {
    $blocked = 'Cannot connect to MySQL. Start MySQL in XAMPP, import database/schema.sql, and check security/config.php.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create administrator | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
  <main class="container py-5" style="max-width: 32rem;">
    <div class="card border-0 shadow-sm p-4">
      <h1 class="h4 mb-1">Create the first administrator</h1>
      <p class="text-muted small">This account can manage every part of the system.</p>
      <?php if ($blocked !== ''): ?>
        <div class="alert alert-warning mb-0"><?php echo sanitizeForOutput($blocked); ?></div>
      <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo sanitizeForOutput($message); ?></div>
        <a class="btn btn-success" href="../html/loginform.html">Go to login</a>
      <?php else: ?>
        <?php if ($message !== ''): ?><div class="alert alert-danger"><?php echo sanitizeForOutput($message); ?></div><?php endif; ?>
        <form method="post">
          <?php echo csrfInput(); ?>
          <div class="row g-3">
            <div class="col-6"><label class="form-label" for="first_name">First name</label><input class="form-control" id="first_name" name="first_name" required value="<?php echo sanitizeForOutput($_POST['first_name'] ?? ''); ?>" /></div>
            <div class="col-6"><label class="form-label" for="last_name">Last name</label><input class="form-control" id="last_name" name="last_name" required value="<?php echo sanitizeForOutput($_POST['last_name'] ?? ''); ?>" /></div>
            <div class="col-12"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required value="<?php echo sanitizeForOutput($_POST['email'] ?? ''); ?>" /></div>
            <div class="col-12"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="new-password" />
              <div class="form-text">At least 8 characters with upper &amp; lower case letters, a number and a symbol.</div></div>
            <div class="col-12"><label class="form-label" for="confirm_password">Confirm password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" /></div>
          </div>
          <button class="btn btn-success w-100 mt-4" type="submit">Create administrator</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
