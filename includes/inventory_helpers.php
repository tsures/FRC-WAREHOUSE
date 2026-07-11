<?php

declare(strict_types=1);

/**
 * פונקציות עזר עבור מודול המלאי.
 */

/**
 * מחזיר את מצבי הפריט.
 *
 * @return array<string, string>
 */
function getInventoryConditions(): array
{
    return [
        'new' => 'חדש',
        'good' => 'תקין',
        'used' => 'משומש',
        'damaged' => 'פגום',
        'broken' => 'שבור',
        'retired' => 'הוצא משימוש',
    ];
}

/**
 * מחזיר את סטטוסי הפריט.
 *
 * @return array<string, string>
 */
function getInventoryStatuses(): array
{
    return [
        'available' => 'זמין',
        'borrowed' => 'מושאל',
        'broken' => 'שבור',
        'retired' => 'הוצא משימוש',
        'missing' => 'חסר',
    ];
}

/**
 * מחזיר יחידות מידה נפוצות.
 *
 * @return array<int, string>
 */
function getInventoryUnits(): array
{
    return [
        'יחידה',
        'סט',
        'קופסה',
        'חבילה',
        'מטר',
        'סנטימטר',
        'מ״מ',
        'מ״ר',
        'ק״ג',
        'גרם',
        'ליטר',
        'מ״ל',
        'גליל',
        'לוח',
        'גיליון',
        'זוג',
    ];
}

/**
 * בודק אם מצב הפריט חוקי.
 */
function isValidInventoryCondition(string $condition): bool
{
    return array_key_exists(
        $condition,
        getInventoryConditions()
    );
}

/**
 * בודק אם סטטוס הפריט חוקי.
 */
function isValidInventoryStatus(string $status): bool
{
    return array_key_exists(
        $status,
        getInventoryStatuses()
    );
}

/**
 * מחזיר תווית מצב בעברית.
 */
function getInventoryConditionLabel(string $condition): string
{
    $conditions = getInventoryConditions();

    return $conditions[$condition] ?? 'לא ידוע';
}

/**
 * מחזיר תווית סטטוס בעברית.
 */
function getInventoryStatusLabel(string $status): string
{
    $statuses = getInventoryStatuses();

    return $statuses[$status] ?? 'לא ידוע';
}

/**
 * מחזיר אייקון לפי מצב הפריט.
 */
function getInventoryConditionIcon(string $condition): string
{
    return match ($condition) {
        'new' => '✨',
        'good' => '✅',
        'used' => '♻️',
        'damaged' => '⚠️',
        'broken' => '🛑',
        'retired' => '📁',
        default => '📦',
    };
}

/**
 * מחזיר אייקון לפי סטטוס הפריט.
 */
function getInventoryStatusIcon(string $status): string
{
    return match ($status) {
        'available' => '✅',
        'borrowed' => '↗️',
        'broken' => '🛑',
        'retired' => '📁',
        'missing' => '❓',
        default => '📦',
    };
}

/**
 * מנרמל קוד פריט.
 */
function normalizeInventoryItemCode(string $itemCode): string
{
    $itemCode = trim($itemCode);
    $itemCode = strtoupper($itemCode);
    $itemCode = preg_replace('/\s+/u', '-', $itemCode) ?? '';
    $itemCode = preg_replace('/[^A-Z0-9\-_]/', '', $itemCode) ?? '';
    $itemCode = preg_replace('/-+/', '-', $itemCode) ?? '';
    $itemCode = trim($itemCode, '-_');

    return $itemCode;
}

/**
 * מנרמל Barcode.
 */
function normalizeInventoryBarcode(?string $barcode): ?string
{
    if ($barcode === null) {
        return null;
    }

    $barcode = trim($barcode);

    return $barcode !== '' ? $barcode : null;
}

/**
 * מנרמל QR Code.
 */
function normalizeInventoryQrCode(?string $qrCode): ?string
{
    if ($qrCode === null) {
        return null;
    }

    $qrCode = trim($qrCode);

    return $qrCode !== '' ? $qrCode : null;
}

/**
 * מנרמל טקסט אופציונלי.
 */
function normalizeInventoryNullableText(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    return $value !== '' ? $value : null;
}

/**
 * מנרמל כמות לשלוש ספרות אחרי הנקודה.
 */
function normalizeInventoryQuantity(
    int|float|string|null $value,
    float $default = 0.0
): float {
    if ($value === null || $value === '') {
        return round($default, 3);
    }

    if (!is_numeric($value)) {
        throw new InvalidArgumentException(
            'הכמות שהוזנה אינה תקינה.'
        );
    }

    return round((float) $value, 3);
}

