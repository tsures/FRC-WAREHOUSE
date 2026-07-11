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

$pdo = Database::getConnection();

$itemId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($itemId === false || $itemId === null) {
    redirect(APP_URL . '/public/inventory/');
    exit;
}

$item = getInventoryItemById($pdo, (int) $itemId);

if ($item === null) {
    redirect(APP_URL . '/public/inventory/');
    exit;
}

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

$pageTitle = 'תנועות מלאי';
$activePage = 'inventory';
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-transactions.css?v=3"
>

<section class="inventory-transactions-page" dir="rtl">
    <div class="transactions-page-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/inventory/"
                class="transactions-back-link"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למלאי</span>
            </a>

            <p class="transactions-eyebrow">
                תנועות מלאי
            </p>

            <h1>
                <?= escape((string) $item['name_he']) ?>
            </h1>

            <p class="transactions-item-code">
                <?= escape((string) $item['item_code']) ?>
            </p>
        </div>

        <div class="transactions-current-stock">
            <span>כמות נוכחית</span>

            <strong id="currentInventoryQuantity">
                <?= escape(
                    number_format(
                        (float) $item['quantity'],
                        3,
                        '.',
                        ','
                    )
                ) ?>
            </strong>

            <small>
                <?= escape((string) $item['unit']) ?>
            </small>
        </div>
    </div>

    <form
        id="inventoryTransactionForm"
        class="inventory-transaction-form"
        novalidate
    >
        <input
            type="hidden"
            id="transactionItemId"
            value="<?= escape((string) $itemId) ?>"
        >

        <div class="transaction-form-header">
            <div>
                <h2>רישום תנועה חדשה</h2>
                <p>הוסף, הפחת, העבר או תקן את כמות המלאי.</p>
            </div>
        </div>

        <div class="transaction-form-grid">
            <div class="form-group">
                <label for="transactionType">
                    סוג תנועה
                    <span class="required-marker">*</span>
                </label>

                <select
                    id="transactionType"
                    required
                >
                    <option value="">בחר סוג תנועה</option>

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

            <div
                id="transactionQuantityGroup"
                class="form-group"
            >
                <label for="transactionQuantity">
                    כמות
                    <span class="required-marker">*</span>
                </label>

                <input
                    type="number"
                    id="transactionQuantity"
                    min="0.001"
                    step="0.001"
                >
            </div>

            <div
                id="transactionNewQuantityGroup"
                class="form-group"
                hidden
            >
                <label for="transactionNewQuantity">
                    כמות חדשה
                    <span class="required-marker">*</span>
                </label>

                <input
                    type="number"
                    id="transactionNewQuantity"
                    min="0"
                    step="0.001"
                >
            </div>

            <div
                id="transactionLocationGroup"
                class="form-group"
                hidden
            >
                <label for="transactionToLocation">
                    מיקום יעד
                    <span class="required-marker">*</span>
                </label>

                <select id="transactionToLocation">
                    <option value="">בחר מיקום</option>

                    <?php foreach ($locations as $location): ?>
                        <option
                            value="<?= escape((string) $location['id']) ?>"
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
                <label for="transactionReference">
                    מספר אסמכתה
                </label>

                <input
                    type="text"
                    id="transactionReference"
                    maxlength="100"
                    placeholder="פקודה, הזמנה, מסמך..."
                >
            </div>

            <div class="form-group transaction-form-full">
                <label for="transactionNotes">
                    הערות
                </label>

                <textarea
                    id="transactionNotes"
                    rows="3"
                    placeholder="הערות לתנועה..."
                ></textarea>
            </div>
        </div>

        <div
            id="transactionFormError"
            class="transaction-form-error"
            hidden
            role="alert"
        ></div>

        <div class="transaction-form-actions">
            <button
                type="submit"
                id="saveTransactionButton"
                class="button button-primary"
            >
                <span id="saveTransactionButtonText">
                    שמירת תנועה
                </span>

                <span
                    id="saveTransactionSpinner"
                    class="transaction-spinner"
                    hidden
                    aria-hidden="true"
                ></span>
            </button>
        </div>
    </form>

    <section class="transactions-history-panel">
        <div class="transactions-history-header">
            <div>
                <h2>היסטוריית תנועות</h2>
                <p>כל השינויים שבוצעו בפריט.</p>
            </div>

            <button
                type="button"
                id="refreshTransactionsButton"
                class="button button-secondary"
            >
                רענון
            </button>
        </div>

        <div
            id="transactionsLoading"
            class="transactions-loading"
        >
            <span class="transaction-spinner"></span>
            <span>טוען תנועות...</span>
        </div>

        <div
            id="transactionsEmptyState"
            class="transactions-empty-state"
            hidden
        >
            עדיין לא נרשמו תנועות עבור פריט זה.
        </div>

        <div
            id="transactionsTableWrapper"
            class="transactions-table-wrapper"
            hidden
        >
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>תאריך</th>
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

                <tbody id="transactionsTableBody"></tbody>
            </table>
        </div>
    </section>
</section>

<div
    id="transactionToastContainer"
    class="transaction-toast-container"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.inventoryTransactionsConfig = {
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
        itemId: <?= (int) $itemId ?>,
        isAdmin: true,
        unit: <?= json_encode(
            (string) $item['unit'],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-transactions.js?v=3"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';