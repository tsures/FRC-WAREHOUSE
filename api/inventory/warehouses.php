<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'שיטת הבקשה אינה נתמכת.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $pdo = Database::getConnection();

    $search = trim((string) ($_GET['search'] ?? ''));
    $locationId = (int) ($_GET['location_id'] ?? 0);
    $stock = trim((string) ($_GET['stock'] ?? 'all'));
    $active = trim((string) ($_GET['active'] ?? 'active'));

    $summarySql = "
        SELECT
            l.id,
            l.parent_id,
            l.name,
            l.code,
            l.location_type,
            COUNT(i.id) AS item_count,
            COALESCE(SUM(i.quantity), 0) AS total_quantity,
            SUM(
                CASE
                    WHEN i.id IS NOT NULL
                    AND i.quantity <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS out_of_stock_count,
            SUM(
                CASE
                    WHEN i.id IS NOT NULL
                    AND i.minimum_quantity > 0
                    AND i.quantity <= i.minimum_quantity
                    THEN 1
                    ELSE 0
                END
            ) AS low_stock_count
        FROM locations l
        LEFT JOIN inventory_items i
            ON i.location_id = l.id
            AND i.is_active = 1
        WHERE l.is_active = 1
        GROUP BY
            l.id,
            l.parent_id,
            l.name,
            l.code,
            l.location_type
        ORDER BY
            l.sort_order ASC,
            l.name ASC,
            l.id ASC
    ";

    $summaryStatement = $pdo->query($summarySql);
    $locations = $summaryStatement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($locations as &$location) {
        $location['id'] = (int) $location['id'];
        $location['parent_id'] = $location['parent_id'] !== null
            ? (int) $location['parent_id']
            : null;
        $location['item_count'] = (int) $location['item_count'];
        $location['total_quantity'] = (float) $location['total_quantity'];
        $location['out_of_stock_count'] =
            (int) $location['out_of_stock_count'];
        $location['low_stock_count'] =
            (int) $location['low_stock_count'];
    }

    unset($location);

    $itemsSql = "
        SELECT
            i.*,
            c.name_he AS category_name_he,
            c.name_en AS category_name_en,
            l.name AS location_name,
            l.code AS location_code,
            l.location_type
        FROM inventory_items i
        LEFT JOIN categories c
            ON c.id = i.category_id
        LEFT JOIN locations l
            ON l.id = i.location_id
        WHERE 1 = 1
    ";

    $parameters = [];

    if ($locationId > 0) {
        $itemsSql .= " AND i.location_id = :location_id";
        $parameters['location_id'] = $locationId;
    }

    if ($search !== '') {
        $searchValue = '%' . $search . '%';

        $itemsSql .= "
            AND (
                i.item_code LIKE :search_item_code
                OR i.name_he LIKE :search_name_he
                OR i.name_en LIKE :search_name_en
                OR i.barcode LIKE :search_barcode
                OR i.qr_code LIKE :search_qr
                OR c.name_he LIKE :search_category
                OR l.name LIKE :search_location
                OR l.code LIKE :search_location_code
            )
        ";

        $parameters['search_item_code'] = $searchValue;
        $parameters['search_name_he'] = $searchValue;
        $parameters['search_name_en'] = $searchValue;
        $parameters['search_barcode'] = $searchValue;
        $parameters['search_qr'] = $searchValue;
        $parameters['search_category'] = $searchValue;
        $parameters['search_location'] = $searchValue;
        $parameters['search_location_code'] = $searchValue;
    }

    if ($active === 'active') {
        $itemsSql .= " AND i.is_active = 1";
    } elseif ($active === 'inactive') {
        $itemsSql .= " AND i.is_active = 0";
    }

    if ($stock === 'low') {
        $itemsSql .= "
            AND i.minimum_quantity > 0
            AND i.quantity <= i.minimum_quantity
        ";
    } elseif ($stock === 'out') {
        $itemsSql .= " AND i.quantity <= 0";
    } elseif ($stock === 'normal') {
        $itemsSql .= "
            AND i.quantity > 0
            AND (
                i.minimum_quantity <= 0
                OR i.quantity > i.minimum_quantity
            )
        ";
    }

    $itemsSql .= "
        ORDER BY
            l.sort_order ASC,
            l.name ASC,
            i.is_pinned DESC,
            i.is_favorite DESC,
            i.name_he ASC,
            i.id DESC
    ";

    $itemsStatement = $pdo->prepare($itemsSql);
    $itemsStatement->execute($parameters);

    $items = $itemsStatement->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(
        static fn(array $item): array =>
            prepareInventoryItemForOutput($item),
        $items
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'locations' => $locations,
            'items' => $items,
            'returned_count' => count($items),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log(
        'Inventory warehouses error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את המלאי לפי מחסן.',
    ], JSON_UNESCAPED_UNICODE);
}
