        </main>
    </div>
</div>

<div
    id="sidebar-overlay"
    class="sidebar-overlay"
></div>

<nav
    class="mobile-bottom-nav"
    aria-label="ניווט תחתון"
>
    <a
        class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"
        href="<?= escape(APP_URL) ?>/public/"
    >
        <span>🏠</span>
        <small>בית</small>
    </a>

    <a
        class="<?= $activePage === 'inventory' ? 'active' : '' ?>"
        href="<?= escape(APP_URL) ?>/public/inventory/"
    >
        <span>📦</span>
        <small>מלאי</small>
    </a>

    <a
        class="mobile-add-button"
        href="<?= escape(APP_URL) ?>/public/inventory/add.php"
        aria-label="הוספת פריט"
    >
        ＋
    </a>

    <a
        class="<?= $activePage === 'search' ? 'active' : '' ?>"
        href="<?= escape(APP_URL) ?>/public/search.php"
    >
        <span>🔍</span>
        <small>חיפוש</small>
    </a>

    <a
        class="<?= $activePage === 'profile' ? 'active' : '' ?>"
        href="<?= escape(APP_URL) ?>/public/profile.php"
    >
        <span>👤</span>
        <small>משתמש</small>
    </a>
</nav>

<script
    src="<?= escape(APP_URL) ?>/assets/js/layout.js"
    defer
></script>
</body>
</html>