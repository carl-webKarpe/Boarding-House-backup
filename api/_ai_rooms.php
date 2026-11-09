<?php

declare(strict_types=1);

/**
 * Room facts for the students' AI helpers (api/ai-compare.php, api/ai-cost.php).
 * Only rooms students can see in Browse Rooms are loaded (approved listings of
 * active landlords, not under maintenance). Everything comes from the database.
 */

require_once __DIR__ . '/../security/ai.php';

const AI_ROOM_TYPES = ['solo' => 'Single Room', 'shared' => 'Shared Room', 'dormitory' => 'Bedspace', 'studio' => 'Studio', 'apartment' => 'Apartment Unit'];
const AI_SCHOOL_POINT = [9.760829, 126.047129]; // SIIT, Dapa

/** @param int[] $ids  @return array<int, array> visible rooms keyed by id (missing ids are left out) */
function aiLoadRooms(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $in = implode(',', $ids);
    $rows = getDb()->query("SELECT r.id, r.room_number, r.room_type, r.price, r.deposit, r.capacity, r.occupants, r.size_sqm, r.description,
            bh.name AS house, bh.property_type, bh.barangay, bh.city, bh.latitude, bh.longitude, bh.availability_status,
            bh.house_rules, bh.description AS house_description, bh.distance_note,
            (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ') FROM room_amenities ra JOIN amenities a ON a.id = ra.amenity_id WHERE ra.room_id = r.id) AS amenities
        FROM rooms r
        JOIN boarding_houses bh ON bh.id = r.boarding_house_id
        JOIN landlords l ON l.id = bh.landlord_id
        JOIN users u ON u.id = l.user_id
        WHERE r.id IN ({$in}) AND bh.status = 'approved' AND bh.availability_status <> 'temporarily_unavailable'
          AND u.status <> 'disabled' AND r.status <> 'maintenance'")->fetchAll();

    $rooms = [];
    foreach ($rows as $row) {
        $whole = isWholeUnit((string) $row['room_type']);
        $free = $row['availability_status'] === 'fully_occupied' ? 0 : max(0, (int) $row['capacity'] - (int) $row['occupants']);
        $meters = null;
        if ($row['latitude'] !== null && $row['longitude'] !== null) {
            [$sLat, $sLng] = AI_SCHOOL_POINT;
            $rad = static fn (float $d): float => $d * M_PI / 180;
            $h = sin($rad((float) $row['latitude'] - $sLat) / 2) ** 2 + cos($rad($sLat)) * cos($rad((float) $row['latitude'])) * sin($rad((float) $row['longitude'] - $sLng) / 2) ** 2;
            $meters = (int) round(2 * 6371000 * asin(sqrt($h)));
        }
        $rooms[(int) $row['id']] = $row + [
            'type_label' => AI_ROOM_TYPES[$row['room_type']] ?? ucfirst((string) $row['room_type']),
            'whole_unit' => $whole,
            'free' => $free,
            'distance' => $row['distance_note'] ?: ($meters === null ? null : ($meters < 1000 ? "{$meters} m from SIIT" : round($meters / 1000, 1) . ' km from SIIT')),
        ];
    }
    return $rooms;
}

/** One line of facts about a room, for the AI. */
function aiRoomLine(array $r, bool $long = false): string {
    $cut = static fn (?string $t, int $n): string => mb_strimwidth(trim((string) $t), 0, $n, '…');
    return implode(' | ', array_filter([
        "Room #{$r['id']}",
        $r['house'] . ' - ' . $r['room_number'],
        propertyTypeLabel($r['property_type']),
        $r['type_label'],
        'PHP ' . number_format((float) $r['price']) . ($r['whole_unit'] ? ' a month for the whole ' . ($r['room_type'] === 'apartment' ? 'unit' : 'room') : ' per person a month'),
        (float) $r['deposit'] > 0 ? 'deposit PHP ' . number_format((float) $r['deposit']) : 'no deposit',
        $r['room_type'] === 'apartment' ? "for up to {$r['capacity']} people, " . ($r['free'] > 0 ? 'vacant' : 'occupied') : "{$r['free']} of {$r['capacity']} slots free",
        trim(($r['barangay'] ?: '') . ', ' . $r['city'], ', '),
        $r['distance'],
        $r['size_sqm'] ? $r['size_sqm'] . ' m²' : null,
        'amenities: ' . ($r['amenities'] ?: 'not listed'),
        $r['description'] ? 'room description: ' . $cut($r['description'], $long ? 800 : 300) : null,
        'house rules: ' . ($r['house_rules'] ? $cut($r['house_rules'], $long ? 1200 : 300) : 'not listed'),
    ]));
}

/** The small card data the page shows for a room (from the database, not from the AI). */
function aiRoomCard(array $r): array {
    return [
        'id' => (int) $r['id'],
        'house' => $r['house'],
        'room_number' => $r['room_number'],
        'type' => $r['type_label'],
        'property_type' => $r['property_type'],
        'price' => (float) $r['price'],
        'per_person' => !$r['whole_unit'],
        'deposit' => (float) $r['deposit'],
        'distance' => $r['distance'],
    ];
}

/** Who is asking (logged-in user or visitor session), for the hourly limits. */
function aiVisitorKey(): string {
    return isLoggedIn() ? 'user:' . currentUserId() : 'guest:' . session_id();
}
