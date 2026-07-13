<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/password_reset_helpers.php';

startSecureSession();
requireGuest();

$pdo = Database::getConnection();

$token = trim(
    (string) (
        $_GET['token'] ??
        $_POST['token'] ??
        ''
    )
);

$error = '';
$success = '';

$record = getValidPasswordResetRecord(
    $pdo,
    $token
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validateCsrfToken(
        is_string($csrfToken) ? $csrfToken : null
    )) {
        $error = 'הבקשה אינה חוקית או שפג תוקפה.';
    } elseif ($record === null) {
        $error =
            'קישור האיפוס אינו תקף או שפג תוקפו.';
    } else {
        $newPassword = (string) (
            $_POST['new_password'] ?? ''
        );

        $confirmPassword = (string) (
            $_POST['confirm_password'] ?? ''
        );

        if (mb_strlen($newPassword) < 8) {
            $error =
                'הסיסמה החדשה חייבת להכיל לפחות 8 תווים.';
        } elseif ($newPassword !== $confirmPassword) {
            $error =
                'אימות הסיסמה אינו תואם.';
        } else {
            try {
                consumePasswordResetToken(
                    $pdo,
                    (int) $record['id'],
                    (int) $record['user_id'],
                    $newPassword
                );

                logAuthenticationAction(
                    (int) $record['user_id'],
                    'password_reset_completed'
                );

                $success =
                    'הסיסמה אופסה בהצלחה. ניתן להתחבר באמצעות הסיסמה החדשה.';

                $record = null;
            } catch (Throwable $exception) {
                error_log(
                    'Reset password error: ' .
                    $exception->getMessage()
                );

                $error =
                    'לא ניתן לאפס את הסיסמה כעת.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="theme-color" content="#2563eb">

    <title>
        איפוס סיסמה | <?= escape(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/app.css"
    >

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/login.css"
    >

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/password-reset.css?v=1"
    >
</head>

<body>
    <main class="login-page">
        <section
            class="login-card"
            aria-labelledby="reset-password-title"
        >
            <header class="login-brand">
                <div class="login-logo" aria-hidden="true">
                    3083
                </div>

                <h1
                    id="reset-password-title"
                    class="login-title"
                >
                    איפוס סיסמה
                </h1>

                <p class="login-subtitle">
                    בחר סיסמה חדשה לחשבון
                </p>
            </header>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?= escape($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= escape($success) ?>
                </div>

                <a
                    href="<?= escape(APP_URL) ?>/public/login.php"
                    class="button button-primary login-button"
                >
                    מעבר להתחברות
                </a>
            <?php elseif ($record !== null): ?>
                <form
                    id="resetPasswordForm"
                    class="login-form"
                    method="post"
                    autocomplete="off"
                >
                    <?= csrfInput() ?>

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escape($token) ?>"
                    >

                    <div class="form-field">
                        <label
                            class="form-label"
                            for="new_password"
                        >
                            סיסמה חדשה
                        </label>

                        <div class="password-wrapper">
                            <input
                                id="new_password"
                                class="form-input"
                                name="new_password"
                                type="password"
                                dir="ltr"
                                minlength="8"
                                required
                                autocomplete="new-password"
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-password-toggle="new_password"
                                aria-label="הצגת סיסמה"
                            >
                                👁
                            </button>
                        </div>
                    </div>

                    <div class="form-field">
                        <label
                            class="form-label"
                            for="confirm_password"
                        >
                            אימות סיסמה חדשה
                        </label>

                        <div class="password-wrapper">
                            <input
                                id="confirm_password"
                                class="form-input"
                                name="confirm_password"
                                type="password"
                                dir="ltr"
                                minlength="8"
                                required
                                autocomplete="new-password"
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-password-toggle="confirm_password"
                                aria-label="הצגת סיסמה"
                            >
                                👁
                            </button>
                        </div>
                    </div>

                    <div
                        id="resetPasswordClientError"
                        class="alert alert-error"
                        hidden
                    ></div>

                    <button
                        class="button button-primary login-button"
                        type="submit"
                    >
                        איפוס הסיסמה
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-error" role="alert">
                    קישור האיפוס אינו תקף או שפג תוקפו.
                </div>

                <a
                    href="<?= escape(APP_URL) ?>/public/forgot-password.php"
                    class="button button-secondary login-button"
                >
                    בקשת קישור חדש
                </a>
            <?php endif; ?>

            <footer class="login-footer">
                <a href="<?= escape(APP_URL) ?>/public/login.php">
                    חזרה להתחברות
                </a>
            </footer>
        </section>
    </main>

    <script
        src="<?= escape(APP_URL) ?>/assets/js/password-reset.js?v=1"
        defer
    ></script>
</body>
</html>
