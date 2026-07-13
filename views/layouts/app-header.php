<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pageTitle = $pageTitle ?? 'לוח בקרה';
$activePage = $activePage ?? 'dashboard';

$userFullName = currentUserFullName() ?? 'משתמש';
$userRole = currentUserRole() ?? 'user';

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
);

$currentPath = is_string($currentPath)
    ? rtrim($currentPath, '/')
    : '';

$appBasePath = rtrim(APP_URL, '/');

function sidebarPathIs(
    string $currentPath,
    string $expectedPath
): bool {
    return rtrim($currentPath, '/') === rtrim($expectedPath, '/');
}

function sidebarPathStartsWith(
    string $currentPath,
    string $expectedPath
): bool {
    $currentPath = rtrim($currentPath, '/');
    $expectedPath = rtrim($expectedPath, '/');

    return
        $currentPath === $expectedPath ||
        str_starts_with(
            $currentPath,
            $expectedPath . '/'
        );
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <title>
        <?= escape($pageTitle) ?> | <?= escape(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/app.css"
    >

    <link
        rel="stylesheet"
        href="<?= escape(APP_URL) ?>/assets/css/layout.css?v=7"
    >
</head>

<body>
<div class="app-shell">

    <aside
        id="sidebar"
        class="sidebar"
        aria-label="תפריט ראשי"
    >
        <div class="sidebar-header">
            <div class="sidebar-logo">
                3083
            </div>

            <div class="sidebar-brand">
                <strong>FRC Warehouse</strong>
                <span>ניהול מחסן</span>
            </div>

            <button
                id="sidebar-collapse"
                class="icon-button sidebar-collapse"
                type="button"
                aria-label="כיווץ תפריט"
            >
                ☰
            </button>
        </div>

        <nav class="sidebar-nav">

            <a
                class="nav-item <?= sidebarPathIs(
                    $currentPath,
                    $appBasePath . '/public'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/"
            >
                <span class="nav-icon">🏠</span>
                <span class="nav-label">בית</span>
            </a>

            <div class="nav-section-title">
                מלאי
            </div>

            <a
                class="nav-item <?= (
                    sidebarPathIs(
                        $currentPath,
                        $appBasePath . '/public/inventory'
                    ) ||
                    sidebarPathIs(
                        $currentPath,
                        $appBasePath . '/public/inventory/index.php'
                    )
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/inventory/"
            >
                <span class="nav-icon">📦</span>
                <span class="nav-label">כל המלאי</span>
            </a>

            <a
                class="nav-item <?= sidebarPathIs(
                    $currentPath,
                    $appBasePath . '/public/inventory/warehouses.php'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/inventory/warehouses.php"
            >
                <span class="nav-icon">🏭</span>
                <span class="nav-label">מלאי לפי מחסן</span>
            </a>

            <a
                class="nav-item <?= sidebarPathIs(
                    $currentPath,
                    $appBasePath . '/public/inventory/shortages.php'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/inventory/shortages.php"
            >
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">חוסרים והשלמות</span>
            </a>

            <a
                class="nav-item <?= sidebarPathIs(
                    $currentPath,
                    $appBasePath . '/public/inventory/history.php'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/inventory/history.php"
            >
                <span class="nav-icon">🧾</span>
                <span class="nav-label">היסטוריית תנועות</span>
            </a>
            
                <a class="nav-item <?= sidebarPathIs(
                   $currentPath,
                   $appBasePath . '/public/inventory/issue.php'
            ) ? 'active' : '' ?>"
            href="<?= escape(APP_URL) ?>/public/inventory/issue.php">
            <span class="nav-icon">📤</span>
            <span class="nav-label">הוצאת ומילוי ציוד</span>
           </a>

            <div class="nav-section-title">
                נתונים
            </div>
        
            <a
                class="nav-item <?= sidebarPathStartsWith(
                    $currentPath,
                    $appBasePath . '/public/categories'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/categories/"
            >
                <span class="nav-icon">🗂️</span>
                <span class="nav-label">קטגוריות</span>
            </a>

            <a
                class="nav-item <?= sidebarPathStartsWith(
                    $currentPath,
                    $appBasePath . '/public/locations'
                ) ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/locations/"
            >
                <span class="nav-icon">📍</span>
                <span class="nav-label">מיקומים</span>
            </a>
         

            <?php if (isAdmin()): ?>
                <div class="nav-section-title">
                    ניהול
                </div>

                <a
                    class="nav-item <?= (
                        sidebarPathIs(
                            $currentPath,
                            $appBasePath . '/public/users'
                        ) ||
                        sidebarPathIs(
                            $currentPath,
                            $appBasePath . '/public/users/index.php'
                        )
                    ) ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/users/"
                >
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">משתמשים</span>
                </a>

                <a
                    class="nav-item <?= sidebarPathIs(
                        $currentPath,
                        $appBasePath . '/public/users/login-attempts.php'
                    ) ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/users/login-attempts.php"
                >
                    <span class="nav-icon">🔐</span>
                    <span class="nav-label">היסטוריית התחברויות</span>
                </a>

                <a
                    class="nav-item <?= sidebarPathStartsWith(
                        $currentPath,
                        $appBasePath . '/public/logs'
                    ) ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/logs/"
                >
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">יומן פעילות</span>
                </a>

                <a
                    class="nav-item <?= sidebarPathStartsWith(
                        $currentPath,
                        $appBasePath . '/public/settings'
                    ) ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/settings/"
                >
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-label">הגדרות</span>
                </a>
            <?php endif; ?>

        </nav>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?= escape(mb_substr($userFullName, 0, 1)) ?>
            </div>

         <a
    href="<?= escape(APP_URL) ?>/public/profile.php"
    class="user-details"
>
    <strong><?= escape($userFullName) ?></strong>
    <span>
        <?= $userRole === 'admin'
            ? 'מנהל מערכת'
            : 'משתמש'
        ?>
    </span>
</a>

            <a
                class="icon-button"
                href="<?= escape(APP_URL) ?>/public/logout.php"
                aria-label="התנתקות"
            >
                🚪
            </a>
        </div>
    </aside>

    <div class="app-main">

        <header class="topbar">

            <div class="topbar-start">
                <button
                    id="mobile-menu-button"
                    class="icon-button mobile-menu-button"
                    type="button"
                    aria-label="פתיחת תפריט"
                >
                    ☰
                </button>

                <div>
                    <h1 class="page-title">
                        <?= escape($pageTitle) ?>
                    </h1>

                    <p class="page-subtitle">
                       ARTEMIS 3083 🏹
                    </p>
                </div>
            </div>

            <div class="topbar-actions">

                <button
                    class="icon-button"
                    type="button"
                    aria-label="התראות"
                >
                    🔔
                </button>
                
                 <a
                class="icon-button"
                href="<?= escape(APP_URL) ?>/public/logout.php"
                aria-label="התנתקות"
            >
                🔚
            </a>
             

            </div>

        </header>

        <main class="content-area">
