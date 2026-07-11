"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryHistoryConfig || {};

    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");

    const elements = {
        search: document.getElementById("inventoryHistorySearch"),
        type: document.getElementById("inventoryHistoryType"),
        location: document.getElementById("inventoryHistoryLocation"),
        dateFrom: document.getElementById("inventoryHistoryDateFrom"),
        dateTo: document.getElementById("inventoryHistoryDateTo"),

        clearButton: document.getElementById(
            "clearInventoryHistoryFilters"
        ),

        refreshButton: document.getElementById(
            "refreshInventoryHistory"
        ),

        count: document.getElementById("inventoryHistoryCount"),
        loading: document.getElementById("inventoryHistoryLoading"),
        empty: document.getElementById("inventoryHistoryEmpty"),

        tableWrapper: document.getElementById(
            "inventoryHistoryTableWrapper"
        ),

        tableBody: document.getElementById(
            "inventoryHistoryTableBody"
        ),

        toastContainer: document.getElementById(
            "inventoryHistoryToastContainer"
        )
    };

    const state = {
        loading: false,
        debounceTimer: null
    };

    bindEvents();
    loadHistory();

    function bindEvents() {
        if (elements.search) {
            elements.search.addEventListener("input", function () {
                window.clearTimeout(state.debounceTimer);

                state.debounceTimer = window.setTimeout(function () {
                    loadHistory();
                }, 350);
            });
        }

        [
            elements.type,
            elements.location,
            elements.dateFrom,
            elements.dateTo
        ].forEach(function (element) {
            if (!element) {
                return;
            }

            element.addEventListener("change", function () {
                loadHistory();
            });
        });

        if (elements.refreshButton) {
            elements.refreshButton.addEventListener(
                "click",
                loadHistory
            );
        }

        if (elements.clearButton) {
            elements.clearButton.addEventListener(
                "click",
                clearFilters
            );
        }
    }

    async function loadHistory() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            const search = String(elements.search?.value || "").trim();
            const type = String(elements.type?.value || "");
            const location = String(elements.location?.value || "");
            const dateFrom = String(elements.dateFrom?.value || "");
            const dateTo = String(elements.dateTo?.value || "");

            if (search !== "") {
                parameters.set("search", search);
            }

            if (type !== "") {
                parameters.set("transaction_type", type);
            }

            if (location !== "") {
                parameters.set("location_id", location);
            }

            if (dateFrom !== "") {
                parameters.set("date_from", dateFrom);
            }

            if (dateTo !== "") {
                parameters.set("date_to", dateTo);
            }

            parameters.set("limit", "500");

            const response = await fetch(
                appUrl +
                    "/api/inventory/history.php?" +
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
                    "לא ניתן לטעון את היסטוריית התנועות."
                );
            }

            const data = result.data || {};

            const transactions = Array.isArray(data.transactions)
                ? data.transactions
                : [];

            renderHistory(transactions);

            if (elements.count) {
                elements.count.textContent = String(
                    data.returned_count ?? transactions.length
                );
            }
        } catch (error) {
            renderHistory([]);

            if (elements.count) {
                elements.count.textContent = "0";
            }

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את היסטוריית התנועות."
            );
        } finally {
            setLoading(false);
        }
    }

