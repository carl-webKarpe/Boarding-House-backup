<?php

declare(strict_types=1);

/**
 * My Tenants: students staying in my boarding houses (approved reservations)
 * and former boarders (completed), with their monthly payment status.
 *
 *   GET tenants.php            ?status=current|past|all &q= &house_id=
 *   GET tenants.php?id=5       one boarder with month-by-month payment history
 *
 * Moving a tenant out is done with reservations.php (status "completed").
 */

require_once __DIR__ . '/_landlord.php';
require_once __DIR__ . '/_rent.php';

requireMethod('GET');
$pdo = getDb();

if (isset($_GET['id'])) {
    $row = ownBooking(requireId());
    if (!in_array($row['status'], ['approved', 'completed'], true)) {
        notFound('Tenant');
    }
    $payments = paymentsFor([(int) $row['id']]);
    jsonResponse(formatBoarder($row, $payments[(int) $row['id']] ?? []));
}

$where = ['bh.landlord_id = :landlord'];
$params = [':landlord' => landlordId()];
$status = queryString('status', 'current');
$where[] = match ($status) {
    'past' => "b.status = 'completed'",
    'all' => "b.status IN ('approved', 'completed')",
    default => "b.status = 'approved'",
};
$houseId = queryInt('house_id', 0, 0);
if ($houseId > 0) {
    $where[] = 'bh.id = :house';
    $params[':house'] = $houseId;
}
$search = queryString('q');
if ($search !== '') {
    $where[] = "(b.contact_name LIKE :q1 OR CONCAT(t.first_name, ' ', t.last_name) LIKE :q2 OR r.room_number LIKE :q3 OR bh.name LIKE :q4)";
    $params += [':q1' => likeValue($search), ':q2' => likeValue($search), ':q3' => likeValue($search), ':q4' => likeValue($search)];
}

$stmt = $pdo->prepare(BOARDER_SELECT . ' ' . BOARDER_FROM . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY bh.name, r.room_number, t.first_name LIMIT 500');
$stmt->execute($params);
$rows = $stmt->fetchAll();
$payments = paymentsFor(array_column($rows, 'id'));
$boarders = array_map(static function ($row) use ($payments) {
    $b = formatBoarder($row, $payments[(int) $row['id']] ?? []);
    unset($b['history']);
    return $b;
}, $rows);

$counts = $pdo->prepare("SELECT COALESCE(SUM(b.status = 'approved'), 0) AS current, COALESCE(SUM(b.status = 'completed'), 0) AS past
    FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE bh.landlord_id = :landlord");
$counts->execute([':landlord' => landlordId()]);
$c = array_map('intval', $counts->fetch());
$c['all'] = $c['current'] + $c['past'];

jsonResponse($boarders, '', 200, ['counts' => $c]);
