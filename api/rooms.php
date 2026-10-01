<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../php/room-data.php';

applySecurityHeaders();
startSecureSession();

$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2;
$offset = max(0, $offset);
$limit = max(1, min(5, $limit));

$roomsSlice = array_slice($rooms, $offset, $limit);
$hasMore = count($rooms) > ($offset + $limit);

header('Content-Type: application/json');
echo json_encode([
    'rooms' => array_map(function ($room) {
        return [
            'id' => (int) $room['id'],
            'title' => $room['title'],
            'rent' => (int) $room['rent'],
            'type' => $room['type'],
            'barangay' => $room['barangay'],
            'city' => $room['city'],
            'rating' => (float) $room['rating'],
            'image' => $room['image'],
            'badge' => $room['badge'],
            'distance' => $room['distance'],
        ];
    }, $roomsSlice),
    'hasMore' => $hasMore,
]);
