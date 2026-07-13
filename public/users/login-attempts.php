<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireAdmin();

$pageTitle = 'היסטוריית התחברויות';
$activePage = 'users';

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/login-attempts.css?v=1"
>

<section class="login-attempts-page" dir="rtl">
    <div class="login-attempts-header">
        <div>
            <a
                href="<?= escape(APP_URL) ?>/public/users/"
                class="login-attempts-back"
            >
                <span aria-hidden="true">→</span>
                <span>חזרה למשתמשים</span>
            </a>

            <p class="login-attempts-eyebrow">אבטחה</p>
            <h1>היסטוריית התחברויות</h1>

            <p>
                מעקב אחר כניסות מוצלחות, ניסיונות כושלים,
                חסימות ורובוטים.
            </p>
        </div>
    </div>

    <section class="login-attempts-stats">
        <article class="login-attempts-stat">
            <span>סה״כ ניסיונות</span>
            <strong id="loginAttemptsTotalCount">0</strong>
        </article>

        <article class="login-attempts-stat is-success">
            <span>כניסות מוצלחות</span>
            <strong id="loginAttemptsSuccessCount">0</strong>
        </article>

        <article class="login-attempts-stat is-danger">
            <span>ניסיונות כושלים</span>
            <strong id="loginAttemptsFailedCount">0</strong>
        </article>

        <article class="login-attempts-stat">
            <span>כתובות IP חשודות</span>
            <strong id="loginAttemptsFailedIpCount">0</strong>
        </article>
    </section>

    <section class="login-attempts-filters">
        <div class="form-group login-attempts-search-group">
            <label for="loginAttemptsSearch">חיפוש</label>
            <input
                type="search"
                id="loginAttemptsSearch"
                placeholder="שם משתמש, שם מלא או כתובת IP..."
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <label for="loginAttemptsUserFilter">משתמש</label>
            <select id="loginAttemptsUserFilter">
                <option value="">כל המשתמשים</option>
            </select>
        </div>

        <div class="form-group">
            <label for="loginAttemptsResultFilter">תוצאה</label>
            <select id="loginAttemptsResultFilter">
                <option value="all">הכול</option>
                <option value="success">הצלחה</option>
                <option value="failed">כישלון</option>
            </select>
        </div>

        <div class="form-group">
            <label for="loginAttemptsReasonFilter">סיבה</label>
            <select id="loginAttemptsReasonFilter">
                <option value="all">כל הסיבות</option>
            </select>
        </div>

        <div class="form-group">
            <label for="loginAttemptsDateFrom">מתאריך</label>
            <input type="date" id="loginAttemptsDateFrom">
        </div>

        <div class="form-group">
            <label for="loginAttemptsDateTo">עד תאריך</label>
            <input type="date" id="loginAttemptsDateTo">
        </div>

        <div class="login-attempts-filter-actions">
            <button
                type="button"
                id="clearLoginAttemptsFilters"
                class="button button-secondary"
            >
                ניקוי
            </button>

            <button
                type="button"
                id="refreshLoginAttempts"
                class="button button-primary"
            >
                רענון
            </button>
        </div>
    </section>

    <div
        id="loginAttemptsLoading"
        class="login-attempts-loading"
    >
        <span class="login-attempts-spinner"></span>
        <span>טוען היסטוריה...</span>
    </div>

    <div
        id="loginAttemptsEmpty"
        class="login-attempts-empty"
        hidden
    >
        לא נמצאו ניסיונות התחברות.
    </div>

    <div
        id="loginAttemptsTableWrapper"
        class="login-attempts-table-wrapper"
        hidden
    >
        <table class="login-attempts-table">
            <thead>
                <tr>
                    <th>תאריך</th>
                    <th>משתמש</th>
                    <th>תוצאה</th>
                    <th>סיבה</th>
                    <th>IP</th>
                    <th>דפדפן / מכשיר</th>
                </tr>
            </thead>
            <tbody id="loginAttemptsTableBody"></tbody>
        </table>
    </div>

    <div class="login-attempts-pagination">
        <button
            type="button"
            id="loginAttemptsPreviousPage"
            class="button button-secondary"
        >
            הקודם
        </button>

        <span id="loginAttemptsPageText">
            עמוד 1 מתוך 1
        </span>

        <button
            type="button"
            id="loginAttemptsNextPage"
            class="button button-secondary"
        >
            הבא
        </button>
    </div>
</section>

<div
    id="loginAttemptsToastContainer"
    class="login-attempts-toast-container"
    aria-live="polite"
></div>

<script>
    window.loginAttemptsConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/login-attempts.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
