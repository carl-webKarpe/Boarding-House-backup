<?php

/**
 * Brevo settings for the Super Admin verification codes. Use these instead of
 * Gmail on free hosting such as InfinityFree, which blocks Gmail's SMTP ports.
 * The codes still arrive in the Super Admin's normal inbox (e.g. Gmail).
 *
 * 1. Create a free account at https://www.brevo.com (300 emails per day).
 * 2. Senders, Domains & Dedicated IPs -> Senders -> add and verify your Gmail address.
 * 3. SMTP & API -> API Keys -> Generate a new API key, and copy it.
 * 4. Copy this file to  security/mail.local.php  (ignored by Git) and fill it in.
 *
 * Check it on the laptop with:  php setup/check-mail.php you@gmail.com
 */

return [
    'provider' => 'brevo',

    // Brevo API key (starts with xkeysib-)
    'api_key' => 'your brevo api key here',

    // The sender address you verified in Brevo
    'from_email' => 'yourgmail@gmail.com',
    'from_name' => 'Boarding House Rental System',
];
