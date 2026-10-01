<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/audit_log.php';

applySecurityHeaders();
startSecureSession();

if (!empty($_SESSION['user_id'])) {
    auditLog('logout', 'User logged out', (int) $_SESSION['user_id']);
}
logoutUser();
header('Location: ../html/loginform.html');
exit;
