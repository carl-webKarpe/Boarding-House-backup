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
        bh.latitude, bh.longitude, bh.nearby_school, bh.distance_note, bh.contact_name, bh.contact_number, bh.house_rules, bh.availability_status,
        u.first_name, u.last_name, u.contact_number AS owner_contact,
        (SELECT i.file_path FROM boarding_house_images i WHERE i.boarding_house_id = bh.id ORDER BY i.is_cover DESC, i.id LIMIT 1) AS cover_image
    FROM boarding_houses bh
    JOIN landlords l ON l.id = bh.landlord_id
    JOIN users u ON u.id = l.user_id
    WHERE bh.status = 'approved' AND bh.availability_status <> 'temporarily_unavailable' AND u.status <> 'disabled'
    ORDER BY bh.approved_at DESC, bh.id DESC")->fetchAll();

$rooms = $pdo->query("SELECT r.id, r.boarding_house_id, r.room_number, r.room_type, r.price, r.capacity, r.occupants, r.status,
        (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR '||') FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = r.id) AS amenities
    FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE bh.status = 'approved'
    ORDER BY r.price")->fetchAll();

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

    $available = $house['availability_status'] === 'fully_occupied'
        ? []
        : array_values(array_filter($houseRooms, fn ($r) => $r['status'] === 'available'));
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
        'location' => implode(', ', array_filter([$house['barangay'], $house['city']])),
        'address' => implode(', ', array_filter([$house['address'], $house['barangay'], $house['city'], $house['province']])),
        'description' => $house['description'],
        'rules' => $house['house_rules'],
        'nearby_school' => $house['nearby_school'],
        'distance' => $house['distance_note'],
        'price' => min($prices),
        'max_price' => max($prices),
        'roomType' => ROOM_TYPE_LABELS[$types[0]] ?? 'Room',
        'roomTypes' => array_map(fn ($t) => ROOM_TYPE_LABELS[$t] ?? $t, $types),
        'availability' => $available ? 'Available' : 'Fully Booked',
        'rooms' => count($available),
        'totalRooms' => count($houseRooms),
        'openBeds' => $openBeds,
        'amenities' => array_slice(array_keys($amenities), 0, 6),
        'coordinates' => $house['latitude'] !== null && $house['longitude'] !== null
            ? [(float) $house['latitude'], (float) $house['longitude']]
            : null,
        'img' => $house['cover_image'] ? publicAssetUrl((string) $house['cover_image']) : null,
        'landlord' => $house['contact_name'] ?: trim($house['first_name'] . ' ' . $house['last_name']),
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
    (SELECT COUNT(*) FROM boarding_houses WHERE status = 'approved' AND availability_status <> 'temporarily_unavailable') AS houses,
    (SELECT COUNT(*) FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id
        WHERE bh.status = 'approved' AND bh.availability_status = 'available' AND r.status = 'available') AS available_rooms,
    (SELECT COUNT(*) FROM users WHERE role = 'tenant' AND status = 'active') AS tenants")->fetch();

jsonResponse($listings, '', 200, ['stats' => array_map('intval', $stats)]);
