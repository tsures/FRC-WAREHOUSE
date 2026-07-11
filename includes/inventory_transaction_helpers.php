<?php

declare(strict_types=1);

/**
 * פונקציות עזר עבור תנועות מלאי.
 */

function getInventoryTransactionTypes(): array
{
    return [
        'initial' => 'מלאי התחלתי',
        'add' => 'הכנסת מלאי',
        'remove' => 'הוצאת מלאי',
        'transfer' => 'העברה',
        'adjustment' => 'תיקון כמות',
        'borrow' => 'השאלה',
        'return' => 'החזרה',
        'damage' => 'נזק',
        'retire' => 'הוצאה משימוש',
    ];
}

function getInventoryTransactionTypeLabel(string $type): string
{
    $types = getInventoryTransactionTypes();

    return $types[$type] ?? 'לא ידוע';
}

function getInventoryTransactionTypeIcon(string $type): string
{
    return match ($type) {
        'initial' => '🏁',
        'add' => '➕',
        'remove' => '➖',
        'transfer' => '🔄',
        'adjustment' => '🛠️',
        'borrow' => '↗️',
        'return' => '↩️',
        'damage' => '⚠️',
        'retire' => '📁',
        default => '📦',
    };
}

function isValidInventoryTransactionType(string $type): bool
{
    return array_key_exists($type, getInventoryTransactionTypes());
}

function getInventoryTransactionUserId(): ?int
{
    $candidates = [
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user_id'] ?? null,
        $_SESSION['current_user']['id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_numeric($candidate) && (int) $candidate > 0) {
            return (int) $candidate;
        }
    }

    return null;
}

function normalizeInventoryTransactionQuantity(
    int|float|string|null $value
): float {
    if ($value === null || $value === '' || !is_numeric($value)) {
        throw new InvalidArgumentException(
            'יש להזין כמות תקינה.'
        );
    }

    $quantity = round((float) $value, 3);

    if ($quantity <= 0) {
        throw new InvalidArgumentException(
            'הכמות חייבת להיות גדולה מאפס.'
        );
    }

    return $quantity;
}

function getInventoryTransactionItemForUpdate(
    PDO $pdo,
    int $itemId
): array {
    $statement = $pdo->prepare(
        "
        SELECT
            id,
            item_code,
            name_he,
            quantity,
            location_id,
            status,
            item_condition,
            is_available,
            is_active
        FROM inventory_items
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
        "
    );

    $statement->execute([
        'id' => $itemId,
    ]);

    $item = $statement->fetch(PDO::FETCH_ASSOC);

    if ($item === false) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    return $item;
}

