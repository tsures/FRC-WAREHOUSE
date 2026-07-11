<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'ניהול מלאי';
$activePage = 'inventory';

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory.css?v=1"
>

<meta
    name="csrf-token"
    content="<?= escape($csrfToken) ?>"
>

<section class="inventory-page" dir="rtl">
    <div class="inventory-page-header">
        <div>
            <p class="inventory-eyebrow">ניהול המחסן</p>

            <h1>מלאי</h1>

            <p class="inventory-page-description">
                ניהול פריטים, כמויות, קטגוריות, מיקומים וסטטוסי מלאי.
            </p>
            <a
    href="<?= escape(APP_URL) ?>/public/inventory/history.php"
    class="button button-secondary"
>
    <span aria-hidden="true">📋</span>
    <span>היסטוריית תנועות</span>
</a>
<a
    href="<?= escape(APP_URL) ?>/public/inventory/warehouses.php"
    class="button button-secondary"
>
    <span aria-hidden="true">🏭</span>
    <span>מלאי לפי מחסן</span>
</a>
<a
    href="<?= escape(APP_URL) ?>/public/inventory/shortages.php"
    class="button button-secondary"
>
    <span aria-hidden="true">⚠️</span>
    <span>חוסרים והשלמות</span>
</a>
        </div>

        <?php if (isAdmin()): ?>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/add.php"
                class="button button-primary inventory-add-button"
            >
                <span aria-hidden="true">＋</span>
                <span>הוספת פריט</span>
            </a>
        <?php endif; ?>
    </div>

    <div
        class="inventory-stats"
        aria-label="סטטיסטיקות מלאי"
    >
        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">📦</div>

            <div>
                <span class="inventory-stat-label">סה״כ פריטים</span>
                <strong id="inventoryTotalCount">0</strong>
            </div>
        </article>

        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">✅</div>

            <div>
                <span class="inventory-stat-label">זמינים</span>
                <strong id="inventoryAvailableCount">0</strong>
            </div>
        </article>

        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">⚠️</div>

            <div>
                <span class="inventory-stat-label">מלאי נמוך</span>
                <strong id="inventoryLowStockCount">0</strong>
            </div>
        </article>

        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">⛔</div>

            <div>
                <span class="inventory-stat-label">אזל מהמלאי</span>
                <strong id="inventoryOutOfStockCount">0</strong>
            </div>
        </article>

        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">🛑</div>

            <div>
                <span class="inventory-stat-label">שבורים</span>
                <strong id="inventoryBrokenCount">0</strong>
            </div>
        </article>

        <article class="inventory-stat-card">
            <div class="inventory-stat-icon" aria-hidden="true">📌</div>

            <div>
                <span class="inventory-stat-label">נעוצים</span>
                <strong id="inventoryPinnedCount">0</strong>
            </div>
        </article>
    </div>

    <div class="inventory-toolbar">
        <div class="inventory-search-wrapper">
            <span
                class="inventory-search-icon"
                aria-hidden="true"
            >
                🔎
            </span>

            <input
                type="search"
                id="inventorySearch"
                class="inventory-search"
                placeholder="חיפוש לפי שם, קוד, ברקוד, דגם, קטגוריה או מיקום..."
                autocomplete="off"
                aria-label="חיפוש פריטי מלאי"
            >

            <button
                type="button"
                id="clearInventorySearch"
                class="inventory-clear-search"
                aria-label="ניקוי החיפוש"
                hidden
            >
                ×
            </button>
        </div>

        <div class="inventory-toolbar-actions">
            <button
                type="button"
                id="toggleInventoryFilters"
                class="button button-secondary"
                aria-expanded="false"
                aria-controls="inventoryFilters"
            >
                <span aria-hidden="true">⚙️</span>
                <span>מסננים</span>
            </button>

            <button
                type="button"
                id="refreshInventoryButton"
                class="button button-secondary"
            >
                <span aria-hidden="true">↻</span>
                <span>רענון</span>
            </button>
        </div>
    </div>

    <div
        id="inventoryFilters"
        class="inventory-filters"
        hidden
    >
        <div class="inventory-filter-group">
            <label for="inventoryCategoryFilter">
                קטגוריה
            </label>

            <select id="inventoryCategoryFilter">
                <option value="">כל הקטגוריות</option>
            </select>
        </div>

        <div class="inventory-filter-group">
            <label for="inventoryLocationFilter">
                מיקום
            </label>

            <select id="inventoryLocationFilter">
                <option value="">כל המיקומים</option>
            </select>
        </div>

        <div class="inventory-filter-group">
            <label for="inventoryStatusFilter">
                סטטוס
            </label>

            <select id="inventoryStatusFilter">
                <option value="">כל הסטטוסים</option>
            </select>
        </div>

        <div class="inventory-filter-group">
            <label for="inventoryConditionFilter">
                מצב פריט
            </label>

            <select id="inventoryConditionFilter">
                <option value="">כל המצבים</option>
            </select>
        </div>

        <div class="inventory-filter-group">
            <label for="inventoryStockFilter">
                מצב מלאי
            </label>

            <select id="inventoryStockFilter">
                <option value="all">כל מצבי המלאי</option>
                <option value="normal">מלאי תקין</option>
                <option value="low">מלאי נמוך</option>
                <option value="out">אזל מהמלאי</option>
            </select>
        </div>

        <div class="inventory-filter-group">
            <label for="inventoryActiveFilter">
                פעילות
            </label>

            <select id="inventoryActiveFilter">
                <option value="all">הכול</option>
                <option value="active">פעילים בלבד</option>
                <option value="inactive">מושבתים בלבד</option>
            </select>
        </div>

        <label class="inventory-checkbox-filter">
            <input
                type="checkbox"
                id="inventoryFavoriteFilter"
            >
            <span>מועדפים בלבד</span>
        </label>

        <label class="inventory-checkbox-filter">
            <input
                type="checkbox"
                id="inventoryPinnedFilter"
            >
            <span>נעוצים בלבד</span>
        </label>

        <button
            type="button"
            id="clearInventoryFilters"
            class="button button-secondary"
        >
            ניקוי מסננים
        </button>
    </div>

    <div
        id="inventoryLoading"
        class="inventory-loading"
        role="status"
    >
        <span class="inventory-spinner" aria-hidden="true"></span>
        <span>טוען פריטי מלאי...</span>
    </div>

    <div
        id="inventoryEmptyState"
        class="inventory-empty-state"
        hidden
    >
        <div class="inventory-empty-icon" aria-hidden="true">📦</div>

        <h2>לא נמצאו פריטי מלאי</h2>

        <p id="inventoryEmptyMessage">
            עדיין לא נוספו פריטים למערכת.
        </p>

        <?php if (isAdmin()): ?>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/add.php"
                class="button button-primary"
            >
                הוספת פריט ראשון
            </a>
        <?php endif; ?>
    </div>

    <div
        id="inventoryResultsSummary"
        class="inventory-results-summary"
        hidden
    >
        נמצאו
        <strong id="inventoryReturnedCount">0</strong>
        פריטים
    </div>

    <div
        id="inventoryItems"
        class="inventory-items"
        aria-live="polite"
    ></div>
</section>

<div
    id="inventoryToastContainer"
    class="inventory-toast-container"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.inventoryConfig = {
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
        isAdmin: <?= isAdmin() ? 'true' : 'false' ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory.js?v=3"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
