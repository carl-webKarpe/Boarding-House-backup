<?php

declare(strict_types=1);

// Old generic dashboard URL: send everyone to the home page for their role
// (administrators -> admin/, landlords -> landlord/, students -> browse-rooms.php).
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';

applySecurityHeaders();
requireLogin();
redirectTo(homePathForRole(currentUserRole()));
