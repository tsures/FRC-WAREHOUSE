"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryShortagesConfig || {};
    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");

    const elements = {
        search: document.getElementById("shortagesSearch"),
        type: document.getElementById("shortagesTypeFilter"),
        location: document.getElementById("shortagesLocationFilter"),
        category: document.getElementById("shortagesCategoryFilter"),
        supplier: document.getElementById("shortagesSupplierFilter"),
        clearButton: document.getElementById("clearShortagesFilters"),
        refreshButton: document.getElementById("refreshShortages"),
        clearSelectedButton: document.getElementById(
            "clearShortagesSelected"
        ),
        selectedCount: document.getElementById(
            "shortagesSelectedCount"
        ),
        totalCount: document.getElementById("shortagesTotalCount"),
        outCount: document.getElementById("shortagesOutCount"),
        lowCount: document.getElementById("shortagesLowCount"),
        estimatedCost: document.getElementById(
            "shortagesEstimatedCost"
        ),
        loading: document.getElementById("shortagesLoading"),
        empty: document.getElementById("shortagesEmpty"),
        items: document.getElementById("shortagesItems"),
        toastContainer: document.getElementById(
            "shortagesToastContainer"
        )
    };

    const state = {
        loading: false,
        debounceTimer: null,
        items: [],
        selected: loadSelectedItems()
    };

    bindEvents();
    updateSelectedCount();
    loadShortages();

    function bindEvents() {
        if (elements.search) {
            elements.search.addEventListener("input", function () {
                window.clearTimeout(state.debounceTimer);

                state.debounceTimer = window.setTimeout(function () {
                    loadShortages();
                }, 350);
            });
        }

        [
            elements.type,
            elements.location,
            elements.category,
            elements.supplier
        ].forEach(function (element) {
            if (!element) {
                return;
            }

            element.addEventListener("change", loadShortages);
        });

        elements.refreshButton?.addEventListener(
            "click",
            loadShortages
        );

        elements.clearButton?.addEventListener(
            "click",
            clearFilters
        );

        elements.clearSelectedButton?.addEventListener(
            "click",
            clearSelected
        );

        elements.items?.addEventListener(
            "change",
            handleItemSelection
        );
    }

    async function loadShortages() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            const search = String(elements.search?.value || "").trim();
            const type = String(elements.type?.value || "all");
            const location = String(elements.location?.value || "");
            const category = String(elements.category?.value || "");
            const supplier = String(elements.supplier?.value || "");

            if (search !== "") {
                parameters.set("search", search);
            }

            parameters.set("shortage_type", type);

            if (location !== "") {
                parameters.set("location_id", location);
            }

            if (category !== "") {
                parameters.set("category_id", category);
            }

            if (supplier !== "") {
                parameters.set("supplier_id", supplier);
            }

            const response = await fetch(
                appUrl +
                    "/api/inventory/shortages.php?" +
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
                    result.message || "לא ניתן לטעון את דוח החוסרים."
                );
            }

            const data = result.data || {};

            state.items = Array.isArray(data.items)
                ? data.items
                : [];

            renderStatistics(data.statistics || {});
            renderItems();
        } catch (error) {
            state.items = [];
            renderStatistics({});
            renderItems();

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את דוח החוסרים."
            );
        } finally {
            setLoading(false);
        }
    }

    function renderStatistics(statistics) {
        setText(elements.totalCount, statistics.total_count || 0);
        setText(
            elements.outCount,
            statistics.out_of_stock_count || 0
        );
        setText(
            elements.lowCount,
            statistics.low_stock_count || 0
        );

        if (elements.estimatedCost) {
            elements.estimatedCost.textContent =
                formatCurrency(statistics.estimated_cost || 0);
        }
    }

    function renderItems() {
        if (!elements.items || !elements.empty) {
            return;
        }

        elements.items.innerHTML = "";

        const hasItems = state.items.length > 0;

        elements.empty.hidden = hasItems;
        elements.empty.style.display = hasItems
            ? "none"
            : "block";

        elements.items.hidden = !hasItems;
        elements.items.style.display = hasItems
            ? "grid"
            : "none";

        if (!hasItems) {
            return;
        }

        const fragment = document.createDocumentFragment();

        state.items.forEach(function (item) {
            fragment.appendChild(createCard(item));
        });

        elements.items.appendChild(fragment);
    }

    function createCard(item) {
        const card = document.createElement("article");
        const selected = state.selected.has(Number(item.id));

        card.className = "inventory-shortage-card";

        if (selected) {
            card.classList.add("is-selected");
        }

        const isOut = item.shortage_type === "out";
        const shortageLabel = isOut
            ? "אזל מהמלאי"
            : "מלאי נמוך";

        card.innerHTML = `
            <div class="inventory-shortage-card-header">
                <div class="inventory-shortage-card-title">
                    <h2>${escapeHtml(item.name_he || "")}</h2>

                    <span class="inventory-shortage-code">
                        ${escapeHtml(item.item_code || "")}
                    </span>
                </div>

                <label class="inventory-shortage-select">
                    <input
                        type="checkbox"
                        data-shortage-select
                        data-item-id="${escapeAttribute(item.id)}"
                        ${selected ? "checked" : ""}
                    >

                    <span>לטיפול</span>
                </label>
            </div>

            <div class="inventory-shortage-badges">
                <span class="inventory-shortage-badge ${
                    isOut ? "is-danger" : "is-warning"
                }">
                    ${isOut ? "⛔" : "⚠️"}
                    ${escapeHtml(shortageLabel)}
                </span>

                ${
                    item.category_name_he
                        ? `
                            <span class="inventory-shortage-badge">
                                ${escapeHtml(item.category_name_he)}
                            </span>
                        `
                        : ""
                }
            </div>

            <div class="inventory-shortage-values">
                ${valueBox(
                    "נוכחי",
                    formatQuantity(item.quantity),
                    item.unit
                )}

                ${valueBox(
                    "מינימום",
                    formatQuantity(item.minimum_quantity),
                    item.unit
                )}

                ${valueBox(
                    "חסר למינימום",
                    formatQuantity(item.shortage_to_minimum),
                    item.unit
                )}

                ${valueBox(
                    "מומלץ להזמין",
                    formatQuantity(item.recommended_restock),
                    item.unit
                )}
            </div>

            <div class="inventory-shortage-details">
                <div>
                    <strong>מיקום:</strong>
                    ${escapeHtml(item.location_name || "ללא מיקום")}
                    ${
                        item.location_code
                            ? ` (${escapeHtml(item.location_code)})`
                            : ""
                    }
                </div>

                <div>
                    <strong>ספק:</strong>
                    ${escapeHtml(item.supplier_name || "לא הוגדר")}
                </div>

                <div>
                    <strong>מחיר יחידה:</strong>
                    ${
                        item.purchase_price !== null
                            ? formatCurrency(item.purchase_price)
                            : "לא הוגדר"
                    }
                </div>

                <div>
                    <strong>עלות השלמה משוערת:</strong>
                    ${
                        item.estimated_cost !== null
                            ? formatCurrency(item.estimated_cost)
                            : "לא ניתן לחשב"
                    }
                </div>
            </div>

            <div class="inventory-shortage-card-actions">
                <a
                    href="${escapeAttribute(
                        appUrl +
                        "/public/inventory/transactions.php?id=" +
                        encodeURIComponent(item.id)
                    )}"
                    class="button button-primary"
                >
                    הכנסת מלאי
                </a>

                <a
                    href="${escapeAttribute(
                        appUrl +
                        "/public/inventory/add.php?id=" +
                        encodeURIComponent(item.id)
                    )}"
                    class="button button-secondary"
                >
                    עריכת פריט
                </a>
            </div>
        `;

        return card;
    }

    function valueBox(label, value, unit) {
        return `
            <div class="inventory-shortage-value">
                <span>${escapeHtml(label)}</span>
                <strong>
                    ${escapeHtml(value)}
                    ${escapeHtml(unit || "")}
                </strong>
            </div>
        `;
    }

    function handleItemSelection(event) {
        const checkbox = event.target.closest("[data-shortage-select]");

        if (!checkbox) {
            return;
        }

        const itemId = Number(checkbox.dataset.itemId || 0);

        if (itemId <= 0) {
            return;
        }

        if (checkbox.checked) {
            state.selected.add(itemId);
        } else {
            state.selected.delete(itemId);
        }

        saveSelectedItems();
        updateSelectedCount();

        checkbox
            .closest(".inventory-shortage-card")
            ?.classList.toggle("is-selected", checkbox.checked);
    }

    function clearSelected() {
        state.selected.clear();
        saveSelectedItems();
        updateSelectedCount();
        renderItems();
    }

    function loadSelectedItems() {
        try {
            const raw = window.localStorage.getItem(
                "inventoryShortagesSelected"
            );

            const values = raw ? JSON.parse(raw) : [];

            return new Set(
                Array.isArray(values)
                    ? values.map(Number).filter(function (value) {
                        return Number.isInteger(value) && value > 0;
                    })
                    : []
            );
        } catch (error) {
            return new Set();
        }
    }

    function saveSelectedItems() {
        window.localStorage.setItem(
            "inventoryShortagesSelected",
            JSON.stringify(Array.from(state.selected))
        );
    }

    function updateSelectedCount() {
        setText(elements.selectedCount, state.selected.size);
    }

    function clearFilters() {
        if (elements.search) {
            elements.search.value = "";
        }

        if (elements.type) {
            elements.type.value = "all";
        }

        if (elements.location) {
            elements.location.value = "";
        }

        if (elements.category) {
            elements.category.value = "";
        }

        if (elements.supplier) {
            elements.supplier.value = "";
        }

        loadShortages();
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

            if (elements.items) {
                elements.items.hidden = true;
                elements.items.style.display = "none";
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
            console.error("Invalid JSON response:", responseText);

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

    function formatCurrency(value) {
        return new Intl.NumberFormat("he-IL", {
            style: "currency",
            currency: "ILS",
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(Number(value || 0));
    }

    function showToast(message) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");
        toast.className = "inventory-shortages-toast";

        toast.innerHTML = `
            <span aria-hidden="true">⚠️</span>

            <div class="inventory-shortages-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="inventory-shortages-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".inventory-shortages-toast-close")
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

    function escapeAttribute(value) {
        return escapeHtml(String(value ?? ""));
    }
});