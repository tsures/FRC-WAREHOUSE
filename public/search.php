<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
requireLogin();

$pageTitle = 'חיפוש';
$activePage = 'search';

require __DIR__ . '/../views/layouts/app-header.php';
?>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">חיפוש במלאי</h2>
    </div>

    <p>
        מנגנון החיפוש ייבנה בשלב מתקדם יותר.
    </p>
</section>

<?php
require __DIR__ . '/../views/layouts/app-footer.php';