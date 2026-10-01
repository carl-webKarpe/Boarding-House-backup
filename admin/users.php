<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/database.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/roles.php';
require_once __DIR__ . '/../security/audit_log.php';

applySecurityHeaders();
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN]);
startSecureSession();

$message = '';
$error = '';

try {
    $pdo = getDb();
    $stmt = $pdo->query('SELECT id, username, email, role, is_active, created_at FROM users ORDER BY id DESC');
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    writeLog('User listing failed: ' . $e->getMessage(), 'ERROR');
    $users = [];
    $error = 'Unable to load users.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>body{background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a}.card{border-radius:20px;box-shadow:0 20px 50px -15px rgba(15,23,42,.12)} </style>
</head>
<body>
  <div class="container py-5">
    <div class="card p-4 p-md-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="h3 mb-1">User Management</h1>
          <p class="text-muted mb-0">Manage access for staff, admins, and regular users.</p>
        </div>
        <a href="../php/dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
      </div>

      <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo sanitizeForOutput($message); ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo sanitizeForOutput($error); ?></div><?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?php echo sanitizeForOutput($user['id']); ?></td>
                <td><?php echo sanitizeForOutput($user['username']); ?></td>
                <td><?php echo sanitizeForOutput($user['email']); ?></td>
                <td><?php echo sanitizeForOutput($user['role']); ?></td>
                <td><?php echo ((int) $user['is_active'] === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                <td><?php echo sanitizeForOutput($user['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
