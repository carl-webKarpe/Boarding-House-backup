<?php

declare(strict_types=1);

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_ADMIN = 'admin';
const ROLE_STAFF = 'staff';
const ROLE_USER = 'user';

function roleHierarchy(string $role): array {
    return match ($role) {
        ROLE_SUPER_ADMIN => [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF, ROLE_USER],
        ROLE_ADMIN => [ROLE_ADMIN, ROLE_STAFF, ROLE_USER],
        ROLE_STAFF => [ROLE_STAFF, ROLE_USER],
        default => [ROLE_USER],
    };
}

function userCan(string $requiredRole, ?string $userRole = null): bool {
    $role = $userRole ?? ($_SESSION['role'] ?? ROLE_USER);
    $allowed = roleHierarchy($role);
    return in_array($requiredRole, $allowed, true);
}
