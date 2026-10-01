<?php

declare(strict_types=1);

/**
 * Role-based access control.
 *
 *   tenant      - students/tenants who search and book rooms
 *   landlord    - boarding house owners who manage listings
 *   admin       - administrators who manage the whole system
 *   super_admin - administrators who can also manage other administrators
 */
const ROLE_TENANT = 'tenant';
const ROLE_LANDLORD = 'landlord';
const ROLE_ADMIN = 'admin';
const ROLE_SUPER_ADMIN = 'super_admin';

const ALL_ROLES = [ROLE_TENANT, ROLE_LANDLORD, ROLE_ADMIN, ROLE_SUPER_ADMIN];
const ADMIN_ROLES = [ROLE_ADMIN, ROLE_SUPER_ADMIN];

function isAdminRole(?string $role): bool {
    return in_array($role, ADMIN_ROLES, true);
}

/**
 * Where each role lands after logging in.
 * Paths are relative to the project root.
 */
function homePathForRole(?string $role): string {
    return match ($role) {
        ROLE_ADMIN, ROLE_SUPER_ADMIN => 'admin/',
        ROLE_LANDLORD => 'php/dashboard.php',
        default => 'php/browse-rooms.php',
    };
}

function roleLabel(?string $role): string {
    return match ($role) {
        ROLE_SUPER_ADMIN => 'Super Admin',
        ROLE_ADMIN => 'Administrator',
        ROLE_LANDLORD => 'Landlord',
        default => 'Tenant',
    };
}
