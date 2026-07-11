<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/user_helpers.php';

startSecureSession();
requireAdmin();

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

    $userId = inputInt($data, 'id', 0);

    if ($userId <= 0) {
        throw new InvalidArgumentException(
            'מזהה המשתמש אינו תקין.'
        );
    }

    $pdo = Database::getConnection();
    $actorUserId = currentUserId();

    if ($actorUserId === null) {
        throw new RuntimeException('לא נמצא משתמש מחובר.');
    }

    if (getUserById($pdo, $userId) === null) {
        throw new InvalidArgumentException(
            'המשתמש לא נמצא.'
        );
    }

    $statement = $pdo->prepare(
        "UPDATE users
         SET
            failed_login_attempts = 0,
            last_failed_login_at = NULL,
            locked_until = NULL,
            updated_by = :updated_by
         WHERE id = :id"
    );

    $statement->execute([
        'updated_by' => $actorUserId,
        'id' => $userId
    ]);

    logUserManagementAction(
        $pdo,
        $actorUserId,
        'user_unlocked',
        $userId
    );

    echo json_encode([
        'success' => true,
        'message' => 'נעילת המשתמש אופסה בהצלחה.'
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('User unlock error: ' . $exception->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לאפס את נעילת המשתמש.'
    ], JSON_UNESCAPED_UNICODE);
}
