<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/category_helpers.php';

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

$categoryId = (int) ($data['id'] ?? 0);

if ($categoryId <= 0) {
    jsonError('מזהה קטגוריה אינו תקין.');
}

$category = getCategoryById($categoryId);

if ($category === null) {
    jsonError('הקטגוריה לא נמצאה.', 404);
}

$newStatus = (int) $category['is_active'] === 1 ? 0 : 1;

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        "UPDATE categories
         SET is_active = :is_active
         WHERE id = :id"
    );

    $statement->execute([
        'is_active' => $newStatus,
        'id' => $categoryId
    ]);

    $logStatement = $pdo->prepare(
        "INSERT INTO activity_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            new_values,
            ip_address,
            user_agent
        ) VALUES (
            :user_id,
            :action,
            'category',
            :entity_id,
            :new_values,
            :ip_address,
            :user_agent
        )"
    );

    $logStatement->execute([
        'user_id' => currentUserId(),
        'action' => $newStatus === 1
            ? 'category_enabled'
            : 'category_disabled',
        'entity_id' => $categoryId,
        'new_values' => json_encode(
            [
                'is_active' => $newStatus
            ],
            JSON_UNESCAPED_UNICODE
        ),
        'ip_address' => getClientIp(),
        'user_agent' => substr(
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            0,
            500
        )
    ]);

    $pdo->commit();

    jsonSuccess(
        [
            'is_active' => $newStatus
        ],
        $newStatus === 1
            ? 'הקטגוריה הופעלה.'
            : 'הקטגוריה הושבתה.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($exception->getMessage());

    jsonError(
        'לא ניתן לעדכן את מצב הקטגוריה.',
        500
    );
}