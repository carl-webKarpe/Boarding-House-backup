<?php

declare(strict_types=1);

/**
 * Query and formatting helpers shared by the admin API (api/admin) and the
 * landlord API (api/landlord).
 */

require_once __DIR__ . '/_common.php';

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

/**
 * Moves one occupant into (+1) or out of (-1) a room inside the current
 * transaction, locking the row so two approvals cannot overbook it.
 */
function adjustOccupancy(int $roomId, int $delta): void {
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT capacity, occupants, status FROM rooms WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $roomId]);
    $room = $stmt->fetch();
    if (!$room) {
        throw new ApiException('Room not found.', 404);
    }

    $capacity = (int) $room['capacity'];
    $occupants = (int) $room['occupants'] + $delta;
    if ($delta > 0) {
        if ($room['status'] === 'maintenance') {
            throw new ApiException('This room is under maintenance and cannot accept tenants.', 409);
        }
        if ($occupants > $capacity) {
            throw new ApiException('This room is already full.', 409);
        }
    }
    $occupants = max(0, $occupants);

    $pdo->prepare('UPDATE rooms SET occupants = :occupants, status = :status WHERE id = :id')
        ->execute([':occupants' => $occupants, ':status' => roomStatusFor($capacity, $occupants, $room['status']), ':id' => $roomId]);
}
