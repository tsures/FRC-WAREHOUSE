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
    $categoryId = (int) ($_GET['category_id'] ?? 0);
    $supplierId = (int) ($_GET['supplier_id'] ?? 0);
    $shortageType = trim((string) ($_GET['shortage_type'] ?? 'all'));

    $sql = "
        SELECT
            i.*,
            c.name_he AS category_name_he,
            l.name AS location_name,
            l.code AS location_code,
            s.name AS supplier_name,
            CASE
                WHEN i.quantity <= 0 THEN 'out'
                ELSE 'low'
            END AS shortage_type,
            GREATEST(i.minimum_quantity - i.quantity, 0)
                AS shortage_to_minimum,
            CASE
                WHEN i.maximum_quantity IS NOT NULL
                AND i.maximum_quantity > i.quantity
                THEN i.maximum_quantity - i.quantity
                ELSE GREATEST(i.minimum_quantity - i.quantity, 0)
            END AS recommended_restock,
            CASE
                WHEN i.purchase_price IS NOT NULL
                THEN (
                    CASE
                        WHEN i.maximum_quantity IS NOT NULL
                        AND i.maximum_quantity > i.quantity
                        THEN i.maximum_quantity - i.quantity
                        ELSE GREATEST(i.minimum_quantity - i.quantity, 0)
                    END
                ) * i.purchase_price
                ELSE NULL
            END AS estimated_cost
        FROM inventory_items i
        LEFT JOIN categories c
            ON c.id = i.category_id
        LEFT JOIN locations l
            ON l.id = i.location_id
        LEFT JOIN suppliers s
            ON s.id = i.supplier_id
        WHERE i.is_active = 1
          AND i.minimum_quantity > 0
          AND i.quantity <= i.minimum_quantity
    ";

    $parameters = [];

    if ($search !== '') {
        $searchValue = '%' . $search . '%';

        $sql .= "
            AND (
                i.item_code LIKE :search_item_code
                OR i.name_he LIKE :search_name_he
                OR i.name_en LIKE :search_name_en
                OR i.barcode LIKE :search_barcode
                OR c.name_he LIKE :search_category
                OR l.name LIKE :search_location
                OR l.code LIKE :search_location_code
                OR s.name LIKE :search_supplier
            )
        ";

        $parameters['search_item_code'] = $searchValue;
        $parameters['search_name_he'] = $searchValue;
        $parameters['search_name_en'] = $searchValue;
        $parameters['search_barcode'] = $searchValue;
        $parameters['search_category'] = $searchValue;
        $parameters['search_location'] = $searchValue;
        $parameters['search_location_code'] = $searchValue;
        $parameters['search_supplier'] = $searchValue;
    }

    if ($locationId > 0) {
        $sql .= " AND i.location_id = :location_id";
        $parameters['location_id'] = $locationId;
    }

    if ($categoryId > 0) {
        $sql .= " AND i.category_id = :category_id";
        $parameters['category_id'] = $categoryId;
    }

    if ($supplierId > 0) {
        $sql .= " AND i.supplier_id = :supplier_id";
        $parameters['supplier_id'] = $supplierId;
    }

    if ($shortageType === 'out') {
        $sql .= " AND i.quantity <= 0";
    } elseif ($shortageType === 'low') {
        $sql .= " AND i.quantity > 0";
    }

    $sql .= "
        ORDER BY
            CASE WHEN i.quantity <= 0 THEN 0 ELSE 1 END ASC,
            shortage_to_minimum DESC,
            i.name_he ASC,
            i.id DESC
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $items = $statement->fetchAll(PDO::FETCH_ASSOC);

    $totalEstimatedCost = 0.0;
    $outCount = 0;
    $lowCount = 0;

    foreach ($items as &$item) {
        $item = prepareInventoryItemForOutput($item);

        $item['shortage_type'] = (string) $item['shortage_type'];
        $item['shortage_to_minimum'] =
            (float) $item['shortage_to_minimum'];
        $item['recommended_restock'] =
            (float) $item['recommended_restock'];
        $item['estimated_cost'] =
            $item['estimated_cost'] !== null
                ? (float) $item['estimated_cost']
                : null;

        if ($item['shortage_type'] === 'out') {
            $outCount++;
        } else {
            $lowCount++;
        }

        if ($item['estimated_cost'] !== null) {
            $totalEstimatedCost += $item['estimated_cost'];
        }
    }

    unset($item);

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $items,
            'statistics' => [
                'total_count' => count($items),
                'out_of_stock_count' => $outCount,
                'low_stock_count' => $lowCount,
                'estimated_cost' => round($totalEstimatedCost, 2),
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log(
        'Inventory shortages error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את דוח החוסרים.',
    ], JSON_UNESCAPED_UNICODE);
}
