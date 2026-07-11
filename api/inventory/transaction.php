<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';
require_once __DIR__ . '/../../includes/inventory_transaction_helpers.php';

startSecureSession();
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'שיטת הבקשה אינה נתמכת.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $payload = json_decode(
        file_get_contents('php://input') ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($payload)) {
        throw new InvalidArgumentException(
            'תוכן הבקשה אינו תקין.'
        );
    }

    $csrfToken = (string) (
        $payload['csrf_token'] ?? ''
    );

    $csrfValid = false;

    if (function_exists('validateCsrfToken')) {
        $csrfValid = (bool) validateCsrfToken($csrfToken);
    } elseif (function_exists('verifyCsrfToken')) {
        $csrfValid = (bool) verifyCsrfToken($csrfToken);
    } elseif (
        isset($_SESSION['csrf_token']) &&
        is_string($_SESSION['csrf_token'])
    ) {
        $csrfValid = hash_equals(
            $_SESSION['csrf_token'],
            $csrfToken
        );
    }

    if (!$csrfValid) {
        http_response_code(419);

        echo json_encode([
            'success' => false,
            'message' => 'תוקף הבקשה פג. רענן את הדף ונסה שוב.',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $pdo = Database::getConnection();
    $userId = getInventoryTransactionUserId();

    $result = createInventoryTransaction(
        $pdo,
        $payload,
        $userId
    );

    if (function_exists('logActivity')) {
        logActivity(
            $pdo,
            $userId,
            'inventory_transaction_created',
            'inventory_items',
            (int) $result['item_id'],
            null,
            $result
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'תנועת המלאי נשמרה בהצלחה.',
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $exception) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'ה־JSON שנשלח אינו תקין.',
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log(
        'Inventory transaction error: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'אירעה שגיאה בעת שמירת תנועת המלאי.',
    ], JSON_UNESCAPED_UNICODE);
}
