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

$error = '';
$success = '';
$identifier = '';

$antiBotState = $_SESSION['forgot_password_antibot'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $antiBotState = initializeForgotPasswordAntiBot();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validateCsrfToken(
        is_string($csrfToken) ? $csrfToken : null
    )) {
        $error = 'הבקשה אינה חוקית או שפג תוקפה.';
    } else {
        $identifier = trim(
            (string) ($_POST['identifier'] ?? '')
        );

        $antiBotValid = validateForgotPasswordAntiBot(
            isset($_POST['forgot_form_token'])
                ? (string) $_POST['forgot_form_token']
                : null,
            trim((string) ($_POST['company'] ?? ''))
        );

        if (!$antiBotValid) {
            usleep(500000);

            $error =
                'לא ניתן להשלים את הבקשה. יש לרענן את הדף ולנסות שוב.';

            $antiBotState = initializeForgotPasswordAntiBot();
        } elseif ($identifier === '') {
            $error =
                'יש להזין שם משתמש או כתובת דוא״ל.';

            $antiBotState = initializeForgotPasswordAntiBot();
        } else {
            try {
                $pdo = Database::getConnection();

                $statement = $pdo->prepare(
                    "SELECT
                        id,
                        username,
                        full_name,
                        email,
                        is_active
                     FROM users
                     WHERE username = :username
                        OR email = :email
                     LIMIT 1"
                );

                $statement->execute([
                    'username' => $identifier,
                    'email' => $identifier
                ]);

                $user = $statement->fetch(PDO::FETCH_ASSOC);

                if (
                    $user &&
                    (int) $user['is_active'] === 1 &&
                    !empty($user['email'])
                ) {
                    $token = createPasswordResetToken(
                        $pdo,
                        (int) $user['id'],
                        30
                    );

                    $scheme = isHttpsRequest()
                        ? 'https'
                        : 'http';

                    $host = $_SERVER['HTTP_HOST'] ?? '';

                    if ($host === '') {
                        throw new RuntimeException(
                            'לא ניתן לזהות את כתובת האתר.'
                        );
                    }

                    $resetUrl =
                        $scheme .
                        '://' .
                        $host .
                        rtrim(APP_URL, '/') .
                        '/public/reset-password.php?token=' .
                        urlencode($token);

                    $sent = sendPasswordResetEmail(
                        (string) $user['email'],
                        (string) $user['full_name'],
                        $resetUrl
                    );

                    if (!$sent) {
                        error_log(
                            'Password reset email could not be sent for user ID ' .
                            (int) $user['id']
                        );
                    }

                    try {
                        $logStatement = $pdo->prepare(
                            "INSERT INTO activity_logs (
                                user_id,
                                action,
                                entity_type,
                                entity_id,
                                ip_address,
                                user_agent
                            ) VALUES (
                                :user_id,
                                'password_reset_requested',
                                'user',
                                :entity_id,
                                :ip_address,
                                :user_agent
                            )"
                        );

                        $logStatement->execute([
                            'user_id' => (int) $user['id'],
                            'entity_id' => (int) $user['id'],
                            'ip_address' => getClientIp(),
                            'user_agent' => getUserAgent()
                        ]);
                    } catch (Throwable $logException) {
                        error_log(
                            'Password reset request log error: ' .
                            $logException->getMessage()
                        );
                    }
                }

                $success =
                    'אם קיים חשבון פעיל עם הפרטים שהוזנו, נשלח אליו קישור לאיפוס הסיסמה.';

                $identifier = '';
                $antiBotState = initializeForgotPasswordAntiBot();
            } catch (Throwable $exception) {
                error_log(
                    'Forgot password error: ' .
                    $exception->getMessage()
                );

                $error =
                    'לא ניתן להשלים את הבקשה כעת. נסה שוב מאוחר יותר.';

                $antiBotState = initializeForgotPasswordAntiBot();
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
        שכחתי סיסמה | <?= escape(APP_NAME) ?>
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
            aria-labelledby="forgot-password-title"
        >
            <header class="login-brand">
                <div class="login-logo" aria-hidden="true">
                    3083
                </div>

                <h1
                    id="forgot-password-title"
                    class="login-title"
                >
                    שכחתי סיסמה
                </h1>

                <p class="login-subtitle">
                    הזן שם משתמש או כתובת דוא״ל
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
            <?php endif; ?>

            <form
                class="login-form"
                method="post"
                autocomplete="off"
            >
                <?= csrfInput() ?>

                <input
                    type="hidden"
                    name="forgot_form_token"
                    value="<?= escape(
                        (string) (
                            $antiBotState['token'] ?? ''
                        )
                    ) ?>"
                >

                <div
                    class="password-reset-honeypot"
                    aria-hidden="true"
                >
                    <label for="company">Company</label>

                    <input
                        id="company"
                        name="company"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="identifier"
                    >
                        שם משתמש או דוא״ל
                    </label>

                    <input
                        id="identifier"
                        class="form-input"
                        name="identifier"
                        type="text"
                        dir="ltr"
                        value="<?= escape($identifier) ?>"
                        required
                        maxlength="190"
                        autocomplete="username"
                    >
                </div>

                <button
                    class="button button-primary login-button"
                    type="submit"
                >
                    שליחת קישור לאיפוס
                </button>
            </form>

            <footer class="login-footer">
                <a href="<?= escape(APP_URL) ?>/public/login.php">
                    חזרה להתחברות
                </a>
            </footer>
        </section>
    </main>
</body>
</html>
