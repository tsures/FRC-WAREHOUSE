<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();


$pdo = Database::getConnection();

$itemId = isset($_GET['id']) && $_GET['id'] !== ''
    ? (int) $_GET['id']
    : null;

if ($itemId !== null && $itemId <= 0) {
    redirect(APP_URL . '/public/inventory/');
}

$item = null;

if ($itemId !== null) {
    $item = getInventoryItemById($pdo, $itemId);

    if ($item === null) {
        redirect(APP_URL . '/public/inventory/');
    }
}

$isEditMode = $item !== null;

$pageTitle = $isEditMode
    ? 'עריכת פריט מלאי'
    : 'הוספת פריט מלאי';

$activePage = 'inventory';

$csrfToken = generateCsrfToken();
$statuses = getInventoryStatuses();
$conditions = getInventoryConditions();
$units = getInventoryUnits();

$categoriesStatement = $pdo->query(
    "
    SELECT
        id,
        parent_id,
        name_he,
        name_en,
        sort_order
    FROM categories
    WHERE is_active = 1
    ORDER BY
        sort_order ASC,
        name_he ASC,
        id ASC
    "
);

$categories = $categoriesStatement->fetchAll(PDO::FETCH_ASSOC);

$locationsStatement = $pdo->query(
    "
    SELECT
        id,
        parent_id,
        name,
        code,
        location_type,
        sort_order
    FROM locations
    WHERE is_active = 1
    ORDER BY
        sort_order ASC,
        name ASC,
        id ASC
    "
);

$locations = $locationsStatement->fetchAll(PDO::FETCH_ASSOC);

$suppliersStatement = $pdo->query(
    "
    SELECT
        id,
        name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY
        name ASC,
        id ASC
    "
);

$suppliers = $suppliersStatement->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-form.css?v=1"
>

<meta
    name="csrf-token"
    content="<?= escape($csrfToken) ?>"
>

