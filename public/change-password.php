<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();

if (!isLoggedIn()) {
    redirect(APP_URL . '/public/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validateCsrfToken(
        is_string($csrfToken) ? $csrfToken : null
    )) {
        $error = 'הבקשה אינה חוקית או שפג תוקפה.';
    } else {
        $currentPassword = (string) (
            $_POST['current_password'] ?? ''
        );

        $newPassword = (string) (
            $_POST['new_password'] ?? ''
        );

        $confirmPassword = (string) (
            $_POST['confirm_password'] ?? ''
        );

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            $error = 'יש למלא את כל השדות.';
        } elseif (mb_strlen($newPassword) < 8) {
            $error = 'הסיסמה החדשה חייבת להכיל לפחות 8 תווים.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'אימות הסיסמה אינו תואם.';
        } elseif ($currentPassword === $newPassword) {
            $error = 'הסיסמה החדשה חייבת להיות שונה מהסיסמה הנוכחית.';
        } else {
            try {
                $pdo = Database::getConnection();
                $userId = currentUserId();

                if ($userId === null) {
                    throw new RuntimeException(
                        'לא נמצא משתמש מחובר.'
                    );
                }

                $statement = $pdo->prepare(
                    "SELECT password_hash
                     FROM users
                     WHERE id = :id
                     LIMIT 1"
                );

                $statement->execute([
                    'id' => $userId
                ]);

                $passwordHash = $statement->fetchColumn();

                if (
                    !is_string($passwordHash) ||
                    !password_verify(
                        $currentPassword,
                        $passwordHash
                    )
                ) {
                    $error = 'הסיסמה הנוכחית אינה נכונה.';
                } else {
                    $updateStatement = $pdo->prepare(
                        "UPDATE users
                         SET
                            password_hash = :password_hash,
                            must_change_password = 0,
                            password_changed_at = NOW(),
                            failed_login_attempts = 0,
                            last_failed_login_at = NULL,
                            locked_until = NULL,
                            updated_by = :updated_by
                         WHERE id = :id"
                    );

                    $updateStatement->execute([
                        'password_hash' => password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        ),
                        'updated_by' => $userId,
                        'id' => $userId
                    ]);

                    $_SESSION['must_change_password'] = 0;
                    session_regenerate_id(true);

                    logAuthenticationAction(
                        $userId,
                        'password_changed'
                    );

                    $success = 'הסיסמה הוחלפה בהצלחה.';

                    $returnUrl = $_SESSION['return_url'] ?? null;
                    unset($_SESSION['return_url']);

                    if (
                        is_string($returnUrl) &&
                        isSafeLocalReturnUrl($returnUrl)
                    ) {
                        redirect($returnUrl);
                    }

                    redirect(APP_URL . '/public/');
                }
            } catch (Throwable $exception) {
                error_log(
                    'Change password error: ' .
                    $exception->getMessage()
                );

                $error = 'לא ניתן להחליף את הסיסמה כעת.';
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

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <title>
        החלפת סיסמה | <?= escape(APP_NAME) ?>
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
            aria-labelledby="change-password-title"
        >
            <header class="login-brand">
                <div
                    class="login-logo"
                    aria-hidden="true"
                >
                    3083
                </div>

                <h1
                    id="change-password-title"
                    class="login-title"
                >
                    החלפת סיסמה
                </h1>

                <p class="login-subtitle">
                    יש להחליף את הסיסמה לפני המשך העבודה
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

            <?php if ($success !== ''): ?>
                <div
                    class="alert alert-success"
                    role="status"
                >
                    <?= escape($success) ?>
                </div>
            <?php endif; ?>

            <form
                class="login-form"
                method="post"
                autocomplete="off"
            >
                <?= csrfInput() ?>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="current_password"
                    >
                        סיסמה נוכחית
                    </label>

                    <input
                        id="current_password"
                        class="form-input"
                        name="current_password"
                        type="password"
                        dir="ltr"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="new_password"
                    >
                        סיסמה חדשה
                    </label>

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
                </div>

                <div class="form-field">
                    <label
                        class="form-label"
                        for="confirm_password"
                    >
                        אימות סיסמה חדשה
                    </label>

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
                </div>

                <button
                    class="button button-primary login-button"
                    type="submit"
                >
                    החלפת סיסמה
                </button>
            </form>

            <footer class="login-footer">
                <a href="<?= escape(APP_URL) ?>/public/logout.php">
                    התנתקות
                </a>
            </footer>
        </section>
    </main>
</body>
</html>
