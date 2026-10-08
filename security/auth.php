<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/upload_security.php';

const BH_LOGIN_FAILURES_PER_ACCOUNT = 10;
const BH_LOGIN_FAILURES_PER_IP = 100;
const BH_LOGIN_WINDOW_MINUTES = 15;

function loginUser(string $email, string $password): array {
    startSecureSession();

    $email = sanitizeEmail($email);
    $ip = getClientIp();
    $accountKey = $ip . '|' . $email;

    if (isRateLimited('login', $accountKey, BH_LOGIN_FAILURES_PER_ACCOUNT, BH_LOGIN_WINDOW_MINUTES)
        || isRateLimited('login_ip', $ip, BH_LOGIN_FAILURES_PER_IP, BH_LOGIN_WINDOW_MINUTES)) {
        writeLog('Login rate limit reached from ' . $ip, 'WARN');
        return ['success' => false, 'status' => 429, 'message' => 'Too many failed login attempts. Please try again in 15 minutes.'];
    }

    $validation = validateEmailValue($email);
    if (!$validation['valid']) {
        return ['success' => false, 'status' => 422, 'message' => $validation['message']];
    }

    if ($password === '') {
        return ['success' => false, 'status' => 422, 'message' => 'Password is required.'];
    }

    $fail = static function () use ($accountKey, $ip, $email): array {
        hitRateLimit('login', $accountKey, BH_LOGIN_WINDOW_MINUTES);
        hitRateLimit('login_ip', $ip, BH_LOGIN_WINDOW_MINUTES);
        recordFailedLogin($email);
        return ['success' => false, 'status' => 401, 'message' => 'Invalid email or password.'];
    };

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, first_name, last_name, status, locked_until FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $validation['value']]);
        $user = $stmt->fetch();

        if (!$user) {
            // Spend the same time as a real check so response timing does not reveal which emails exist.
            password_verify($password, '$2y$10$cGHazvV7tOeecYRdl9msVu6U1iUigeiLSYw3nBxaJ.xhvMv8gjWP.');
            return $fail();
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            return ['success' => false, 'status' => 423, 'message' => 'Account is temporarily locked after too many failed attempts. Please try again later.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return $fail();
        }

        if ($user['status'] === 'disabled') {
            return ['success' => false, 'status' => 403, 'message' => 'This account has been disabled. Please contact the administrator.'];
        }

        $updates = 'failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW()';
        $params = [':id' => $user['id']];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $updates .= ', password_hash = :password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $pdo->prepare("UPDATE users SET {$updates} WHERE id = :id")->execute($params);
        clearRateLimit('login', $accountKey);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['email'] = (string) $user['email'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']) ?: (string) $user['username'];
        $_SESSION['is_logged_in'] = true;
        unset($_SESSION['csrf_token']);

        auditLog('login', roleLabel($user['role']) . ' logged in: ' . $_SESSION['full_name'], (int) $user['id'], 'user', (int) $user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'username' => (string) $user['username'],
            'name' => $_SESSION['full_name'],
            'role' => (string) $user['role'],
            'redirect' => homePathForRole((string) $user['role']),
        ];
    } catch (Throwable $e) {
        writeLog('Login error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'Login is unavailable right now. Please try again later.'];
    }
}

/**
 * Validates the profile fields shared by tenant and landlord registration.
 * Returns ['errors' => [...], 'values' => [...]].
 */
function validateRegistrationProfile(array $data, string $phoneField, string $addressField): array {
    $errors = [];
    $values = [];
    $checks = [
        // The registration forms call this field "gmail".
        'email' => validateEmailValue($data['gmail'] ?? $data['email'] ?? ''),
        'username' => validateUsernameValue($data['username'] ?? ''),
        'password' => validatePasswordValue($data['password'] ?? ''),
        'first_name' => validateTextValue($data['firstName'] ?? '', 'First name', 80),
        'middle_name' => validateTextValue($data['middleName'] ?? '', 'Middle name', 80, false),
        'last_name' => validateTextValue($data['lastName'] ?? '', 'Last name', 80),
        'contact_number' => validatePhoneValue($data[$phoneField] ?? ''),
        'address' => validateTextValue($data[$addressField] ?? '', 'Address', 255),
        'gender' => validateEnumValue($data['gender'] ?? '', ['male', 'female', 'prefer_not_to_say'], 'Gender'),
        'birth_date' => validateDateValue($data['dob'] ?? '', 'Date of birth'),
    ];

    foreach ($checks as $field => $result) {
        if (!$result['valid']) {
            $errors[$field] = $result['message'];
        } else {
            $values[$field] = $result['value'] ?? null;
        }
    }

    if (($data['password'] ?? '') !== ($data['confirmPassword'] ?? '')) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    if (isset($values['birth_date']) && $values['birth_date'] !== null) {
        $age = (new DateTimeImmutable($values['birth_date']))->diff(new DateTimeImmutable('today'))->y;
        if ($age < 16 || $age > 120) {
            $errors['birth_date'] = 'You must be at least 16 years old.';
        }
    }

    if (($data['terms'] ?? '') !== 'true' || ($data['privacy'] ?? '') !== 'true') {
        $errors['terms'] = 'You must agree to the Terms and the Privacy Policy.';
    }

    return ['errors' => $errors, 'values' => $values];
}

/**
 * Registers a tenant or landlord with their full profile and verification
 * documents. Everything is written in one transaction: if any part fails,
 * no account is created and stored files are removed.
 *
 * @param array $data   Form fields ($_POST)
 * @param array $files  role-specific uploads: doc_type => $_FILES entry
 */
function registerAccount(string $role, array $data, array $files): array {
    if (!in_array($role, [ROLE_TENANT, ROLE_LANDLORD], true)) {
        return ['success' => false, 'status' => 422, 'message' => 'Invalid account type.'];
    }

    $settingKey = $role === ROLE_TENANT ? 'allow_tenant_registration' : 'allow_landlord_registration';
    if (!settingEnabled($settingKey)) {
        return ['success' => false, 'status' => 403, 'message' => 'Registration is currently closed. Please try again later.'];
    }

    $isTenant = $role === ROLE_TENANT;
    $validated = validateRegistrationProfile($data, $isTenant ? 'mobileNumber' : 'contactNumber', $isTenant ? 'currentAddress' : 'homeAddress');
    $errors = $validated['errors'];
    $values = $validated['values'];

    $landlord = [];
    if (!$isTenant) {
        foreach ([
            'business_name' => validateTextValue($data['propertyName'] ?? '', 'Boarding house name', 150),
            'business_address' => validateTextValue($data['businessAddress'] ?? '', 'Business address', 255),
            'barangay' => validateTextValue($data['barangay'] ?? '', 'Barangay', 100),
            'city' => validateTextValue($data['municipality'] ?? '', 'Municipality/City', 100),
            'province' => validateTextValue($data['province'] ?? '', 'Province', 100),
        ] as $field => $result) {
            if (!$result['valid']) {
                $errors[$field] = $result['message'];
            } else {
                $landlord[$field] = $result['value'];
            }
        }
    }

    $requiredDocs = $isTenant ? ['id_document'] : ['government_id', 'selfie'];
    foreach ($requiredDocs as $docKey) {
        if (empty($files[$docKey]) || ($files[$docKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[$docKey] = 'Please upload the required document.';
        }
    }

    $tenantIdType = null;
    if ($isTenant) {
        $tenantIdType = in_array($data['idType'] ?? '', ['student_id', 'government_id'], true) ? $data['idType'] : null;
        if ($tenantIdType === null) {
            $errors['idType'] = 'Please select which ID you are uploading.';
        }
    }

    if ($errors) {
        return ['success' => false, 'status' => 422, 'message' => (string) reset($errors), 'errors' => $errors];
    }

    $storedFiles = [];
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT email, username FROM users WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([':email' => $values['email'], ':username' => $values['username']]);
        if ($existing = $stmt->fetch()) {
            $field = strcasecmp((string) $existing['email'], $values['email']) === 0 ? 'email' : 'username';
            return ['success' => false, 'status' => 409, 'message' => $field === 'email' ? 'This email is already registered.' : 'This username is already taken.', 'errors' => [$field => 'Already in use.']];
        }

        // Map uploads to document types and store them before touching the DB.
        $docMap = $isTenant
            ? ['id_document' => $tenantIdType]
            : ['government_id' => 'government_id', 'selfie' => 'selfie', 'business_permit' => 'business_permit', 'proof_of_ownership' => 'proof_of_ownership'];
        foreach ($docMap as $fileKey => $docType) {
            if (empty($files[$fileKey]) || ($files[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $stored = storeVerificationDocument($files[$fileKey]);
            if (!$stored['success']) {
                throw new InvalidArgumentException($stored['message'] ?? 'Upload failed.');
            }
            $storedFiles[] = $stored + ['doc_type' => $docType];
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status)
            VALUES (:username, :email, :password_hash, :role, :first_name, :middle_name, :last_name, :contact_number, :address, :gender, :birth_date, :status)');
        $stmt->execute([
            ':username' => $values['username'],
            ':email' => $values['email'],
            ':password_hash' => password_hash((string) $values['password'], PASSWORD_DEFAULT),
            ':role' => $role,
            ':first_name' => $values['first_name'],
            ':middle_name' => $values['middle_name'],
            ':last_name' => $values['last_name'],
            ':contact_number' => $values['contact_number'],
            ':address' => $values['address'],
            ':gender' => $values['gender'],
            ':birth_date' => $values['birth_date'],
            // Landlords can log in right away but stay "pending" until an admin verifies them.
            ':status' => $isTenant ? 'active' : 'pending',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $docStmt = $pdo->prepare('INSERT INTO verification_documents (user_id, doc_type, original_name, stored_name, mime_type, file_size) VALUES (:user_id, :doc_type, :original_name, :stored_name, :mime_type, :file_size)');
        foreach ($storedFiles as $doc) {
            $docStmt->execute([
                ':user_id' => $userId,
                ':doc_type' => $doc['doc_type'],
                ':original_name' => $doc['original_name'],
                ':stored_name' => $doc['stored_name'],
                ':mime_type' => $doc['mime'],
                ':file_size' => $doc['size'],
            ]);
        }

        $fullName = $values['first_name'] . ' ' . $values['last_name'];
        if (!$isTenant) {
            $pdo->prepare('INSERT INTO landlords (user_id, business_name, business_address, verification_status) VALUES (:user_id, :business_name, :business_address, \'pending\')')
                ->execute([
                    ':user_id' => $userId,
                    ':business_name' => $landlord['business_name'],
                    ':business_address' => $landlord['business_address'],
                ]);
            $landlordId = (int) $pdo->lastInsertId();

            // The property named during registration becomes the landlord's first listing, waiting for approval.
            // barangay_id links to the barangays list when the typed name matches one.
            $pdo->prepare('INSERT INTO boarding_houses (landlord_id, name, address, barangay_id, barangay, city, province, contact_name, contact_number, contact_email, status)
                VALUES (:landlord_id, :name, :address,
                    (SELECT id FROM barangays WHERE name = :barangay_lookup AND municipality = :city_lookup LIMIT 1),
                    :barangay, :city, :province, :contact_name, :contact_number, :contact_email, \'pending\')')
                ->execute([
                    ':barangay_lookup' => $landlord['barangay'],
                    ':city_lookup' => $landlord['city'],
                    ':contact_name' => $fullName,
                    ':landlord_id' => $landlordId,
                    ':name' => $landlord['business_name'],
                    ':address' => $landlord['business_address'],
                    ':barangay' => $landlord['barangay'],
                    ':city' => $landlord['city'],
                    ':province' => $landlord['province'],
                    ':contact_number' => $values['contact_number'],
                    ':contact_email' => $values['email'],
                ]);
            $houseId = (int) $pdo->lastInsertId();
        }
        $pdo->commit();

        if ($isTenant) {
            auditLog('register', 'New tenant registered: ' . $fullName, $userId, 'user', $userId);
            notifyAdmins('user_registered', 'New user registered', $fullName . ' created a tenant account.', '#/users?role=tenant');
        } else {
            auditLog('register', 'New landlord registered: ' . $fullName, $userId, 'user', $userId);
            auditLog('listing_create', 'New boarding house submitted: ' . $landlord['business_name'], $userId, 'boarding_house', $houseId ?? null);
            notifyAdmins('landlord_registered', 'New landlord registered', $fullName . ' registered as a landlord and is waiting for verification.', '#/landlords');
            notifyAdmins('listing_submitted', 'Listing requires approval', $landlord['business_name'] . ' was submitted and requires approval.', '#/boarding-houses?status=pending');
        }

        return ['success' => true, 'message' => 'Registration successful.'];
    } catch (InvalidArgumentException $e) {
        cleanupStoredDocuments($storedFiles);
        return ['success' => false, 'status' => 422, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        cleanupStoredDocuments($storedFiles);
        writeLog('Registration error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'status' => 503, 'message' => 'Registration failed. Please try again later.'];
    }
}

function cleanupStoredDocuments(array $storedFiles): void {
    foreach ($storedFiles as $doc) {
        @unlink(BH_DOCUMENT_DIR . '/' . $doc['stored_name']);
    }
}

function changePassword(int $userId, string $newPassword): bool {
    $passwordValidation = validatePasswordValue($newPassword);
    if (!$passwordValidation['valid']) {
        return false;
    }

    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        auditLog('password_change', 'Password changed', $userId, 'user', $userId);
        return true;
    } catch (Throwable $e) {
        writeLog('Password update failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}