function inventoryTransactionLocationExists(
    PDO $pdo,
    int $locationId
): bool {
    if ($locationId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "
        SELECT 1
        FROM locations
        WHERE id = :id
          AND is_active = 1
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $locationId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * מבצע תנועת מלאי ומעדכן את פריט המלאי באותה טרנזקציה.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function createInventoryTransaction(
    PDO $pdo,
    array $data,
    ?int $userId
): array {
    $itemId = (int) ($data['item_id'] ?? 0);
    $type = trim((string) ($data['transaction_type'] ?? ''));

    if ($itemId <= 0) {
        throw new InvalidArgumentException(
            'מזהה הפריט אינו תקין.'
        );
    }

    if (!isValidInventoryTransactionType($type)) {
        throw new InvalidArgumentException(
            'סוג התנועה אינו חוקי.'
        );
    }

    $referenceNumber = normalizeInventoryNullableText(
        isset($data['reference_number'])
            ? (string) $data['reference_number']
            : null
    );

    $notes = normalizeInventoryNullableText(
        isset($data['notes'])
            ? (string) $data['notes']
            : null
    );

    if (
        $referenceNumber !== null &&
        mb_strlen($referenceNumber) > 100
    ) {
        throw new InvalidArgumentException(
            'מספר האסמכתה יכול להכיל עד 100 תווים.'
        );
    }

    $pdo->beginTransaction();

    try {
        $item = getInventoryTransactionItemForUpdate(
            $pdo,
            $itemId
        );

        $quantityBefore = round(
            (float) $item['quantity'],
            3
        );

        $quantityAfter = $quantityBefore;
        $quantityChange = 0.0;

        $fromLocationId = $item['location_id'] !== null
            ? (int) $item['location_id']
            : null;

        $toLocationId = null;

        $newStatus = (string) $item['status'];
        $newCondition = (string) $item['item_condition'];
        $newIsAvailable = (int) $item['is_available'];

        switch ($type) {
            case 'initial':
                if ($quantityBefore != 0.0) {
                    throw new InvalidArgumentException(
                        'ניתן לרשום מלאי התחלתי רק כאשר הכמות הנוכחית היא אפס.'
                    );
                }

                $quantityAfter = normalizeInventoryTransactionQuantity(
                    $data['quantity'] ?? null
                );
                $quantityChange = $quantityAfter;
                break;

            case 'add':
            case 'return':
                $quantity = normalizeInventoryTransactionQuantity(
                    $data['quantity'] ?? null
                );

                $quantityChange = $quantity;
                $quantityAfter = round(
                    $quantityBefore + $quantity,
                    3
                );

                if ($type === 'return') {
                    $newStatus = 'available';
                    $newIsAvailable = 1;
                }
                break;

            case 'remove':
            case 'borrow':
            case 'damage':
                $quantity = normalizeInventoryTransactionQuantity(
                    $data['quantity'] ?? null
                );

                if ($quantity > $quantityBefore) {
                    throw new InvalidArgumentException(
                        'לא ניתן להפחית כמות גדולה מהכמות הקיימת במלאי.'
                    );
                }

                $quantityChange = -$quantity;
                $quantityAfter = round(
                    $quantityBefore - $quantity,
                    3
                );

                if ($type === 'borrow' && $quantityAfter <= 0) {
                    $newStatus = 'borrowed';
                    $newIsAvailable = 0;
                }

                if ($type === 'damage') {
                    $newCondition = $quantityAfter <= 0
                        ? 'broken'
                        : 'damaged';

                    if ($quantityAfter <= 0) {
                        $newStatus = 'broken';
                        $newIsAvailable = 0;
                    }
                }
                break;

            case 'adjustment':
                $newQuantityRaw = $data['new_quantity'] ?? null;

                if (
                    $newQuantityRaw === null ||
                    $newQuantityRaw === '' ||
                    !is_numeric($newQuantityRaw)
                ) {
                    throw new InvalidArgumentException(
                        'יש להזין כמות חדשה תקינה.'
                    );
                }

                $quantityAfter = round(
                    (float) $newQuantityRaw,
                    3
                );

                if ($quantityAfter < 0) {
                    throw new InvalidArgumentException(
                        'הכמות החדשה אינה יכולה להיות שלילית.'
                    );
                }

                $quantityChange = round(
                    $quantityAfter - $quantityBefore,
                    3
                );

                if ($quantityChange == 0.0) {
                    throw new InvalidArgumentException(
                        'הכמות החדשה זהה לכמות הקיימת.'
                    );
                }
                break;

            case 'transfer':
                $toLocationId = (int) (
                    $data['to_location_id'] ?? 0
                );

                if (
                    $toLocationId <= 0 ||
                    !inventoryTransactionLocationExists(
                        $pdo,
                        $toLocationId
                    )
                ) {
                    throw new InvalidArgumentException(
                        'יש לבחור מיקום יעד תקין.'
                    );
                }

                if (
                    $fromLocationId !== null &&
                    $toLocationId === $fromLocationId
                ) {
                    throw new InvalidArgumentException(
                        'מיקום היעד זהה למיקום הנוכחי.'
                    );
                }
                break;

            case 'retire':
                if ($quantityBefore <= 0) {
                    throw new InvalidArgumentException(
                        'אין כמות זמינה להוצאה משימוש.'
                    );
                }

                $quantityChange = -$quantityBefore;
                $quantityAfter = 0.0;
                $newStatus = 'retired';
                $newCondition = 'retired';
                $newIsAvailable = 0;
                break;
        }

        $updateSql = "
            UPDATE inventory_items
            SET
                quantity = :quantity,
                status = :status,
                item_condition = :item_condition,
                is_available = :is_available,
                updated_by = :updated_by
        ";

        $updateParameters = [
            'quantity' => $quantityAfter,
            'status' => $newStatus,
            'item_condition' => $newCondition,
            'is_available' => $newIsAvailable,
            'updated_by' => $userId,
            'id' => $itemId,
        ];

        if ($type === 'transfer') {
            $updateSql .= ",
                location_id = :location_id
            ";

            $updateParameters['location_id'] = $toLocationId;
        }

        $updateSql .= "
            WHERE id = :id
        ";

        $updateStatement = $pdo->prepare($updateSql);
        $updateStatement->execute($updateParameters);

        $insertStatement = $pdo->prepare(
            "
            INSERT INTO inventory_transactions (
                item_id,
                transaction_type,
                quantity_change,
                quantity_before,
                quantity_after,
                from_location_id,
                to_location_id,
                reference_number,
                notes,
                created_by
            ) VALUES (
                :item_id,
                :transaction_type,
                :quantity_change,
                :quantity_before,
                :quantity_after,
                :from_location_id,
                :to_location_id,
                :reference_number,
                :notes,
                :created_by
            )
            "
        );

        $insertStatement->execute([
            'item_id' => $itemId,
            'transaction_type' => $type,
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        $transactionId = (int) $pdo->lastInsertId();

        $pdo->commit();

        return [
            'transaction_id' => $transactionId,
            'item_id' => $itemId,
            'transaction_type' => $type,
            'transaction_label' =>
                getInventoryTransactionTypeLabel($type),
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function getInventoryTransactions(
    PDO $pdo,
    int $itemId,
    int $limit = 100
): array {
    if ($itemId <= 0) {
        throw new InvalidArgumentException(
            'מזהה הפריט אינו תקין.'
        );
    }

    $limit = max(1, min($limit, 500));

    $statement = $pdo->prepare(
        "
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
            u.full_name AS created_by_name,
            fl.name AS from_location_name,
            fl.code AS from_location_code,
            tl.name AS to_location_name,
            tl.code AS to_location_code
        FROM inventory_transactions t
        LEFT JOIN users u
            ON u.id = t.created_by
        LEFT JOIN locations fl
            ON fl.id = t.from_location_id
        LEFT JOIN locations tl
            ON tl.id = t.to_location_id
        WHERE t.item_id = :item_id
        ORDER BY
            t.created_at DESC,
            t.id DESC
        LIMIT {$limit}
        "
    );

    $statement->execute([
        'item_id' => $itemId,
    ]);

    $transactions = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as &$transaction) {
        $transaction['id'] = (int) $transaction['id'];
        $transaction['item_id'] = (int) $transaction['item_id'];
        $transaction['created_by'] =
            $transaction['created_by'] !== null
                ? (int) $transaction['created_by']
                : null;

        $transaction['from_location_id'] =
            $transaction['from_location_id'] !== null
                ? (int) $transaction['from_location_id']
                : null;

        $transaction['to_location_id'] =
            $transaction['to_location_id'] !== null
                ? (int) $transaction['to_location_id']
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

    return $transactions;
}
