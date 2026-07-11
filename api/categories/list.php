<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/category_helpers.php';

startSecureSession();
requireAdmin();

try {
    $categories = getAllCategories(true);
    $tree = buildCategoryTree($categories);
    $flat = flattenCategoryTree($tree);

    jsonSuccess([
        'categories' => $flat,
        'count' => count($flat)
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());

    jsonError(
        'לא ניתן לטעון את רשימת הקטגוריות.',
        500
    );
}