<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_transaction_helpers.php';

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
    $transactionType = trim(
        (string) ($_GET['transaction_type'] ?? '')
    );
    $itemId = (int) ($_GET['item_id'] ?? 0);
    $locationId = (int) ($_GET['location_id'] ?? 0);
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $limit = (int) ($_GET['limit'] ?? 200);

    $limit = max(1, min($limit, 500));

    $sql = "
        SELECT
            t.id,
            t.item_id,
            t.transaction_type,
            t.quantity_change,
            t.quantity_before,
            t.quantity_after,
            t.from_location_id,
            t.to_location_id,
            t.reference_number,
            t.notes,
            t.created_by,
            t.created_at,
            i.item_code,
            i.name_he AS item_name_he,
            i.unit,
            u.full_name AS created_by_name,
            fl.name AS from_location_name,
            fl.code AS from_location_code,
            tl.name AS to_location_name,
            tl.code AS to_location_code
        FROM inventory_transactions t
        INNER JOIN inventory_items i
            ON i.id = t.item_id
        LEFT JOIN users u
            ON u.id = t.created_by
        LEFT JOIN locations fl
            ON fl.id = t.from_location_id
        LEFT JOIN locations tl
            ON tl.id = t.to_location_id
        WHERE 1 = 1
    ";

    $parameters = [];

    if ($search !== '') {
        $searchValue = '%' . $search . '%';

        $sql .= "
            AND (
                i.item_code LIKE :search_item_code
                OR i.name_he LIKE :search_item_name
                OR t.reference_number LIKE :search_reference
                OR t.notes LIKE :search_notes
                OR u.full_name LIKE :search_user
            )
        ";

        $parameters['search_item_code'] = $searchValue;
        $parameters['search_item_name'] = $searchValue;
        $parameters['search_reference'] = $searchValue;
        $parameters['search_notes'] = $searchValue;
        $parameters['search_user'] = $searchValue;
    }

    if (
        $transactionType !== '' &&
        isValidInventoryTransactionType($transactionType)
    ) {
        $sql .= "
            AND t.transaction_type = :transaction_type
        ";

        $parameters['transaction_type'] = $transactionType;
    }

    if ($itemId > 0) {
        $sql .= " AND t.item_id = :item_id";
        $parameters['item_id'] = $itemId;
    }

    if ($locationId > 0) {
        $sql .= "
            AND (
                t.from_location_id = :location_from
                OR t.to_location_id = :location_to
            )
        ";

        $parameters['location_from'] = $locationId;
        $parameters['location_to'] = $locationId;
    }

    if ($dateFrom !== '') {
        $sql .= " AND t.created_at >= :date_from";
        $parameters['date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $sql .= " AND t.created_at <= :date_to";
        $parameters['date_to'] = $dateTo . ' 23:59:59';
    }

    $sql .= "
        ORDER BY
            t.created_at DESC,
            t.id DESC
        LIMIT {$limit}
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $transactions = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as &$transaction) {
        $transaction['id'] = (int) $transaction['id'];
        $transaction['item_id'] = (int) $transaction['item_id'];

        $transaction['from_location_id'] =
            $transaction['from_location_id'] !== null
                ? (int) $transaction['from_location_id']
                : null;

        $transaction['to_location_id'] =
            $transaction['to_location_id'] !== null
                ? (int) $transaction['to_location_id']
                : null;

        $transaction['created_by'] =
            $transaction['created_by'] !== null
                ? (int) $transaction['created_by']
                : null;

        $transaction['quantity_change'] =
            (float) $transaction['quantity_change'];

        $transaction['quantity_before'] =
            (float) $transaction['quantity_before'];

        $transaction['quantity_after'] =
            (float) $transaction['quantity_after'];

        $type = (string) $transaction['transaction_type'];

        $transaction['transaction_label'] =
            getInventoryTransactionTypeLabel($type);

        $transaction['transaction_icon'] =
            getInventoryTransactionTypeIcon($type);
    }

    unset($transaction);

    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'returned_count' => count($transactions),
            'types' => getInventoryTransactionTypes(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log(
        'Inventory history error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את היסטוריית תנועות המלאי.',
    ], JSON_UNESCAPED_UNICODE);
}
