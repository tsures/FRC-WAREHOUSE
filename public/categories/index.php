<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireAdmin();

$pageTitle = 'קטגוריות';
$activePage = 'categories';

$csrfToken = generateCsrfToken();

require __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/categories.css?v=2"
>

<meta
    name="csrf-token"
    content="<?= escape($csrfToken) ?>"
>

<section class="categories-page">

    <div class="categories-toolbar">

        <div class="categories-search-wrapper">
            <span aria-hidden="true">🔍</span>

            <input
                id="category-search"
                class="categories-search"
                type="search"
                placeholder="חיפוש קטגוריה..."
                autocomplete="off"
            >
        </div>

        <button
            id="add-category-button"
            class="button button-primary"
            type="button"
        >
            <span>＋</span>
            <span>הוספת קטגוריה</span>
        </button>

    </div>

    <section class="panel">

        <div class="panel-header">
            <div>
                <h2 class="panel-title">
                    רשימת קטגוריות
                </h2>

                <p
                    id="category-count"
                    class="category-count"
                >
                    טוען...
                </p>
            </div>
        </div>

        <div
            id="categories-loading"
            class="categories-loading"
        >
            <div class="loading-spinner"></div>
            <span>טוען קטגוריות...</span>
        </div>

        <div
            id="categories-empty"
            class="categories-empty"
            hidden
        >
            <div class="empty-icon">🗂️</div>
            <h3>לא נמצאו קטגוריות</h3>
            <p>אפשר להוסיף את הקטגוריה הראשונה.</p>
        </div>

        <div
            id="categories-list"
            class="categories-list"
        ></div>

    </section>

</section>

<div
    id="category-modal"
    class="modal"
    hidden
>
    <div
        class="modal-backdrop"
        data-close-modal
    ></div>

    <section
        class="modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="category-modal-title"
    >
        <header class="modal-header">
            <div>
                <h2
                    id="category-modal-title"
                    class="modal-title"
                >
                    הוספת קטגוריה
                </h2>

                <p class="modal-subtitle">
                    הגדרת פרטי הקטגוריה וההיררכיה
                </p>
            </div>

            <button
                class="icon-button modal-close"
                type="button"
                data-close-modal
                aria-label="סגירת חלון"
            >
                ✕
            </button>
        </header>

        <form
            id="category-form"
            class="category-form"
        >
            <input
                id="category-id"
                type="hidden"
            >

            <div class="form-grid">

                <div class="form-field form-field-full">
                    <label
                        class="form-label"
                        for="category-name-he"
                    >
                        שם בעברית
                    </label>

                    <input
                        id="category-name-he"
                        class="form-input"
                        type="text"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="form-field form-field-full">
                    <label
                        class="form-label"
                        for="category-name-en"
                    >
                        שם באנגלית
                    </label>

                    <input
                        id="category-name-en"
                        class="form-input"
                        type="text"
                        dir="ltr"
                        maxlength="150"
                    >
                </div>

                <div class="form-field form-field-full">
                    <label
                        class="form-label"
                        for="category-parent"
                    >
                        קטגוריית אב
                    </label>

                    <select
                        id="category-parent"
                        class="form-input"
                    >
                        <option value="">
                            קטגוריה ראשית
                        </option>
                    </select>
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="category-icon"
                    >
                        אייקון
                    </label>

                    <input
                        id="category-icon"
                        class="form-input"
                        type="text"
                        maxlength="100"
                        placeholder="לדוגמה: ⚙️"
                    >
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="category-color"
                    >
                        צבע
                    </label>

                    <input
                        id="category-color"
                        class="form-input color-input"
                        type="color"
                        value="#2563eb"
                    >
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="category-sort-order"
                    >
                        סדר תצוגה
                    </label>

                    <input
                        id="category-sort-order"
                        class="form-input"
                        type="number"
                        value="0"
                        min="-9999"
                        max="9999"
                    >
                </div>

                <div class="form-field form-field-full">
                    <label
                        class="form-label"
                        for="category-description"
                    >
                        תיאור
                    </label>

                    <textarea
                        id="category-description"
                        class="form-input form-textarea"
                        rows="4"
                    ></textarea>
                </div>

            </div>

            <footer class="modal-actions">

                <button
                    class="button secondary-button"
                    type="button"
                    data-close-modal
                >
                    ביטול
                </button>

                <button
                    id="save-category-button"
                    class="button button-primary"
                    type="submit"
                >
                    שמירה
                </button>

            </footer>
        </form>
    </section>
</div>

<div
    id="toast-container"
    class="toast-container"
    aria-live="polite"
></div>

<script>
    window.FRC_APP_URL = <?= json_encode(
        APP_URL,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?>;
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/categories.js?v=2"
    defer
></script>

<?php
require __DIR__ . '/../../views/layouts/app-footer.php';