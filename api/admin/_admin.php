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

function requireId(string $key = 'id'): int {
    $id = queryInt($key, 0, 0);
    if ($id <= 0) {
        throw new ApiException('A valid id is required.', 400);
    }

    return $id;
}

function notFound(string $what): never {
    throw new ApiException($what . ' not found.', 404);
}

/**
 * Builds "ORDER BY" from ?sort=&order= using a whitelist of column expressions.
 */
function orderBy(array $allowed, string $defaultSort, string $defaultOrder = 'desc'): string {
    $sort = queryString('sort', $defaultSort);
    $column = $allowed[$sort] ?? $allowed[$defaultSort];
    $order = strtolower(queryString('order', $defaultOrder)) === 'asc' ? 'ASC' : 'DESC';
    return " ORDER BY {$column} {$order}";
}

/**
 * Runs a COUNT(*) and a paged SELECT that share the same FROM/WHERE.
 */
function pagedQuery(string $select, string $fromWhere, array $params, string $orderBy, array $pagination): array {
    $pdo = getDb();
    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $fromWhere);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare($select . ' ' . $fromWhere . $orderBy . ' LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset']);
    $stmt->execute($params);

    return [$stmt->fetchAll(), paginationMeta($total, $pagination)];
}

function fullName(array $row, string $prefix = ''): string {
    $name = trim(($row[$prefix . 'first_name'] ?? '') . ' ' . ($row[$prefix . 'last_name'] ?? ''));
    return $name !== '' ? $name : (string) ($row[$prefix . 'username'] ?? '');
}

/**
 * Recomputes a room's status from its occupancy unless it is under maintenance.
 */
function roomStatusFor(int $capacity, int $occupants, string $requested): string {
    if ($requested === 'maintenance') {
        return 'maintenance';
    }

    return $occupants >= $capacity ? 'occupied' : 'available';
}

function castRow(array $row, array $intFields = [], array $floatFields = []): array {
    foreach ($intFields as $f) {
        if (array_key_exists($f, $row) && $row[$f] !== null) {
            $row[$f] = (int) $row[$f];
        }
    }
    foreach ($floatFields as $f) {
        if (array_key_exists($f, $row) && $row[$f] !== null) {
            $row[$f] = (float) $row[$f];
        }
    }

    return $row;
}
