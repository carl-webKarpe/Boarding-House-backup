<?php

/**
 * Router for PHP's built-in web server (used by start-server.bat).
 *
 *   php -S localhost:8000 router.php
 *
 * The built-in server ignores .htaccess files, so this script blocks the
 * same private folders that .htaccess protects on Apache.
 */

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

// Private folders: logs, ID documents, configuration, SQL files, build files.
$blocked = '#^/(storage|security|database|node_modules|\.git)(/|$)#i';
if (preg_match($blocked, $path)
    || preg_match('#^/uploads/.*\.(php|phtml|phar)$#i', $path)
    || preg_match('#(^|/)\.#', $path)
    || in_array(basename($path), ['router.php', 'package.json', 'package-lock.json', 'composer.json', 'composer.lock', 'tenant.tailwind.config.js'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Send "/" to the home page.
if ($path === '/') {
    header('Location: /html/index.html');
    return true;
}

// "/admin" and "/admin/" serve admin/index.php.
$file = __DIR__ . $path;
if (is_dir($file)) {
    if (!str_ends_with($path, '/')) {
        header('Location: ' . $path . '/');
        return true;
    }
    if (is_file($file . 'index.php')) {
        $_SERVER['SCRIPT_NAME'] = $path . 'index.php';
        $_SERVER['SCRIPT_FILENAME'] = $file . 'index.php';
        chdir($file);
        require $file . 'index.php';
        return true;
    }
}

// Let the built-in server deliver everything else normally.
return false;
