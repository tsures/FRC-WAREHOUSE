<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireAdmin();

$pageTitle = 'ניהול משתמשים';
$activePage = 'users';
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/users.css?v=1"
>

<section class="users-page" dir="rtl">
    <div class="users-page-header">
        <div>
            <p class="users-eyebrow">ניהול מערכת</p>
            <h1>משתמשים</h1>
            <p>יצירה, עריכה, הפעלה, השבתה ואיפוס נעילה.</p>
        </div>

        <button
            type="button"
            id="addUserButton"
            class="button button-primary"
        >
            <span aria-hidden="true">＋</span>
            <span>הוספת משתמש</span>
        </button>
    </div>

    <section class="users-filters">
        <div class="form-group users-search-group">
            <label for="usersSearch">חיפוש</label>

            <input
                type="search"
                id="usersSearch"
                placeholder="שם, שם משתמש, דוא״ל או טלפון..."
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <label for="usersRoleFilter">תפקיד</label>

            <select id="usersRoleFilter">
                <option value="all">כל התפקידים</option>
                <option value="admin">מנהל מערכת</option>
                <option value="user">משתמש</option>
            </select>
        </div>

        <div class="form-group">
            <label for="usersActiveFilter">מצב</label>

            <select id="usersActiveFilter">
                <option value="all">הכול</option>
                <option value="active">פעילים</option>
                <option value="inactive">מושבתים</option>
            </select>
        </div>

        <div class="users-filter-actions">
            <button
                type="button"
                id="clearUsersFilters"
                class="button button-secondary"
            >
                ניקוי
            </button>

            <button
                type="button"
                id="refreshUsers"
                class="button button-primary"
            >
                רענון
            </button>
        </div>
    </section>

    <div class="users-count">
        משתמשים מוצגים:
        <strong id="usersCount">0</strong>
    </div>

    <div id="usersLoading" class="users-loading">
        <span class="users-spinner"></span>
        <span>טוען משתמשים...</span>
    </div>

    <div id="usersEmpty" class="users-empty" hidden>
        לא נמצאו משתמשים.
    </div>

    <section id="usersGrid" class="users-grid" hidden></section>
</section>

<div id="userModal" class="users-modal" hidden>
    <div class="users-modal-backdrop" data-close-user-modal></div>

    <section
        class="users-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="userModalTitle"
    >
        <div class="users-modal-header">
            <h2 id="userModalTitle">הוספת משתמש</h2>

            <button
                type="button"
                class="users-modal-close"
                data-close-user-modal
                aria-label="סגירה"
            >
                ×
            </button>
        </div>

        <form id="userForm" novalidate>
            <input type="hidden" id="userId">

            <div class="users-form-grid">
                <div class="form-group">
                    <label for="userUsername">שם משתמש *</label>
                    <input
                        type="text"
                        id="userUsername"
                        maxlength="80"
                        dir="ltr"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="userFullName">שם מלא *</label>
                    <input
                        type="text"
                        id="userFullName"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="userEmail">דוא״ל</label>
                    <input
                        type="email"
                        id="userEmail"
                        maxlength="190"
                        dir="ltr"
                    >
                </div>

                <div class="form-group">
                    <label for="userPhone">טלפון</label>
                    <input
                        type="text"
                        id="userPhone"
                        maxlength="30"
                        dir="ltr"
                    >
                </div>

                <div class="form-group">
                    <label for="userRole">תפקיד</label>
                    <select id="userRole">
                        <option value="user">משתמש</option>
                        <option value="admin">מנהל מערכת</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="userPassword">
                        סיסמה
                        <span id="userPasswordRequired">*</span>
                    </label>

                    <input
                        type="password"
                        id="userPassword"
                        minlength="8"
                        autocomplete="new-password"
                        dir="ltr"
                    >

                    <small id="userPasswordHelp">
                        לפחות 8 תווים.
                    </small>
                </div>

                <label class="users-check">
                    <input
                        type="checkbox"
                        id="userIsActive"
                        checked
                    >
                    <span>משתמש פעיל</span>
                </label>

                <label class="users-check">
                    <input
                        type="checkbox"
                        id="userMustChangePassword"
                    >
                    <span>חייב להחליף סיסמה בכניסה הבאה</span>
                </label>
            </div>

            <div
                id="userFormError"
                class="users-form-error"
                hidden
            ></div>

            <div class="users-modal-actions">
                <button
                    type="button"
                    class="button button-secondary"
                    data-close-user-modal
                >
                    ביטול
                </button>

                <button
                    type="submit"
                    id="saveUserButton"
                    class="button button-primary"
                >
                    שמירה
                </button>
            </div>
        </form>
    </section>
</div>

<div
    id="usersToastContainer"
    class="users-toast-container"
    aria-live="polite"
></div>

<script>
    window.usersConfig = {
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
        currentUserId: <?= (int) (currentUserId() ?? 0) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/users.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';
