<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
requireLogin();

$pdo = Database::getConnection();
$userId = currentUserId();

if ($userId === null) {
    redirect(APP_URL . '/public/login.php');
}

$statement = $pdo->prepare(
    "SELECT
        id,
        username,
        full_name,
        email,
        phone,
        role,
        is_active,
        last_login_at,
        last_login_ip,
        password_changed_at,
        created_at
     FROM users
     WHERE id = :id
     LIMIT 1"
);

$statement->execute([
    'id' => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    logoutUser();
}

$pageTitle = 'הפרופיל שלי';
$activePage = 'profile';
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../views/layouts/app-header.php';
?>

<link
    rel="stylesheet"
    href="<?= escape(APP_URL) ?>/assets/css/profile.css?v=1"
>

<section class="profile-page" dir="rtl">
    <div class="profile-header">
        <div>
            <p class="profile-eyebrow">חשבון משתמש</p>
            <h1>הפרופיל שלי</h1>
            <p>עדכון פרטים אישיים וניהול אבטחת החשבון.</p>
        </div>

        <div class="profile-avatar">
            <?= escape(
                mb_substr(
                    (string) $user['full_name'],
                    0,
                    1
                )
            ) ?>
        </div>
    </div>

    <div class="profile-layout">
        <section class="profile-card">
            <div class="profile-card-header">
                <div>
                    <h2>פרטים אישיים</h2>
                    <p>השם, הדוא״ל והטלפון שלך.</p>
                </div>
            </div>

            <form id="profileForm" novalidate>
                <div class="profile-form-grid">
                    <div class="form-group">
                        <label for="profileUsername">
                            שם משתמש
                        </label>

                        <input
                            type="text"
                            id="profileUsername"
                            value="<?= escape((string) $user['username']) ?>"
                            disabled
                            dir="ltr"
                        >

                        <small>
                            שינוי שם משתמש מתבצע על ידי מנהל.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="profileFullName">
                            שם מלא *
                        </label>

                        <input
                            type="text"
                            id="profileFullName"
                            value="<?= escape((string) $user['full_name']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="profileEmail">
                            דוא״ל
                        </label>

                        <input
                            type="email"
                            id="profileEmail"
                            value="<?= escape((string) ($user['email'] ?? '')) ?>"
                            maxlength="190"
                            dir="ltr"
                        >
                    </div>

                    <div class="form-group">
                        <label for="profilePhone">
                            טלפון
                        </label>

                        <input
                            type="text"
                            id="profilePhone"
                            value="<?= escape((string) ($user['phone'] ?? '')) ?>"
                            maxlength="30"
                            dir="ltr"
                        >
                    </div>
                </div>

                <div
                    id="profileFormError"
                    class="profile-message is-error"
                    hidden
                ></div>

                <div
                    id="profileFormSuccess"
                    class="profile-message is-success"
                    hidden
                ></div>

                <div class="profile-actions">
                    <button
                        type="submit"
                        id="saveProfileButton"
                        class="button button-primary"
                    >
                        שמירת פרטים
                    </button>
                </div>
            </form>
        </section>

        <aside class="profile-side">
            <section class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h2>אבטחת החשבון</h2>
                        <p>סיסמה ופרטי כניסה.</p>
                    </div>
                </div>

                <div class="profile-security-list">
                    <div class="profile-security-row">
                        <span>תפקיד</span>
                        <strong>
                            <?= $user['role'] === 'admin'
                                ? 'מנהל מערכת'
                                : 'משתמש'
                            ?>
                        </strong>
                    </div>

                    <div class="profile-security-row">
                        <span>כניסה אחרונה</span>
                        <strong>
                            <?= escape(
                                formatDateTimeHe(
                                    $user['last_login_at']
                                ) ?: 'לא קיימת'
                            ) ?>
                        </strong>
                    </div>

                    <div class="profile-security-row">
                        <span>IP אחרון</span>
                        <strong dir="ltr">
                            <?= escape(
                                (string) (
                                    $user['last_login_ip'] ??
                                    'לא קיים'
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div class="profile-security-row">
                        <span>החלפת סיסמה אחרונה</span>
                        <strong>
                            <?= escape(
                                formatDateTimeHe(
                                    $user['password_changed_at']
                                ) ?: 'לא ידוע'
                            ) ?>
                        </strong>
                    </div>
                </div>

                <a
                    href="<?= escape(APP_URL) ?>/public/change-password.php"
                    class="button button-secondary profile-password-button"
                >
                    שינוי סיסמה
                </a>
            </section>

            <section class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h2>פרטי חשבון</h2>
                    </div>
                </div>

                <div class="profile-security-list">
                    <div class="profile-security-row">
                        <span>נוצר בתאריך</span>
                        <strong>
                            <?= escape(
                                formatDateTimeHe(
                                    $user['created_at']
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div class="profile-security-row">
                        <span>מצב</span>
                        <strong>
                            <?= (int) $user['is_active'] === 1
                                ? 'פעיל'
                                : 'מושבת'
                            ?>
                        </strong>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</section>

<div
    id="profileToastContainer"
    class="profile-toast-container"
    aria-live="polite"
></div>

<script>
    window.profileConfig = {
        appUrl: <?= json_encode(
            rtrim(APP_URL, '/'),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>,
        csrfToken: <?= json_encode(
            $csrfToken,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>
    };
</script>

<script
    src="<?= escape(APP_URL) ?>/assets/js/profile.js?v=1"
    defer
></script>

<?php
require_once __DIR__ . '/../views/layouts/app-footer.php';
