<?php

declare(strict_types=1);

/**
 * Bootstrap for every /api/admin/* endpoint.
 *
 *  - JSON responses + security headers
 *  - 401 when not logged in, 403 when the user is not an administrator
 *  - The role and status are re-read from the database on every request, so
 *    disabling an admin or changing their role takes effect immediately.
 *  - Every state-changing request (POST/PUT/PATCH/DELETE) must carry the CSRF token.
 */

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../_queries.php';

apiBootstrap();

if (!isLoggedIn()) {
    jsonError('Please log in to continue.', 401);
}

$adminStmt = getDb()->prepare('SELECT id, username, email, role, first_name, last_name, status FROM users WHERE id = :id LIMIT 1');
$adminStmt->execute([':id' => currentUserId()]);
$ADMIN = $adminStmt->fetch();

if (!$ADMIN || $ADMIN['status'] === 'disabled') {
    logoutUser();
    jsonError('Your session has ended. Please log in again.', 401);
}

if (!isAdminRole($ADMIN['role'])) {
    jsonError('You do not have permission to access the admin area.', 403);
}

$_SESSION['role'] = $ADMIN['role'];
$ADMIN['id'] = (int) $ADMIN['id'];
$ADMIN['full_name'] = trim($ADMIN['first_name'] . ' ' . $ADMIN['last_name']) ?: $ADMIN['username'];

if (requestMethod() !== 'GET') {
    requireCsrf();
}

function adminId(): int {
    global $ADMIN;
    return $ADMIN['id'];
}

function isSuperAdmin(): bool {
    global $ADMIN;
    return $ADMIN['role'] === ROLE_SUPER_ADMIN;
}

function adminLog(string $action, string $description, ?string $entityType = null, ?int $entityId = null): void {
    auditLog($action, $description, adminId(), $entityType, $entityId);
}
