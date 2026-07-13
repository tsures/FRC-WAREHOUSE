<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'שיטת הבקשה אינה נתמכת.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $data = getJsonRequestBody();

    if (!validateCsrfToken(
        isset($data['csrf_token'])
            ? (string) $data['csrf_token']
            : null
    )) {
        http_response_code(419);

        throw new InvalidArgumentException(
            'הבקשה אינה חוקית או שפג תוקפה.'
        );
    }

    $fullName = inputString($data, 'full_name');
    $email = nullIfEmpty(inputString($data, 'email'));
    $phone = nullIfEmpty(inputString($data, 'phone'));

    if ($fullName === '') {
        throw new InvalidArgumentException(
            'יש להזין שם מלא.'
        );
    }

    if (mb_strlen($fullName) > 150) {
        throw new InvalidArgumentException(
            'השם המלא ארוך מדי.'
        );
    }

    if (
        $email !== null &&
        filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new InvalidArgumentException(
            'כתובת הדוא״ל אינה תקינה.'
        );
    }

    if ($email !== null && mb_strlen($email) > 190) {
        throw new InvalidArgumentException(
            'כתובת הדוא״ל ארוכה מדי.'
        );
    }

    if ($phone !== null && mb_strlen($phone) > 30) {
        throw new InvalidArgumentException(
            'מספר הטלפון ארוך מדי.'
        );
    }

    $pdo = Database::getConnection();
    $userId = currentUserId();

    if ($userId === null) {
        throw new RuntimeException(
            'לא נמצא משתמש מחובר.'
        );
    }

    if ($email !== null) {
        $emailStatement = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE email = :email
               AND id <> :id
             LIMIT 1"
        );

        $emailStatement->execute([
            'email' => $email,
            'id' => $userId
        ]);

        if ($emailStatement->fetch()) {
            throw new InvalidArgumentException(
                'כתובת הדוא״ל כבר משויכת למשתמש אחר.'
            );
        }
    }

    $statement = $pdo->prepare(
        "UPDATE users
         SET
            full_name = :full_name,
            email = :email,
            phone = :phone,
            updated_by = :updated_by
         WHERE id = :id"
    );

    $statement->execute([
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'updated_by' => $userId,
        'id' => $userId
    ]);

    $_SESSION['full_name'] = $fullName;

    try {
        $logStatement = $pdo->prepare(
            "INSERT INTO activity_logs (
                user_id,
                action,
                entity_type,
                entity_id,
                ip_address,
                user_agent
            ) VALUES (
                :user_id,
                'profile_updated',
                'user',
                :entity_id,
                :ip_address,
                :user_agent
            )"
        );

        $logStatement->execute([
            'user_id' => $userId,
            'entity_id' => $userId,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent()
        ]);
    } catch (Throwable $logException) {
        error_log(
            'Profile log error: ' .
            $logException->getMessage()
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'הפרופיל עודכן בהצלחה.',
        'data' => [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log(
        'Profile update error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לעדכן את הפרופיל.'
    ], JSON_UNESCAPED_UNICODE);
}
