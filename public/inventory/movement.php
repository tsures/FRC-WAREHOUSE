<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'הוצאת ומילוי ציוד';
$activePage = 'movement';

$csrfToken = generateCsrfToken();
$formToken = bin2hex(random_bytes(32));

$_SESSION['inventory_quick_transaction_token'] = $formToken;

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-movement.css?v=11"
>

<section class="inventory-movement-page" dir="rtl">
    <div class="inventory-movement-header">
        <div>
            <p class="inventory-movement-eyebrow">תנועת מלאי</p>
            <h1>הוצאת ומילוי ציוד</h1>
            <p>
                בחר פריט, צפה במלאי הקיים ובצע הוצאה או מילוי.
            </p>
        </div>
    </div>

    <div class="inventory-movement-layout">
        <section class="inventory-movement-card">
            <div class="inventory-movement-card-header">
                <div>
                    <h2>בחירת פעולה ופריט</h2>
                    <p>
                        המערכת תציג אוטומטית את המיקום והמלאי הקיים.
                    </p>
                </div>
            </div>

            <form
                id="inventoryMovementForm"
                class="inventory-movement-form"
                novalidate
            >
                <div class="inventory-movement-type-grid">
                    <label class="inventory-movement-type is-selected">
                        <input
                            type="radio"
                            name="transaction_type"
                            value="remove"
                            checked
                        >

                        <span class="inventory-movement-type-icon">➖</span>

                        <span>
                            <strong>הוצאת ציוד</strong>
                            <small>הפחתת כמות מהמלאי הקיים</small>
                        </span>
                    </label>

                    <label class="inventory-movement-type">
                        <input
                            type="radio"
                            name="transaction_type"
                            value="add"
                        >

                        <span class="inventory-movement-type-icon">➕</span>

                        <span>
                            <strong>מילוי מלאי</strong>
                            <small>הוספת כמות למלאי הקיים</small>
                        </span>
                    </label>
                </div>

                <div class="form-group inventory-item-search-group">
                    <label for="inventoryMovementSearch">
                        חיפוש פריט
                        <span class="required-marker">*</span>
                    </label>

                    <div class="inventory-item-search-wrapper">
                        <input
                            type="search"
                            id="inventoryMovementSearch"
                            placeholder="שם פריט, קוד, ברקוד או QR..."
                            autocomplete="off"
                        >

                        <span
                            id="inventoryMovementSearchSpinner"
                            class="inventory-movement-small-spinner"
                            hidden
                        ></span>
                    </div>

                    <div
                        id="inventoryMovementSearchResults"
                        class="inventory-movement-search-results"
                        hidden
                    ></div>
                </div>

                <input
                    type="hidden"
                    id="inventoryMovementItemId"
                >

                <div
                    id="inventoryMovementSelectedItem"
                    class="inventory-selected-item"
                    hidden
                >
                    <div class="inventory-selected-item-main">
                        <div>
                            <span class="inventory-selected-item-label">
                                הפריט שנבחר
                            </span>

                            <h3 id="inventorySelectedItemName"></h3>

                            <span
                                id="inventorySelectedItemCode"
                                class="inventory-selected-item-code"
                            ></span>
                        </div>

                        <button
                            type="button"
                            id="changeInventoryMovementItem"
                            class="button button-secondary"
                        >
                            החלפת פריט
                        </button>
                    </div>

                    <div class="inventory-selected-item-data">
                        <div>
                            <span>מלאי נוכחי</span>
                            <strong id="inventorySelectedItemQuantity">0</strong>
                        </div>

                        <div>
                            <span>יחידת מידה</span>
                            <strong id="inventorySelectedItemUnit">—</strong>
                        </div>

                        <div>
                            <span>מחסן / מיקום</span>
                            <strong id="inventorySelectedItemLocation">—</strong>
                        </div>

                        <div>
                            <span>מדף / תא</span>
                            <strong id="inventorySelectedItemShelf">—</strong>
                        </div>
                    </div>
                </div>

                <div class="inventory-movement-form-grid">
                    <div class="form-group">
                        <label for="inventoryMovementQuantity">
                            כמות
                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="number"
                            id="inventoryMovementQuantity"
                            min="0.001"
                            step="0.001"
                            required
                        >

                        <small id="inventoryMovementQuantityHint">
                            בחר פריט כדי לראות את הכמות הזמינה.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="inventoryMovementReference">
                            אסמכתה
                        </label>

                        <input
                            type="text"
                            id="inventoryMovementReference"
                            maxlength="100"
                            placeholder="משימה, הזמנה או מסמך..."
                        >
                    </div>

                    <div class="form-group inventory-movement-full">
                        <label for="inventoryMovementNotes">
                            למי / לאיזו מטרה / הערות
                        </label>

                        <textarea
                            id="inventoryMovementNotes"
                            rows="4"
                            maxlength="1000"
                            placeholder="לדוגמה: הועבר לצוות מכני עבור פרויקט..."
                        ></textarea>
                    </div>
                </div>

                <div
                    id="inventoryMovementError"
                    class="inventory-movement-message is-error"
                    hidden
                ></div>

                <div
                    id="inventoryMovementSuccess"
                    class="inventory-movement-message is-success"
                    hidden
                ></div>

                <div class="inventory-movement-actions">
                    <button
                        type="reset"
                        id="resetInventoryMovementForm"
                        class="button button-secondary"
                    >
                        ניקוי
                    </button>

                    <button
                        type="submit"
                        id="saveInventoryMovementButton"
                        class="button button-primary"
                    >
                        <span id="saveInventoryMovementButtonText">
                            הוצאת ציוד
                        </span>

                        <span
                            id="saveInventoryMovementSpinner"
                            class="inventory-movement-small-spinner"
                            hidden
                        ></span>
                    </button>
                </div>
            </form>
        </section>

        <aside class="inventory-movement-side">
            <section class="inventory-movement-card">
                <div class="inventory-movement-card-header">
                    <div>
                        <h2>כללי עבודה</h2>
                    </div>
                </div>

                <ul class="inventory-movement-rules">
                    <li>
                        בהוצאת ציוד לא ניתן להזין כמות גדולה מהמלאי הקיים.
                    </li>
                    <li>
                        המחסן והמיקום נקבעים אוטומטית לפי הפריט.
                    </li>
                    <li>
                        כל פעולה נשמרת עם שם המשתמש, התאריך והשעה.
                    </li>
                    <li>
                        במקרה של טעות יש לפנות למנהל לצורך תנועה מתקנת.
                    </li>
                </ul>
            </section>

            <section
                id="inventoryMovementLastAction"
                class="inventory-movement-card inventory-last-action"
                hidden
            >
                <div class="inventory-movement-card-header">
                    <div>
                        <h2>פעולה אחרונה</h2>
                    </div>
                </div>

                <div class="inventory-last-action-content">
                    <strong id="inventoryLastActionTitle"></strong>
                    <span id="inventoryLastActionDetails"></span>
                </div>
            </section>
        </aside>
    </div>
</section>

<script>
    window.inventoryMovementConfig = {
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
        formToken: <?= json_encode(
            $formToken,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/inventory-movement.js?v=2"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
