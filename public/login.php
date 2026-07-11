<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
requireGuest();

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validateCsrfToken(
        is_string($csrfToken) ? $csrfToken : null
    )) {
        $error = 'הבקשה אינה חוקית או שפג תוקפה.';
    } else {
        $username = trim(
            (string) ($_POST['username'] ?? '')
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        $rememberMe =
            isset($_POST['remember_me']) &&
            $_POST['remember_me'] === '1';

        try {
            $result = attemptLogin(
                $username,
                $password,
                $rememberMe
            );

            if ($result['success']) {
                $returnUrl = $_SESSION['return_url'] ?? null;

                unset($_SESSION['return_url']);

                if (
                    is_string($returnUrl) &&
                    str_starts_with($returnUrl, '/warehouse/')
                ) {
                    redirect($returnUrl);
                }

                redirect(APP_URL . '/public/');
            }

            $error = $result['message'];
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $error = 'אירעה שגיאה בהתחברות. נסה שוב.';
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

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <title>
        התחברות | <?= escape(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/app.css"
    >

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/login.css"
    >
</head>

<body>
    <main class="login-page">
        <section
            class="login-card"
            aria-labelledby="login-title"
        >
            <header class="login-brand">
                <div
                    class="login-logo"
                    aria-hidden="true"
                >
                    3083
                </div>

                <h1
                    id="login-title"
                    class="login-title"
                >
                    מערכת ניהול המחסן
                </h1>

                <p class="login-subtitle">
                    FRC Team 3083
                </p>
            </header>

            <?php if ($error !== ''): ?>
                <div
                    class="alert alert-error"
                    role="alert"
                >
                    <?= escape($error) ?>
                </div>
            <?php endif; ?>

            <form
                id="login-form"
                class="login-form"
                method="post"
                autocomplete="on"
            >
                <?= csrfInput() ?>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="username"
                    >
                        שם משתמש
                    </label>

                    <input
                        id="username"
                        class="form-input"
                        name="username"
                        type="text"
                        dir="ltr"
                        value="<?= escape($username) ?>"
                        required
                        maxlength="80"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                    >
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="password"
                    >
                        סיסמה
                    </label>

                    <div class="password-wrapper">
                        <input
                            id="password"
                            class="form-input"
                            name="password"
                            type="password"
                            dir="ltr"
                            required
                            autocomplete="current-password"
                        >

                        <button
                            id="password-toggle"
                            class="password-toggle"
                            type="button"
                            aria-label="הצגת סיסמה"
                        >
                            👁
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="checkbox-label">
                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1"
                        >

                        <span>זכור אותי</span>
                    </label>

                    <a
                        class="forgot-link"
                        href="<?= escape(APP_URL) ?>/public/forgot-password.php"
                    >
                        שכחתי סיסמה
                    </a>
                </div>

                <button
                    id="login-submit"
                    class="button button-primary login-button"
                    type="submit"
                >
                    התחברות
                </button>
            </form>

            <footer class="login-footer">
                מערכת פנימית עבור צוות FRC 3083
            </footer>
        </section>
    </main>

    <script
        src="<?= escape(APP_URL) ?>/assets/js/login.js"
        defer
    ></script>
</body>
</html>