function renderHistory(transactions) {
    if (
        !elements.tableBody ||
        !elements.tableWrapper ||
        !elements.empty
    ) {
        return;
    }

    elements.tableBody.innerHTML = "";

    const hasTransactions = transactions.length > 0;

    elements.empty.hidden = hasTransactions;
    elements.empty.style.display = hasTransactions
        ? "none"
        : "block";

    elements.tableWrapper.hidden = !hasTransactions;
    elements.tableWrapper.style.display = hasTransactions
        ? "block"
        : "none";

    if (!hasTransactions) {
        return;
    }

    const fragment = document.createDocumentFragment();

    transactions.forEach(function (transaction) {
        const row = document.createElement("tr");

        const quantityChange = Number(
            transaction.quantity_change || 0
        );

        const changeClass = quantityChange > 0
            ? "inventory-history-positive"
            : quantityChange < 0
                ? "inventory-history-negative"
                : "inventory-history-neutral";

        row.innerHTML = `
            <td>
                ${escapeHtml(
                    formatDateTime(transaction.created_at)
                )}
            </td>

            <td>
                <a
                    href="${escapeAttribute(
                        appUrl +
                        "/public/inventory/transactions.php?id=" +
                        encodeURIComponent(transaction.item_id)
                    )}"
                    class="inventory-history-item-link"
                >
                    ${escapeHtml(
                        transaction.item_name_he || ""
                    )}
                </a>

                <span class="inventory-history-item-code">
                    ${escapeHtml(
                        transaction.item_code || ""
                    )}
                </span>
            </td>

            <td>
                <span class="inventory-history-type">
                    ${escapeHtml(
                        transaction.transaction_icon || "📦"
                    )}

                    ${escapeHtml(
                        transaction.transaction_label || ""
                    )}
                </span>
            </td>

            <td class="${changeClass}">
                ${escapeHtml(
                    formatSignedQuantity(quantityChange)
                )}

                ${escapeHtml(
                    transaction.unit || ""
                )}
            </td>

            <td>
                ${escapeHtml(
                    formatQuantity(
                        transaction.quantity_before
                    )
                )}
            </td>

            <td>
                ${escapeHtml(
                    formatQuantity(
                        transaction.quantity_after
                    )
                )}
            </td>

            <td>
                ${escapeHtml(
                    getLocationText(transaction)
                )}
            </td>

            <td>
                ${escapeHtml(
                    transaction.reference_number || "—"
                )}
            </td>

            <td>
                ${escapeHtml(
                    transaction.created_by_name || "מערכת"
                )}
            </td>

            <td>
                ${escapeHtml(
                    transaction.notes || "—"
                )}
            </td>
        `;

        fragment.appendChild(row);
    });

    elements.tableBody.appendChild(fragment);
}

    function getLocationText(transaction) {
        const fromName = String(
            transaction.from_location_name || ""
        );

        const toName = String(
            transaction.to_location_name || ""
        );

        const fromCode = String(
            transaction.from_location_code || ""
        );

        const toCode = String(
            transaction.to_location_code || ""
        );

        const fromText = fromName === ""
            ? ""
            : fromName + (fromCode !== "" ? ` (${fromCode})` : "");

        const toText = toName === ""
            ? ""
            : toName + (toCode !== "" ? ` (${toCode})` : "");

        if (fromText !== "" && toText !== "") {
            return fromText + " ← " + toText;
        }

        if (toText !== "") {
            return toText;
        }

        if (fromText !== "") {
            return fromText;
        }

        return "—";
    }

    function clearFilters() {
        if (elements.search) {
            elements.search.value = "";
        }

        if (elements.type) {
            elements.type.value = "";
        }

        if (elements.location) {
            elements.location.value = "";
        }

        if (elements.dateFrom) {
            elements.dateFrom.value = "";
        }

        if (elements.dateTo) {
            elements.dateTo.value = "";
        }

        loadHistory();
    }

function setLoading(isLoading) {
    state.loading = isLoading;

    if (elements.loading) {
        elements.loading.hidden = !isLoading;
        elements.loading.style.display = isLoading
            ? "flex"
            : "none";
    }

    if (elements.refreshButton) {
        elements.refreshButton.disabled = isLoading;
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

    async function parseJsonResponse(response) {
        const responseText = await response.text();

        if (responseText.trim() === "") {
            throw new Error("השרת החזיר תשובה ריקה.");
        }

        try {
            return JSON.parse(responseText);
        } catch (error) {
            console.error(
                "Invalid JSON response:",
                responseText
            );

            throw new Error(
                "השרת החזיר תשובה שאינה תקינה."
            );
        }
    }

    function formatQuantity(value) {
        return new Intl.NumberFormat("he-IL", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3
        }).format(Number(value || 0));
    }

    function formatSignedQuantity(value) {
        const number = Number(value || 0);
        const formatted = formatQuantity(Math.abs(number));

        if (number > 0) {
            return "+" + formatted;
        }

        if (number < 0) {
            return "-" + formatted;
        }

        return formatted;
    }

    function formatDateTime(value) {
        if (!value) {
            return "—";
        }

        const normalized = String(value).replace(" ", "T");
        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat("he-IL", {
            dateStyle: "short",
            timeStyle: "short"
        }).format(date);
    }

    function showToast(message) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");

        toast.className = "inventory-history-toast";

        toast.innerHTML = `
            <span aria-hidden="true">⚠️</span>

            <div class="inventory-history-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="inventory-history-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".inventory-history-toast-close")
            ?.addEventListener("click", function () {
                toast.remove();
            });

        elements.toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 6500);
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