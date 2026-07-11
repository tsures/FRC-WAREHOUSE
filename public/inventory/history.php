<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/inventory_transaction_helpers.php';

startSecureSession();
requireLogin();

$pdo = Database::getConnection();

$pageTitle = 'היסטוריית תנועות מלאי';
$activePage = 'inventory';

$locationsStatement = $pdo->query(
    "
    SELECT
        id,
        name,
        code
    FROM locations
    WHERE is_active = 1
    ORDER BY
        sort_order ASC,
        name ASC,
        id ASC
    "
);

$locations = $locationsStatement->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-history.css?v=4"
>

<section class="inventory-history-page" dir="rtl">
    <div class="inventory-history-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="inventory-history-back"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למלאי</span>
            </a>

            <p class="inventory-history-eyebrow">מלאי</p>

            <h1>היסטוריית תנועות</h1>

            <p class="inventory-history-description">
                צפייה בכל ההכנסות, ההוצאות, ההעברות והתיקונים שבוצעו במלאי.
            </p>
        </div>

        <div class="inventory-history-count">
            <span>תוצאות</span>
            <strong id="inventoryHistoryCount">0</strong>
        </div>
    </div>

    <section class="inventory-history-filters">
        <div class="form-group inventory-history-search-group">
            <label for="inventoryHistorySearch">חיפוש</label>

            <input
                type="search"
                id="inventoryHistorySearch"
                placeholder="פריט, קוד, אסמכתה, הערה או משתמש..."
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <label for="inventoryHistoryType">סוג תנועה</label>

            <select id="inventoryHistoryType">
                <option value="">כל סוגי התנועות</option>

                <?php foreach (
                    getInventoryTransactionTypes()
                    as $value => $label
                ): ?>
                    <option value="<?= escape($value) ?>">
                        <?= escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="inventoryHistoryLocation">מיקום</label>

            <select id="inventoryHistoryLocation">
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
            <label for="inventoryHistoryDateFrom">מתאריך</label>

            <input
                type="date"
                id="inventoryHistoryDateFrom"
            >
        </div>

        <div class="form-group">
            <label for="inventoryHistoryDateTo">עד תאריך</label>

            <input
                type="date"
                id="inventoryHistoryDateTo"
            >
        </div>

        <div class="inventory-history-filter-actions">
            <button
                type="button"
                id="clearInventoryHistoryFilters"
                class="button button-secondary"
            >
                ניקוי
            </button>

            <button
                type="button"
                id="refreshInventoryHistory"
                class="button button-primary"
            >
                רענון
            </button>
        </div>
    </section>

    <section class="inventory-history-panel">
        <div
            id="inventoryHistoryLoading"
            class="inventory-history-loading"
        >
            <span class="inventory-history-spinner"></span>
            <span>טוען תנועות...</span>
        </div>

        <div
            id="inventoryHistoryEmpty"
            class="inventory-history-empty"
            hidden
        >
            לא נמצאו תנועות מלאי.
        </div>

        <div
            id="inventoryHistoryTableWrapper"
            class="inventory-history-table-wrapper"
            hidden
        >
            <table class="inventory-history-table">
                <thead>
                    <tr>
                        <th>תאריך</th>
                        <th>פריט</th>
                        <th>סוג</th>
                        <th>שינוי</th>
                        <th>לפני</th>
                        <th>אחרי</th>
                        <th>מיקום</th>
                        <th>אסמכתה</th>
                        <th>משתמש</th>
                        <th>הערות</th>
                    </tr>
                </thead>

                <tbody id="inventoryHistoryTableBody"></tbody>
            </table>
        </div>
    </section>
</section>

<div
    id="inventoryHistoryToastContainer"
    class="inventory-history-toast-container"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.inventoryHistoryConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-history.js?v=5"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';