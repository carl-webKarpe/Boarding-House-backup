<?php

declare(strict_types=1);

/**
 * @param string[] $frameSources Extra origins this page may embed in an
 *                               <iframe> (e.g. the OpenStreetMap map). None by default.
 * @param bool $geolocation       Lets the page ask for the device location (landlord map picker).
 */
function applySecurityHeaders(array $frameSources = [], bool $geolocation = false): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(' . ($geolocation ? 'self' : '') . '), microphone=(), camera=()');
    $frameSrc = $frameSources ? 'frame-src ' . implode(' ', $frameSources) . '; ' : '';
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com; img-src 'self' data: https:; connect-src 'self'; {$frameSrc}object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
}
