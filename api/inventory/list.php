<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError(
        'שיטת בקשה לא נתמכת.',
        405
    );
}

$search = trim((string) ($_GET['search'] ?? ''));

$categoryId = isset($_GET['category_id'])
    && $_GET['category_id'] !== ''
        ? (int) $_GET['category_id']
        : null;

$locationId = isset($_GET['location_id'])
    && $_GET['location_id'] !== ''
        ? (int) $_GET['location_id']
        : null;

$supplierId = isset($_GET['supplier_id'])
    && $_GET['supplier_id'] !== ''
        ? (int) $_GET['supplier_id']
        : null;

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$itemCondition = trim(
    (string) ($_GET['item_condition'] ?? '')
);

$active = trim(
    (string) ($_GET['active'] ?? 'all')
);

$stock = trim(
    (string) ($_GET['stock'] ?? 'all')
);

$favorite = isset($_GET['favorite'])
    && in_array(
        strtolower((string) $_GET['favorite']),
        ['1', 'true', 'yes', 'on'],
        true
    );

$pinned = isset($_GET['pinned'])
    && in_array(
        strtolower((string) $_GET['pinned']),
        ['1', 'true', 'yes', 'on'],
        true
    );

if (
    $categoryId !== null &&
    $categoryId <= 0
) {
    jsonError(
        'מזהה הקטגוריה אינו תקין.'
    );
}

if (
    $locationId !== null &&
    $locationId <= 0
) {
    jsonError(
        'מזהה המיקום אינו תקין.'
    );
}

if (
    $supplierId !== null &&
    $supplierId <= 0
) {
    jsonError(
        'מזהה הספק אינו תקין.'
    );
}

if (
    $status !== '' &&
    !isValidInventoryStatus($status)
) {
    jsonError(
        'סטטוס הפריט אינו חוקי.'
    );
}

if (
    $itemCondition !== '' &&
    !isValidInventoryCondition($itemCondition)
) {
    jsonError(
        'מצב הפריט אינו חוקי.'
    );
}

if (
    !in_array(
        $active,
        ['all', 'active', 'inactive'],
        true
    )
) {
    $active = 'all';
}

if (
    !in_array(
        $stock,
        ['all', 'normal', 'low', 'out'],
        true
    )
) {
    $stock = 'all';
}

$pdo = Database::getConnection();

try {
    $filters = [
        'search' => $search,
        'category_id' => $categoryId,
        'location_id' => $locationId,
        'supplier_id' => $supplierId,
        'status' => $status,
        'item_condition' => $itemCondition,
        'active' => $active,
        'stock' => $stock,
        'favorite' => $favorite,
        'pinned' => $pinned,
    ];

    $items = getInventoryItems(
        $pdo,
        $filters
    );

    $statistics = getInventoryStatistics($pdo);

    $categoryStatement = $pdo->prepare(
        "
        SELECT
            id,
            parent_id,
            name_he,
            name_en,
            icon,
            color,
            sort_order,
            is_active
        FROM categories
        WHERE is_active = 1
        ORDER BY
            sort_order ASC,
            name_he ASC,
            id ASC
        "
    );

    $categoryStatement->execute();

    $categories = $categoryStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($categories as &$category) {
        $category['id'] = (int) $category['id'];

        $category['parent_id'] =
            $category['parent_id'] !== null
                ? (int) $category['parent_id']
                : null;

        $category['sort_order'] = (int) (
            $category['sort_order'] ?? 0
        );

        $category['is_active'] = (bool) (
            $category['is_active'] ?? false
        );
    }

    unset($category);

    $locationStatement = $pdo->prepare(
        "
        SELECT
            id,
            parent_id,
            name,
            code,
            location_type,
            sort_order,
            is_active
        FROM locations
        WHERE is_active = 1
        ORDER BY
            sort_order ASC,
            name ASC,
            id ASC
        "
    );

    $locationStatement->execute();

    $locations = $locationStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($locations as &$location) {
        $location['id'] = (int) $location['id'];

        $location['parent_id'] =
            $location['parent_id'] !== null
                ? (int) $location['parent_id']
                : null;

        $location['sort_order'] = (int) (
            $location['sort_order'] ?? 0
        );

        $location['is_active'] = (bool) (
            $location['is_active'] ?? false
        );
    }

    unset($location);

    jsonSuccess(
        [
            'items' => $items,

            'statistics' => $statistics,

            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'location_id' => $locationId,
                'supplier_id' => $supplierId,
                'status' => $status,
                'item_condition' => $itemCondition,
                'active' => $active,
                'stock' => $stock,
                'favorite' => $favorite,
                'pinned' => $pinned,
            ],

            'options' => [
                'statuses' => getInventoryStatuses(),
                'conditions' => getInventoryConditions(),
                'units' => getInventoryUnits(),
                'categories' => $categories,
                'locations' => $locations,
            ],

            'meta' => [
                'returned_count' => count($items),
                'total_count' => (
                    $statistics['total_count'] ?? 0
                ),
            ],
        ],
        'פריטי המלאי נטענו בהצלחה.'
    );
} catch (Throwable $exception) {
    error_log(
        sprintf(
            '[Inventory List API] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonError(
        'אירעה שגיאה בטעינת פריטי המלאי.',
        500
    );
}