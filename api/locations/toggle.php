<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/location_helpers.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('שיטת בקשה לא נתמכת.', 405);
}

$data = getJsonRequestBody();

$csrfToken = $data['csrf_token'] ?? null;

if (
    !is_string($csrfToken) ||
    !validateCsrfToken($csrfToken)
) {
    jsonError(
        'הבקשה אינה חוקית או שפג תוקפה.',
        419
    );
}

$locationId = isset($data['id'])
    ? (int) $data['id']
    : 0;

if ($locationId <= 0) {
    jsonError('מזהה המיקום אינו תקין.');
}

$pdo = Database::getConnection();

try {
    $location = getLocationById(
        $pdo,
        $locationId
    );

    if ($location === null) {
        jsonError('המיקום לא נמצא.', 404);
    }

    $newStatus = !(bool) $location['is_active'];

    $pdo->beginTransaction();

    setLocationActiveStatus(
        $pdo,
        $locationId,
        $newStatus
    );

    $logStatement = $pdo->prepare(
        "INSERT INTO activity_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            old_values,
            new_values,
            ip_address,
            user_agent
        ) VALUES (
            :user_id,
            :action,
            'location',
            :entity_id,
            :old_values,
            :new_values,
            :ip_address,
            :user_agent
        )"
    );

    $logStatement->execute([
        'user_id' => currentUserId(),
        'action' => $newStatus
            ? 'location_activated'
            : 'location_deactivated',
        'entity_id' => $locationId,
        'old_values' => json_encode(
            [
                'is_active' => (bool) $location['is_active'],
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ),
        'new_values' => json_encode(
            [
                'is_active' => $newStatus,
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ),
        'ip_address' => getClientIp(),
        'user_agent' => substr(
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            0,
            500
        ),
    ]);

    $pdo->commit();

    jsonSuccess(
        [
            'id' => $locationId,
            'is_active' => $newStatus,
        ],
        $newStatus
            ? 'המיקום הופעל בהצלחה.'
            : 'המיקום הושבת בהצלחה.'
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonError(
        $exception->getMessage(),
        422
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Locations Toggle API] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonError(
        'לא ניתן לשנות את מצב המיקום.',
        500
    );
}