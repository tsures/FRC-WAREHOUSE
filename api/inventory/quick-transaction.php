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
requireLogin();

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
    $payload = getJsonRequestBody();

    $csrfToken = isset($payload['csrf_token'])
        ? (string) $payload['csrf_token']
        : '';

    if (!validateCsrfToken($csrfToken)) {
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'תוקף הבקשה פג. רענן את הדף ונסה שוב.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formToken = isset($payload['form_token'])
        ? (string) $payload['form_token']
        : '';

    $sessionFormToken = $_SESSION[
        'inventory_quick_transaction_token'
    ] ?? '';

    if (
        !is_string($sessionFormToken) ||
        $sessionFormToken === '' ||
        $formToken === '' ||
        !hash_equals($sessionFormToken, $formToken)
    ) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'הטופס כבר נשלח או שפג תוקפו. רענן את הדף.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $transactionType = trim(
        (string) ($payload['transaction_type'] ?? '')
    );

    if (!in_array($transactionType, ['add', 'remove'], true)) {
        throw new InvalidArgumentException(
            'ניתן לבצע רק הוצאת ציוד או מילוי מלאי.'
        );
    }

    $itemId = (int) ($payload['item_id'] ?? 0);

    if ($itemId <= 0) {
        throw new InvalidArgumentException('יש לבחור פריט מלאי.');
    }

    $pdo = Database::getConnection();

    $itemStatement = $pdo->prepare(
        "SELECT
            id,
            item_code,
            name_he,
            quantity,
            unit,
            is_active,
            is_available
         FROM inventory_items
         WHERE id = :id
         LIMIT 1"
    );

    $itemStatement->execute(['id' => $itemId]);
    $item = $itemStatement->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new InvalidArgumentException('פריט המלאי לא נמצא.');
    }

    if ((int) $item['is_active'] !== 1) {
        throw new InvalidArgumentException(
            'לא ניתן לבצע תנועה בפריט שאינו פעיל.'
        );
    }

    if (
        $transactionType === 'remove' &&
        (int) $item['is_available'] !== 1
    ) {
        throw new InvalidArgumentException(
            'הפריט אינו מסומן כזמין להוצאה.'
        );
    }

    $result = createInventoryTransaction(
        $pdo,
        [
            'item_id' => $itemId,
            'transaction_type' => $transactionType,
            'quantity' => $payload['quantity'] ?? null,
            'reference_number' => $payload['reference_number'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ],
        currentUserId()
    );

    unset($_SESSION['inventory_quick_transaction_token']);

    $nextFormToken = bin2hex(random_bytes(32));
    $_SESSION['inventory_quick_transaction_token'] =
        $nextFormToken;

    try {
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
                'inventory_item',
                :entity_id,
                :new_values,
                :ip_address,
                :user_agent
            )"
        );

        $logStatement->execute([
            'user_id' => currentUserId(),
            'action' => $transactionType === 'remove'
                ? 'inventory_quick_remove'
                : 'inventory_quick_add',
            'entity_id' => $itemId,
            'new_values' => json_encode(
                $result,
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
    } catch (Throwable $logException) {
        error_log(
            'Quick inventory transaction log error: ' .
            $logException->getMessage()
        );
    }

    echo json_encode([
        'success' => true,
        'message' => $transactionType === 'remove'
            ? 'הציוד הוצא מהמלאי בהצלחה.'
            : 'המלאי עודכן בהצלחה.',
        'data' => [
            ...$result,
            'item_name' => (string) $item['name_he'],
            'item_code' => (string) $item['item_code'],
            'unit' => (string) $item['unit'],
            'form_token' => $nextFormToken,
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
        'Quick inventory transaction error: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'אירעה שגיאה בעת שמירת תנועת המלאי.',
    ], JSON_UNESCAPED_UNICODE);
}
