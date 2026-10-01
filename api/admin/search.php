<?php

declare(strict_types=1);

/**
 * Global search used by the top navigation bar.
 *
 *   GET search.php?q=santos
 */

require_once __DIR__ . '/_admin.php';

requireMethod('GET');

$search = queryString('q');
if (mb_strlen($search) < 2) {
    jsonResponse(['users' => [], 'boarding_houses' => [], 'rooms' => [], 'bookings' => []]);
}

$pdo = getDb();
$like = likeValue($search);

$users = $pdo->prepare("SELECT id, first_name, last_name, username, email, role FROM users
    WHERE CONCAT(first_name, ' ', last_name) LIKE :q1 OR email LIKE :q2 OR username LIKE :q3 ORDER BY created_at DESC LIMIT 5");
$users->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);

$houses = $pdo->prepare('SELECT id, name, city, status FROM boarding_houses WHERE name LIKE :q1 OR city LIKE :q2 OR barangay LIKE :q3 ORDER BY name LIMIT 5');
$houses->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);

$rooms = $pdo->prepare('SELECT r.id, r.room_number, r.status, bh.name AS boarding_house_name FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE CONCAT(bh.name, \' \', r.room_number) LIKE :q1 ORDER BY bh.name, r.room_number LIMIT 5');
$rooms->execute([':q1' => $like]);

$bookingId = (int) preg_replace('/\D/', '', $search);
$bookings = [];
if ($bookingId > 0 && preg_match('/^(bk-?)?\d+$/i', $search)) {
    $stmt = $pdo->prepare('SELECT b.id, b.status, t.first_name, t.last_name, t.username FROM bookings b JOIN users t ON t.id = b.tenant_id WHERE b.id = :id');
    $stmt->execute([':id' => $bookingId]);
    $bookings = array_map(fn ($b) => ['id' => (int) $b['id'], 'code' => sprintf('BK-%05d', $b['id']), 'status' => $b['status'], 'tenant_name' => fullName($b)], $stmt->fetchAll());
}

jsonResponse([
    'users' => array_map(fn ($u) => ['id' => (int) $u['id'], 'name' => fullName($u), 'email' => $u['email'], 'role' => $u['role'], 'role_label' => roleLabel($u['role'])], $users->fetchAll()),
    'boarding_houses' => array_map(fn ($h) => castRow($h, ['id']), $houses->fetchAll()),
    'rooms' => array_map(fn ($r) => castRow($r, ['id']), $rooms->fetchAll()),
    'bookings' => $bookings,
]);
