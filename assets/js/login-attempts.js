"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.loginAttemptsConfig || {};
    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");

    const elements = {
        search: document.getElementById("loginAttemptsSearch"),
        user: document.getElementById("loginAttemptsUserFilter"),
        result: document.getElementById("loginAttemptsResultFilter"),
        reason: document.getElementById("loginAttemptsReasonFilter"),
        dateFrom: document.getElementById("loginAttemptsDateFrom"),
        dateTo: document.getElementById("loginAttemptsDateTo"),

        clear: document.getElementById(
            "clearLoginAttemptsFilters"
        ),

        refresh: document.getElementById(
            "refreshLoginAttempts"
        ),

        totalCount: document.getElementById(
            "loginAttemptsTotalCount"
        ),

        successCount: document.getElementById(
            "loginAttemptsSuccessCount"
        ),

        failedCount: document.getElementById(
            "loginAttemptsFailedCount"
        ),

        failedIpCount: document.getElementById(
            "loginAttemptsFailedIpCount"
        ),

        loading: document.getElementById(
            "loginAttemptsLoading"
        ),

        empty: document.getElementById(
            "loginAttemptsEmpty"
        ),

        tableWrapper: document.getElementById(
            "loginAttemptsTableWrapper"
        ),

        tableBody: document.getElementById(
            "loginAttemptsTableBody"
        ),

        previousPage: document.getElementById(
            "loginAttemptsPreviousPage"
        ),

        nextPage: document.getElementById(
            "loginAttemptsNextPage"
        ),

        pageText: document.getElementById(
            "loginAttemptsPageText"
        ),

        toastContainer: document.getElementById(
            "loginAttemptsToastContainer"
        )
    };

    const state = {
        loading: false,
        page: 1,
        pageSize: 25,
        totalPages: 1,
        debounceTimer: null,
        usersLoaded: false,
        reasonsLoaded: false
    };

    bindEvents();
    loadAttempts();

    function bindEvents() {
        elements.search?.addEventListener("input", function () {
            window.clearTimeout(state.debounceTimer);

            state.debounceTimer = window.setTimeout(function () {
                state.page = 1;
                loadAttempts();
            }, 350);
        });

        [
            elements.user,
            elements.result,
            elements.reason,
            elements.dateFrom,
            elements.dateTo
        ].forEach(function (element) {
            element?.addEventListener("change", function () {
                state.page = 1;
                loadAttempts();
            });
        });

        elements.refresh?.addEventListener(
            "click",
            loadAttempts
        );

        elements.clear?.addEventListener(
            "click",
            clearFilters
        );

        elements.previousPage?.addEventListener(
            "click",
            function () {
                if (state.page <= 1) {
                    return;
                }

                state.page--;
                loadAttempts();
            }
        );

        elements.nextPage?.addEventListener(
            "click",
            function () {
                if (state.page >= state.totalPages) {
                    return;
                }

                state.page++;
                loadAttempts();
            }
        );
    }

    async function loadAttempts() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            const search = String(
                elements.search?.value || ""
            ).trim();

            const userId = String(
                elements.user?.value || ""
            );

            const result = String(
                elements.result?.value || "all"
            );

            const reason = String(
                elements.reason?.value || "all"
            );

            const dateFrom = String(
                elements.dateFrom?.value || ""
            );

            const dateTo = String(
                elements.dateTo?.value || ""
            );

            if (search !== "") {
                parameters.set("search", search);
            }

            if (userId !== "") {
                parameters.set("user_id", userId);
            }

            parameters.set("result", result);
            parameters.set("reason", reason);
            parameters.set("page", String(state.page));
            parameters.set(
                "page_size",
                String(state.pageSize)
            );

            if (dateFrom !== "") {
                parameters.set("date_from", dateFrom);
            }

            if (dateTo !== "") {
                parameters.set("date_to", dateTo);
            }

            const response = await fetch(
                appUrl +
                    "/api/users/login-attempts.php?" +
                    parameters.toString(),
                {
                    method: "GET",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json"
                    }
                }
            );

            const resultData = await parseJsonResponse(
                response
            );

            if (
                !response.ok ||
                resultData.success !== true
            ) {
                throw new Error(
                    resultData.message ||
                    "לא ניתן לטעון את היסטוריית ההתחברויות."
                );
            }

            const data = resultData.data || {};

            fillUsers(
                Array.isArray(data.users)
                    ? data.users
                    : []
            );

            fillReasons(
                Array.isArray(data.reasons)
                    ? data.reasons
                    : []
            );

            renderSummary(data.summary || {});

            renderAttempts(
                Array.isArray(data.attempts)
                    ? data.attempts
                    : []
            );

            updatePagination(data.pagination || {});
        } catch (error) {
            renderSummary({});
            renderAttempts([]);
            updatePagination({});

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את היסטוריית ההתחברויות."
            );
        } finally {
            setLoading(false);
        }
    }

    function fillUsers(users) {
        if (!elements.user || state.usersLoaded) {
            return;
        }

        const currentValue = elements.user.value;

        users.forEach(function (user) {
            const option = document.createElement("option");

            option.value = String(user.id);

            option.textContent =
                String(user.full_name || user.username) +
                " (" +
                String(user.username || "") +
                ")";

            elements.user.appendChild(option);
        });

        elements.user.value = currentValue;
        state.usersLoaded = true;
    }

    function fillReasons(reasons) {
        if (!elements.reason || state.reasonsLoaded) {
            return;
        }

        const currentValue = elements.reason.value;

        reasons.forEach(function (reason) {
            const option = document.createElement("option");

            option.value = String(reason);
            option.textContent = getReasonLabel(reason);

            elements.reason.appendChild(option);
        });

        elements.reason.value = currentValue;
        state.reasonsLoaded = true;
    }

    function renderSummary(summary) {
        setText(
            elements.totalCount,
            summary.total_count || 0
        );

        setText(
            elements.successCount,
            summary.success_count || 0
        );

        setText(
            elements.failedCount,
            summary.failed_count || 0
        );

        setText(
            elements.failedIpCount,
            summary.failed_ip_count || 0
        );
    }

    function renderAttempts(attempts) {
        if (
            !elements.tableBody ||
            !elements.empty ||
            !elements.tableWrapper
        ) {
            return;
        }

        elements.tableBody.innerHTML = "";

        const hasAttempts = attempts.length > 0;

        elements.empty.hidden = hasAttempts;
        elements.empty.style.display = hasAttempts
            ? "none"
            : "block";

        elements.tableWrapper.hidden = !hasAttempts;
        elements.tableWrapper.style.display = hasAttempts
            ? "block"
            : "none";

        if (!hasAttempts) {
            return;
        }

        const fragment = document.createDocumentFragment();

        attempts.forEach(function (attempt) {
            const row = document.createElement("tr");

            const wasSuccessful =
                Number(attempt.was_successful) === 1;

            const displayName =
                attempt.full_name ||
                attempt.username_attempted ||
                "לא ידוע";

            const username =
                attempt.actual_username ||
                attempt.username_attempted ||
                "";

            row.innerHTML = `
                <td>
                    ${escapeHtml(
                        formatDateTime(attempt.created_at)
                    )}
                </td>

                <td>
                    <strong>
                        ${escapeHtml(displayName)}
                    </strong>

                    <div class="user-username">
                        ${escapeHtml(username)}
                    </div>
                </td>

                <td>
                    <span class="login-attempts-result ${
                        wasSuccessful
                            ? "is-success"
                            : "is-failed"
                    }">
                        ${
                            wasSuccessful
                                ? "הצלחה"
                                : "כישלון"
                        }
                    </span>
                </td>

                <td>
                    ${
                        wasSuccessful
                            ? "—"
                            : `
                                <span class="login-attempts-reason">
                                    ${escapeHtml(
                                        getReasonLabel(
                                            attempt.failure_reason
                                        )
                                    )}
                                </span>
                            `
                    }
                </td>

                <td class="login-attempts-ip">
                    ${escapeHtml(
                        attempt.ip_address || "לא ידוע"
                    )}
                </td>

                <td class="login-attempts-user-agent">
                    ${escapeHtml(
                        attempt.user_agent || "לא ידוע"
                    )}
                </td>
            `;

            fragment.appendChild(row);
        });

        elements.tableBody.appendChild(fragment);
    }

    function updatePagination(pagination) {
        state.page = Number(pagination.page || 1);
        state.totalPages = Number(
            pagination.total_pages || 1
        );

        if (elements.pageText) {
            elements.pageText.textContent =
                "עמוד " +
                state.page +
                " מתוך " +
                state.totalPages;
        }

        if (elements.previousPage) {
            elements.previousPage.disabled =
                state.page <= 1;
        }

        if (elements.nextPage) {
            elements.nextPage.disabled =
                state.page >= state.totalPages;
        }
    }

    function clearFilters() {
        if (elements.search) {
            elements.search.value = "";
        }

        if (elements.user) {
            elements.user.value = "";
        }

        if (elements.result) {
            elements.result.value = "all";
        }

        if (elements.reason) {
            elements.reason.value = "all";
        }

        if (elements.dateFrom) {
            elements.dateFrom.value = "";
        }

        if (elements.dateTo) {
            elements.dateTo.value = "";
        }

        state.page = 1;
        loadAttempts();
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

            if (elements.tableWrapper) {
                elements.tableWrapper.hidden = true;
                elements.tableWrapper.style.display = "none";
            }
        }
    }

    function getReasonLabel(reason) {
        const labels = {
            invalid_credentials: "פרטי התחברות שגויים",
            inactive: "משתמש מושבת",
            locked: "משתמש נעול",
            honeypot: "מלכודת דבש",
            too_fast: "שליחה מהירה מדי",
            invalid_antibot_token: "טופס לא תקין",
            expired_antibot_token: "פג תוקף הטופס",
            ip_rate_limit: "חסימת כתובת IP"
        };

        return labels[String(reason || "")] ||
            String(reason || "לא ידוע");
    }

    function formatDateTime(value) {
        if (!value) {
            return "—";
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

    function showToast(message) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");
        toast.className = "login-attempts-toast";

        toast.innerHTML = `
            <span aria-hidden="true">⚠️</span>

            <div class="login-attempts-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="login-attempts-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(
                ".login-attempts-toast-close"
            )
            ?.addEventListener("click", function () {
                toast.remove();
            });

        elements.toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 6500);
    }

    function setText(element, value) {
        if (element) {
            element.textContent = String(value);
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
});