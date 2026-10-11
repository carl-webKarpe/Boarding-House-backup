<?php

declare(strict_types=1);

/**
 * Sending email, in one of two ways (chosen by 'provider' in security/mail.local.php):
 *
 *  - 'gmail' (default): PHPMailer (installed with Composer) through Gmail SMTP.
 *    Settings: copy security/mail.local.example.php to security/mail.local.php.
 *  - 'brevo': the Brevo email API over HTTPS. For free hosting such as
 *    InfinityFree, which blocks the SMTP ports Gmail needs.
 *    Settings: copy security/mail.brevo.example.php to security/mail.local.php.
 *
 * Library:  composer install   ->  security/vendor/
 */

require_once __DIR__ . '/config.php';

const BH_MAIL_CONFIG_FILE = __DIR__ . '/mail.local.php';
const BH_COMPOSER_AUTOLOAD = __DIR__ . '/vendor/autoload.php';

/** The mail settings, or null when security/mail.local.php does not exist yet. */
function mailConfig(): ?array {
    static $config = false;
    if ($config !== false) {
        return $config;
    }
    if (!is_file(BH_MAIL_CONFIG_FILE)) {
        return $config = null;
    }
    $loaded = require BH_MAIL_CONFIG_FILE;
    if (!is_array($loaded)) {
        return $config = null;
    }
    $loaded['provider'] = strtolower(trim((string) ($loaded['provider'] ?? 'gmail')));
    $loaded['password'] = str_replace(' ', '', (string) ($loaded['password'] ?? ''));
    $loaded['api_key'] = trim((string) ($loaded['api_key'] ?? ''));
    return $config = $loaded + ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'Boarding House Rental System'];
}

function mailUsesBrevo(): bool {
    return (mailConfig()['provider'] ?? '') === 'brevo';
}

/** True when email can be sent (settings filled in and PHPMailer installed). */
function mailReady(): bool {
    return mailConfig() !== null && mailProblem() === '';
}

/** Why mail is not ready, for the logs and the setup check ('' when ready). */
function mailProblem(): string {
    $config = mailConfig();
    if ($config === null) {
        return 'security/mail.local.php is missing (copy security/mail.local.example.php or security/mail.brevo.example.php).';
    }
    if (mailUsesBrevo()) {
        return match (true) {
            in_array($config['api_key'], ['', 'your brevo api key here'], true) => 'Put your Brevo API key in security/mail.local.php.',
            !filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL) => "'from_email' in security/mail.local.php must be the sender address you verified in Brevo.",
            !function_exists('curl_init') => 'The PHP curl extension is off (needed for Brevo).',
            default => '',
        };
    }
    return match (true) {
        !filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL) => 'The Gmail username in security/mail.local.php is not a valid email address.',
        in_array($config['password'], ['', 'yourapppasswordhere', 'yournewapppasswordhere'], true) => 'Put your Gmail App Password in security/mail.local.php.',
        !is_file(BH_COMPOSER_AUTOLOAD) => 'PHPMailer is not installed. Run "composer install" in the project folder.',
        default => '',
    };
}

/**
 * Sends one email. Returns ['success' => bool, 'message' => string].
 * Errors are written to storage/app.log; the visitor only gets a short message.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array {
    if (!mailReady()) {
        writeLog('Email not sent: ' . mailProblem(), 'ERROR');
        return ['success' => false, 'message' => 'Email is not set up on this server yet.'];
    }
    if (mailUsesBrevo()) {
        return sendMailBrevo($toEmail, $toName, $subject, $htmlBody, $textBody);
    }
    require_once BH_COMPOSER_AUTOLOAD;
    $config = mailConfig();

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) $config['host'];
        $mail->Port = (int) $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config['username'];
        $mail->Password = (string) $config['password'];
        $encryption = strtolower((string) $config['encryption']);
        if ($encryption === 'none' || $encryption === '') {
            // Only for a local test mail server (e.g. Mailpit); Gmail needs tls or ssl.
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Timeout = 20;                         // connecting
        $mail->getSMTPInstance()->Timelimit = 30;     // waiting for each server reply (default 300 s)
        $mail->CharSet = 'UTF-8';

        $from = filter_var($config['from_email'] ?? '', FILTER_VALIDATE_EMAIL) ? $config['from_email'] : $config['username'];
        $mail->setFrom((string) $from, (string) $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return ['success' => true, 'message' => 'Email sent.'];
    } catch (Throwable $e) {
        writeLog('Email to ' . $toEmail . ' failed: ' . ($mail->ErrorInfo ?: $e->getMessage()), 'ERROR');
        return ['success' => false, 'message' => 'The verification email could not be sent. Please try again in a moment.'];
    }
}

/** Sends one email through the Brevo API (https://developers.brevo.com/reference/sendtransacemail). */
function sendMailBrevo(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): array {
    $config = mailConfig();
    $payload = [
        'sender' => ['email' => (string) $config['from_email'], 'name' => (string) $config['from_name']],
        'to' => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
        'subject' => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $textBody,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $config['api_key']],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    // XAMPP's PHP often has no certificate list: use the one installed by Composer.
    if (is_file(BH_COMPOSER_AUTOLOAD)) {
        require_once BH_COMPOSER_AUTOLOAD;
        if (class_exists(\Composer\CaBundle\CaBundle::class)) {
            $bundle = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
            curl_setopt($ch, is_dir($bundle) ? CURLOPT_CAPATH : CURLOPT_CAINFO, $bundle);
        }
    }
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body !== false && $status >= 200 && $status < 300) {
        return ['success' => true, 'message' => 'Email sent.'];
    }
    $reason = $body === false ? 'could not reach api.brevo.com: ' . $error : "HTTP {$status}: " . substr((string) $body, 0, 300);
    writeLog('Email to ' . $toEmail . ' failed (Brevo): ' . $reason, 'ERROR');
    return ['success' => false, 'message' => 'The verification email could not be sent. Please try again in a moment.'];
}
