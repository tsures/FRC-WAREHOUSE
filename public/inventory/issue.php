<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'הוצאה והוספת מלאי';
$activePage = 'movement';

$csrfToken = generateCsrfToken();
$formToken = bin2hex(random_bytes(32));

$_SESSION['inventory_quick_transaction_token'] = $formToken;

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/inventory-issue.css?v=2"
>

<section class="inventory-issue-page" dir="rtl">
    <header class="inventory-issue-header">
        <div>
            <p class="inventory-issue-eyebrow">
                תנועת מלאי
            </p>

            <h1 id="inventoryIssuePageTitle">
                הוצאת ציוד מהמחסן
            </h1>

            <p
                id="inventoryIssuePageDescription"
                class="inventory-issue-description"
            >
                בחר פריט, הזן כמות ופרטי מסירה, ואשר את ההוצאה.
            </p>
        </div>
    </header>

    <form
        id="inventoryIssueForm"
        class="inventory-issue-form"
        novalidate
    >
        <section class="inventory-issue-section">
            <div class="inventory-issue-section-header">
                <span
                    class="inventory-issue-section-icon"
                    aria-hidden="true"
                >
                    ⇄
                </span>

                <div>
                    <h2>בחירת פעולה</h2>
                    <p>בחר אם להוציא ציוד או להוסיף מלאי לפריט קיים.</p>
                </div>
            </div>

            <div class="inventory-issue-section-body">
                <div class="inventory-issue-operation-grid">
                    <label class="inventory-issue-operation is-selected">
                        <input
                            type="radio"
                            name="inventory_issue_operation"
                            value="remove"
                            checked
                        >

                        <span class="inventory-issue-operation-icon">
                            📤
                        </span>

                        <span>
                            <strong>הוצאת ציוד</strong>
                            <small>הפחתת כמות מהמלאי הקיים</small>
                        </span>
                    </label>

                    <label class="inventory-issue-operation">
                        <input
                            type="radio"
                            name="inventory_issue_operation"
                            value="add"
                        >

                        <span class="inventory-issue-operation-icon">
                            📥
                        </span>

                        <span>
                            <strong>הוספת מלאי</strong>
                            <small>הוספת כמות לפריט קיים</small>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="inventory-issue-section">
            <div class="inventory-issue-section-header">
                <span
                    class="inventory-issue-section-icon"
                    aria-hidden="true"
                >
                    🔎
                </span>

                <div>
                    <h2>בחירת פריט</h2>
                    <p>חפש לפי שם, קוד, ברקוד או QR.</p>
                </div>
            </div>

            <div class="inventory-issue-section-body">
                <div class="form-group inventory-issue-search-group">
                    <label for="inventoryIssueSearch">
                        חיפוש פריט
                        <span class="required-marker">*</span>
                    </label>

                    <div class="inventory-issue-search-wrapper">
                        <input
                            type="search"
                            id="inventoryIssueSearch"
                            placeholder="שם פריט, קוד, ברקוד או QR..."
                            autocomplete="off"
                        >

                        <span
                            id="inventoryIssueSearchSpinner"
                            class="inventory-issue-spinner"
                            hidden
                        ></span>
                    </div>

                    <div
                        id="inventoryIssueSearchResults"
                        class="inventory-issue-search-results"
                        hidden
                    ></div>
                </div>

                <input
                    type="hidden"
                    id="inventoryIssueItemId"
                >

                <div
                    id="inventoryIssueSelectedItem"
                    class="inventory-issue-selected-item"
                    hidden
                >
                    <div class="inventory-issue-selected-main">
                        <div>
                            <span class="inventory-issue-selected-label">
                                הפריט שנבחר
                            </span>

                            <h3 id="inventoryIssueItemName"></h3>

                            <span
                                id="inventoryIssueItemCode"
                                class="inventory-issue-item-code"
                            ></span>
                        </div>

                        <button
                            type="button"
                            id="inventoryIssueChangeItem"
                            class="button button-secondary"
                        >
                            החלפת פריט
                        </button>
                    </div>

                    <div class="inventory-issue-item-data">
                        <article>
                            <span>מלאי קיים</span>
                            <strong id="inventoryIssueItemQuantity">0</strong>
                        </article>

                        <article>
                            <span>יחידת מידה</span>
                            <strong id="inventoryIssueItemUnit">—</strong>
                        </article>

                        <article>
                            <span>מחסן / מיקום</span>
                            <strong id="inventoryIssueItemLocation">—</strong>
                        </article>

                        <article>
                            <span>מדף / תא</span>
                            <strong id="inventoryIssueItemShelf">—</strong>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="inventory-issue-section">
            <div class="inventory-issue-section-header">
                <span
                    id="inventoryIssueDetailsIcon"
                    class="inventory-issue-section-icon"
                    aria-hidden="true"
                >
                    📤
                </span>

                <div>
                    <h2 id="inventoryIssueDetailsTitle">
                        פרטי ההוצאה
                    </h2>

                    <p id="inventoryIssueDetailsDescription">
                        הכמות ופרטי האדם או הגורם שמקבל את הציוד.
                    </p>
                </div>
            </div>

            <div class="inventory-issue-section-body">
                <div class="inventory-issue-grid">
                    <div class="form-group">
                        <label for="inventoryIssueQuantity">
                            <span id="inventoryIssueQuantityLabel">
                                כמות להוצאה
                            </span>

                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="number"
                            id="inventoryIssueQuantity"
                            min="0.001"
                            step="0.001"
                            inputmode="decimal"
                            required
                        >

                        <small id="inventoryIssueQuantityHint">
                            בחר פריט כדי לראות את הכמות הזמינה.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="inventoryIssueRecipient">
                            <span id="inventoryIssueRecipientLabel">
                                נמסר ל־
                            </span>

                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="text"
                            id="inventoryIssueRecipient"
                            maxlength="190"
                            placeholder="שם עובד, צוות או מחלקה"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="inventoryIssueDepartment">
                            <span id="inventoryIssueDepartmentLabel">
                                מחלקה
                            </span>
                        </label>

                        <input
                            type="text"
                            id="inventoryIssueDepartment"
                            maxlength="190"
                            placeholder="לדוגמה: ייצור, אחזקה, איכות"
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group">
                        <label for="inventoryIssueReference">
                            פקודת עבודה / פרויקט / אסמכתה
                        </label>

                        <input
                            type="text"
                            id="inventoryIssueReference"
                            maxlength="100"
                            placeholder="לדוגמה: פק״ע 12345"
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group inventory-issue-full">
                        <label for="inventoryIssuePurpose">
                            <span id="inventoryIssuePurposeLabel">
                                מטרת ההוצאה
                            </span>

                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="text"
                            id="inventoryIssuePurpose"
                            maxlength="250"
                            placeholder="לדוגמה: התקנה במכונה, טיפול תקלה, פרויקט..."
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-group inventory-issue-full">
                        <label for="inventoryIssueNotes">
                            הערות נוספות
                        </label>

                        <textarea
                            id="inventoryIssueNotes"
                            rows="4"
                            maxlength="1000"
                            placeholder="מידע נוסף על הפעולה..."
                        ></textarea>
                    </div>
                </div>
            </div>
        </section>

        <div
            id="inventoryIssueError"
            class="inventory-issue-message is-error"
            hidden
            role="alert"
        ></div>

        <div
            id="inventoryIssueSuccess"
            class="inventory-issue-message is-success"
            hidden
            role="status"
        ></div>

        <div class="inventory-issue-actions">
            <button
                type="button"
                id="inventoryIssueClearButton"
                class="button button-secondary"
            >
                ניקוי הטופס
            </button>

            <button
                type="submit"
                id="inventoryIssueSubmitButton"
                class="button button-primary"
            >
                <span id="inventoryIssueSubmitText">
                    הוצאת ציוד
                </span>

                <span
                    id="inventoryIssueSubmitSpinner"
                    class="inventory-issue-spinner"
                    hidden
                ></span>
            </button>
        </div>
    </form>
</section>

<script>
    window.inventoryIssueConfig = {
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
    src="<?= escape(APP_URL) ?>/assets/js/inventory-issue.js?v=2"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';