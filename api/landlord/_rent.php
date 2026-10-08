<?php

declare(strict_types=1);

/**
 * Monthly rent helpers for My Tenants and Payments (landlord side).
 *
 * A boarder is an APPROVED reservation in one of my boarding houses
 * (a COMPLETED one is a former boarder). Rent is owed for every month from
 * the move-in month until now (or until the reservation was completed).
 * Single room / studio: the room price. Shared room / bedspace: price x persons.
 */

const PAYMENT_METHODS = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank' => 'Bank transfer', 'other' => 'Other'];

const BOARDER_SELECT = "SELECT b.id, b.status, b.booking_date, b.move_in_date, b.occupants_count, b.updated_at,
    b.contact_name, b.contact_number, b.contact_email, b.tenant_id,
    t.first_name, t.last_name, t.username, t.email AS tenant_email, t.contact_number AS tenant_phone, t.avatar_path,
    r.id AS room_id, r.room_number, r.room_type, r.price, bh.id AS house_id, bh.name AS house_name";

const BOARDER_FROM = 'FROM bookings b JOIN users t ON t.id = b.tenant_id JOIN rooms r ON r.id = b.room_id
    JOIN boarding_houses bh ON bh.id = r.boarding_house_id';

function monthlyRent(string $roomType, float $price, int $occupants): float {
    return in_array($roomType, ['solo', 'studio'], true) ? $price : $price * max(1, $occupants);
}

/** "2026-10" -> "2026-10-01"; anything else -> this month. */
function monthStart(string $value = ''): string {
    return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value) ? $value . '-01' : date('Y-m-01');
}

function monthLabel(string $monthStart): string {
    return date('F Y', strtotime($monthStart));
}

/** First month rent is owed: the move-in month (or the month the reservation was made). */
function boarderStartMonth(array $row): string {
    return date('Y-m-01', strtotime((string) ($row['move_in_date'] ?: $row['booking_date'])));
}

/** Last month rent is owed: this month for current boarders, the move-out month for former ones. */
function boarderEndMonth(array $row): string {
    if ($row['status'] === 'completed' && $row['updated_at']) {
        return min(date('Y-m-01', strtotime((string) $row['updated_at'])), date('Y-m-01'));
    }
    return date('Y-m-01');
}

/** One of MY reservations (any status), or 404. */
function ownBooking(int $id): array {
    $stmt = getDb()->prepare(BOARDER_SELECT . ' ' . BOARDER_FROM . ' WHERE b.id = :id AND bh.landlord_id = :landlord');
    $stmt->execute([':id' => $id, ':landlord' => landlordId()]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('Tenant');
    }
    return $row;
}

/** Payment rows of many reservations, keyed [booking_id][Y-m-01]. */
function paymentsFor(array $bookingIds): array {
    $bookingIds = array_values(array_filter(array_map('intval', $bookingIds)));
    if (!$bookingIds) {
        return [];
    }
    $in = implode(',', $bookingIds);
    $out = [];
    foreach (getDb()->query("SELECT * FROM rent_payments WHERE booking_id IN ({$in})") as $p) {
        $out[(int) $p['booking_id']][$p['period_month']] = $p;
    }
    return $out;
}

/** paid / partial / unpaid / overdue (an unpaid past month) / upcoming (before move-in). */
function paymentState(?array $payment, string $month, string $startMonth): string {
    if ($month < $startMonth) {
        return 'upcoming';
    }
    if ($payment && $payment['status'] === 'paid') {
        return 'paid';
    }
    if ($payment && $payment['status'] === 'partial') {
        return $month < date('Y-m-01') ? 'overdue' : 'partial';
    }
    return $month < date('Y-m-01') ? 'overdue' : 'unpaid';
}

function formatPayment(?array $p): ?array {
    if (!$p) {
        return null;
    }
    return [
        'id' => (int) $p['id'],
        'amount_due' => (float) $p['amount_due'],
        'amount_paid' => (float) $p['amount_paid'],
        'status' => $p['status'],
        'paid_at' => $p['paid_at'],
        'method' => $p['method'],
        'method_label' => PAYMENT_METHODS[$p['method']] ?? null,
        'reference' => $p['reference'],
        'note' => $p['note'],
        'updated_at' => $p['updated_at'] ?? $p['created_at'],
    ];
}

/** Month-by-month rent history of one boarder (newest first, at most 24 months). */
function paymentHistory(array $row, array $payments): array {
    $start = boarderStartMonth($row);
    $end = boarderEndMonth($row);
    $rent = monthlyRent($row['room_type'], (float) $row['price'], (int) $row['occupants_count']);
    $history = [];
    for ($m = $end, $i = 0; $m >= $start && $i < 24; $m = date('Y-m-01', strtotime($m . ' -1 month')), $i++) {
        $p = $payments[$m] ?? null;
        $history[] = [
            'month' => substr($m, 0, 7),
            'label' => monthLabel($m),
            'amount_due' => $p ? (float) $p['amount_due'] : $rent,
            'amount_paid' => $p ? (float) $p['amount_paid'] : 0.0,
            'state' => paymentState($p, $m, $start),
            'payment' => formatPayment($p),
        ];
    }
    return $history;
}

/** The list/detail shape of a boarder. */
function formatBoarder(array $row, array $payments): array {
    $start = boarderStartMonth($row);
    $thisMonth = date('Y-m-01');
    $history = paymentHistory($row, $payments);
    $unpaid = array_values(array_filter($history, fn ($h) => in_array($h['state'], ['unpaid', 'partial', 'overdue'], true)));
    $current = $payments[$thisMonth] ?? null;

    return [
        'id' => (int) $row['id'],
        'code' => sprintf('BK-%05d', $row['id']),
        'status' => $row['status'],
        'tenant_id' => (int) $row['tenant_id'],
        'name' => $row['contact_name'] ?: fullName($row),
        'phone' => $row['contact_number'] ?: $row['tenant_phone'],
        'email' => $row['contact_email'] ?: $row['tenant_email'],
        'photo' => $row['avatar_path'] ? publicAssetUrl((string) $row['avatar_path']) : null,
        'house_id' => (int) $row['house_id'],
        'house_name' => $row['house_name'],
        'room_id' => (int) $row['room_id'],
        'room_number' => $row['room_number'],
        'room_type_label' => ROOM_TYPE_LABELS[$row['room_type']] ?? $row['room_type'],
        'occupants_count' => (int) $row['occupants_count'],
        'move_in_date' => $row['move_in_date'],
        'moved_out_at' => $row['status'] === 'completed' ? $row['updated_at'] : null,
        'monthly_rent' => monthlyRent($row['room_type'], (float) $row['price'], (int) $row['occupants_count']),
        'this_month' => $row['status'] === 'approved' ? paymentState($current, $thisMonth, $start) : null,
        'unpaid_months' => count($unpaid),
        'balance' => array_sum(array_map(fn ($h) => max(0, $h['amount_due'] - $h['amount_paid']), $unpaid)),
        'history' => $history,
    ];
}
