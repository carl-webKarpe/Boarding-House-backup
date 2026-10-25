<?php

declare(strict_types=1);

/**
 * Who is logged in, for static pages (html/index.html) that cannot read the
 * session themselves. Opening the landing page never logs anyone out; this
 * lets it show "Hi, name" and the right page instead of "Login".
 *
 *   GET api/me.php   { logged_in, name, role, role_label, home }
 */

require_once __DIR__ . '/_common.php';

apiBootstrap();
requireMethod('GET');

if (!isLoggedIn()) {
    jsonResponse(['logged_in' => false]);
}

$role = (string) currentUserRole();
jsonResponse([
    'logged_in' => true,
    'name' => (string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''),
    'role' => $role,
    'role_label' => roleLabel($role),
    // Relative to the project root, e.g. "php/browse-rooms.php" or "landlord/".
    'home' => homePathForRole($role),
]);
