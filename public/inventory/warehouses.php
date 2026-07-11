<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'מלאי לפי מחסן';
$activePage = 'inventory';

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-warehouses.css?v=1"
>

<section class="inventory-warehouses-page" dir="rtl">
    <div class="inventory-warehouses-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="inventory-warehouses-back"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למלאי</span>
            </a>

            <p class="inventory-warehouses-eyebrow">
                מלאי
            </p>

            <h1>מלאי לפי מחסן</h1>

            <p class="inventory-warehouses-description">
                צפייה בכמויות ובפריטים בכל מחסן או מיקום.
            </p>
        </div>

        <div class="inventory-warehouses-count">
            <span>פריטים מוצגים</span>
            <strong id="warehouseItemsCount">0</strong>
        </div>
    </div>

    <section class="inventory-warehouse-filters">
        <div class="form-group inventory-warehouse-search-group">
            <label for="warehouseInventorySearch">
                חיפוש
            </label>

            <input
                type="search"
                id="warehouseInventorySearch"
                placeholder="שם פריט, קוד, קטגוריה או מיקום..."
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <label for="warehouseLocationFilter">
                מחסן / מיקום
            </label>

            <select id="warehouseLocationFilter">
                <option value="">כל המחסנים והמיקומים</option>
            </select>
        </div>

        <div class="form-group">
            <label for="warehouseStockFilter">
                מצב מלאי
            </label>

            <select id="warehouseStockFilter">
                <option value="all">הכול</option>
                <option value="normal">מלאי תקין</option>
                <option value="low">מלאי נמוך</option>
                <option value="out">אזל מהמלאי</option>
            </select>
        </div>

        <div class="form-group">
            <label for="warehouseActiveFilter">
                מצב פריט
            </label>

            <select id="warehouseActiveFilter">
                <option value="active">פעילים בלבד</option>
                <option value="all">הכול</option>
                <option value="inactive">מושבתים בלבד</option>
            </select>
        </div>

        <div class="inventory-warehouse-filter-actions">
            <button
                type="button"
                id="clearWarehouseInventoryFilters"
                class="button button-secondary"
            >
                ניקוי
            </button>

            <button
                type="button"
                id="refreshWarehouseInventory"
                class="button button-primary"
            >
                רענון
            </button>
        </div>
    </section>

    <section
        id="warehouseSummaryCards"
        class="inventory-warehouse-summary"
    ></section>

    <div
        id="warehouseInventoryLoading"
        class="inventory-warehouse-loading"
    >
        <span class="inventory-warehouse-spinner"></span>
        <span>טוען מלאי...</span>
    </div>

    <div
        id="warehouseInventoryEmpty"
        class="inventory-warehouse-empty"
        hidden
    >
        לא נמצאו פריטי מלאי.
    </div>

    <section
        id="warehouseInventoryGroups"
        class="inventory-warehouse-groups"
        hidden
    ></section>
</section>

<div
    id="warehouseInventoryToastContainer"
    class="inventory-warehouse-toast-container"
    aria-live="polite"
></div>

<script>
    window.inventoryWarehousesConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-warehouses.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
