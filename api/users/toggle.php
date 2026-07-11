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

    $user = getUserById($pdo, $userId);

    if ($user === null) {
        throw new InvalidArgumentException(
            'המשתמש לא נמצא.'
        );
    }

    $newActive = (int) $user['is_active'] !== 1;

    if ($userId === $actorUserId && !$newActive) {
        throw new InvalidArgumentException(
            'לא ניתן להשבית את המשתמש המחובר.'
        );
    }

    if (
        $user['role'] === 'admin' &&
        (int) $user['is_active'] === 1 &&
        !$newActive &&
        countActiveAdmins($pdo) <= 1
    ) {
        throw new InvalidArgumentException(
            'לא ניתן להשבית את המנהל הפעיל האחרון.'
        );
    }

    $statement = $pdo->prepare(
        "UPDATE users
         SET
            is_active = :is_active,
            updated_by = :updated_by
         WHERE id = :id"
    );

    $statement->execute([
        'is_active' => $newActive ? 1 : 0,
        'updated_by' => $actorUserId,
        'id' => $userId
    ]);

    logUserManagementAction(
        $pdo,
        $actorUserId,
        $newActive ? 'user_activated' : 'user_deactivated',
        $userId
    );

    echo json_encode([
        'success' => true,
        'message' => $newActive
            ? 'המשתמש הופעל בהצלחה.'
            : 'המשתמש הושבת בהצלחה.',
        'data' => [
            'user' => getUserById($pdo, $userId)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('User toggle error: ' . $exception->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לעדכן את מצב המשתמש.'
    ], JSON_UNESCAPED_UNICODE);
}
