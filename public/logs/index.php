<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSecureSession();
requireLogin();
requireAdmin();

$pageTitle = 'לוג מערכת';
$activePage = 'logs';

require __DIR__ . '/../../views/layouts/app-header.php';
?>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">ניהול מלאי</h2>
    </div>

    <p>
        מודול ניהול המלאי ייבנה בשלב הבא.
    </p>
</section>

<?php
require __DIR__ . '/../../views/layouts/app-footer.php';