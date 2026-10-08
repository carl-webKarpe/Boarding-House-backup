<?php

declare(strict_types=1);

/**
 * Bootstrap for every /api/landlord/* endpoint.
 *
 *  - 401 when not logged in, 403 when the account is not a landlord.
 *  - Role and status are re-read from the database on every request.
 *  - Every state-changing request must carry the CSRF token.
 *  - ownHouse() / ownRoom() are the ONLY way to load a listing: they always
 *    filter by the logged-in landlord, so a landlord can never read, edit or
 *    delete another landlord's boarding houses, rooms, reservations or messages.
 *    Records of other landlords answer "not found" (no information leak).
 */

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../_queries.php';
require_once __DIR__ . '/../../security/upload_security.php';
require_once __DIR__ . '/../../security/settings.php';

apiBootstrap();

if (!isLoggedIn()) {
    jsonError('Please log in to continue.', 401);
}

$stmt = getDb()->prepare('SELECT u.id, u.username, u.email, u.role, u.first_name, u.last_name, u.contact_number, u.status, u.avatar_path,
        l.id AS landlord_id, l.business_name, l.verification_status
    FROM users u LEFT JOIN landlords l ON l.user_id = u.id WHERE u.id = :id LIMIT 1');
$stmt->execute([':id' => currentUserId()]);
$LANDLORD = $stmt->fetch();

if (!$LANDLORD || $LANDLORD['status'] === 'disabled') {
    logoutUser();
    jsonError('Your session has ended. Please log in again.', 401);
}
if ($LANDLORD['role'] !== ROLE_LANDLORD || !$LANDLORD['landlord_id']) {
    jsonError('This area is only for landlord accounts.', 403);
}
$LANDLORD['id'] = (int) $LANDLORD['id'];
$LANDLORD['landlord_id'] = (int) $LANDLORD['landlord_id'];
$LANDLORD['full_name'] = fullName($LANDLORD);

if (requestMethod() !== 'GET') {
    requireCsrf();
}

const ROOM_TYPE_LABELS = [
    'solo' => 'Single Room',
    'shared' => 'Shared Room',
    'dormitory' => 'Bedspace',
    'studio' => 'Studio',
];

const AVAILABILITY_LABELS = [
    'available' => 'Available',
    'fully_occupied' => 'Fully Occupied',
    'temporarily_unavailable' => 'Temporarily Unavailable',
];

function landlordId(): int {
    global $LANDLORD;
    return $LANDLORD['landlord_id'];
}

function landlordUserId(): int {
    global $LANDLORD;
    return $LANDLORD['id'];
}

function landlordName(): string {
    global $LANDLORD;
    return $LANDLORD['full_name'];
}

function landlordLog(string $action, string $description, ?string $entityType = null, ?int $entityId = null): void {
    auditLog($action, $description, landlordUserId(), $entityType, $entityId);
}

/**
 * Loads one of MY boarding houses, or answers 404.
 */
function ownHouse(int $id): array {
    $stmt = getDb()->prepare('SELECT * FROM boarding_houses WHERE id = :id AND landlord_id = :landlord');
    $stmt->execute([':id' => $id, ':landlord' => landlordId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Boarding house');
    }

    return $row;
}

/**
 * Loads one of MY rooms (through its boarding house), or answers 404.
 */
function ownRoom(int $id): array {
    $stmt = getDb()->prepare('SELECT r.*, bh.name AS house_name, bh.landlord_id FROM rooms r
        JOIN boarding_houses bh ON bh.id = r.boarding_house_id
        WHERE r.id = :id AND bh.landlord_id = :landlord');
    $stmt->execute([':id' => $id, ':landlord' => landlordId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Room');
    }

    return $row;
}

/**
 * What the landlord and tenants see as the listing status.
 * Approval (admin) comes first; after approval the landlord's own setting applies.
 */
function listingStatus(string $approval, string $availability): array {
    return match ($approval) {
        'pending' => ['key' => 'pending_approval', 'label' => 'Pending Approval'],
        'rejected' => ['key' => 'rejected', 'label' => 'Not Approved'],
        'inactive' => ['key' => 'inactive', 'label' => 'Deactivated by Admin'],
        default => ['key' => $availability, 'label' => AVAILABILITY_LABELS[$availability] ?? 'Available'],
    };
}

function roomTypeFromInput(string $value): ?string {
    return match ($value) {
        'single', 'solo' => 'solo',
        'shared' => 'shared',
        'dormitory', 'bedspace' => 'dormitory',
        'studio' => 'studio',
        default => null,
    };
}
