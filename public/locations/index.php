<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/location_helpers.php';

startSecureSession();
requireAdmin();

$pageTitle = 'ניהול מיקומים';
$activePage = 'locations';

$csrfToken = generateCsrfToken();
$locationTypes = getLocationTypes();

require_once __DIR__ . '/../../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/locations.css?v=2"
>

<meta
    name="csrf-token"
    content="<?= escape($csrfToken) ?>"
>

<section class="locations-page" dir="rtl">
    <div class="locations-page-header">
        <div>
            <p class="locations-eyebrow">
                ניהול המחסן
            </p>

            <h1>מיקומים</h1>

            <p class="locations-page-description">
                ניהול היררכי של מחסנים, חדרים, ארונות, מדפים ותאים.
            </p>
        </div>

        <button
            type="button"
            class="button button-primary locations-add-button"
            id="addLocationButton"
        >
            <span aria-hidden="true">＋</span>
            <span>הוספת מיקום</span>
        </button>
    </div>

    <div
        class="locations-stats"
        aria-label="סטטיסטיקות מיקומים"
    >
        <article class="location-stat-card">
            <div
                class="location-stat-icon"
                aria-hidden="true"
            >
                📍
            </div>

            <div>
                <span class="location-stat-label">
                    סה״כ מיקומים
                </span>

                <strong id="locationsTotalCount">
                    0
                </strong>
            </div>
        </article>

        <article class="location-stat-card">
            <div
                class="location-stat-icon"
                aria-hidden="true"
            >
                ✅
            </div>

            <div>
                <span class="location-stat-label">
                    פעילים
                </span>

                <strong id="locationsActiveCount">
                    0
                </strong>
            </div>
        </article>

        <article class="location-stat-card">
            <div
                class="location-stat-icon"
                aria-hidden="true"
            >
                ⛔
            </div>

            <div>
                <span class="location-stat-label">
                    מושבתים
                </span>

                <strong id="locationsInactiveCount">
                    0
                </strong>
            </div>
        </article>

        <article class="location-stat-card">
            <div
                class="location-stat-icon"
                aria-hidden="true"
            >
                🏭
            </div>

            <div>
                <span class="location-stat-label">
                    מיקומי שורש
                </span>

                <strong id="locationsRootCount">
                    0
                </strong>
            </div>
        </article>
    </div>

    <div class="locations-toolbar">
        <div class="locations-search-wrapper">
            <span
                class="locations-search-icon"
                aria-hidden="true"
            >
                🔎
            </span>

            <input
                type="search"
                id="locationsSearch"
                class="locations-search"
                placeholder="חיפוש לפי שם, קוד, תיאור או סוג..."
                autocomplete="off"
                aria-label="חיפוש מיקומים"
            >

            <button
                type="button"
                id="clearLocationsSearch"
                class="locations-clear-search"
                aria-label="ניקוי החיפוש"
                hidden
            >
                ×
            </button>
        </div>

        <div class="locations-toolbar-controls">
            <label
                for="locationsStatusFilter"
                class="sr-only"
            >
                סינון לפי מצב
            </label>

            <select
                id="locationsStatusFilter"
                class="locations-filter-select"
            >
                <option value="all">
                    כל המיקומים
                </option>

                <option value="active">
                    פעילים בלבד
                </option>

                <option value="inactive">
                    מושבתים בלבד
                </option>
            </select>

            <button
                type="button"
                id="refreshLocationsButton"
                class="button button-secondary"
            >
                <span aria-hidden="true">↻</span>
                <span>רענון</span>
            </button>
        </div>
    </div>

    <div
        id="locationsLoading"
        class="locations-loading"
        role="status"
    >
        <span
            class="locations-spinner"
            aria-hidden="true"
        ></span>

        <span>
            טוען מיקומים...
        </span>
    </div>

    <div
        id="locationsEmptyState"
        class="locations-empty-state"
        hidden
    >
        <div
            class="locations-empty-icon"
            aria-hidden="true"
        >
            📍
        </div>

        <h2>
            לא נמצאו מיקומים
        </h2>

        <p id="locationsEmptyMessage">
            עדיין לא נוספו מיקומים למערכת.
        </p>

        <button
            type="button"
            class="button button-primary"
            id="emptyAddLocationButton"
        >
            הוספת מיקום ראשון
        </button>
    </div>

    <div
        id="locationsTree"
        class="locations-tree"
        aria-live="polite"
    ></div>
