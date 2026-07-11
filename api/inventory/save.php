<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(
        'שיטת בקשה לא נתמכת.',
        405
    );
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

$itemId = isset($data['id'])
    && $data['id'] !== ''
    && $data['id'] !== null
        ? (int) $data['id']
        : null;

if ($itemId !== null && $itemId <= 0) {
    jsonError(
        'מזהה פריט המלאי אינו תקין.'
    );
}

$pdo = Database::getConnection();

try {
    $existingItem = null;

    if ($itemId !== null) {
        $existingItem = getInventoryItemById(
            $pdo,
            $itemId
        );

        if ($existingItem === null) {
            jsonError(
                'פריט המלאי לא נמצא.',
                404
            );
        }
    }

    /*
     * מבצעים את האימות לפני תחילת ה־Transaction,
     * כדי להחזיר שגיאות קלט ברורות למשתמש.
     */
    $validatedData = validateInventoryItemData(
        $pdo,
        $data,
        $itemId
    );

    $pdo->beginTransaction();

    if ($itemId === null) {
        $itemId = createInventoryItem(
            $pdo,
            $validatedData,
            currentUserId()
        );

        $action = 'inventory_item_created';
        $message = 'פריט המלאי נוסף בהצלחה.';
    } else {
        updateInventoryItem(
            $pdo,
            $itemId,
            $validatedData,
            currentUserId()
        );

        $action = 'inventory_item_updated';
        $message = 'פריט המלאי עודכן בהצלחה.';
    }

    $savedItem = getInventoryItemById(
        $pdo,
        $itemId
    );

    if ($savedItem === null) {
        throw new RuntimeException(
            'פריט המלאי נשמר אך לא ניתן היה לטעון אותו מחדש.'
        );
    }

    $logStatement = $pdo->prepare(
        "
        INSERT INTO activity_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            old_values,
            new_values,
            ip_address,
            user_agent
        ) VALUES (
            :user_id,
            :action,
            'inventory_item',
            :entity_id,
            :old_values,
            :new_values,
            :ip_address,
            :user_agent
        )
        "
    );

    $oldValues = null;

    if ($existingItem !== null) {
        $oldValues = json_encode(
            [
                'item_code' => $existingItem['item_code'] ?? null,
                'barcode' => $existingItem['barcode'] ?? null,
                'qr_code' => $existingItem['qr_code'] ?? null,
                'name_he' => $existingItem['name_he'] ?? null,
                'name_en' => $existingItem['name_en'] ?? null,
                'category_id' => $existingItem['category_id'] ?? null,
                'location_id' => $existingItem['location_id'] ?? null,
                'supplier_id' => $existingItem['supplier_id'] ?? null,
                'quantity' => $existingItem['quantity'] ?? null,
                'minimum_quantity' => (
                    $existingItem['minimum_quantity'] ?? null
                ),
                'maximum_quantity' => (
                    $existingItem['maximum_quantity'] ?? null
                ),
                'unit' => $existingItem['unit'] ?? null,
                'item_condition' => (
                    $existingItem['item_condition'] ?? null
                ),
                'status' => $existingItem['status'] ?? null,
                'is_available' => (
                    $existingItem['is_available'] ?? null
                ),
                'is_favorite' => (
                    $existingItem['is_favorite'] ?? null
                ),
                'is_pinned' => (
                    $existingItem['is_pinned'] ?? null
                ),
                'is_active' => (
                    $existingItem['is_active'] ?? null
                ),
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
    }

    $newValues = json_encode(
        [
            'item_code' => $savedItem['item_code'] ?? null,
            'barcode' => $savedItem['barcode'] ?? null,
            'qr_code' => $savedItem['qr_code'] ?? null,
            'name_he' => $savedItem['name_he'] ?? null,
            'name_en' => $savedItem['name_en'] ?? null,
            'category_id' => $savedItem['category_id'] ?? null,
            'location_id' => $savedItem['location_id'] ?? null,
            'supplier_id' => $savedItem['supplier_id'] ?? null,
            'quantity' => $savedItem['quantity'] ?? null,
            'minimum_quantity' => (
                $savedItem['minimum_quantity'] ?? null
            ),
            'maximum_quantity' => (
                $savedItem['maximum_quantity'] ?? null
            ),
            'unit' => $savedItem['unit'] ?? null,
            'item_condition' => (
                $savedItem['item_condition'] ?? null
            ),
            'status' => $savedItem['status'] ?? null,
            'is_available' => (
                $savedItem['is_available'] ?? null
            ),
            'is_favorite' => (
                $savedItem['is_favorite'] ?? null
            ),
            'is_pinned' => (
                $savedItem['is_pinned'] ?? null
            ),
            'is_active' => (
                $savedItem['is_active'] ?? null
            ),
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    $logStatement->execute([
        'user_id' => currentUserId(),
        'action' => $action,
        'entity_id' => $itemId,
        'old_values' => $oldValues,
        'new_values' => $newValues,
        'ip_address' => getClientIp(),
        'user_agent' => substr(
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            0,
            500
        ),
    ]);

    $pdo->commit();

    jsonSuccess(
        [
            'id' => $itemId,
            'item' => $savedItem,
        ],
        $message
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonError(
        $exception->getMessage(),
        422
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Inventory Save API - Database] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    if ($exception->getCode() === '23000') {
        $databaseMessage = $exception->getMessage();

        if (
            str_contains(
                $databaseMessage,
                'uq_inventory_item_code'
            )
        ) {
            jsonError(
                'קוד הפריט כבר נמצא בשימוש.',
                409
            );
        }

        if (
            str_contains(
                $databaseMessage,
                'uq_inventory_barcode'
            )
        ) {
            jsonError(
                'ה־Barcode כבר נמצא בשימוש.',
                409
            );
        }

        if (
            str_contains(
                $databaseMessage,
                'uq_inventory_qr_code'
            )
        ) {
            jsonError(
                'ה־QR Code כבר נמצא בשימוש.',
                409
            );
        }

        jsonError(
            'אחד מהערכים הייחודיים כבר נמצא בשימוש.',
            409
        );
    }

    jsonError(
        'לא ניתן לשמור את פריט המלאי.',
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Inventory Save API] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonError(
        'לא ניתן לשמור את פריט המלאי.',
        500
    );
}