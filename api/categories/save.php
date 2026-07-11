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

$categoryId = isset($data['id']) && $data['id'] !== ''
    ? (int) $data['id']
    : null;

$nameHe = trim((string) ($data['name_he'] ?? ''));
$nameEn = trim((string) ($data['name_en'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$icon = trim((string) ($data['icon'] ?? ''));
$color = trim((string) ($data['color'] ?? ''));
$sortOrder = (int) ($data['sort_order'] ?? 0);

$parentId = isset($data['parent_id']) && $data['parent_id'] !== ''
    ? (int) $data['parent_id']
    : null;

if ($nameHe === '') {
    jsonError('יש להזין שם קטגוריה בעברית.');
}

if (mb_strlen($nameHe) > 150) {
    jsonError('שם הקטגוריה בעברית ארוך מדי.');
}

if (mb_strlen($nameEn) > 150) {
    jsonError('שם הקטגוריה באנגלית ארוך מדי.');
}

if (mb_strlen($icon) > 100) {
    jsonError('ערך האייקון ארוך מדי.');
}

if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    jsonError('צבע הקטגוריה אינו תקין.');
}

if ($parentId !== null && getCategoryById($parentId) === null) {
    jsonError('קטגוריית האב שנבחרה אינה קיימת.');
}

if (
    $categoryId !== null &&
    !isValidCategoryParent($categoryId, $parentId)
) {
    jsonError(
        'לא ניתן להגדיר את הקטגוריה כקטגוריית אב של עצמה או של אחד מצאצאיה.'
    );
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    if ($categoryId === null) {
        $statement = $pdo->prepare(
            "INSERT INTO categories (
                parent_id,
                name_he,
                name_en,
                description,
                icon,
                color,
                sort_order,
                is_active,
                created_by
            ) VALUES (
                :parent_id,
                :name_he,
                :name_en,
                :description,
                :icon,
                :color,
                :sort_order,
                1,
                :created_by
            )"
        );

        $statement->execute([
            'parent_id' => $parentId,
            'name_he' => $nameHe,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'description' => $description !== '' ? $description : null,
            'icon' => $icon !== '' ? $icon : null,
            'color' => $color !== '' ? $color : null,
            'sort_order' => $sortOrder,
            'created_by' => currentUserId()
        ]);

        $categoryId = (int) $pdo->lastInsertId();
        $action = 'category_created';
        $message = 'הקטגוריה נוספה בהצלחה.';
    } else {
        $existingCategory = getCategoryById($categoryId);

        if ($existingCategory === null) {
            jsonError('הקטגוריה לא נמצאה.', 404);
        }

        $statement = $pdo->prepare(
            "UPDATE categories
             SET
                parent_id = :parent_id,
                name_he = :name_he,
                name_en = :name_en,
                description = :description,
                icon = :icon,
                color = :color,
                sort_order = :sort_order
             WHERE id = :id"
        );

        $statement->execute([
            'parent_id' => $parentId,
            'name_he' => $nameHe,
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'description' => $description !== '' ? $description : null,
            'icon' => $icon !== '' ? $icon : null,
            'color' => $color !== '' ? $color : null,
            'sort_order' => $sortOrder,
            'id' => $categoryId
        ]);

        $action = 'category_updated';
        $message = 'הקטגוריה עודכנה בהצלחה.';
    }

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
        'action' => $action,
        'entity_id' => $categoryId,
        'new_values' => json_encode(
            [
                'name_he' => $nameHe,
                'name_en' => $nameEn,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder
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
            'id' => $categoryId
        ],
        $message
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($exception->getMessage());

    jsonError(
        'לא ניתן לשמור את הקטגוריה.',
        500
    );
}