<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSecureSession();
requireLogin();

$pdo = Database::getConnection();

$totalItems = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM inventory_items
         WHERE is_active = 1"
    )
    ->fetchColumn();

$availableItems = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM inventory_items
         WHERE is_active = 1
           AND status = 'available'"
    )
    ->fetchColumn();

$lowStockItems = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM inventory_items
         WHERE is_active = 1
           AND quantity <= minimum_quantity"
    )
    ->fetchColumn();

$brokenItems = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM inventory_items
         WHERE is_active = 1
           AND (
               status = 'broken'
               OR item_condition = 'broken'
           )"
    )
    ->fetchColumn();

$recentActivitiesStatement = $pdo->prepare(
    "SELECT
        al.action,
        al.entity_type,
        al.created_at,
        u.full_name
     FROM activity_logs al
     LEFT JOIN users u
        ON u.id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 6"
);

$recentActivitiesStatement->execute();

$recentActivities = $recentActivitiesStatement->fetchAll();

$pageTitle = 'לוח בקרה';
$activePage = 'dashboard';

require __DIR__ . '/../views/layouts/app-header.php';
?>

<section class="dashboard-grid">

    <div class="stats-grid">

        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">סה״כ פריטים</span>
                <div class="stat-icon">📦</div>
            </div>

            <div class="stat-value">
                <?= number_format($totalItems) ?>
            </div>

            <div class="stat-note">
                פריטים פעילים במערכת
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">פריטים זמינים</span>
                <div class="stat-icon">✅</div>
            </div>

            <div class="stat-value">
                <?= number_format($availableItems) ?>
            </div>

            <div class="stat-note">
                זמינים לשימוש
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">מלאי נמוך</span>
                <div class="stat-icon">⚠️</div>
            </div>

            <div class="stat-value">
                <?= number_format($lowStockItems) ?>
            </div>

            <div class="stat-note">
                דורשים השלמת מלאי
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">פריטים שבורים</span>
                <div class="stat-icon">🛠️</div>
            </div>

            <div class="stat-value">
                <?= number_format($brokenItems) ?>
            </div>

            <div class="stat-note">
                דורשים בדיקה או תיקון
            </div>
        </article>

    </div>

    <div class="dashboard-columns">

        <section class="panel">

            <div class="panel-header">
                <h2 class="panel-title">
                    פעולות מהירות
                </h2>
            </div>

            <div class="quick-actions">

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/inventory/add.php"
                >
                    <span class="quick-action-icon">➕</span>
                    <strong>הוספת פריט חדש</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/inventory/"
                >
                    <span class="quick-action-icon">📦</span>
                    <strong>צפייה במלאי</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/categories/"
                >
                    <span class="quick-action-icon">🗂️</span>
                    <strong>ניהול קטגוריות</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/locations/"
                >
                    <span class="quick-action-icon">📍</span>
                    <strong>ניהול מיקומים</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/search.php"
                >
                    <span class="quick-action-icon">🔍</span>
                    <strong>חיפוש במלאי</strong>
                </a>

                <a
                    class="quick-action"
                    href="<?= escape(APP_URL) ?>/public/reports/"
                >
                    <span class="quick-action-icon">📊</span>
                    <strong>דוחות</strong>
                </a>

            </div>

        </section>

        <section class="panel">

            <div class="panel-header">
                <h2 class="panel-title">
                    פעילות אחרונה
                </h2>
            </div>

            <div class="activity-list">

                <?php if ($recentActivities === []): ?>

                    <div class="activity-item">
                        <div class="activity-icon">ℹ️</div>

                        <div class="activity-content">
                            <strong>אין עדיין פעילות</strong>
                            <span>
                                פעולות משתמשים יוצגו כאן
                            </span>
                        </div>
                    </div>

                <?php else: ?>

                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">

                            <div class="activity-icon">
                                <?= $activity['action'] === 'login'
                                    ? '🔐'
                                    : '📝'
                                ?>
                            </div>

                            <div class="activity-content">

                                <strong>
                                    <?= escape(
                                        $activity['full_name']
                                        ?? 'משתמש'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= escape(
                                        $activity['action']
                                    ) ?>
                                    ·
                                    <?= escape(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $activity['created_at']
                                            )
                                        )
                                    ) ?>
                                </span>

                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </div>

</section>

<?php
require __DIR__ . '/../views/layouts/app-footer.php';