<section class="inventory-form-page" dir="rtl">
    <div class="inventory-form-page-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="inventory-back-link"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למלאי</span>
            </a>

            <p class="inventory-form-eyebrow">
                ניהול מלאי
            </p>

            <h1>
                <?= $isEditMode ? 'עריכת פריט' : 'הוספת פריט חדש' ?>
            </h1>

            <p class="inventory-form-page-description">
                <?= $isEditMode
                    ? 'עדכון פרטי פריט המלאי הקיים.'
                    : 'הזנת פרטי פריט חדש למערכת המלאי.' ?>
            </p>
        </div>

        <?php if ($isEditMode): ?>
            <div class="inventory-edit-badge">
                <span aria-hidden="true">✏️</span>
                <span>מצב עריכה</span>
            </div>
        <?php endif; ?>
    </div>

    <form
        id="inventoryItemForm"
        class="inventory-item-form"
        novalidate
    >
        <input
            type="hidden"
            id="inventoryItemId"
            name="id"
            value="<?= $itemId !== null ? escape((string) $itemId) : '' ?>"
        >

        <div class="inventory-form-section">
            <div class="inventory-form-section-header">
                <div>
                    <span class="inventory-form-section-icon" aria-hidden="true">
                        📦
                    </span>

                    <div>
                        <h2>פרטים בסיסיים</h2>
                        <p>זיהוי ושמות הפריט.</p>
                    </div>
                </div>
            </div>

            <div class="inventory-form-grid">
                <div class="form-group">
                    <label for="inventoryItemCode">
                        קוד פריט
                        <span class="required-marker">*</span>
                    </label>

                    <input
                        type="text"
                        id="inventoryItemCode"
                        name="item_code"
                        maxlength="80"
                        required
                        autocomplete="off"
                        dir="ltr"
                        placeholder="לדוגמה: FRC-SCREW-M3"
                        value="<?= escape((string) ($item['item_code'] ?? '')) ?>"
                    >

                    <small class="form-hint">
                        אותיות באנגלית, מספרים, מקף או קו תחתון.
                    </small>

                    <small
                        id="inventoryItemCodeError"
                        class="form-error"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="inventoryNameHe">
                        שם בעברית
                        <span class="required-marker">*</span>
                    </label>

                    <input
                        type="text"
                        id="inventoryNameHe"
                        name="name_he"
                        maxlength="190"
                        required
                        autocomplete="off"
                        placeholder="לדוגמה: בורג M3"
                        value="<?= escape((string) ($item['name_he'] ?? '')) ?>"
                    >

                    <small
                        id="inventoryNameHeError"
                        class="form-error"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="inventoryNameEn">
                        שם באנגלית
                    </label>

                    <input
                        type="text"
                        id="inventoryNameEn"
                        name="name_en"
                        maxlength="190"
                        autocomplete="off"
                        dir="ltr"
                        placeholder="M3 Screw"
                        value="<?= escape((string) ($item['name_en'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryUnit">
                        יחידת מידה
                        <span class="required-marker">*</span>
                    </label>

                    <input
                        type="text"
                        id="inventoryUnit"
                        name="unit"
                        maxlength="50"
                        required
                        list="inventoryUnitsList"
                        value="<?= escape((string) ($item['unit'] ?? 'יחידה')) ?>"
                    >

                    <datalist id="inventoryUnitsList">
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= escape($unit) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group inventory-form-full">
                    <label for="inventoryDescription">
                        תיאור
                    </label>

                    <textarea
                        id="inventoryDescription"
                        name="description"
                        rows="4"
                        placeholder="תיאור הפריט, שימושים או מידע נוסף..."
                    ><?= escape((string) ($item['description'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="inventory-form-section">
            <div class="inventory-form-section-header">
                <div>
                    <span class="inventory-form-section-icon" aria-hidden="true">
                        🗂️
                    </span>

                    <div>
                        <h2>שיוך ומיקום</h2>
                        <p>קטגוריה, מיקום וספק.</p>
                    </div>
                </div>
            </div>

            <div class="inventory-form-grid">
                <div class="form-group">
                    <label for="inventoryCategory">
                        קטגוריה
                    </label>

                    <select
                        id="inventoryCategory"
                        name="category_id"
                    >
                        <option value="">ללא קטגוריה</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= escape((string) $category['id']) ?>"
                                <?= (string) ($item['category_id'] ?? '') === (string) $category['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escape((string) $category['name_he']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inventoryLocation">
                        מיקום
                    </label>

                    <select
                        id="inventoryLocation"
                        name="location_id"
                    >
                        <option value="">ללא מיקום</option>

                        <?php foreach ($locations as $location): ?>
                            <option
                                value="<?= escape((string) $location['id']) ?>"
                                <?= (string) ($item['location_id'] ?? '') === (string) $location['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escape((string) $location['name']) ?>
                                <?= !empty($location['code'])
                                    ? ' (' . escape((string) $location['code']) . ')'
                                    : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inventorySupplier">
                        ספק
                    </label>

                    <select
                        id="inventorySupplier"
                        name="supplier_id"
                    >
                        <option value="">ללא ספק</option>

                        <?php foreach ($suppliers as $supplier): ?>
                            <option
                                value="<?= escape((string) $supplier['id']) ?>"
                                <?= (string) ($item['supplier_id'] ?? '') === (string) $supplier['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escape((string) $supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inventoryManufacturer">
                        יצרן
                    </label>

                    <input
                        type="text"
                        id="inventoryManufacturer"
                        name="manufacturer"
                        maxlength="190"
                        value="<?= escape((string) ($item['manufacturer'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryModel">
                        דגם
                    </label>

                    <input
                        type="text"
                        id="inventoryModel"
                        name="model"
                        maxlength="190"
                        dir="ltr"
                        value="<?= escape((string) ($item['model'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryShelf">
                        מדף
                    </label>

                    <input
                        type="text"
                        id="inventoryShelf"
                        name="shelf"
                        maxlength="100"
                        value="<?= escape((string) ($item['shelf'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryBin">
                        תא
                    </label>

                    <input
                        type="text"
                        id="inventoryBin"
                        name="bin"
                        maxlength="100"
                        value="<?= escape((string) ($item['bin'] ?? '')) ?>"
                    >
                </div>
            </div>
        </div>

        <div class="inventory-form-section">
            <div class="inventory-form-section-header">
                <div>
                    <span class="inventory-form-section-icon" aria-hidden="true">
                        📊
                    </span>

                    <div>
                        <h2>כמויות ומצב</h2>
                        <p>כמות נוכחית, גבולות מלאי ומצב הפריט.</p>
                    </div>
                </div>
            </div>

            <div class="inventory-form-grid">
                <div class="form-group">
                    <label for="inventoryQuantity">
                        כמות נוכחית
                        <span class="required-marker">*</span>
                    </label>

                    <input
                        type="number"
                        id="inventoryQuantity"
                        name="quantity"
                        min="0"
                        step="0.001"
                        required
                        value="<?= escape((string) ($item['quantity'] ?? '0')) ?>"
                    >

                    <small
                        id="inventoryQuantityError"
                        class="form-error"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="inventoryMinimumQuantity">
                        כמות מינימום
                    </label>

                    <input
                        type="number"
                        id="inventoryMinimumQuantity"
                        name="minimum_quantity"
                        min="0"
                        step="0.001"
                        value="<?= escape((string) ($item['minimum_quantity'] ?? '0')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryMaximumQuantity">
                        כמות מקסימום
                    </label>

                    <input
                        type="number"
                        id="inventoryMaximumQuantity"
                        name="maximum_quantity"
                        min="0"
                        step="0.001"
                        value="<?= isset($item['maximum_quantity']) && $item['maximum_quantity'] !== null
                            ? escape((string) $item['maximum_quantity'])
                            : '' ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryCondition">
                        מצב הפריט
                        <span class="required-marker">*</span>
                    </label>

                    <select
                        id="inventoryCondition"
                        name="item_condition"
                        required
                    >
                        <?php foreach ($conditions as $value => $label): ?>
                            <option
                                value="<?= escape($value) ?>"
                                <?= (string) ($item['item_condition'] ?? 'good') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inventoryStatus">
                        סטטוס
                        <span class="required-marker">*</span>
                    </label>

                    <select
                        id="inventoryStatus"
                        name="status"
                        required
                    >
                        <?php foreach ($statuses as $value => $label): ?>
                            <option
                                value="<?= escape($value) ?>"
                                <?= (string) ($item['status'] ?? 'available') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="inventory-toggle-grid inventory-form-full">
                    <label class="inventory-toggle-option">
                        <input
                            type="checkbox"
                            id="inventoryIsAvailable"
                            name="is_available"
                            <?= !isset($item['is_available']) || !empty($item['is_available'])
                                ? 'checked'
                                : '' ?>
                        >

                        <span>
                            <strong>זמין לשימוש</strong>
                            <small>הפריט זמין לשימוש או להוצאה.</small>
                        </span>
                    </label>

                    <label class="inventory-toggle-option">
                        <input
                            type="checkbox"
                            id="inventoryIsFavorite"
                            name="is_favorite"
                            <?= !empty($item['is_favorite']) ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>מועדף</strong>
                            <small>הצגת הפריט כמועדף.</small>
                        </span>
                    </label>

                    <label class="inventory-toggle-option">
                        <input
                            type="checkbox"
                            id="inventoryIsPinned"
                            name="is_pinned"
                            <?= !empty($item['is_pinned']) ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>נעוץ</strong>
                            <small>הצגת הפריט בראש הרשימה.</small>
                        </span>
                    </label>

                    <label class="inventory-toggle-option">
                        <input
                            type="checkbox"
                            id="inventoryIsActive"
                            name="is_active"
                            <?= !isset($item['is_active']) || !empty($item['is_active'])
                                ? 'checked'
                                : '' ?>
                        >

                        <span>
                            <strong>פעיל</strong>
                            <small>פריט פעיל במערכת.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="inventory-form-section">
            <div class="inventory-form-section-header">
                <div>
                    <span class="inventory-form-section-icon" aria-hidden="true">
                        🏷️
                    </span>

                    <div>
                        <h2>זיהוי ורכש</h2>
                        <p>ברקוד, QR ופרטי רכישה.</p>
                    </div>
                </div>
            </div>

            <div class="inventory-form-grid">
                <div class="form-group">
                    <label for="inventoryBarcode">
                        Barcode
                    </label>

                    <input
                        type="text"
                        id="inventoryBarcode"
                        name="barcode"
                        maxlength="100"
                        dir="ltr"
                        value="<?= escape((string) ($item['barcode'] ?? '')) ?>"
                    >

                    <small
                        id="inventoryBarcodeError"
                        class="form-error"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="inventoryQrCode">
                        QR Code
                    </label>

                    <input
                        type="text"
                        id="inventoryQrCode"
                        name="qr_code"
                        maxlength="190"
                        dir="ltr"
                        value="<?= escape((string) ($item['qr_code'] ?? '')) ?>"
                    >

                    <small
                        id="inventoryQrCodeError"
                        class="form-error"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="inventoryPurchaseDate">
                        תאריך רכישה
                    </label>

                    <input
                        type="date"
                        id="inventoryPurchaseDate"
                        name="purchase_date"
                        value="<?= escape((string) ($item['purchase_date'] ?? '')) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="inventoryPurchasePrice">
                        מחיר רכישה
                    </label>

                    <input
                        type="number"
                        id="inventoryPurchasePrice"
                        name="purchase_price"
                        min="0"
                        step="0.01"
                        value="<?= isset($item['purchase_price']) && $item['purchase_price'] !== null
                            ? escape((string) $item['purchase_price'])
                            : '' ?>"
                    >
                </div>

                <div class="form-group inventory-form-full">
                    <label for="inventoryNotes">
                        הערות
                    </label>

                    <textarea
                        id="inventoryNotes"
                        name="notes"
                        rows="4"
                        placeholder="הערות פנימיות..."
                    ><?= escape((string) ($item['notes'] ?? '')) ?></textarea>
                </div>

                <div class="form-group inventory-form-full">
                    <label for="inventoryKeywords">
                        מילות מפתח
                    </label>

                    <textarea
                        id="inventoryKeywords"
                        name="keywords"
                        rows="3"
                        placeholder="לדוגמה: בורג, M3, פלדה, מכני"
                    ><?= escape((string) ($item['keywords'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div
            id="inventoryFormGeneralError"
            class="inventory-form-general-error"
            role="alert"
            hidden
        ></div>

        <div class="inventory-form-actions">
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="button button-secondary"
            >
                ביטול
            </a>

            <button
                type="submit"
                id="saveInventoryItemButton"
                class="button button-primary"
            >
                <span id="saveInventoryItemButtonText">
                    <?= $isEditMode ? 'שמירת שינויים' : 'הוספת פריט' ?>
                </span>

                <span
                    id="saveInventoryItemSpinner"
                    class="inventory-button-spinner"
                    hidden
                    aria-hidden="true"
                ></span>
            </button>
        </div>
    </form>
</section>

<div
    id="inventoryFormToastContainer"
    class="inventory-form-toast-container"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.inventoryFormConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>,
        csrfToken: <?= json_encode(
            $csrfToken,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>,
        isEditMode: <?= $isEditMode ? 'true' : 'false' ?>,
        itemId: <?= $itemId !== null ? (int) $itemId : 'null' ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-form.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
