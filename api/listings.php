<?php

declare(strict_types=1);

/**
 * Public listings for the landing page (html/index.html).
 *
 *   GET api/listings.php
 *
 * Only APPROVED boarding houses are returned, so a listing appears on the
 * landing page as soon as an administrator approves it in the Admin
 * Dashboard, and disappears when it is rejected or deactivated.
 * No login required; no private data (IDs, emails of tenants) is exposed.
 */

require_once __DIR__ . '/_common.php';

apiBootstrap();
requireMethod('GET');

const ROOM_TYPE_LABELS = [
    'solo' => 'Single Room',
    'shared' => 'Shared Room',
    'dormitory' => 'Bedspace',
    'studio' => 'Studio',
];

$pdo = getDb();

$houses = $pdo->query("SELECT bh.id, bh.name, bh.description, bh.address, bh.barangay, bh.city, bh.province,
        bh.latitude, bh.longitude, bh.nearby_school, bh.contact_number, bh.house_rules,
        u.first_name, u.last_name, u.contact_number AS owner_contact
    FROM boarding_houses bh
    JOIN landlords l ON l.id = bh.landlord_id
    JOIN users u ON u.id = l.user_id
    WHERE bh.status = 'approved' AND u.status <> 'disabled'
    ORDER BY bh.approved_at DESC, bh.id DESC")->fetchAll();

$rooms = $pdo->query("SELECT r.id, r.boarding_house_id, r.room_number, r.room_type, r.price, r.capacity, r.occupants, r.status,
        (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR '||') FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = r.id) AS amenities
    FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE bh.status = 'approved'
    ORDER BY r.price")->fetchAll();

// All photos per house: cover first, then in upload order (for the slideshow).
$imagesByHouse = [];
foreach ($pdo->query("SELECT i.boarding_house_id, i.file_path FROM boarding_house_images i
    JOIN boarding_houses bh ON bh.id = i.boarding_house_id WHERE bh.status = 'approved'
    ORDER BY i.boarding_house_id, i.is_cover DESC, i.id") as $image) {
    $imagesByHouse[(int) $image['boarding_house_id']][] = publicImageUrl((string) $image['file_path']);
}

/**
 * Turns a stored path (e.g. "uploads/listings/x.jpg" or "Image/ROOM 1.jfif")
 * into a URL usable from html/index.html. Full http(s) URLs are kept.
 */
function publicImageUrl(string $path): string {
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return '../' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
}

$roomsByHouse = [];
foreach ($rooms as $room) {
    $roomsByHouse[(int) $room['boarding_house_id']][] = $room;
}

$listings = [];
foreach ($houses as $house) {
    $houseRooms = $roomsByHouse[(int) $house['id']] ?? [];
    if (!$houseRooms) {
        continue; // Nothing to rent yet.
    }

    $available = array_values(array_filter($houseRooms, fn ($r) => $r['status'] === 'available'));
    $allMaintenance = !array_filter($houseRooms, fn ($r) => $r['status'] !== 'maintenance');
    $priced = $available ?: array_values(array_filter($houseRooms, fn ($r) => $r['status'] !== 'maintenance'));
    $prices = array_map(fn ($r) => (float) $r['price'], $priced ?: $houseRooms);

    // Most common room type among the rooms that can be rented.
    $typeCounts = array_count_values(array_map(fn ($r) => $r['room_type'], $available ?: $houseRooms));
    arsort($typeCounts);
    $types = array_keys($typeCounts);

    $amenities = [];
    foreach ($houseRooms as $room) {
        foreach (explode('||', (string) $room['amenities']) as $name) {
            if ($name !== '') {
                $amenities[$name] = ($amenities[$name] ?? 0) + 1;
            }
        }
    }
    arsort($amenities);

    $openBeds = array_sum(array_map(fn ($r) => max(0, (int) $r['capacity'] - (int) $r['occupants']), $available));

    $listings[] = [
        'id' => (int) $house['id'],
        'name' => $house['name'],
        'location' => implode(', ', array_filter([$house['barangay'] ?: $house['address'], $house['city']])),
        'address' => implode(', ', array_unique(array_filter([$house['address'], $house['barangay'], $house['city'], $house['province']]))),
        'description' => $house['description'],
        'rules' => $house['house_rules'],
        'nearby_school' => $house['nearby_school'],
        'price' => min($prices),
        'max_price' => max($prices),
        'roomType' => ROOM_TYPE_LABELS[$types[0]] ?? 'Room',
        'roomTypes' => array_map(fn ($t) => ROOM_TYPE_LABELS[$t] ?? $t, $types),
        // "Coming Soon" when every room is still being prepared (maintenance).
        'availability' => $available ? 'Available' : ($allMaintenance ? 'Coming Soon' : 'Fully Booked'),
        'rooms' => count($available),
        'totalRooms' => count($houseRooms),
        'openBeds' => $openBeds,
        'totalBeds' => array_sum(array_map(fn ($r) => (int) $r['capacity'], array_filter($houseRooms, fn ($r) => $r['status'] !== 'maintenance'))),
        'amenities' => array_slice(array_keys($amenities), 0, 6),
        'coordinates' => $house['latitude'] !== null && $house['longitude'] !== null
            ? [(float) $house['latitude'], (float) $house['longitude']]
            : null,
        'img' => $imagesByHouse[(int) $house['id']][0] ?? null,
        'images' => $imagesByHouse[(int) $house['id']] ?? [],
        'landlord' => trim($house['first_name'] . ' ' . $house['last_name']),
        'contact' => $house['contact_number'] ?: $house['owner_contact'],
        'roomList' => array_map(fn ($r) => [
            'room_number' => $r['room_number'],
            'type' => ROOM_TYPE_LABELS[$r['room_type']] ?? $r['room_type'],
            'price' => (float) $r['price'],
            'open' => max(0, (int) $r['capacity'] - (int) $r['occupants']),
            'status' => $r['status'],
        ], $houseRooms),
    ];
}

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM boarding_houses WHERE status = 'approved') AS houses,
    (SELECT COUNT(*) FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.status = 'approved' AND r.status = 'available') AS available_rooms,
    (SELECT COUNT(*) FROM users WHERE role = 'tenant' AND status = 'active') AS tenants")->fetch();

jsonResponse($listings, '', 200, ['stats' => array_map('intval', $stats)]);
