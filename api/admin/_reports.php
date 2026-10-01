<?php

declare(strict_types=1);

/**
 * Shared statistics queries for the dashboard and the Reports page.
 */

/**
 * @return array{keys: string[], labels: string[], start: string}
 */
function monthRange(int $months): array {
    $first = new DateTimeImmutable('first day of this month 00:00:00');
    $keys = [];
    $labels = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $month = $first->modify("-{$i} months");
        $keys[] = $month->format('Y-m');
        // The current month is still in progress, so say so instead of showing a misleading drop.
        $labels[] = $month->format('M Y') . ($i === 0 ? ' (to date)' : '');
    }

    return ['keys' => $keys, 'labels' => $labels, 'start' => $first->modify('-' . ($months - 1) . ' months')->format('Y-m-d H:i:s')];
}

/**
 * Counts rows per month, split by a category column.
 * @return array<string, int[]> category => counts aligned with $range['keys']
 */
function monthlySeries(string $table, string $dateColumn, string $categoryColumn, array $categories, array $range, string $extraWhere = ''): array {
    $stmt = getDb()->prepare("SELECT DATE_FORMAT({$dateColumn}, '%Y-%m') AS ym, {$categoryColumn} AS category, COUNT(*) AS total
        FROM {$table} WHERE {$dateColumn} >= :start {$extraWhere} GROUP BY ym, category");
    $stmt->execute([':start' => $range['start']]);

    $series = [];
    foreach ($categories as $key => $values) {
        $series[$key] = array_fill(0, count($range['keys']), 0);
    }
    $index = array_flip($range['keys']);
    foreach ($stmt->fetchAll() as $row) {
        foreach ($categories as $key => $values) {
            if (in_array($row['category'], $values, true) && isset($index[$row['ym']])) {
                $series[$key][$index[$row['ym']]] += (int) $row['total'];
            }
        }
    }

    return $series;
}

function statusCounts(string $table, array $statuses): array {
    $counts = array_fill_keys($statuses, 0);
    foreach (getDb()->query("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status") as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['total'];
        }
    }

    return $counts;
}

function overviewCards(): array {
    $pdo = getDb();
    $monthStart = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');

    $users = $pdo->prepare("SELECT
        SUM(role = 'tenant') AS tenants,
        SUM(role = 'tenant' AND created_at >= :m1) AS tenants_month,
        SUM(role = 'landlord') AS landlords,
        SUM(role = 'landlord' AND status = 'active') AS active_landlords,
        SUM(role = 'landlord' AND created_at >= :m2) AS landlords_month,
        COUNT(*) AS users,
        SUM(created_at >= :m3) AS users_month
        FROM users");
    $users->execute([':m1' => $monthStart, ':m2' => $monthStart, ':m3' => $monthStart]);
    $u = array_map('intval', $users->fetch());

    $houses = $pdo->prepare("SELECT COUNT(*) AS total, SUM(created_at >= :m) AS month, SUM(status = 'pending') AS pending, SUM(status = 'approved') AS approved FROM boarding_houses");
    $houses->execute([':m' => $monthStart]);
    $h = array_map('intval', $houses->fetch());

    $rooms = array_map('intval', $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'available') AS available, SUM(status = 'occupied') AS occupied,
        SUM(status = 'maintenance') AS maintenance, COALESCE(SUM(capacity), 0) AS beds, COALESCE(SUM(occupants), 0) AS occupants FROM rooms")->fetch());

    $pendingLandlords = (int) $pdo->query("SELECT COUNT(*) FROM landlords WHERE verification_status = 'pending'")->fetchColumn();
    $pendingBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();

    $bookingsMonth = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_date >= :m');
    $bookingsMonth->execute([':m' => $monthStart]);

    return [
        'tenants' => ['total' => $u['tenants'], 'this_month' => $u['tenants_month']],
        'landlords' => ['total' => $u['landlords'], 'active' => $u['active_landlords'], 'this_month' => $u['landlords_month']],
        'users' => ['total' => $u['users'], 'this_month' => $u['users_month']],
        'boarding_houses' => ['total' => $h['total'], 'approved' => $h['approved'], 'this_month' => $h['month']],
        'rooms' => $rooms,
        'available_rooms' => $rooms['available'],
        'occupied_rooms' => $rooms['occupied'],
        'occupancy_rate' => $rooms['beds'] > 0 ? round($rooms['occupants'] / $rooms['beds'] * 100, 1) : 0,
        'pending_approvals' => [
            'total' => $h['pending'] + $pendingLandlords,
            'listings' => $h['pending'],
            'landlords' => $pendingLandlords,
        ],
        'pending_bookings' => $pendingBookings,
        'bookings_this_month' => (int) $bookingsMonth->fetchColumn(),
    ];
}

function recentActivities(int $limit): array {
    $stmt = getDb()->prepare('SELECT a.id, a.action, a.description, a.entity_type, a.entity_id, a.created_at,
        u.first_name, u.last_name, u.username, u.role
        FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit);
    $stmt->execute();

    return array_map(static function (array $row): array {
        $row = castRow($row, ['id', 'entity_id']);
        $row['actor'] = $row['username'] ? fullName($row) : 'System';
        unset($row['first_name'], $row['last_name'], $row['username']);
        return $row;
    }, $stmt->fetchAll());
}