/**
 * מנרמל מחיר לשתי ספרות אחרי הנקודה.
 */
function normalizeInventoryPrice(
    int|float|string|null $value
): ?float {
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        throw new InvalidArgumentException(
            'מחיר הרכישה שהוזן אינו תקין.'
        );
    }

    return round((float) $value, 2);
}

/**
 * בודק תאריך בפורמט YYYY-MM-DD.
 */
function isValidInventoryDate(?string $date): bool
{
    if ($date === null || $date === '') {
        return true;
    }

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return $dateObject !== false
        && $dateObject->format('Y-m-d') === $date;
}

/**
 * מחזיר פריט מלאי לפי מזהה.
 *
 * @return array<string, mixed>|null
 */
function getInventoryItemById(
    PDO $pdo,
    int $itemId
): ?array {
    if ($itemId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        "
        SELECT
            i.*,
            c.name_he AS category_name_he,
            c.name_en AS category_name_en,
            l.name AS location_name,
            l.code AS location_code,
            l.location_type,
            creator.full_name AS created_by_name,
            updater.full_name AS updated_by_name
        FROM inventory_items i
        LEFT JOIN categories c
            ON c.id = i.category_id
        LEFT JOIN locations l
            ON l.id = i.location_id
        LEFT JOIN users creator
            ON creator.id = i.created_by
        LEFT JOIN users updater
            ON updater.id = i.updated_by
        WHERE i.id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $itemId,
    ]);

    $item = $statement->fetch(PDO::FETCH_ASSOC);

    if ($item === false) {
        return null;
    }

    return prepareInventoryItemForOutput($item);
}

/**
 * מחזיר פריט לפי קוד פריט.
 *
 * @return array<string, mixed>|null
 */
function getInventoryItemByCode(
    PDO $pdo,
    string $itemCode
): ?array {
    $itemCode = normalizeInventoryItemCode($itemCode);

    if ($itemCode === '') {
        return null;
    }

    $statement = $pdo->prepare(
        "
        SELECT id
        FROM inventory_items
        WHERE item_code = :item_code
        LIMIT 1
        "
    );

    $statement->execute([
        'item_code' => $itemCode,
    ]);

    $itemId = $statement->fetchColumn();

    if ($itemId === false) {
        return null;
    }

    return getInventoryItemById(
        $pdo,
        (int) $itemId
    );
}

/**
 * מחזיר פריט לפי Barcode.
 *
 * @return array<string, mixed>|null
 */
function getInventoryItemByBarcode(
    PDO $pdo,
    string $barcode
): ?array {
    $barcode = trim($barcode);

    if ($barcode === '') {
        return null;
    }

    $statement = $pdo->prepare(
        "
        SELECT id
        FROM inventory_items
        WHERE barcode = :barcode
        LIMIT 1
        "
    );

    $statement->execute([
        'barcode' => $barcode,
    ]);

    $itemId = $statement->fetchColumn();

    if ($itemId === false) {
        return null;
    }

    return getInventoryItemById(
        $pdo,
        (int) $itemId
    );
}

/**
 * מחזיר פריט לפי QR Code.
 *
 * @return array<string, mixed>|null
 */
function getInventoryItemByQrCode(
    PDO $pdo,
    string $qrCode
): ?array {
    $qrCode = trim($qrCode);

    if ($qrCode === '') {
        return null;
    }

    $statement = $pdo->prepare(
        "
        SELECT id
        FROM inventory_items
        WHERE qr_code = :qr_code
        LIMIT 1
        "
    );

    $statement->execute([
        'qr_code' => $qrCode,
    ]);

    $itemId = $statement->fetchColumn();

    if ($itemId === false) {
        return null;
    }

    return getInventoryItemById(
        $pdo,
        (int) $itemId
    );
}

/**
 * בודק אם פריט קיים.
 */
