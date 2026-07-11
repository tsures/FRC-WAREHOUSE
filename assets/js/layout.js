document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const collapseButton = document.getElementById("sidebar-collapse");
    const mobileMenuButton = document.getElementById("mobile-menu-button");
    const overlay = document.getElementById("sidebar-overlay");

    const collapseKey = "frc_sidebar_collapsed";

    function isMobile() {
        return window.innerWidth <= 767;
    }

    function applyDesktopState() {
        if (isMobile()) {
            document.body.classList.remove("sidebar-is-collapsed");
            sidebar?.classList.remove("collapsed");
            return;
        }

        const collapsed =
            localStorage.getItem(collapseKey) === "1";

        sidebar?.classList.toggle("collapsed", collapsed);
        document.body.classList.toggle(
            "sidebar-is-collapsed",
            collapsed
        );
    }

    function openMobileSidebar() {
        sidebar?.classList.add("mobile-open");
        overlay?.classList.add("visible");
        document.body.style.overflow = "hidden";
    }

    function closeMobileSidebar() {
        sidebar?.classList.remove("mobile-open");
        overlay?.classList.remove("visible");
        document.body.style.overflow = "";
    }

    collapseButton?.addEventListener("click", function () {
        const collapsed =
            !sidebar?.classList.contains("collapsed");

        sidebar?.classList.toggle("collapsed", collapsed);
        document.body.classList.toggle(
            "sidebar-is-collapsed",
            collapsed
        );

        localStorage.setItem(
            collapseKey,
            collapsed ? "1" : "0"
        );
    });

    mobileMenuButton?.addEventListener(
        "click",
        openMobileSidebar
    );

    overlay?.addEventListener(
        "click",
        closeMobileSidebar
    );

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMobileSidebar();
        }
    });

    window.addEventListener("resize", function () {
        closeMobileSidebar();
        applyDesktopState();
    });

    applyDesktopState();
});