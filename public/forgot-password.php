<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        איפוס סיסמה | <?= escape(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/app.css"
    >

    <style>
        body {
            min-height: 100vh;
            padding: 20px;
            display: grid;
            place-items: center;
        }

        .message-card {
            width: min(100%, 480px);
            padding: 32px;
            border-radius: 20px;
            background: var(--surface);
            box-shadow: var(--shadow-medium);
            text-align: center;
        }

        .message-card h1 {
            margin-top: 0;
        }

        .message-card p {
            color: var(--text-secondary);
        }

        .message-card .button {
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <main class="message-card">
        <h1>איפוס סיסמה</h1>

        <p>
            בשלב זה איפוס הסיסמה מתבצע על ידי מנהל
            המערכת.
        </p>

        <a
            class="button button-primary"
            href="<?= escape(APP_URL) ?>/public/login.php"
        >
            חזרה להתחברות
        </a>
    </main>
</body>
</html>