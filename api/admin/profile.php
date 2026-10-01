<?php

declare(strict_types=1);

/**
 * The logged-in administrator's own account.
 *
 *   GET profile.php
 *   PUT profile.php                       { first_name, last_name, email, contact_number }
 *   PUT profile.php?action=password       { current_password, new_password, confirm_password }
 *   PUT profile.php?action=preferences    { email_new_users, email_new_listings, email_bookings, browser_alerts }
 */

require_once __DIR__ . '/_admin.php';

const DEFAULT_NOTIFICATION_PREFS = [
    'new_users' => true,
    'new_listings' => true,
    'bookings' => true,
    'system' => true,
];

function loadProfile(): array {
    $stmt = getDb()->prepare('SELECT id, username, email, role, first_name, last_name, contact_number, last_login_at, created_at, notification_prefs FROM users WHERE id = :id');
    $stmt->execute([':id' => adminId()]);
    $row = $stmt->fetch();
    $prefs = json_decode((string) ($row['notification_prefs'] ?? ''), true);
    $row['notification_prefs'] = array_merge(DEFAULT_NOTIFICATION_PREFS, is_array($prefs) ? $prefs : []);
    $row['id'] = (int) $row['id'];
    $row['full_name'] = fullName($row);
    $row['role_label'] = roleLabel($row['role']);
    return $row;
}

$pdo = getDb();
$action = queryString('action');

switch (requestMethod()) {
    case 'GET':
        jsonResponse(loadProfile());

    case 'PUT':
    case 'PATCH':
        $body = requestBody();

        if ($action === 'password') {
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
            $stmt->execute([':id' => adminId()]);
            if (!password_verify((string) ($body['current_password'] ?? ''), (string) $stmt->fetchColumn())) {
                throw new ApiException('Your current password is incorrect.', 422, ['current_password' => 'Incorrect password.']);
            }
            $values = collectValidated(['new_password' => validatePasswordValue($body['new_password'] ?? '')]);
            if (($body['new_password'] ?? '') !== ($body['confirm_password'] ?? '')) {
                throw new ApiException('The new passwords do not match.', 422, ['confirm_password' => 'Does not match.']);
            }
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([':hash' => password_hash($values['new_password'], PASSWORD_DEFAULT), ':id' => adminId()]);
            session_regenerate_id(true);
            adminLog('password_change', 'Administrator changed their password', 'user', adminId());
            jsonResponse(null, 'Password changed.');
        }

        if ($action === 'preferences') {
            $prefs = [];
            foreach (array_keys(DEFAULT_NOTIFICATION_PREFS) as $key) {
                $prefs[$key] = filter_var($body[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $pdo->prepare('UPDATE users SET notification_prefs = :prefs WHERE id = :id')->execute([':prefs' => json_encode($prefs), ':id' => adminId()]);
            jsonResponse(loadProfile(), 'Notification preferences saved.');
        }

        $values = collectValidated([
            'first_name' => validateTextValue($body['first_name'] ?? '', 'First name', 80),
            'last_name' => validateTextValue($body['last_name'] ?? '', 'Last name', 80),
            'email' => validateEmailValue($body['email'] ?? ''),
            'contact_number' => trim((string) ($body['contact_number'] ?? '')) === '' ? ['valid' => true, 'value' => null] : validatePhoneValue($body['contact_number']),
        ]);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id');
        $stmt->execute([':email' => $values['email'], ':id' => adminId()]);
        if ($stmt->fetch()) {
            throw new ApiException('This email is already used by another account.', 409, ['email' => 'Already in use.']);
        }
        $pdo->prepare('UPDATE users SET first_name = :first, last_name = :last, email = :email, contact_number = :contact WHERE id = :id')
            ->execute([':first' => $values['first_name'], ':last' => $values['last_name'], ':email' => $values['email'], ':contact' => $values['contact_number'], ':id' => adminId()]);
        $profile = loadProfile();
        $_SESSION['full_name'] = $profile['full_name'];
        $_SESSION['email'] = $profile['email'];
        adminLog('profile_update', 'Administrator updated their profile', 'user', adminId());
        jsonResponse($profile, 'Profile saved.');

    default:
        requireMethod('GET', 'PUT', 'PATCH');
}
