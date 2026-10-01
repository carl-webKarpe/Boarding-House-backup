<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/roles.php';

applySecurityHeaders();
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN]);
startSecureSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Panel | Boarding House Rental System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>body{background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a}.card{border-radius:20px;box-shadow:0 20px 50px -15px rgba(15,23,42,.12)} </style>
</head>
<body>
  <div class="container py-5">
    <div class="card p-4 p-md-5">
      <h1 class="h3 mb-2">Admin Panel</h1>
      <p class="text-muted">Use the secure admin section to manage users and operations.</p>
      <div class="d-flex gap-2 flex-wrap mt-4">
        <a href="users.php" class="btn btn-success">Manage Users</a>
        <a href="../php/dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
      </div>
    </div>
  </div>
</body>
</html>
