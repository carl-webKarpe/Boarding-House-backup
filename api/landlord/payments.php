<?php

declare(strict_types=1);

/**
 * Monthly rent payments of my boarders.
 *
 *   GET    payments.php?month=2026-10 &status=paid|unpaid|partial|overdue &house_id=
 *          every boarder who owes rent that month, with what was paid + totals
 *   POST   payments.php   { booking_id, month, amount_paid, paid_at, method, reference, note }
 *          record (or correct) the payment of one month
 *   DELETE payments.php?id=9   remove a recorded payment (back to unpaid)
 */

require_once __DIR__ . '/_landlord.php';
require_once __DIR__ . '/_rent.php';

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        $month = monthStart(queryString('month'));
        $monthEnd = date('Y-m-t', strtotime($month));
        // Current boarders who had moved in by the end of the month, plus anyone
        // (also former boarders) who already has a payment recorded for it.
        $where = "bh.landlord_id = :landlord AND (
            (b.status = 'approved' AND COALESCE(b.move_in_date, DATE(b.booking_date)) <= :month_end)
            OR (b.status = 'completed' AND COALESCE(b.move_in_date, DATE(b.booking_date)) <= :month_end2 AND DATE(b.updated_at) >= :month_start)
            OR EXISTS (SELECT 1 FROM rent_payments p WHERE p.booking_id = b.id AND p.period_month = :month_start2))";
        $params = [':landlord' => landlordId(), ':month_end' => $monthEnd, ':month_end2' => $monthEnd, ':month_start' => $month, ':month_start2' => $month];
        $houseId = queryInt('house_id', 0, 0);
        if ($houseId > 0) {
            $where .= ' AND bh.id = :house';
            $params[':house'] = $houseId;
        }
        $stmt = $pdo->prepare(BOARDER_SELECT . ' ' . BOARDER_FROM . ' WHERE ' . $where . ' ORDER BY bh.name, r.room_number, t.first_name');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $payments = paymentsFor(array_column($rows, 'id'));

        $items = [];
        $totals = ['expected' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0, 'paid' => 0, 'unpaid' => 0];
        foreach ($rows as $row) {
            $p = $payments[(int) $row['id']][$month] ?? null;
            $due = $p ? (float) $p['amount_due'] : monthlyRent($row['room_type'], (float) $row['price'], (int) $row['occupants_count']);
            $paid = $p ? (float) $p['amount_paid'] : 0.0;
            $state = paymentState($p, $month, boarderStartMonth($row));
            $totals['expected'] += $due;
            $totals['collected'] += $paid;
            $totals['outstanding'] += max(0, $due - $paid);
            $state === 'paid' ? $totals['paid']++ : $totals['unpaid']++;
            $items[] = [
                'id' => (int) $row['id'],
                'code' => sprintf('BK-%05d', $row['id']),
                'tenant_id' => (int) $row['tenant_id'],
                'name' => $row['contact_name'] ?: fullName($row),
                'phone' => $row['contact_number'] ?: $row['tenant_phone'],
                'house_name' => $row['house_name'],
                'room_number' => $row['room_number'],
                'occupants_count' => (int) $row['occupants_count'],
                'boarder_status' => $row['status'],
                'amount_due' => $due,
                'amount_paid' => $paid,
                'state' => $state,
                'payment' => formatPayment($p),
            ];
        }
        $filter = queryString('status');
        if ($filter !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['state'] === $filter || ($filter === 'unpaid' && in_array($i['state'], ['unpaid', 'partial', 'overdue'], true))));
        }
        jsonResponse($items, '', 200, [
            'month' => substr($month, 0, 7),
            'month_label' => monthLabel($month),
            'totals' => $totals,
            'methods' => PAYMENT_METHODS,
        ]);

    case 'POST':
        $body = requestBody();
        $booking = ownBooking((int) ($body['booking_id'] ?? 0));
        if (!in_array($booking['status'], ['approved', 'completed'], true)) {
            throw new ApiException('Payments can only be recorded for approved tenants.', 409);
        }
        $month = monthStart((string) ($body['month'] ?? ''));
        if ($month > date('Y-m-01', strtotime('+1 month'))) {
            throw new ApiException('You can record rent up to next month only.', 422, ['month' => 'Too far ahead.']);
        }
        $values = collectValidated([
            'amount_paid' => validateNumberValue($body['amount_paid'] ?? '', 'Amount paid', 0, 1000000),
            'paid_at' => ($body['paid_at'] ?? '') === '' ? ['valid' => true, 'value' => date('Y-m-d')] : validateDateValue($body['paid_at'], 'Payment date'),
            'method' => validateEnumValue($body['method'] ?? 'cash', array_keys(PAYMENT_METHODS), 'Payment method'),
            'reference' => validateTextValue($body['reference'] ?? '', 'Reference number', 100, false),
            'note' => validateTextValue($body['note'] ?? '', 'Note', 500, false),
        ]);
        if ($values['paid_at'] > date('Y-m-d')) {
            throw new ApiException('The payment date cannot be in the future.', 422, ['paid_at' => 'Future date.']);
        }

        $existing = $pdo->prepare('SELECT amount_due FROM rent_payments WHERE booking_id = :b AND period_month = :m');
        $existing->execute([':b' => $booking['id'], ':m' => $month]);
        $due = $existing->fetchColumn();
        $due = $due !== false ? (float) $due : monthlyRent($booking['room_type'], (float) $booking['price'], (int) $booking['occupants_count']);
        $paid = (float) $values['amount_paid'];
        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.009 >= $due ? 'paid' : 'partial');

        $pdo->prepare('INSERT INTO rent_payments (booking_id, period_month, amount_due, amount_paid, status, paid_at, method, reference, note, recorded_by)
            VALUES (:b, :m, :due, :paid, :status, :paid_at, :method, :ref, :note, :by)
            ON DUPLICATE KEY UPDATE amount_paid = VALUES(amount_paid), status = VALUES(status), paid_at = VALUES(paid_at),
                method = VALUES(method), reference = VALUES(reference), note = VALUES(note), recorded_by = VALUES(recorded_by)')
            ->execute([':b' => $booking['id'], ':m' => $month, ':due' => $due, ':paid' => $paid, ':status' => $status,
                ':paid_at' => $paid > 0 ? $values['paid_at'] : null, ':method' => $paid > 0 ? $values['method'] : null,
                ':ref' => $values['reference'] ?: null, ':note' => $values['note'] ?: null, ':by' => landlordUserId()]);

        $label = monthLabel($month);
        $words = ['paid' => 'fully paid', 'partial' => 'partly paid', 'unpaid' => 'marked unpaid'];
        notifyUser((int) $booking['tenant_id'], 'payment', 'Rent payment recorded',
            "Your rent for {$label} at {$booking['house_name']} ({$booking['room_number']}) is {$words[$status]}: ₱" . number_format($paid, 2) . ' of ₱' . number_format($due, 2) . '.');
        landlordLog('payment_record', "Recorded rent for {$label}: ₱" . number_format($paid, 2) . ' (' . ($booking['contact_name'] ?: fullName($booking)) . ", {$booking['house_name']} {$booking['room_number']})", 'booking', (int) $booking['id']);

        $payments = paymentsFor([(int) $booking['id']]);
        jsonResponse(formatBoarder($booking, $payments[(int) $booking['id']] ?? []), "Payment for {$label} saved ({$words[$status]}).");

    case 'DELETE':
        $id = requireId();
        $stmt = $pdo->prepare('SELECT p.id, p.booking_id, p.period_month FROM rent_payments p JOIN bookings b ON b.id = p.booking_id
            JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id WHERE p.id = :id AND bh.landlord_id = :landlord');
        $stmt->execute([':id' => $id, ':landlord' => landlordId()]);
        $payment = $stmt->fetch();
        if (!$payment) {
            notFound('Payment');
        }
        $pdo->prepare('DELETE FROM rent_payments WHERE id = :id')->execute([':id' => $id]);
        landlordLog('payment_delete', 'Removed the rent record for ' . monthLabel($payment['period_month']), 'booking', (int) $payment['booking_id']);
        jsonResponse(null, 'Payment record removed.');

    default:
        requireMethod('GET', 'POST', 'DELETE');
}
