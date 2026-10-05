<?php

declare(strict_types=1);

/**
 * Reports & analytics.
 *
 *   GET reports.php?months=6|12
 */

require_once __DIR__ . '/_admin.php';
require_once __DIR__ . '/_reports.php';

requireMethod('GET');

$months = queryInt('months', 6, 3, 24);
$range = monthRange($months);
$pdo = getDb();

$byCity = $pdo->query("SELECT bh.city, COUNT(DISTINCT bh.id) AS houses, COUNT(r.id) AS rooms,
    COALESCE(SUM(r.status = 'available'), 0) AS available_rooms, COALESCE(ROUND(AVG(r.price)), 0) AS avg_price
    FROM boarding_houses bh LEFT JOIN rooms r ON r.boarding_house_id = bh.id
    WHERE bh.status = 'approved' GROUP BY bh.city ORDER BY houses DESC, bh.city")->fetchAll();

$byType = $pdo->query("SELECT room_type, COUNT(*) AS rooms, ROUND(AVG(price)) AS avg_price, MIN(price) AS min_price, MAX(price) AS max_price,
    SUM(capacity) AS beds, SUM(occupants) AS occupants FROM rooms GROUP BY room_type ORDER BY room_type")->fetchAll();

$topHouses = $pdo->query("SELECT bh.id, bh.name, bh.city, SUM(r.capacity) AS beds, SUM(r.occupants) AS occupants,
    ROUND(SUM(r.occupants) / NULLIF(SUM(r.capacity), 0) * 100, 1) AS occupancy_rate
    FROM boarding_houses bh JOIN rooms r ON r.boarding_house_id = bh.id
    WHERE bh.status = 'approved' GROUP BY bh.id, bh.name, bh.city ORDER BY occupancy_rate DESC, beds DESC LIMIT 10")->fetchAll();

jsonResponse([
    'months' => $months,
    'summary' => overviewCards(),
    'labels' => $range['labels'],
    'registrations' => monthlySeries('users', 'created_at', 'role', ['tenants' => ['tenant'], 'landlords' => ['landlord']], $range),
    'listings' => monthlySeries('boarding_houses', 'created_at', 'status', ['submitted' => ['pending', 'approved', 'rejected', 'inactive'], 'approved' => ['approved']], $range),
    'booking_activity' => monthlySeries('bookings', 'booking_date', 'status', [
        'pending' => ['pending'], 'approved' => ['approved'], 'rejected' => ['rejected'], 'cancelled' => ['cancelled'], 'completed' => ['completed'],
    ], $range),
    'rooms' => statusCounts('rooms', ['available', 'occupied', 'maintenance']),
    'boarding_houses' => statusCounts('boarding_houses', ['approved', 'pending', 'rejected', 'inactive']),
    'bookings' => statusCounts('bookings', ['pending', 'approved', 'rejected', 'cancelled', 'completed']),
    'by_city' => array_map(fn ($r) => castRow($r, ['houses', 'rooms', 'available_rooms'], ['avg_price']), $byCity),
    'by_room_type' => array_map(fn ($r) => castRow($r, ['rooms', 'beds', 'occupants'], ['avg_price', 'min_price', 'max_price']), $byType),
    'top_occupancy' => array_map(fn ($r) => castRow($r, ['id', 'beds', 'occupants'], ['occupancy_rate']), $topHouses),
]);
