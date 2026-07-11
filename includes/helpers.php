<?php

declare(strict_types=1);

/**
 * Escapes a value for safe HTML output.
 */
function escape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Redirects the browser and stops script execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Returns the current HTTP request method.
 */
function requestMethod(): string
{
    return strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
}

/**
 * Checks whether the current request is a POST request.
 */
function isPostRequest(): bool
{
    return requestMethod() === 'POST';
}

/**
 * Checks whether the current request is a GET request.
 */
function isGetRequest(): bool
{
    return requestMethod() === 'GET';
}

/**
 * Checks whether the current request was sent with AJAX/fetch.
 */
function isAjaxRequest(): bool
{
    $requestedWith =
        $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    if (
        is_string($requestedWith) &&
        strtolower($requestedWith) === 'xmlhttprequest'
    ) {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return
        is_string($accept) &&
        str_contains(
            strtolower($accept),
            'application/json'
        );
}

/**
 * Reads and decodes a JSON request body.
 */
function getJsonRequestBody(): array
{
    $body = file_get_contents('php://input');

    if ($body === false || trim($body) === '') {
        return [];
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        return [];
    }

    return $data;
}

/**
 * Returns the client IP address.
 *
 * REMOTE_ADDR is intentionally preferred because forwarded headers
 * should only be trusted when the server is configured behind a
 * trusted proxy.
 */
function getClientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (
        !is_string($ip) ||
        filter_var($ip, FILTER_VALIDATE_IP) === false
    ) {
        return 'unknown';
    }

    return $ip;
}

/**
 * Returns the current user agent, limited to the database field size.
 */
function getUserAgent(int $maxLength = 500): string
{
    $userAgent =
        $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (!is_string($userAgent)) {
        return '';
    }

    return mb_substr(
        $userAgent,
        0,
        $maxLength
    );
}

/**
 * Returns a trimmed string value from an array.
 */
function inputString(
    array $source,
    string $key,
    string $default = ''
): string {
    $value = $source[$key] ?? $default;

    if (
        !is_string($value) &&
        !is_numeric($value)
    ) {
        return $default;
    }

    return trim((string) $value);
}

/**
 * Returns an integer value from an array.
 */
function inputInt(
    array $source,
    string $key,
    int $default = 0
): int {
    $value = $source[$key] ?? $default;

    if (
        filter_var(
            $value,
            FILTER_VALIDATE_INT
        ) === false
    ) {
        return $default;
    }

    return (int) $value;
}

/**
 * Returns a nullable positive integer from an array.
 */
function inputNullableInt(
    array $source,
    string $key
): ?int {
    if (
        !array_key_exists($key, $source) ||
        $source[$key] === null ||
        $source[$key] === ''
    ) {
        return null;
    }

    $value = filter_var(
        $source[$key],
        FILTER_VALIDATE_INT
    );

    if ($value === false) {
        return null;
    }

    return (int) $value;
}

/**
 * Returns a boolean value from an array.
 */
function inputBool(
    array $source,
    string $key,
    bool $default = false
): bool {
    if (!array_key_exists($key, $source)) {
        return $default;
    }

    $value = $source[$key];

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    if (is_string($value)) {
        return in_array(
            strtolower(trim($value)),
            [
                '1',
                'true',
                'yes',
                'on'
            ],
            true
        );
    }

    return $default;
}

/**
 * Returns null for an empty string.
 */
function nullIfEmpty(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    return $value === ''
        ? null
        : $value;
}

/**
 * Checks whether a string is a valid six-digit hexadecimal color.
 */
function isValidHexColor(string $color): bool
{
    return preg_match(
        '/^#[0-9a-fA-F]{6}$/',
        $color
    ) === 1;
}

/**
 * Creates a URL-safe random token.
 */
function generateRandomToken(
    int $bytes = 32
): string {
    return bin2hex(
        random_bytes($bytes)
    );
}

/**
 * Returns the current application timestamp.
 */
function nowDateTime(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Formats a database datetime for the Hebrew interface.
 */
function formatDateTimeHe(
    ?string $dateTime
): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return '';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return '';
    }

    return date(
        'd/m/Y H:i',
        $timestamp
    );
}

/**
 * Formats a database date for the Hebrew interface.
 */
function formatDateHe(
    ?string $date
): string {
    if ($date === null || trim($date) === '') {
        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '';
    }

    return date(
        'd/m/Y',
        $timestamp
    );
}

/**
 * Creates a short plain-text excerpt.
 */
function textExcerpt(
    ?string $text,
    int $length = 120
): string {
    $text = trim(
        strip_tags($text ?? '')
    );

    if (
        mb_strlen($text) <= $length
    ) {
        return $text;
    }

    return mb_substr(
        $text,
        0,
        $length
    ) . '…';
}

/**
 * Validates that a local return URL belongs to this application.
 */
function isSafeLocalReturnUrl(
    string $url
): bool {
    if ($url === '') {
        return false;
    }

    if (
        str_contains($url, "\r") ||
        str_contains($url, "\n")
    ) {
        return false;
    }

    $parts = parse_url($url);

    if ($parts === false) {
        return false;
    }

    if (
        isset($parts['scheme']) ||
        isset($parts['host'])
    ) {
        return false;
    }

    $path = $parts['path'] ?? '';

    return str_starts_with(
        $path,
        '/warehouse/'
    );
}