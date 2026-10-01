<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function validateRequired(mixed $value, string $fieldName): array {
    if (trim((string) $value) === '') {
        return ['valid' => false, 'message' => sprintf('%s is required.', $fieldName)];
    }

    return ['valid' => true, 'message' => ''];
}

function validateEmailValue(mixed $value): array {
    $email = sanitizeEmail($value);
    if ($email === '') {
        return ['valid' => false, 'message' => 'Email is required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Please enter a valid email address.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $email];
}

function validateUsernameValue(mixed $value): array {
    $username = sanitizeUsername($value);
    if ($username === '') {
        return ['valid' => false, 'message' => 'Username is required.'];
    }

    if (strlen($username) < 5 || strlen($username) > 20) {
        return ['valid' => false, 'message' => 'Username must be 5-20 characters long.'];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Username may only contain letters, numbers, and underscores.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $username];
}

function validatePasswordValue(mixed $value): array {
    $password = (string) $value;
    if ($password === '') {
        return ['valid' => false, 'message' => 'Password is required.'];
    }

    if (strlen($password) < BH_PASSWORD_MIN_LENGTH) {
        return ['valid' => false, 'message' => 'Password must be at least 8 characters long.'];
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include uppercase and lowercase letters.'];
    }

    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include at least one number.'];
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must include at least one special character.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $password];
}

function validatePhoneValue(mixed $value): array {
    $phone = sanitizeText($value);
    if ($phone === '') {
        return ['valid' => false, 'message' => 'Phone number is required.'];
    }

    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $phone)) {
        return ['valid' => false, 'message' => 'Enter a valid Philippine mobile number.'];
    }

    return ['valid' => true, 'message' => '', 'value' => $phone];
}
