"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.usersConfig || {};
    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");
    const currentUserId = Number(config.currentUserId || 0);

    const elements = {
        search: document.getElementById("usersSearch"),
        roleFilter: document.getElementById("usersRoleFilter"),
        activeFilter: document.getElementById("usersActiveFilter"),
        clearFilters: document.getElementById("clearUsersFilters"),
        refresh: document.getElementById("refreshUsers"),
        addButton: document.getElementById("addUserButton"),

        count: document.getElementById("usersCount"),
        loading: document.getElementById("usersLoading"),
        empty: document.getElementById("usersEmpty"),
        grid: document.getElementById("usersGrid"),

        modal: document.getElementById("userModal"),
        modalTitle: document.getElementById("userModalTitle"),
        form: document.getElementById("userForm"),
        formError: document.getElementById("userFormError"),
        saveButton: document.getElementById("saveUserButton"),

        id: document.getElementById("userId"),
        username: document.getElementById("userUsername"),
        fullName: document.getElementById("userFullName"),
        email: document.getElementById("userEmail"),
        phone: document.getElementById("userPhone"),
        role: document.getElementById("userRole"),
        password: document.getElementById("userPassword"),
        passwordRequired: document.getElementById(
            "userPasswordRequired"
        ),
        passwordHelp: document.getElementById(
            "userPasswordHelp"
        ),
        isActive: document.getElementById("userIsActive"),
        mustChangePassword: document.getElementById(
            "userMustChangePassword"
        ),

        toastContainer: document.getElementById(
            "usersToastContainer"
        )
    };

    const state = {
        loading: false,
        saving: false,
        users: [],
        debounceTimer: null
    };

    bindEvents();
    loadUsers();

    function bindEvents() {
        elements.search?.addEventListener("input", function () {
            window.clearTimeout(state.debounceTimer);

            state.debounceTimer = window.setTimeout(function () {
                loadUsers();
            }, 350);
        });

        elements.roleFilter?.addEventListener(
            "change",
            loadUsers
        );

        elements.activeFilter?.addEventListener(
            "change",
            loadUsers
        );

        elements.refresh?.addEventListener(
            "click",
            loadUsers
        );

        elements.clearFilters?.addEventListener(
            "click",
            clearFilters
        );

        elements.addButton?.addEventListener(
            "click",
            openNewUserModal
        );

        elements.form?.addEventListener(
            "submit",
            saveUser
        );

        document.querySelectorAll(
            "[data-close-user-modal]"
        ).forEach(function (button) {
            button.addEventListener(
                "click",
                closeUserModal
            );
        });

        elements.grid?.addEventListener(
            "click",
            handleGridClick
        );

        document.addEventListener("keydown", function (event) {
            if (
                event.key === "Escape" &&
                elements.modal &&
                !elements.modal.hidden
            ) {
                closeUserModal();
            }
        });
    }

    async function loadUsers() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            const search = String(
                elements.search?.value || ""
            ).trim();

            const role = String(
                elements.roleFilter?.value || "all"
            );

            const active = String(
                elements.activeFilter?.value || "all"
            );

            if (search !== "") {
                parameters.set("search", search);
            }

            parameters.set("role", role);
            parameters.set("active", active);

            const response = await fetch(
                appUrl +
                    "/api/users/list.php?" +
                    parameters.toString(),
                {
                    method: "GET",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json"
                    }
                }
            );

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message ||
                    "לא ניתן לטעון את המשתמשים."
                );
            }

            const data = result.data || {};

            state.users = Array.isArray(data.users)
                ? data.users
                : [];

            renderUsers();

            if (elements.count) {
                elements.count.textContent = String(
                    data.count ?? state.users.length
                );
            }
        } catch (error) {
            state.users = [];
            renderUsers();

            if (elements.count) {
                elements.count.textContent = "0";
            }

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את המשתמשים.",
                true
            );
        } finally {
            setLoading(false);
        }
    }

    function renderUsers() {
        if (!elements.grid || !elements.empty) {
            return;
        }

        elements.grid.innerHTML = "";

        const hasUsers = state.users.length > 0;

        elements.empty.hidden = hasUsers;
        elements.empty.style.display = hasUsers
            ? "none"
            : "block";

        elements.grid.hidden = !hasUsers;
        elements.grid.style.display = hasUsers
            ? "grid"
            : "none";

        if (!hasUsers) {
            return;
        }

        const fragment = document.createDocumentFragment();

        state.users.forEach(function (user) {
            fragment.appendChild(createUserCard(user));
        });

        elements.grid.appendChild(fragment);
    }

    function createUserCard(user) {
        const card = document.createElement("article");
        const isActive = Number(user.is_active) === 1;
        const isAdmin = user.role === "admin";
        const isLocked = isUserLocked(user);
        const mustChange =
            Number(user.must_change_password) === 1;

        card.className = "user-card";

        if (!isActive) {
            card.classList.add("is-inactive");
        }

        card.dataset.userId = String(user.id);

        card.innerHTML = `
            <div class="user-card-header">
                <div>
                    <h2>
                        ${escapeHtml(user.full_name || "")}
                    </h2>

                    <div class="user-username">
                        ${escapeHtml(user.username || "")}
                    </div>
                </div>

                <div class="user-badges">
                    <span class="user-badge ${
                        isAdmin ? "is-admin" : ""
                    }">
                        ${
                            isAdmin
                                ? "מנהל מערכת"
                                : "משתמש"
                        }
                    </span>

                    <span class="user-badge ${
                        isActive
                            ? "is-active"
                            : "is-inactive"
                    }">
                        ${
                            isActive
                                ? "פעיל"
                                : "מושבת"
                        }
                    </span>

                    ${
                        isLocked
                            ? `
                                <span class="user-badge is-locked">
                                    נעול
                                </span>
                            `
                            : ""
                    }

                    ${
                        mustChange
                            ? `
                                <span class="user-badge is-warning">
                                    נדרשת החלפת סיסמה
                                </span>
                            `
                            : ""
                    }
                </div>
            </div>

            <div class="user-details">
                <div>
                    <strong>דוא״ל:</strong>
                    ${escapeHtml(user.email || "לא הוגדר")}
                </div>

                <div>
                    <strong>טלפון:</strong>
                    ${escapeHtml(user.phone || "לא הוגדר")}
                </div>

                <div>
                    <strong>כניסה אחרונה:</strong>
                    ${escapeHtml(
                        formatDateTime(user.last_login_at)
                    )}
                </div>

                <div>
                    <strong>IP אחרון:</strong>
                    ${escapeHtml(
                        user.last_login_ip || "לא קיים"
                    )}
                </div>

                <div>
                    <strong>ניסיונות כושלים:</strong>
                    ${escapeHtml(
                        user.failed_login_attempts || 0
                    )}
                </div>
            </div>

            <div class="user-actions">
                <button
                    type="button"
                    class="button button-secondary"
                    data-action="edit"
                    data-user-id="${escapeAttribute(user.id)}"
                >
                    עריכה
                </button>

                <button
                    type="button"
                    class="button button-secondary"
                    data-action="toggle"
                    data-user-id="${escapeAttribute(user.id)}"
                >
                    ${
                        isActive
                            ? "השבתה"
                            : "הפעלה"
                    }
                </button>

                ${
                    isLocked ||
                    Number(user.failed_login_attempts) > 0
                        ? `
                            <button
                                type="button"
                                class="button button-secondary"
                                data-action="unlock"
                                data-user-id="${escapeAttribute(
                                    user.id
                                )}"
                            >
                                איפוס נעילה
                            </button>
                        `
                        : ""
                }
            </div>
        `;

        return card;
    }

    function handleGridClick(event) {
        const button = event.target.closest(
            "[data-action][data-user-id]"
        );

        if (!button) {
            return;
        }

        const userId = Number(button.dataset.userId || 0);
        const action = String(button.dataset.action || "");

        const user = state.users.find(function (current) {
            return Number(current.id) === userId;
        });

        if (!user) {
            return;
        }

        if (action === "edit") {
            openEditUserModal(user);
            return;
        }

        if (action === "toggle") {
            toggleUser(user);
            return;
        }

        if (action === "unlock") {
            unlockUser(user);
        }
    }

    function openNewUserModal() {
        resetForm();

        if (elements.modalTitle) {
            elements.modalTitle.textContent = "הוספת משתמש";
        }

        if (elements.passwordRequired) {
            elements.passwordRequired.hidden = false;
        }

        if (elements.passwordHelp) {
            elements.passwordHelp.textContent =
                "לפחות 8 תווים.";
        }

        openModal();
    }

    function openEditUserModal(user) {
        resetForm();

        if (elements.modalTitle) {
            elements.modalTitle.textContent =
                "עריכת משתמש";
        }

        elements.id.value = String(user.id);
        elements.username.value = user.username || "";
        elements.fullName.value = user.full_name || "";
        elements.email.value = user.email || "";
        elements.phone.value = user.phone || "";
        elements.role.value = user.role || "user";
        elements.password.value = "";
        elements.isActive.checked =
            Number(user.is_active) === 1;
        elements.mustChangePassword.checked =
            Number(user.must_change_password) === 1;

        if (elements.passwordRequired) {
            elements.passwordRequired.hidden = true;
        }

        if (elements.passwordHelp) {
            elements.passwordHelp.textContent =
                "השאר ריק כדי לשמור את הסיסמה הקיימת.";
        }

        openModal();
    }

    async function saveUser(event) {
        event.preventDefault();

        if (state.saving) {
            return;
        }

        hideFormError();

        const userId = Number(elements.id?.value || 0);
        const username = String(
            elements.username?.value || ""
        ).trim();

        const fullName = String(
            elements.fullName?.value || ""
        ).trim();

        const password = String(
            elements.password?.value || ""
        );

        if (username === "" || fullName === "") {
            showFormError(
                "יש להזין שם משתמש ושם מלא."
            );

            return;
        }

        if (userId <= 0 && password.length < 8) {
            showFormError(
                "סיסמה למשתמש חדש חייבת להכיל לפחות 8 תווים."
            );

            return;
        }

        if (
            userId > 0 &&
            password !== "" &&
            password.length < 8
        ) {
            showFormError(
                "הסיסמה חייבת להכיל לפחות 8 תווים."
            );

            return;
        }

        state.saving = true;

        if (elements.saveButton) {
            elements.saveButton.disabled = true;
            elements.saveButton.textContent = "שומר...";
        }

        try {
            const response = await fetch(
                appUrl + "/api/users/save.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        id: userId,
                        username: username,
                        full_name: fullName,
                        email: String(
                            elements.email?.value || ""
                        ).trim(),
                        phone: String(
                            elements.phone?.value || ""
                        ).trim(),
                        role: String(
                            elements.role?.value || "user"
                        ),
                        password: password,
                        is_active: Boolean(
                            elements.isActive?.checked
                        ),
                        must_change_password: Boolean(
                            elements.mustChangePassword?.checked
                        )
                    })
                }
            );

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message ||
                    "לא ניתן לשמור את המשתמש."
                );
            }

            closeUserModal();
            showToast(
                result.message ||
                "המשתמש נשמר בהצלחה."
            );

            await loadUsers();
        } catch (error) {
            showFormError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשמור את המשתמש."
            );
        } finally {
            state.saving = false;

            if (elements.saveButton) {
                elements.saveButton.disabled = false;
                elements.saveButton.textContent = "שמירה";
            }
        }
    }

    async function toggleUser(user) {
        const isActive = Number(user.is_active) === 1;
        const actionText = isActive
            ? "להשבית"
            : "להפעיל";

        if (
            !window.confirm(
                `האם ${actionText} את המשתמש ${user.full_name}?`
            )
        ) {
            return;
        }

        try {
            const result = await postAction(
                "/api/users/toggle.php",
                {
                    id: Number(user.id)
                }
            );

            showToast(result.message);
            await loadUsers();
        } catch (error) {
            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לעדכן את המשתמש.",
                true
            );
        }
    }

    async function unlockUser(user) {
        if (
            !window.confirm(
                `האם לאפס את נעילת המשתמש ${user.full_name}?`
            )
        ) {
            return;
        }

        try {
            const result = await postAction(
                "/api/users/unlock.php",
                {
                    id: Number(user.id)
                }
            );

            showToast(result.message);
            await loadUsers();
        } catch (error) {
            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לאפס את הנעילה.",
                true
            );
        }
    }

    async function postAction(path, data) {
        const response = await fetch(
            appUrl + path,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    ...data
                })
            }
        );

        const result = await parseJsonResponse(response);

        if (!response.ok || result.success !== true) {
            throw new Error(
                result.message || "הפעולה נכשלה."
            );
        }

        return result;
    }

    function clearFilters() {
        if (elements.search) {
            elements.search.value = "";
        }

        if (elements.roleFilter) {
            elements.roleFilter.value = "all";
        }

        if (elements.activeFilter) {
            elements.activeFilter.value = "all";
        }

        loadUsers();
    }

    function resetForm() {
        elements.form?.reset();

        if (elements.id) {
            elements.id.value = "";
        }

        if (elements.role) {
            elements.role.value = "user";
        }

        if (elements.isActive) {
            elements.isActive.checked = true;
        }

        if (elements.mustChangePassword) {
            elements.mustChangePassword.checked = false;
        }

        hideFormError();
    }

    function openModal() {
        if (!elements.modal) {
            return;
        }

        elements.modal.hidden = false;
        elements.modal.style.display = "flex";
        document.body.style.overflow = "hidden";

        window.setTimeout(function () {
            elements.username?.focus();
        }, 50);
    }

    function closeUserModal() {
        if (!elements.modal) {
            return;
        }

        elements.modal.hidden = true;
        elements.modal.style.display = "none";
        document.body.style.overflow = "";

        resetForm();
    }

    function setLoading(isLoading) {
        state.loading = isLoading;

        if (elements.loading) {
            elements.loading.hidden = !isLoading;
            elements.loading.style.display = isLoading
                ? "flex"
                : "none";
        }

        if (elements.refresh) {
            elements.refresh.disabled = isLoading;
        }

        if (isLoading) {
            if (elements.empty) {
                elements.empty.hidden = true;
                elements.empty.style.display = "none";
            }

            if (elements.grid) {
                elements.grid.hidden = true;
                elements.grid.style.display = "none";
            }
        }
    }

    function isUserLocked(user) {
        if (!user.locked_until) {
            return false;
        }

        const lockedUntil = new Date(
            String(user.locked_until).replace(" ", "T")
        );

        return !Number.isNaN(lockedUntil.getTime()) &&
            lockedUntil.getTime() > Date.now();
    }

    function formatDateTime(value) {
        if (!value) {
            return "לא בוצעה כניסה";
        }

        const date = new Date(
            String(value).replace(" ", "T")
        );

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat("he-IL", {
            dateStyle: "short",
            timeStyle: "short"
        }).format(date);
    }

    function showFormError(message) {
        if (!elements.formError) {
            return;
        }

        elements.formError.textContent = message;
        elements.formError.hidden = false;
        elements.formError.style.display = "block";
    }

    function hideFormError() {
        if (!elements.formError) {
            return;
        }

        elements.formError.textContent = "";
        elements.formError.hidden = true;
        elements.formError.style.display = "none";
    }

    function showToast(message, isError = false) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");

        toast.className = "users-toast";

        if (isError) {
            toast.classList.add("is-error");
        }

        toast.innerHTML = `
            <span aria-hidden="true">
                ${isError ? "⚠️" : "✅"}
            </span>

            <div class="users-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="users-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".users-toast-close")
            ?.addEventListener("click", function () {
                toast.remove();
            });

        elements.toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 6000);
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        if (text.trim() === "") {
            throw new Error("השרת החזיר תשובה ריקה.");
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error("Invalid JSON response:", text);

            throw new Error(
                "השרת החזיר תשובה שאינה תקינה."
            );
        }
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function escapeAttribute(value) {
        return escapeHtml(String(value ?? ""));
    }
});