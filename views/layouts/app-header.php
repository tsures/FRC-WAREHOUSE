<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pageTitle = $pageTitle ?? 'לוח בקרה';
$activePage = $activePage ?? 'dashboard';

$userFullName = currentUserFullName() ?? 'משתמש';
$userRole = currentUserRole() ?? 'user';
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
        href="<?= escape(APP_URL) ?>/assets/css/layout.css"
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
                class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/"
            >
                <span class="nav-icon">🏠</span>
                <span class="nav-label">בית</span>
            </a>

            <a
                class="nav-item <?= $activePage === 'inventory' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/inventory/"
            >
                <span class="nav-icon">📦</span>
                <span class="nav-label">מלאי</span>
            </a>

            <a
                class="nav-item <?= $activePage === 'categories' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/categories/"
            >
                <span class="nav-icon">🗂️</span>
                <span class="nav-label">קטגוריות</span>
            </a>

            <a
                class="nav-item <?= $activePage === 'locations' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/locations/"
            >
                <span class="nav-icon">📍</span>
                <span class="nav-label">מיקומים</span>
            </a>

            <a
                class="nav-item <?= $activePage === 'search' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/search.php"
            >
                <span class="nav-icon">🔍</span>
                <span class="nav-label">חיפוש</span>
            </a>

            <a
                class="nav-item <?= $activePage === 'reports' ? 'active' : '' ?>"
                href="<?= escape(APP_URL) ?>/public/reports/"
            >
                <span class="nav-icon">📊</span>
                <span class="nav-label">דוחות</span>
            </a>

            <?php if (isAdmin()): ?>
                <div class="nav-section-title">
                    ניהול
                </div>

                <a
                    class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/users/"
                >
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">משתמשים</span>
                </a>

                <a
                    class="nav-item <?= $activePage === 'logs' ? 'active' : '' ?>"
                    href="<?= escape(APP_URL) ?>/public/logs/"
                >
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">יומן פעילות</span>
                </a>

                <a
                    class="nav-item <?= $activePage === 'settings' ? 'active' : '' ?>"
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

            <div class="user-details">
                <strong><?= escape($userFullName) ?></strong>
                <span>
                    <?= $userRole === 'admin'
                        ? 'מנהל מערכת'
                        : 'משתמש'
                    ?>
                </span>
            </div>

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
                        צוות FRC 3083
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
                    class="button button-primary topbar-add-button"
                    href="<?= escape(APP_URL) ?>/public/inventory/add.php"
                >
                    <span>＋</span>
                    <span>הוספת פריט</span>
                </a>

            </div>

        </header>

        <main class="content-area">