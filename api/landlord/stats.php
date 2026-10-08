<?php

declare(strict_types=1);

/**
 * Landlord dashboard overview (only the logged-in landlord's data).
 *
 *   GET stats.php
 */

require_once __DIR__ . '/_landlord.php';

requireMethod('GET');

$pdo = getDb();
$me = [':landlord' => landlordId()];

$houses = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'approved'), 0) AS published, COALESCE(SUM(status = 'pending'), 0) AS pending_approval
    FROM boarding_houses WHERE landlord_id = :landlord");
$houses->execute($me);
$h = array_map('intval', $houses->fetch());

// Available = open for reservations with a free place; Reserved = has a pending or approved reservation.
$rooms = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(r.status = 'available'), 0) AS available,
        COALESCE(SUM(EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = r.id AND b.status IN ('pending', 'approved'))), 0) AS reserved,
        COALESCE(SUM(GREATEST(r.capacity - r.occupants, 0) * (r.status <> 'maintenance')), 0) AS open_slots
    FROM rooms r JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.landlord_id = :landlord");
$rooms->execute($me);
$r = array_map('intval', $rooms->fetch());

$pending = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE bh.landlord_id = :landlord AND b.status = 'pending'");
$pending->execute($me);

$unread = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE landlord_user_id = :me AND status = 'new'");
$unread->execute([':me' => landlordUserId()]);

$recentHouses = $pdo->prepare("SELECT bh.id, bh.name, bh.barangay, bh.city, bh.status, bh.availability_status, bh.created_at,
        (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
        (SELECT MIN(r.price) FROM rooms r WHERE r.boarding_house_id = bh.id) AS min_price,
        (SELECT i.file_path FROM boarding_house_images i WHERE i.boarding_house_id = bh.id ORDER BY i.is_cover DESC, i.id LIMIT 1) AS cover_image
    FROM boarding_houses bh WHERE bh.landlord_id = :landlord ORDER BY bh.created_at DESC, bh.id DESC LIMIT 5");
$recentHouses->execute($me);

$recentReservations = $pdo->prepare("SELECT b.id, b.status, b.booking_date, b.move_in_date, b.occupants_count, b.contact_name,
        t.first_name, t.last_name, t.username, r.room_number, bh.name AS house_name
    FROM bookings b JOIN users t ON t.id = b.tenant_id JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id
    WHERE bh.landlord_id = :landlord ORDER BY b.booking_date DESC, b.id DESC LIMIT 5");
$recentReservations->execute($me);

jsonResponse([
    'cards' => [
        'boarding_houses' => $h['total'],
        'published' => $h['published'],
        'pending_approval' => $h['pending_approval'],
        'rooms' => $r['total'],
        'available_rooms' => $r['available'],
        'reserved_rooms' => $r['reserved'],
        'open_slots' => $r['open_slots'],
        'pending_reservations' => (int) $pending->fetchColumn(),
        'unread_messages' => (int) $unread->fetchColumn(),
    ],
    'recent_listings' => array_map(static function ($row) {
        $row = castRow($row, ['id', 'room_count'], ['min_price']);
        $row['display_status'] = listingStatus($row['status'], $row['availability_status']);
        return $row;
    }, $recentHouses->fetchAll()),
    'recent_reservations' => array_map(static function ($row) {
        $row = castRow($row, ['id', 'occupants_count']);
        $row['code'] = sprintf('BK-%05d', $row['id']);
        $row['tenant_name'] = $row['contact_name'] ?: fullName($row);
        unset($row['first_name'], $row['last_name'], $row['username']);
        return $row;
    }, $recentReservations->fetchAll()),
]);
