<?php

declare(strict_types=1);

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    $token = htmlspecialchars(
        generateCsrfToken(),
        ENT_QUOTES,
        'UTF-8'
    );

    return sprintf(
        '<input type="hidden" name="csrf_token" value="%s">',
        $token
    );
}

function validateCsrfToken(?string $token): bool
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}

function requireValidCsrfToken(?string $token): void
{
    if (!validateCsrfToken($token)) {
        http_response_code(419);
        exit('בקשה לא חוקית או שפג תוקפה.');
    }
}