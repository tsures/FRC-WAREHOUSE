<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';
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
    $itemId = filter_input(
        INPUT_GET,
        'item_id',
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    $limit = filter_input(
        INPUT_GET,
        'limit',
        FILTER_VALIDATE_INT
    );

    if ($itemId === false || $itemId === null) {
        throw new InvalidArgumentException(
            'מזהה הפריט אינו תקין.'
        );
    }

    if ($limit === false || $limit === null) {
        $limit = 100;
    }

    $limit = max(1, min((int) $limit, 500));

    $pdo = Database::getConnection();

    $item = getInventoryItemById(
        $pdo,
        (int) $itemId
    );

    if ($item === null) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    $transactions = getInventoryTransactions(
        $pdo,
        (int) $itemId,
        $limit
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'item' => $item,
            'transactions' => $transactions,
            'returned_count' => count($transactions),
            'types' => getInventoryTransactionTypes(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log(
        'Inventory transactions list error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את היסטוריית התנועות.',
    ], JSON_UNESCAPED_UNICODE);
}