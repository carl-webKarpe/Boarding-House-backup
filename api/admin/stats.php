<?php

declare(strict_types=1);

/**
 * Dashboard overview: statistic cards, chart data, recent activity and
 * items waiting for approval.
 *
 *   GET stats.php?months=6
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/_reports.php';

requireMethod('GET');

$range = monthRange(queryInt('months', 6, 3, 12));
$pdo = getDb();

$pendingHouses = $pdo->query("SELECT bh.id, bh.name, bh.city, bh.created_at, u.first_name AS owner_first_name, u.last_name AS owner_last_name, u.username AS owner_username
    FROM boarding_houses bh JOIN landlords l ON l.id = bh.landlord_id JOIN users u ON u.id = l.user_id
    WHERE bh.status = 'pending' ORDER BY bh.created_at DESC LIMIT 5")->fetchAll();

jsonResponse([
    'cards' => overviewCards(),
    'charts' => [
        'labels' => $range['labels'],
        'registrations' => monthlySeries('users', 'created_at', 'role', ['tenants' => ['tenant'], 'landlords' => ['landlord']], $range),
        'boarding_houses' => statusCounts('boarding_houses', ['approved', 'pending', 'rejected', 'inactive']),
        'rooms' => statusCounts('rooms', ['available', 'occupied', 'maintenance']),
        'bookings' => statusCounts('bookings', ['pending', 'approved', 'cancelled', 'completed']),
    ],
    'recent_activities' => recentActivities(8),
    'pending_listings' => array_map(static fn ($h) => [
        'id' => (int) $h['id'],
        'name' => $h['name'],
        'city' => $h['city'],
        'created_at' => $h['created_at'],
        'owner_name' => fullName($h, 'owner_'),
    ], $pendingHouses),
]);
