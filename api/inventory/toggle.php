<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(
        'שיטת בקשה לא נתמכת.',
        405
    );
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

$itemId = isset($data['id'])
    ? (int) $data['id']
    : 0;

if ($itemId <= 0) {
    jsonError(
        'מזהה פריט המלאי אינו תקין.'
    );
}

$pdo = Database::getConnection();

try {
    $item = getInventoryItemById(
        $pdo,
        $itemId
    );

    if ($item === null) {
        jsonError(
            'פריט המלאי לא נמצא.',
            404
        );
    }

    $currentStatus = (bool) (
        $item['is_active'] ?? false
    );

    $newStatus = !$currentStatus;

    $pdo->beginTransaction();

    setInventoryItemActiveStatus(
        $pdo,
        $itemId,
        $newStatus,
        currentUserId()
    );

    $logStatement = $pdo->prepare(
        "
        INSERT INTO activity_logs (
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
            'inventory_item',
            :entity_id,
            :old_values,
            :new_values,
            :ip_address,
            :user_agent
        )
        "
    );

    $logStatement->execute([
        'user_id' => currentUserId(),
        'action' => $newStatus
            ? 'inventory_item_activated'
            : 'inventory_item_deactivated',
        'entity_id' => $itemId,
        'old_values' => json_encode(
            [
                'is_active' => $currentStatus,
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
            'id' => $itemId,
            'is_active' => $newStatus,
        ],
        $newStatus
            ? 'פריט המלאי הופעל בהצלחה.'
            : 'פריט המלאי הושבת בהצלחה.'
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
            '[Inventory Toggle API] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonError(
        'לא ניתן לשנות את מצב פריט המלאי.',
        500
    );
}