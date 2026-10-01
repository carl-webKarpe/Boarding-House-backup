<?php

declare(strict_types=1);

/**
 * Admin user management (tenants, landlords, administrators).
 *
 *   GET    users.php                 list   ?q=&role=tenant|landlord|admin&status=&page=&per_page=&sort=&order=
 *   GET    users.php?id=5            details (+ documents, boarding houses, bookings)
 *   POST   users.php                 create
 *   PUT    users.php?id=5            update (profile, role, status, verification, password reset)
 *   DELETE users.php?id=5            delete
 */

require_once __DIR__ . '/_admin.php';

const USER_SELECT = "SELECT u.id, u.username, u.email, u.role, u.first_name, u.middle_name, u.last_name, u.contact_number, u.address,
    u.gender, u.birth_date, u.status, u.last_login_at, u.created_at, u.updated_at,
    l.id AS landlord_id, l.business_name, l.verification_status,
    (SELECT COUNT(*) FROM boarding_houses bh WHERE bh.landlord_id = l.id) AS house_count,
    (SELECT COUNT(*) FROM bookings b WHERE b.tenant_id = u.id) AS booking_count";

function formatUser(array $row): array {
    $row = castRow($row, ['id', 'landlord_id', 'house_count', 'booking_count']);
    $row['full_name'] = fullName($row);
    $row['role_label'] = roleLabel($row['role']);
    return $row;
}

function loadUser(int $id): array {
    $stmt = getDb()->prepare(USER_SELECT . ' FROM users u LEFT JOIN landlords l ON l.user_id = u.id WHERE u.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        notFound('User');
    }

    return formatUser($row);
}

/**
 * Admins may manage tenants and landlords. Only a super admin may manage
 * other administrators. Nobody can change their own role or status here.
 */
function assertCanManage(array $target, bool $selfAllowed = false): void {
    if (!$selfAllowed && (int) $target['id'] === adminId()) {
        throw new ApiException('You cannot change your own role, status or account here. Use Settings > Profile instead.', 403);
    }

    if (isAdminRole($target['role']) && !isSuperAdmin()) {
        throw new ApiException('Only a super admin can manage administrator accounts.', 403);
    }
}

function validateUserPayload(array $body, bool $creating): array {
    $rules = [];
    if ($creating || array_key_exists('username', $body)) {
        $rules['username'] = validateUsernameValue($body['username'] ?? '');
    }
    if ($creating || array_key_exists('email', $body)) {
        $rules['email'] = validateEmailValue($body['email'] ?? '');
    }
    if ($creating || array_key_exists('first_name', $body)) {
        $rules['first_name'] = validateTextValue($body['first_name'] ?? '', 'First name', 80);
    }
    if (array_key_exists('middle_name', $body)) {
        $rules['middle_name'] = validateTextValue($body['middle_name'] ?? '', 'Middle name', 80, false);
    }
    if ($creating || array_key_exists('last_name', $body)) {
        $rules['last_name'] = validateTextValue($body['last_name'] ?? '', 'Last name', 80);
    }
    if (array_key_exists('contact_number', $body) && trim((string) $body['contact_number']) !== '') {
        $rules['contact_number'] = validatePhoneValue($body['contact_number']);
    } elseif (array_key_exists('contact_number', $body)) {
        $rules['contact_number'] = ['valid' => true, 'value' => null];
    }
    if (array_key_exists('address', $body)) {
        $rules['address'] = validateTextValue($body['address'] ?? '', 'Address', 255, false);
    }
    if ($creating || array_key_exists('role', $body)) {
        $rules['role'] = validateEnumValue($body['role'] ?? '', ALL_ROLES, 'Role');
    }
    if (array_key_exists('status', $body)) {
        $rules['status'] = validateEnumValue($body['status'] ?? '', ['active', 'pending', 'disabled'], 'Status');
    }
    if (array_key_exists('verification_status', $body)) {
        $rules['verification_status'] = validateEnumValue($body['verification_status'] ?? '', ['unverified', 'pending', 'verified', 'rejected'], 'Verification status');
    }
    if (array_key_exists('business_name', $body)) {
        $rules['business_name'] = validateTextValue($body['business_name'] ?? '', 'Business name', 150, false);
    }
    if ($creating || (isset($body['password']) && $body['password'] !== '')) {
        $rules['password'] = validatePasswordValue($body['password'] ?? '');
    }

    $values = collectValidated($rules);

    if (isset($values['role']) && isAdminRole($values['role']) && !isSuperAdmin()) {
        throw new ApiException('Only a super admin can create or promote administrators.', 403, ['role' => 'Not allowed.']);
    }

    return $values;
}

function assertUnique(?string $email, ?string $username, int $ignoreId = 0): void {
    $pdo = getDb();
    if ($email !== null) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :v AND id <> :id');
        $stmt->execute([':v' => $email, ':id' => $ignoreId]);
        if ($stmt->fetch()) {
            throw new ApiException('This email is already registered.', 409, ['email' => 'Already in use.']);
        }
    }
    if ($username !== null) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :v AND id <> :id');
        $stmt->execute([':v' => $username, ':id' => $ignoreId]);
        if ($stmt->fetch()) {
            throw new ApiException('This username is already taken.', 409, ['username' => 'Already in use.']);
        }
    }
}

