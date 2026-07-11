<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();

$pdo = Database::getConnection();

$pageTitle = 'חוסרים והשלמות מלאי';
$activePage = 'inventory';

$categories = $pdo->query(
    "
    SELECT id, name_he
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order ASC, name_he ASC
    "
)->fetchAll(PDO::FETCH_ASSOC);

$locations = $pdo->query(
    "
    SELECT id, name, code
    FROM locations
    WHERE is_active = 1
    ORDER BY sort_order ASC, name ASC
    "
)->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $pdo->query(
    "
    SELECT id, name
    FROM suppliers
    WHERE is_active = 1
    ORDER BY name ASC
    "
)->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-shortages.css?v=1"
>

<section class="inventory-shortages-page" dir="rtl">
    <div class="inventory-shortages-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="inventory-shortages-back"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למלאי</span>
            </a>

            <p class="inventory-shortages-eyebrow">מלאי</p>

            <h1>חוסרים והשלמות מלאי</h1>

            <p class="inventory-shortages-description">
                פריטים שאזלו או הגיעו לכמות המינימום.
            </p>
        </div>
    </div>

    <section class="inventory-shortages-stats">
        <article class="inventory-shortages-stat">
            <span>סה״כ חוסרים</span>
            <strong id="shortagesTotalCount">0</strong>
        </article>

        <article class="inventory-shortages-stat">
            <span>אזל מהמלאי</span>
            <strong id="shortagesOutCount">0</strong>
        </article>

        <article class="inventory-shortages-stat">
            <span>מלאי נמוך</span>
            <strong id="shortagesLowCount">0</strong>
        </article>

        <article class="inventory-shortages-stat">
            <span>עלות השלמה משוערת</span>
            <strong id="shortagesEstimatedCost">₪0</strong>
        </article>
    </section>

    <section class="inventory-shortages-filters">
        <div class="form-group inventory-shortages-search-group">
            <label for="shortagesSearch">חיפוש</label>

            <input
                type="search"
                id="shortagesSearch"
                placeholder="שם פריט, קוד, קטגוריה, מחסן או ספק..."
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <label for="shortagesTypeFilter">סוג חוסר</label>

            <select id="shortagesTypeFilter">
                <option value="all">הכול</option>
                <option value="out">אזל מהמלאי</option>
                <option value="low">מלאי נמוך</option>
            </select>
        </div>

        <div class="form-group">
            <label for="shortagesLocationFilter">מחסן / מיקום</label>

            <select id="shortagesLocationFilter">
                <option value="">כל המיקומים</option>

                <?php foreach ($locations as $location): ?>
                    <option value="<?= escape((string) $location['id']) ?>">
                        <?= escape((string) $location['name']) ?>
                        <?= !empty($location['code'])
                            ? ' (' . escape((string) $location['code']) . ')'
                            : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="shortagesCategoryFilter">קטגוריה</label>

            <select id="shortagesCategoryFilter">
                <option value="">כל הקטגוריות</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= escape((string) $category['id']) ?>">
                        <?= escape((string) $category['name_he']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="shortagesSupplierFilter">ספק</label>

            <select id="shortagesSupplierFilter">
                <option value="">כל הספקים</option>

                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= escape((string) $supplier['id']) ?>">
                        <?= escape((string) $supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="inventory-shortages-filter-actions">
            <button
                type="button"
                id="clearShortagesFilters"
                class="button button-secondary"
            >
                ניקוי
            </button>

            <button
                type="button"
                id="refreshShortages"
                class="button button-primary"
            >
                רענון
            </button>
        </div>
    </section>

    <div class="inventory-shortages-toolbar">
        <span>
            מסומנים לטיפול:
            <strong id="shortagesSelectedCount">0</strong>
        </span>

        <button
            type="button"
            id="clearShortagesSelected"
            class="button button-secondary"
        >
            ניקוי סימונים
        </button>
    </div>

    <div
        id="shortagesLoading"
        class="inventory-shortages-loading"
    >
        <span class="inventory-shortages-spinner"></span>
        <span>טוען חוסרים...</span>
    </div>

    <div
        id="shortagesEmpty"
        class="inventory-shortages-empty"
        hidden
    >
        לא נמצאו חוסרים לפי הסינון שנבחר.
    </div>

    <section
        id="shortagesItems"
        class="inventory-shortages-items"
        hidden
    ></section>
</section>

<div
    id="shortagesToastContainer"
    class="inventory-shortages-toast-container"
    aria-live="polite"
></div>

<script>
    window.inventoryShortagesConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-shortages.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
