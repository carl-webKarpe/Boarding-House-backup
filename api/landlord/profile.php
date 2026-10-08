<?php

declare(strict_types=1);

/**
 * The logged-in landlord's own account.
 *
 *   GET  profile.php
 *   PUT  profile.php                     { first_name, last_name, email, contact_number, address, business_name }
 *   POST profile.php?action=photo        multipart "photo" (shown to students on Contact Landlord)
 *   PUT  profile.php?action=password     { current_password, new_password, confirm_password }
 */

require_once __DIR__ . '/_landlord.php';

function loadLandlordProfile(): array {
    $stmt = getDb()->prepare('SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.contact_number, u.address, u.avatar_path, u.status,
            u.created_at, l.business_name, l.verification_status
        FROM users u JOIN landlords l ON l.user_id = u.id WHERE u.id = :id');
    $stmt->execute([':id' => landlordUserId()]);
    $row = $stmt->fetch();
    $row['id'] = (int) $row['id'];
    $row['full_name'] = fullName($row);
    return $row;
}

$pdo = getDb();
$action = queryString('action');

switch (requestMethod()) {
    case 'GET':
        jsonResponse(loadLandlordProfile());

    case 'POST':
        if ($action !== 'photo') {
            throw new ApiException('Unknown action.', 400);
        }
        $files = uploadedFileList($_FILES['photo'] ?? null);
        if (!$files) {
            throw new ApiException('Choose a photo.', 422);
        }
        $result = storeAvatarImage($files[0]);
        if (!$result['success']) {
            throw new ApiException($result['message'], 422);
        }
        $old = loadLandlordProfile()['avatar_path'];
        $pdo->prepare('UPDATE users SET avatar_path = :path WHERE id = :id')->execute([':path' => $result['path'], ':id' => landlordUserId()]);
        if ($old) {
            deleteUploadedImage((string) $old);
        }
        jsonResponse(loadLandlordProfile(), 'Profile photo updated.');

    case 'PUT':
    case 'PATCH':
        $body = requestBody();
        if ($action === 'password') {
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
            $stmt->execute([':id' => landlordUserId()]);
            if (!password_verify((string) ($body['current_password'] ?? ''), (string) $stmt->fetchColumn())) {
                throw new ApiException('Your current password is incorrect.', 422, ['current_password' => 'Incorrect password.']);
            }
            $values = collectValidated(['new_password' => validatePasswordValue($body['new_password'] ?? '')]);
            if (($body['new_password'] ?? '') !== ($body['confirm_password'] ?? '')) {
                throw new ApiException('The new passwords do not match.', 422, ['confirm_password' => 'Does not match.']);
            }
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([':hash' => password_hash($values['new_password'], PASSWORD_DEFAULT), ':id' => landlordUserId()]);
            session_regenerate_id(true);
            landlordLog('password_change', 'Landlord changed their password', 'user', landlordUserId());
            jsonResponse(null, 'Password changed.');
        }

        $values = collectValidated([
            'first_name' => validateTextValue($body['first_name'] ?? '', 'First name', 80),
            'last_name' => validateTextValue($body['last_name'] ?? '', 'Last name', 80),
            'email' => validateEmailValue($body['email'] ?? ''),
            'contact_number' => validatePhoneValue($body['contact_number'] ?? ''),
            'address' => validateTextValue($body['address'] ?? '', 'Address', 255, false),
            'business_name' => validateTextValue($body['business_name'] ?? '', 'Business name', 150, false),
        ]);
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id');
        $dup->execute([':email' => $values['email'], ':id' => landlordUserId()]);
        if ($dup->fetch()) {
            throw new ApiException('This email is already used by another account.', 409, ['email' => 'Already in use.']);
        }
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET first_name = :first, last_name = :last, email = :email, contact_number = :phone, address = :address WHERE id = :id')
            ->execute([':first' => $values['first_name'], ':last' => $values['last_name'], ':email' => $values['email'],
                ':phone' => $values['contact_number'], ':address' => $values['address'], ':id' => landlordUserId()]);
        $pdo->prepare('UPDATE landlords SET business_name = :business WHERE user_id = :id')->execute([':business' => $values['business_name'], ':id' => landlordUserId()]);
        $pdo->commit();
        $profile = loadLandlordProfile();
        $_SESSION['full_name'] = $profile['full_name'];
        $_SESSION['email'] = $profile['email'];
        jsonResponse($profile, 'Profile saved.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH');
}
