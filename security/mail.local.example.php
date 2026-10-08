<?php

/**
 * Gmail settings for the Super Admin verification codes.
 *
 * 1. Copy this file to  security/mail.local.php  (that file is ignored by Git,
 *    so your app password is never uploaded to GitHub).
 * 2. Turn on 2-Step Verification for the Gmail account, then create an
 *    App Password at https://myaccount.google.com/apppasswords
 * 3. Paste the 16-character app password below (spaces are removed automatically).
 *
 * Gmail sends only from the account you log in with, so from_email should be
 * the same address as username (or an alias added in Gmail settings).
 */

return [
    'host' => 'smtp.gmail.com',
    'port' => 587,

    'username' => 'yourgmail@gmail.com',

    // Gmail App Password (16 characters)
    'password' => 'your app password here',

    'encryption' => 'tls',

    'from_email' => 'yourgmail@gmail.com',
    'from_name' => 'Boarding House Rental System',
];
