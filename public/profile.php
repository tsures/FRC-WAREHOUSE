<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'הפרופיל שלי';
$activePage = 'profile';

require __DIR__ . '/../views/layouts/app-header.php';
?>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">
            פרטי משתמש
        </h2>
    </div>

    <p>
        <strong>שם:</strong>
        <?= escape(currentUserFullName()) ?>
    </p>

    <p>
        <strong>שם משתמש:</strong>
        <?= escape(currentUsername()) ?>
    </p>

    <p>
        <strong>תפקיד:</strong>
        <?= isAdmin() ? 'מנהל מערכת' : 'משתמש' ?>
    </p>
</section>

<?php
require __DIR__ . '/../views/layouts/app-footer.php';