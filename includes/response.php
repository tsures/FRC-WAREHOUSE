<?php

declare(strict_types=1);

function jsonResponse(
    bool $success,
    mixed $data = null,
    string $message = '',
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function jsonSuccess(
    mixed $data = null,
    string $message = 'הפעולה הושלמה בהצלחה.',
    int $statusCode = 200
): never {
    jsonResponse(true, $data, $message, $statusCode);
}

function jsonError(
    string $message,
    int $statusCode = 400,
    mixed $data = null
): never {
    jsonResponse(false, $data, $message, $statusCode);
}