$pdo = getDb();

switch (requestMethod()) {
    case 'GET':
        if (isset($_GET['id'])) {
            $user = loadUser(requireId());

            $docs = $pdo->prepare('SELECT id, doc_type, original_name, mime_type, file_size, created_at FROM verification_documents WHERE user_id = :id ORDER BY id');
            $docs->execute([':id' => $user['id']]);
            $user['documents'] = array_map(fn ($d) => castRow($d, ['id', 'file_size']), $docs->fetchAll());

            $user['boarding_houses'] = [];
            if ($user['landlord_id']) {
                $houses = $pdo->prepare('SELECT bh.id, bh.name, bh.city, bh.status, bh.created_at,
                    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id) AS room_count,
                    (SELECT COUNT(*) FROM rooms r WHERE r.boarding_house_id = bh.id AND r.status = \'available\') AS available_rooms
                    FROM boarding_houses bh WHERE bh.landlord_id = :id ORDER BY bh.created_at DESC');
                $houses->execute([':id' => $user['landlord_id']]);
                $user['boarding_houses'] = array_map(fn ($h) => castRow($h, ['id', 'room_count', 'available_rooms']), $houses->fetchAll());
            }

            $bookings = $pdo->prepare('SELECT b.id, b.status, b.booking_date, r.room_number, bh.name AS boarding_house
                FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN boarding_houses bh ON bh.id = r.boarding_house_id
                WHERE b.tenant_id = :id ORDER BY b.booking_date DESC LIMIT 10');
            $bookings->execute([':id' => $user['id']]);
            $user['bookings'] = array_map(fn ($b) => castRow($b, ['id']), $bookings->fetchAll());

            jsonResponse($user);
        }

        $where = [];
        $params = [];
        $search = queryString('q');
        if ($search !== '') {
            $where[] = '(u.username LIKE :q1 OR u.email LIKE :q2 OR CONCAT(u.first_name, \' \', u.last_name) LIKE :q3 OR u.contact_number LIKE :q4 OR l.business_name LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[':' . $k] = likeValue($search);
            }
        }

        $role = queryString('role');
        if ($role === 'admin') {
            $where[] = "u.role IN ('admin', 'super_admin')";
        } elseif (in_array($role, [ROLE_TENANT, ROLE_LANDLORD], true)) {
            $where[] = 'u.role = :role';
            $params[':role'] = $role;
        }

        $status = queryString('status');
        if (in_array($status, ['active', 'pending', 'disabled'], true)) {
            $where[] = 'u.status = :status';
            $params[':status'] = $status;
        }

        $verification = queryString('verification');
        if (in_array($verification, ['unverified', 'pending', 'verified', 'rejected'], true)) {
            $where[] = 'l.verification_status = :verification';
            $params[':verification'] = $verification;
        }

        $fromWhere = 'FROM users u LEFT JOIN landlords l ON l.user_id = u.id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $order = orderBy([
            'created_at' => 'u.created_at',
            'name' => 'u.first_name',
            'email' => 'u.email',
            'role' => 'u.role',
            'status' => 'u.status',
            'last_login_at' => 'u.last_login_at',
        ], 'created_at');

        [$rows, $meta] = pagedQuery(USER_SELECT, $fromWhere, $params, $order, paginationParams());

        $counts = $pdo->query("SELECT
            COUNT(*) AS all_users,
            SUM(role = 'tenant') AS tenant,
            SUM(role = 'landlord') AS landlord,
            SUM(role IN ('admin', 'super_admin')) AS admin,
            SUM(status = 'disabled') AS disabled,
            SUM(status = 'pending') AS pending
            FROM users")->fetch();

        jsonResponse(array_map('formatUser', $rows), '', 200, [
            'meta' => $meta,
            'counts' => array_map('intval', $counts),
        ]);

    case 'POST':
        $values = validateUserPayload(requestBody(), true);
        assertUnique($values['email'], $values['username']);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, status)
            VALUES (:username, :email, :password_hash, :role, :first_name, :middle_name, :last_name, :contact_number, :address, :status)');
        $stmt->execute([
            ':username' => $values['username'],
            ':email' => $values['email'],
            ':password_hash' => password_hash((string) $values['password'], PASSWORD_DEFAULT),
            ':role' => $values['role'],
            ':first_name' => $values['first_name'],
            ':middle_name' => $values['middle_name'] ?? null,
            ':last_name' => $values['last_name'],
            ':contact_number' => $values['contact_number'] ?? null,
            ':address' => $values['address'] ?? null,
            ':status' => $values['status'] ?? 'active',
        ]);
        $id = (int) $pdo->lastInsertId();

        if ($values['role'] === ROLE_LANDLORD) {
            // Accounts created by an admin are trusted, so they start verified.
            $pdo->prepare('INSERT INTO landlords (user_id, business_name, verification_status, verified_at) VALUES (:id, :business, :status, IF(:status2 = \'verified\', NOW(), NULL))')
                ->execute([
                    ':id' => $id,
                    ':business' => $values['business_name'] ?? null,
                    ':status' => $values['verification_status'] ?? 'verified',
                    ':status2' => $values['verification_status'] ?? 'verified',
                ]);
        }
        $pdo->commit();

        $user = loadUser($id);
        adminLog('user_create', sprintf('Administrator created %s account: %s', strtolower($user['role_label']), $user['full_name']), 'user', $id);
        jsonResponse($user, 'User account created.', 201);

    case 'PUT':
    case 'PATCH':
        $id = requireId();
        $target = loadUser($id);
        $body = requestBody();
        $values = validateUserPayload($body, false);

        $isSelf = $id === adminId();
        $changesAccess = isset($values['role']) && $values['role'] !== $target['role']
            || isset($values['status']) && $values['status'] !== $target['status'];
        assertCanManage($target, !$changesAccess);
        if ($isSelf && isset($values['password'])) {
            throw new ApiException('Change your own password in Settings > Security.', 403);
        }

        assertUnique($values['email'] ?? null, $values['username'] ?? null, $id);

        if (isset($values['role']) && $target['role'] === ROLE_LANDLORD && $values['role'] !== ROLE_LANDLORD && $target['house_count'] > 0) {
            throw new ApiException('This landlord still owns boarding houses. Reassign or delete them before changing the role.', 409, ['role' => 'Landlord has listings.']);
        }

        $columns = ['username', 'email', 'first_name', 'middle_name', 'last_name', 'contact_number', 'address', 'role', 'status'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($columns as $column) {
            if (array_key_exists($column, $values)) {
                $sets[] = "{$column} = :{$column}";
                $params[':' . $column] = $values[$column];
            }
        }
        if (isset($values['password'])) {
            $sets[] = 'password_hash = :password_hash, failed_login_attempts = 0, locked_until = NULL';
            $params[':password_hash'] = password_hash((string) $values['password'], PASSWORD_DEFAULT);
        }

        $pdo->beginTransaction();
        if ($sets) {
            $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }

        $newRole = $values['role'] ?? $target['role'];
        if ($newRole === ROLE_LANDLORD) {
            if (!$target['landlord_id']) {
                $pdo->prepare('INSERT INTO landlords (user_id, verification_status) VALUES (:id, \'pending\')')->execute([':id' => $id]);
            }
            $landlordSets = [];
            $landlordParams = [':id' => $id];
            if (array_key_exists('business_name', $values)) {
                $landlordSets[] = 'business_name = :business';
                $landlordParams[':business'] = $values['business_name'];
            }
            if (isset($values['verification_status'])) {
                $landlordSets[] = 'verification_status = :vstatus, verified_at = IF(:vstatus2 = \'verified\', NOW(), NULL)';
                $landlordParams[':vstatus'] = $values['verification_status'];
                $landlordParams[':vstatus2'] = $values['verification_status'];
            }
            if ($landlordSets) {
                $pdo->prepare('UPDATE landlords SET ' . implode(', ', $landlordSets) . ' WHERE user_id = :id')->execute($landlordParams);
            }
        }
        $pdo->commit();

        $user = loadUser($id);
        $changes = [];
        if (isset($values['status']) && $values['status'] !== $target['status']) {
            $changes[] = 'status ' . $target['status'] . ' → ' . $values['status'];
            if ($values['status'] === 'disabled') {
                notifyUser($id, 'account', 'Account disabled', 'Your account was disabled by an administrator.');
            }
        }
        if (isset($values['role']) && $values['role'] !== $target['role']) {
            $changes[] = 'role ' . $target['role'] . ' → ' . $values['role'];
        }
        if (isset($values['verification_status']) && $values['verification_status'] !== $target['verification_status']) {
            $changes[] = 'verification → ' . $values['verification_status'];
            if ($values['verification_status'] === 'verified') {
                notifyUser($id, 'account', 'Landlord account verified', 'Your landlord account has been verified. You can now publish listings.');
            }
        }
        if (isset($values['password'])) {
            $changes[] = 'password reset';
        }

        $action = isset($values['status']) && $values['status'] === 'disabled' && $target['status'] !== 'disabled' ? 'user_disable' : 'user_update';
        adminLog($action, 'Administrator updated user ' . $user['full_name'] . ($changes ? ' (' . implode(', ', $changes) . ')' : ''), 'user', $id);
        jsonResponse($user, 'User account updated.');

    case 'DELETE':
        $id = requireId();
        $target = loadUser($id);
        assertCanManage($target);

        $docs = $pdo->prepare('SELECT stored_name FROM verification_documents WHERE user_id = :id');
        $docs->execute([':id' => $id]);
        $files = $docs->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw new ApiException('This user has booking records, so the account cannot be deleted. Disable the account instead to keep the rental history.', 409);
            }
            throw $e;
        }

        foreach ($files as $file) {
            @unlink(BH_DOCUMENT_DIR . '/' . basename((string) $file));
        }

        adminLog('user_delete', 'Administrator deleted user ' . $target['full_name'] . ' (' . $target['email'] . ')', 'user', $id);
        jsonResponse(null, 'User account deleted.');

    default:
        requireMethod('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
}