function inventoryItemExists(
    PDO $pdo,
    int $itemId
): bool {
    if ($itemId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "
        SELECT 1
        FROM inventory_items
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $itemId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם קטגוריה קיימת.
 */
function inventoryCategoryExists(
    PDO $pdo,
    int $categoryId
): bool {
    if ($categoryId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "
        SELECT 1
        FROM categories
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $categoryId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם מיקום קיים.
 */
function inventoryLocationExists(
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
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $locationId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם ספק קיים.
 */
function inventorySupplierExists(
    PDO $pdo,
    int $supplierId
): bool {
    if ($supplierId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        "
        SELECT 1
        FROM suppliers
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        'id' => $supplierId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם קוד פריט כבר קיים.
 */
function inventoryItemCodeExists(
    PDO $pdo,
    string $itemCode,
    ?int $excludeItemId = null
): bool {
    $itemCode = normalizeInventoryItemCode($itemCode);

    if ($itemCode === '') {
        return false;
    }

    $sql = "
        SELECT 1
        FROM inventory_items
        WHERE item_code = :item_code
    ";

    $parameters = [
        'item_code' => $itemCode,
    ];

    if ($excludeItemId !== null && $excludeItemId > 0) {
        $sql .= " AND id <> :exclude_id";
        $parameters['exclude_id'] = $excludeItemId;
    }

    $sql .= " LIMIT 1";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם Barcode כבר קיים.
 */
function inventoryBarcodeExists(
    PDO $pdo,
    string $barcode,
    ?int $excludeItemId = null
): bool {
    $barcode = trim($barcode);

    if ($barcode === '') {
        return false;
    }

    $sql = "
        SELECT 1
        FROM inventory_items
        WHERE barcode = :barcode
    ";

    $parameters = [
        'barcode' => $barcode,
    ];

    if ($excludeItemId !== null && $excludeItemId > 0) {
        $sql .= " AND id <> :exclude_id";
        $parameters['exclude_id'] = $excludeItemId;
    }

    $sql .= " LIMIT 1";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם QR Code כבר קיים.
 */
function inventoryQrCodeExists(
    PDO $pdo,
    string $qrCode,
    ?int $excludeItemId = null
): bool {
    $qrCode = trim($qrCode);

    if ($qrCode === '') {
        return false;
    }

    $sql = "
        SELECT 1
        FROM inventory_items
        WHERE qr_code = :qr_code
    ";

    $parameters = [
        'qr_code' => $qrCode,
    ];

    if ($excludeItemId !== null && $excludeItemId > 0) {
        $sql .= " AND id <> :exclude_id";
        $parameters['exclude_id'] = $excludeItemId;
    }

    $sql .= " LIMIT 1";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם הפריט נמצא במלאי נמוך.
 *
 * מלאי נמוך מתקיים כאשר:
 * הכמות הנוכחית קטנה או שווה לכמות המינימום.
 */
function isInventoryLowStock(
    float $quantity,
    float $minimumQuantity
): bool {
    return $minimumQuantity > 0
        && $quantity <= $minimumQuantity;
}

/**
 * בודק אם הפריט אזל מהמלאי.
 */
function isInventoryOutOfStock(float $quantity): bool
{
    return $quantity <= 0;
}

/**
 * מחשב כמה חסר עד לכמות המינימום.
 */
function calculateInventoryShortage(
    float $quantity,
    float $minimumQuantity
): float {
    if ($minimumQuantity <= $quantity) {
        return 0.0;
    }

    return round(
        $minimumQuantity - $quantity,
        3
    );
}

/**
 * מחשב כמה חסר עד לכמות המקסימום.
 */
function calculateInventoryRestockToMaximum(
    float $quantity,
    ?float $maximumQuantity
): float {
    if (
        $maximumQuantity === null ||
        $maximumQuantity <= $quantity
    ) {
        return 0.0;
    }

    return round(
        $maximumQuantity - $quantity,
        3
    );
}

/**
 * מחזיר מצב מלאי מחושב.
 *
 * @return array<string, mixed>
 */
function getInventoryStockState(
    float $quantity,
    float $minimumQuantity,
    ?float $maximumQuantity
): array {
    if ($quantity <= 0) {
        return [
            'key' => 'out_of_stock',
            'label' => 'אזל מהמלאי',
            'icon' => '⛔',
            'is_low_stock' => true,
            'is_out_of_stock' => true,
            'shortage' => calculateInventoryShortage(
                $quantity,
                $minimumQuantity
            ),
        ];
    }

    if (
        $minimumQuantity > 0 &&
        $quantity <= $minimumQuantity
    ) {
        return [
            'key' => 'low_stock',
            'label' => 'מלאי נמוך',
            'icon' => '⚠️',
            'is_low_stock' => true,
            'is_out_of_stock' => false,
            'shortage' => calculateInventoryShortage(
                $quantity,
                $minimumQuantity
            ),
        ];
    }

    if (
        $maximumQuantity !== null &&
        $maximumQuantity > 0 &&
        $quantity > $maximumQuantity
    ) {
        return [
            'key' => 'overstock',
            'label' => 'מעל מלאי מקסימום',
            'icon' => '📈',
            'is_low_stock' => false,
            'is_out_of_stock' => false,
            'shortage' => 0.0,
        ];
    }

    return [
        'key' => 'normal',
        'label' => 'מלאי תקין',
        'icon' => '✅',
        'is_low_stock' => false,
        'is_out_of_stock' => false,
        'shortage' => 0.0,
    ];
}

/**
 * מעבד ערכי פריט לפני החזרה ל־API.
 *
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function prepareInventoryItemForOutput(array $item): array
{
    $integerFields = [
        'id',
        'category_id',
        'location_id',
        'supplier_id',
        'created_by',
        'updated_by',
    ];

    foreach ($integerFields as $field) {
        if (array_key_exists($field, $item)) {
            $item[$field] = $item[$field] !== null
                ? (int) $item[$field]
                : null;
        }
    }

    $booleanFields = [
        'is_available',
        'is_favorite',
        'is_pinned',
        'is_active',
    ];

    foreach ($booleanFields as $field) {
        if (array_key_exists($field, $item)) {
            $item[$field] = (bool) $item[$field];
        }
    }

    $quantity = isset($item['quantity'])
        ? (float) $item['quantity']
        : 0.0;

    $minimumQuantity = isset($item['minimum_quantity'])
        ? (float) $item['minimum_quantity']
        : 0.0;

    $maximumQuantity = (
        isset($item['maximum_quantity']) &&
        $item['maximum_quantity'] !== null
    )
        ? (float) $item['maximum_quantity']
        : null;

    $item['quantity'] = $quantity;
    $item['minimum_quantity'] = $minimumQuantity;
    $item['maximum_quantity'] = $maximumQuantity;

    $item['purchase_price'] = (
        isset($item['purchase_price']) &&
        $item['purchase_price'] !== null
    )
        ? (float) $item['purchase_price']
        : null;

    $condition = (string) (
        $item['item_condition'] ?? 'good'
    );

    $status = (string) (
        $item['status'] ?? 'available'
    );

    $item['condition_label'] =
        getInventoryConditionLabel($condition);

    $item['condition_icon'] =
        getInventoryConditionIcon($condition);

    $item['status_label'] =
        getInventoryStatusLabel($status);

    $item['status_icon'] =
        getInventoryStatusIcon($status);

    $item['stock_state'] = getInventoryStockState(
        $quantity,
        $minimumQuantity,
        $maximumQuantity
    );

    $item['restock_to_maximum'] =
        calculateInventoryRestockToMaximum(
            $quantity,
            $maximumQuantity
        );

    return $item;
}

/**
 * מחזיר רשימת פריטי מלאי עם חיפוש וסינון.
 *
 * פילטרים נתמכים:
 * search
 * category_id
 * location_id
 * supplier_id
 * status
 * item_condition
 * active
 * stock
 * favorite
 * pinned
 *
 * @param array<string, mixed> $filters
 * @return array<int, array<string, mixed>>
 */
function getInventoryItems(
    PDO $pdo,
    array $filters = []
): array {
    $sql = "
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

$search = trim((string) ($filters['search'] ?? ''));

if ($search !== '') {
    $searchValue = '%' . $search . '%';

    $sql .= "
        AND (
            i.item_code LIKE :search_item_code
            OR i.barcode LIKE :search_barcode
            OR i.qr_code LIKE :search_qr_code
            OR i.name_he LIKE :search_name_he
            OR i.name_en LIKE :search_name_en
            OR i.description LIKE :search_description
            OR i.manufacturer LIKE :search_manufacturer
            OR i.model LIKE :search_model
            OR i.notes LIKE :search_notes
            OR i.keywords LIKE :search_keywords
            OR c.name_he LIKE :search_category_he
            OR c.name_en LIKE :search_category_en
            OR l.name LIKE :search_location_name
            OR l.code LIKE :search_location_code
        )
    ";

    $parameters['search_item_code'] = $searchValue;
    $parameters['search_barcode'] = $searchValue;
    $parameters['search_qr_code'] = $searchValue;
    $parameters['search_name_he'] = $searchValue;
    $parameters['search_name_en'] = $searchValue;
    $parameters['search_description'] = $searchValue;
    $parameters['search_manufacturer'] = $searchValue;
    $parameters['search_model'] = $searchValue;
    $parameters['search_notes'] = $searchValue;
    $parameters['search_keywords'] = $searchValue;
    $parameters['search_category_he'] = $searchValue;
    $parameters['search_category_en'] = $searchValue;
    $parameters['search_location_name'] = $searchValue;
    $parameters['search_location_code'] = $searchValue;
}

    $numericFilters = [
        'category_id' => 'i.category_id',
        'location_id' => 'i.location_id',
        'supplier_id' => 'i.supplier_id',
    ];

    foreach ($numericFilters as $key => $column) {
        $value = isset($filters[$key])
            ? (int) $filters[$key]
            : 0;

        if ($value > 0) {
            $sql .= " AND {$column} = :{$key}";
            $parameters[$key] = $value;
        }
    }

    $status = trim((string) ($filters['status'] ?? ''));

    if ($status !== '' && isValidInventoryStatus($status)) {
        $sql .= " AND i.status = :status";
        $parameters['status'] = $status;
    }

    $condition = trim(
        (string) ($filters['item_condition'] ?? '')
    );

    if (
        $condition !== '' &&
        isValidInventoryCondition($condition)
    ) {
        $sql .= " AND i.item_condition = :item_condition";
        $parameters['item_condition'] = $condition;
    }

    $active = (string) ($filters['active'] ?? 'all');

    if ($active === 'active') {
        $sql .= " AND i.is_active = 1";
    } elseif ($active === 'inactive') {
        $sql .= " AND i.is_active = 0";
    }

    $stock = (string) ($filters['stock'] ?? 'all');

    if ($stock === 'low') {
        $sql .= "
            AND i.minimum_quantity > 0
            AND i.quantity <= i.minimum_quantity
        ";
    } elseif ($stock === 'out') {
        $sql .= " AND i.quantity <= 0";
    } elseif ($stock === 'normal') {
        $sql .= "
            AND i.quantity > 0
            AND (
                i.minimum_quantity <= 0
                OR i.quantity > i.minimum_quantity
            )
        ";
    }

    if (!empty($filters['favorite'])) {
        $sql .= " AND i.is_favorite = 1";
    }

    if (!empty($filters['pinned'])) {
        $sql .= " AND i.is_pinned = 1";
    }

    $sql .= "
        ORDER BY
            i.is_pinned DESC,
            i.is_favorite DESC,
            i.name_he ASC,
            i.id DESC
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $items = $statement->fetchAll(PDO::FETCH_ASSOC);

    return array_map(
        static fn(array $item): array =>
            prepareInventoryItemForOutput($item),
        $items
    );
}

/**
 * מחזיר סטטיסטיקות מלאי.
 *
 * @return array<string, int|float>
 */
function getInventoryStatistics(PDO $pdo): array
{
    $statement = $pdo->query(
        "
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)
                AS active_count,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)
                AS inactive_count,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END)
                AS available_count,
            SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END)
                AS borrowed_count,
            SUM(CASE WHEN status = 'broken' THEN 1 ELSE 0 END)
                AS broken_count,
            SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END)
                AS missing_count,
            SUM(CASE WHEN status = 'retired' THEN 1 ELSE 0 END)
                AS retired_count,
            SUM(
                CASE
                    WHEN minimum_quantity > 0
                    AND quantity <= minimum_quantity
                    THEN 1
                    ELSE 0
                END
            ) AS low_stock_count,
            SUM(
                CASE
                    WHEN quantity <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS out_of_stock_count,
            SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END)
                AS favorite_count,
            SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END)
                AS pinned_count,
            COALESCE(SUM(quantity), 0) AS total_quantity
        FROM inventory_items
        "
    );

    $statistics = $statement->fetch(PDO::FETCH_ASSOC);

    if ($statistics === false) {
        return [
            'total_count' => 0,
            'active_count' => 0,
            'inactive_count' => 0,
            'available_count' => 0,
            'borrowed_count' => 0,
            'broken_count' => 0,
            'missing_count' => 0,
            'retired_count' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'favorite_count' => 0,
            'pinned_count' => 0,
            'total_quantity' => 0.0,
        ];
    }

    foreach ($statistics as $key => $value) {
        if ($key === 'total_quantity') {
            $statistics[$key] = (float) ($value ?? 0);
        } else {
            $statistics[$key] = (int) ($value ?? 0);
        }
    }

    return $statistics;
}

/**
 * מאמת את נתוני הפריט.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function validateInventoryItemData(
    PDO $pdo,
    array $data,
    ?int $itemId = null
): array {
    $itemCode = normalizeInventoryItemCode(
        (string) ($data['item_code'] ?? '')
    );

    $nameHe = trim(
        (string) ($data['name_he'] ?? '')
    );

    $nameEn = normalizeInventoryNullableText(
        isset($data['name_en'])
            ? (string) $data['name_en']
            : null
    );

    $barcode = normalizeInventoryBarcode(
        isset($data['barcode'])
            ? (string) $data['barcode']
            : null
    );

    $qrCode = normalizeInventoryQrCode(
        isset($data['qr_code'])
            ? (string) $data['qr_code']
            : null
    );

    if ($itemCode === '') {
        throw new InvalidArgumentException(
            'יש להזין קוד פריט.'
        );
    }

    if (strlen($itemCode) > 80) {
        throw new InvalidArgumentException(
            'קוד הפריט יכול להכיל עד 80 תווים.'
        );
    }

    if ($nameHe === '') {
        throw new InvalidArgumentException(
            'יש להזין שם פריט בעברית.'
        );
    }

    if (mb_strlen($nameHe) > 190) {
        throw new InvalidArgumentException(
            'שם הפריט בעברית יכול להכיל עד 190 תווים.'
        );
    }

    if (
        $nameEn !== null &&
        mb_strlen($nameEn) > 190
    ) {
        throw new InvalidArgumentException(
            'שם הפריט באנגלית יכול להכיל עד 190 תווים.'
        );
    }

    if (
        $barcode !== null &&
        mb_strlen($barcode) > 100
    ) {
        throw new InvalidArgumentException(
            'ה־Barcode יכול להכיל עד 100 תווים.'
        );
    }

    if (
        $qrCode !== null &&
        mb_strlen($qrCode) > 190
    ) {
        throw new InvalidArgumentException(
            'ה־QR Code יכול להכיל עד 190 תווים.'
        );
    }

    if (
        inventoryItemCodeExists(
            $pdo,
            $itemCode,
            $itemId
        )
    ) {
        throw new InvalidArgumentException(
            'קוד הפריט כבר נמצא בשימוש.'
        );
    }

    if (
        $barcode !== null &&
        inventoryBarcodeExists(
            $pdo,
            $barcode,
            $itemId
        )
    ) {
        throw new InvalidArgumentException(
            'ה־Barcode כבר נמצא בשימוש.'
        );
    }

    if (
        $qrCode !== null &&
        inventoryQrCodeExists(
            $pdo,
            $qrCode,
            $itemId
        )
    ) {
        throw new InvalidArgumentException(
            'ה־QR Code כבר נמצא בשימוש.'
        );
    }

    $categoryId = isset($data['category_id'])
        && $data['category_id'] !== ''
        && $data['category_id'] !== null
            ? (int) $data['category_id']
            : null;

    $locationId = isset($data['location_id'])
        && $data['location_id'] !== ''
        && $data['location_id'] !== null
            ? (int) $data['location_id']
            : null;

    $supplierId = isset($data['supplier_id'])
        && $data['supplier_id'] !== ''
        && $data['supplier_id'] !== null
            ? (int) $data['supplier_id']
            : null;

    if (
        $categoryId !== null &&
        !inventoryCategoryExists($pdo, $categoryId)
    ) {
        throw new InvalidArgumentException(
            'הקטגוריה שנבחרה אינה קיימת.'
        );
    }

    if (
        $locationId !== null &&
        !inventoryLocationExists($pdo, $locationId)
    ) {
        throw new InvalidArgumentException(
            'המיקום שנבחר אינו קיים.'
        );
    }

    if (
        $supplierId !== null &&
        !inventorySupplierExists($pdo, $supplierId)
    ) {
        throw new InvalidArgumentException(
            'הספק שנבחר אינו קיים.'
        );
    }

    $quantity = normalizeInventoryQuantity(
        $data['quantity'] ?? 0
    );

    $minimumQuantity = normalizeInventoryQuantity(
        $data['minimum_quantity'] ?? 0
    );

    $maximumQuantity = (
        !isset($data['maximum_quantity']) ||
        $data['maximum_quantity'] === '' ||
        $data['maximum_quantity'] === null
    )
        ? null
        : normalizeInventoryQuantity(
            $data['maximum_quantity']
        );

    if ($quantity < 0) {
        throw new InvalidArgumentException(
            'הכמות אינה יכולה להיות שלילית.'
        );
    }

    if ($minimumQuantity < 0) {
        throw new InvalidArgumentException(
            'כמות המינימום אינה יכולה להיות שלילית.'
        );
    }

    if (
        $maximumQuantity !== null &&
        $maximumQuantity < 0
    ) {
        throw new InvalidArgumentException(
            'כמות המקסימום אינה יכולה להיות שלילית.'
        );
    }

    if (
        $maximumQuantity !== null &&
        $maximumQuantity < $minimumQuantity
    ) {
        throw new InvalidArgumentException(
            'כמות המקסימום אינה יכולה להיות קטנה מכמות המינימום.'
        );
    }

    $condition = trim(
        (string) ($data['item_condition'] ?? 'good')
    );

    $status = trim(
        (string) ($data['status'] ?? 'available')
    );

    if (!isValidInventoryCondition($condition)) {
        throw new InvalidArgumentException(
            'מצב הפריט אינו חוקי.'
        );
    }

    if (!isValidInventoryStatus($status)) {
        throw new InvalidArgumentException(
            'סטטוס הפריט אינו חוקי.'
        );
    }

    $unit = trim(
        (string) ($data['unit'] ?? 'יחידה')
    );

    if ($unit === '') {
        $unit = 'יחידה';
    }

    if (mb_strlen($unit) > 50) {
        throw new InvalidArgumentException(
            'יחידת המידה יכולה להכיל עד 50 תווים.'
        );
    }

    $purchaseDate = normalizeInventoryNullableText(
        isset($data['purchase_date'])
            ? (string) $data['purchase_date']
            : null
    );

    if (!isValidInventoryDate($purchaseDate)) {
        throw new InvalidArgumentException(
            'תאריך הרכישה אינו תקין.'
        );
    }

    return [
        'item_code' => $itemCode,
        'barcode' => $barcode,
        'qr_code' => $qrCode,
        'name_he' => $nameHe,
        'name_en' => $nameEn,
        'description' => normalizeInventoryNullableText(
            isset($data['description'])
                ? (string) $data['description']
                : null
        ),
        'category_id' => $categoryId,
        'location_id' => $locationId,
        'supplier_id' => $supplierId,
        'manufacturer' => normalizeInventoryNullableText(
            isset($data['manufacturer'])
                ? (string) $data['manufacturer']
                : null
        ),
        'model' => normalizeInventoryNullableText(
            isset($data['model'])
                ? (string) $data['model']
                : null
        ),
        'shelf' => normalizeInventoryNullableText(
            isset($data['shelf'])
                ? (string) $data['shelf']
                : null
        ),
        'bin' => normalizeInventoryNullableText(
            isset($data['bin'])
                ? (string) $data['bin']
                : null
        ),
        'quantity' => $quantity,
        'minimum_quantity' => $minimumQuantity,
        'maximum_quantity' => $maximumQuantity,
        'unit' => $unit,
        'purchase_date' => $purchaseDate,
        'purchase_price' => normalizeInventoryPrice(
            $data['purchase_price'] ?? null
        ),
        'item_condition' => $condition,
        'status' => $status,
        'is_available' => !empty(
            $data['is_available'] ?? true
        ),
        'is_favorite' => !empty(
            $data['is_favorite'] ?? false
        ),
        'is_pinned' => !empty(
            $data['is_pinned'] ?? false
        ),
        'is_active' => !isset($data['is_active'])
            || !empty($data['is_active']),
        'notes' => normalizeInventoryNullableText(
            isset($data['notes'])
                ? (string) $data['notes']
                : null
        ),
        'keywords' => normalizeInventoryNullableText(
            isset($data['keywords'])
                ? (string) $data['keywords']
                : null
        ),
    ];
}

/**
 * יוצר פריט מלאי חדש.
 *
 * @param array<string, mixed> $data
 */
function createInventoryItem(
    PDO $pdo,
    array $data,
    ?int $userId
): int {
    $validated = $data;

    $statement = $pdo->prepare(
        "
        INSERT INTO inventory_items (
            item_code,
            barcode,
            qr_code,
            name_he,
            name_en,
            description,
            category_id,
            location_id,
            supplier_id,
            manufacturer,
            model,
            shelf,
            bin,
            quantity,
            minimum_quantity,
            maximum_quantity,
            unit,
            purchase_date,
            purchase_price,
            item_condition,
            status,
            is_available,
            is_favorite,
            is_pinned,
            is_active,
            notes,
            keywords,
            created_by,
            updated_by
        ) VALUES (
            :item_code,
            :barcode,
            :qr_code,
            :name_he,
            :name_en,
            :description,
            :category_id,
            :location_id,
            :supplier_id,
            :manufacturer,
            :model,
            :shelf,
            :bin,
            :quantity,
            :minimum_quantity,
            :maximum_quantity,
            :unit,
            :purchase_date,
            :purchase_price,
            :item_condition,
            :status,
            :is_available,
            :is_favorite,
            :is_pinned,
            :is_active,
            :notes,
            :keywords,
            :created_by,
            :updated_by
        )
        "
    );

    $statement->execute([
        ...$validated,
        'is_available' => $validated['is_available'] ? 1 : 0,
        'is_favorite' => $validated['is_favorite'] ? 1 : 0,
        'is_pinned' => $validated['is_pinned'] ? 1 : 0,
        'is_active' => $validated['is_active'] ? 1 : 0,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * מעדכן פריט מלאי.
 *
 * @param array<string, mixed> $data
 */
function updateInventoryItem(
    PDO $pdo,
    int $itemId,
    array $data,
    ?int $userId
): bool {
    if (!inventoryItemExists($pdo, $itemId)) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    $validated =  $data;

    $statement = $pdo->prepare(
        "
        UPDATE inventory_items
        SET
            item_code = :item_code,
            barcode = :barcode,
            qr_code = :qr_code,
            name_he = :name_he,
            name_en = :name_en,
            description = :description,
            category_id = :category_id,
            location_id = :location_id,
            supplier_id = :supplier_id,
            manufacturer = :manufacturer,
            model = :model,
            shelf = :shelf,
            bin = :bin,
            quantity = :quantity,
            minimum_quantity = :minimum_quantity,
            maximum_quantity = :maximum_quantity,
            unit = :unit,
            purchase_date = :purchase_date,
            purchase_price = :purchase_price,
            item_condition = :item_condition,
            status = :status,
            is_available = :is_available,
            is_favorite = :is_favorite,
            is_pinned = :is_pinned,
            is_active = :is_active,
            notes = :notes,
            keywords = :keywords,
            updated_by = :updated_by
        WHERE id = :id
        "
    );

    return $statement->execute([
        ...$validated,
        'is_available' => $validated['is_available'] ? 1 : 0,
        'is_favorite' => $validated['is_favorite'] ? 1 : 0,
        'is_pinned' => $validated['is_pinned'] ? 1 : 0,
        'is_active' => $validated['is_active'] ? 1 : 0,
        'updated_by' => $userId,
        'id' => $itemId,
    ]);
}

/**
 * משנה את מצב הפעילות של פריט.
 */
function setInventoryItemActiveStatus(
    PDO $pdo,
    int $itemId,
    bool $isActive,
    ?int $userId
): bool {
    if (!inventoryItemExists($pdo, $itemId)) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    $statement = $pdo->prepare(
        "
        UPDATE inventory_items
        SET
            is_active = :is_active,
            updated_by = :updated_by
        WHERE id = :id
        "
    );

    return $statement->execute([
        'is_active' => $isActive ? 1 : 0,
        'updated_by' => $userId,
        'id' => $itemId,
    ]);
}

/**
 * משנה סימון מועדף.
 */
function setInventoryItemFavorite(
    PDO $pdo,
    int $itemId,
    bool $isFavorite,
    ?int $userId
): bool {
    if (!inventoryItemExists($pdo, $itemId)) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    $statement = $pdo->prepare(
        "
        UPDATE inventory_items
        SET
            is_favorite = :is_favorite,
            updated_by = :updated_by
        WHERE id = :id
        "
    );

    return $statement->execute([
        'is_favorite' => $isFavorite ? 1 : 0,
        'updated_by' => $userId,
        'id' => $itemId,
    ]);
}

/**
 * משנה סימון נעוץ.
 */
function setInventoryItemPinned(
    PDO $pdo,
    int $itemId,
    bool $isPinned,
    ?int $userId
): bool {
    if (!inventoryItemExists($pdo, $itemId)) {
        throw new InvalidArgumentException(
            'פריט המלאי לא נמצא.'
        );
    }

    $statement = $pdo->prepare(
        "
        UPDATE inventory_items
        SET
            is_pinned = :is_pinned,
            updated_by = :updated_by
        WHERE id = :id
        "
    );

    return $statement->execute([
        'is_pinned' => $isPinned ? 1 : 0,
        'updated_by' => $userId,
        'id' => $itemId,
    ]);
}