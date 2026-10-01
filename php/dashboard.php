<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';

applySecurityHeaders();
requireLogin();
startSecureSession();

$userName = sanitizeForOutput($_SESSION['username'] ?? 'User');
$role = sanitizeForOutput($_SESSION['role'] ?? 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
    .card { border-radius: 18px; box-shadow: 0 20px 50px -15px rgba(15, 23, 42, 0.12); }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="card p-4 p-md-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="h3 mb-1">Welcome, <?php echo $userName; ?>!</h1>
          <p class="text-muted mb-0">Role: <?php echo $role; ?></p>
        </div>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
      </div>
      <div class="alert alert-success">Your account is protected with secure sessions, password hashing, and CSRF validation.</div>
      <p>This dashboard is a protected landing page for authenticated users. Extend it with real boarding-house management features as the system grows.</p>
      <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="browse-rooms.php" class="btn btn-outline-primary">Browse Rooms</a>
        <?php if (in_array($role, ['super_admin', 'admin'], true)): ?>
          <a href="../admin/adminpanel.php" class="btn btn-outline-success">Open Admin Panel</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
