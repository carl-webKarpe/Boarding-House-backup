<?php

declare(strict_types=1);

/**
 * Lookup lists for form dropdowns.
 *
 *   GET options.php?type=landlords|boarding_houses|rooms|tenants|amenities[&q=]
 */

require_once __DIR__ . '/_admin.php';

requireMethod('GET');

$pdo = getDb();
$search = queryString('q');
$like = likeValue($search);

switch (queryString('type')) {
    case 'landlords':
        $rows = $pdo->query("SELECT l.id, u.first_name, u.last_name, u.username, l.business_name, u.status FROM landlords l JOIN users u ON u.id = l.user_id ORDER BY u.first_name, u.last_name")->fetchAll();
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => fullName($r) . ($r['business_name'] ? ' — ' . $r['business_name'] : ''), 'status' => $r['status']], $rows));

    case 'boarding_houses':
        $rows = $pdo->query('SELECT id, name, city, status FROM boarding_houses ORDER BY name')->fetchAll();
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => $r['name'] . ' (' . $r['city'] . ')', 'status' => $r['status']], $rows));

    case 'rooms':
        $stmt = $pdo->prepare("SELECT r.id, r.room_number, r.capacity, r.occupants, r.status, r.price, bh.name FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
            WHERE bh.status = 'approved' AND r.status = 'available' AND (bh.name LIKE :q1 OR r.room_number LIKE :q2) ORDER BY bh.name, r.room_number LIMIT 200");
        $stmt->execute([':q1' => $like, ':q2' => $like]);
        jsonResponse(array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'label' => sprintf('%s — Room %s (%d/%d, ₱%s)', $r['name'], $r['room_number'], $r['occupants'], $r['capacity'], number_format((float) $r['price'])),
        ], $stmt->fetchAll()));

    case 'tenants':
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, username, email FROM users WHERE role = 'tenant' AND status <> 'disabled'
            AND (CONCAT(first_name, ' ', last_name) LIKE :q1 OR email LIKE :q2) ORDER BY first_name, last_name LIMIT 200");
        $stmt->execute([':q1' => $like, ':q2' => $like]);
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => fullName($r) . ' — ' . $r['email']], $stmt->fetchAll()));

    case 'amenities':
        $rows = $pdo->query('SELECT id, name FROM amenities ORDER BY name')->fetchAll();
        jsonResponse(array_map(fn ($r) => ['id' => (int) $r['id'], 'label' => $r['name']], $rows));

    default:
        throw new ApiException('Unknown option type.', 400);
}