</section>

<div
    id="locationModal"
    class="location-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="locationModalTitle"
    hidden
>
    <div
        class="location-modal-backdrop"
        data-close-location-modal
    ></div>

    <div class="location-modal-dialog">
        <div class="location-modal-header">
            <div>
                <p class="location-modal-eyebrow">
                    ניהול מיקום
                </p>

                <h2 id="locationModalTitle">
                    הוספת מיקום
                </h2>
            </div>

            <button
                type="button"
                class="location-modal-close"
                data-close-location-modal
                aria-label="סגירת החלון"
            >
                ×
            </button>
        </div>

        <form
            id="locationForm"
            novalidate
        >
            <input
                type="hidden"
                id="locationId"
                name="id"
                value=""
            >

            <div class="location-form-grid">
                <div class="form-group location-form-full">
                    <label for="locationName">
                        שם המיקום
                        <span class="required-marker">*</span>
                    </label>

                    <input
                        type="text"
                        id="locationName"
                        name="name"
                        maxlength="150"
                        required
                        autocomplete="off"
                        placeholder="לדוגמה: חדר אלקטרוניקה"
                    >

                    <small
                        class="form-error"
                        id="locationNameError"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="locationCode">
                        קוד מיקום
                    </label>

                    <input
                        type="text"
                        id="locationCode"
                        name="code"
                        maxlength="80"
                        autocomplete="off"
                        placeholder="ELECTRONICS-ROOM"
                        dir="ltr"
                    >

                    <small class="form-hint">
                        אותיות באנגלית, מספרים, מקף או קו תחתון.
                    </small>

                    <small
                        class="form-error"
                        id="locationCodeError"
                    ></small>
                </div>

                <div class="form-group">
                    <label for="locationType">
                        סוג מיקום
                        <span class="required-marker">*</span>
                    </label>

                    <select
                        id="locationType"
                        name="location_type"
                        required
                    >
                        <?php foreach ($locationTypes as $typeValue => $typeLabel): ?>
                            <option
                                value="<?= escape($typeValue) ?>"
                                <?= $typeValue === 'other' ? 'selected' : '' ?>
                            >
                                <?= escape($typeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small
                        class="form-error"
                        id="locationTypeError"
                    ></small>
                </div>

                <div class="form-group location-form-full">
                    <label for="locationParent">
                        מיקום אב
                    </label>

                    <select
                        id="locationParent"
                        name="parent_id"
                    >
                        <option value="">
                            ללא מיקום אב — מיקום שורש
                        </option>
                    </select>

                    <small class="form-hint">
                        ניתן ליצור היררכיה כגון מחסן ← חדר ← ארון ← מדף ← תא.
                    </small>
                </div>

                <div class="form-group">
                    <label for="locationSortOrder">
                        סדר תצוגה
                    </label>

                    <input
                        type="number"
                        id="locationSortOrder"
                        name="sort_order"
                        min="0"
                        step="1"
                        value="0"
                    >
                </div>

                <div class="form-group location-form-full">
                    <label for="locationDescription">
                        תיאור
                    </label>

                    <textarea
                        id="locationDescription"
                        name="description"
                        rows="4"
                        maxlength="10000"
                        placeholder="פרטים נוספים על המיקום..."
                    ></textarea>
                </div>
            </div>

            <div
                id="locationFormGeneralError"
                class="location-form-general-error"
                role="alert"
                hidden
            ></div>

            <div class="location-modal-actions">
                <button
                    type="button"
                    class="button button-secondary"
                    data-close-location-modal
                >
                    ביטול
                </button>

                <button
                    type="submit"
                    class="button button-primary"
                    id="saveLocationButton"
                >
                    <span id="saveLocationButtonText">
                        שמירת מיקום
                    </span>

                    <span
                        id="saveLocationButtonSpinner"
                        class="button-spinner"
                        hidden
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </form>
    </div>
</div>

<div
    id="locationsToastContainer"
    class="locations-toast-container"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.locationsConfig = {
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
        locationTypes: <?= json_encode(
            $locationTypes,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/locations.js?v=2"
    defer
></script>

<?php
require_once __DIR__ . '/../../views/layouts/app-footer.php';