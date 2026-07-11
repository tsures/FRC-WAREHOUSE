"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryWarehousesConfig || {};
    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");

    const elements = {
        search: document.getElementById("warehouseInventorySearch"),
        location: document.getElementById("warehouseLocationFilter"),
        stock: document.getElementById("warehouseStockFilter"),
        active: document.getElementById("warehouseActiveFilter"),

        clearButton: document.getElementById(
            "clearWarehouseInventoryFilters"
        ),

        refreshButton: document.getElementById(
            "refreshWarehouseInventory"
        ),

        count: document.getElementById("warehouseItemsCount"),

        summary: document.getElementById(
            "warehouseSummaryCards"
        ),

        loading: document.getElementById(
            "warehouseInventoryLoading"
        ),

        empty: document.getElementById(
            "warehouseInventoryEmpty"
        ),

        groups: document.getElementById(
            "warehouseInventoryGroups"
        ),

        toastContainer: document.getElementById(
            "warehouseInventoryToastContainer"
        )
    };

    const state = {
        loading: false,
        debounceTimer: null,
        locations: [],
        items: []
    };

    bindEvents();
    loadInventory();

    function bindEvents() {
        if (elements.search) {
            elements.search.addEventListener("input", function () {
                window.clearTimeout(state.debounceTimer);

                state.debounceTimer = window.setTimeout(function () {
                    loadInventory();
                }, 350);
            });
        }

        [
            elements.location,
            elements.stock,
            elements.active
        ].forEach(function (element) {
            if (!element) {
                return;
            }

            element.addEventListener("change", function () {
                loadInventory();
            });
        });

        if (elements.refreshButton) {
            elements.refreshButton.addEventListener(
                "click",
                loadInventory
            );
        }

        if (elements.clearButton) {
            elements.clearButton.addEventListener(
                "click",
                clearFilters
            );
        }

        if (elements.summary) {
            elements.summary.addEventListener(
                "click",
                handleSummaryClick
            );
        }
    }

    async function loadInventory() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            const search = String(
                elements.search?.value || ""
            ).trim();

            const location = String(
                elements.location?.value || ""
            );

            const stock = String(
                elements.stock?.value || "all"
            );

            const active = String(
                elements.active?.value || "active"
            );

            if (search !== "") {
                parameters.set("search", search);
            }

            if (location !== "") {
                parameters.set("location_id", location);
            }

            parameters.set("stock", stock);
            parameters.set("active", active);

            const response = await fetch(
                appUrl +
                    "/api/inventory/warehouses.php?" +
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
                    "לא ניתן לטעון את המלאי לפי מחסן."
                );
            }

            const data = result.data || {};

            state.locations = Array.isArray(data.locations)
                ? data.locations
                : [];

            state.items = Array.isArray(data.items)
                ? data.items
                : [];

            fillLocations();
            renderSummary();
            renderGroups();

            if (elements.count) {
                elements.count.textContent = String(
                    data.returned_count ?? state.items.length
                );
            }
        } catch (error) {
            state.locations = [];
            state.items = [];

            renderSummary();
            renderGroups();

            if (elements.count) {
                elements.count.textContent = "0";
            }

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את המלאי לפי מחסן."
            );
        } finally {
            setLoading(false);
        }
    }

    function fillLocations() {
        if (!elements.location) {
            return;
        }

        const currentValue = elements.location.value;

        elements.location.innerHTML = "";

        const firstOption = document.createElement("option");
        firstOption.value = "";
        firstOption.textContent = "כל המחסנים והמיקומים";

        elements.location.appendChild(firstOption);

        state.locations.forEach(function (location) {
            const option = document.createElement("option");

            option.value = String(location.id);

            option.textContent =
                String(location.name || "") +
                (
                    location.code
                        ? ` (${location.code})`
                        : ""
                );

            elements.location.appendChild(option);
        });

        elements.location.value = currentValue;
    }

    function renderSummary() {
        if (!elements.summary) {
            return;
        }

        elements.summary.innerHTML = "";

        const fragment = document.createDocumentFragment();

        state.locations.forEach(function (location) {
            const card = document.createElement("article");

            card.className = "inventory-warehouse-summary-card";
            card.dataset.locationId = String(location.id);

            if (
                String(elements.location?.value || "") ===
                String(location.id)
            ) {
                card.classList.add("is-selected");
            }

            card.innerHTML = `
                <h2>
                    ${escapeHtml(location.name || "")}
                </h2>

                <span class="inventory-warehouse-summary-code">
                    ${escapeHtml(location.code || "")}
                </span>

                <div class="inventory-warehouse-summary-stats">
                    <div class="inventory-warehouse-summary-stat">
                        <span>פריטים</span>
                        <strong>
                            ${escapeHtml(location.item_count || 0)}
                        </strong>
                    </div>

                    <div class="inventory-warehouse-summary-stat">
                        <span>מלאי נמוך</span>
                        <strong>
                            ${escapeHtml(
                                location.low_stock_count || 0
                            )}
                        </strong>
                    </div>

                    <div class="inventory-warehouse-summary-stat">
                        <span>אזל</span>
                        <strong>
                            ${escapeHtml(
                                location.out_of_stock_count || 0
                            )}
                        </strong>
                    </div>
                </div>
            `;

            fragment.appendChild(card);
        });

        elements.summary.appendChild(fragment);
    }

    function renderGroups() {
        if (!elements.groups || !elements.empty) {
            return;
        }

        elements.groups.innerHTML = "";

        const hasItems = state.items.length > 0;

        elements.empty.hidden = hasItems;
        elements.empty.style.display = hasItems
            ? "none"
            : "block";

        elements.groups.hidden = !hasItems;
        elements.groups.style.display = hasItems
            ? "flex"
            : "none";

        if (!hasItems) {
            return;
        }

        const grouped = new Map();

        state.items.forEach(function (item) {
            const locationId = item.location_id || 0;

            if (!grouped.has(locationId)) {
                grouped.set(locationId, []);
            }

            grouped.get(locationId).push(item);
        });

        const fragment = document.createDocumentFragment();

        grouped.forEach(function (items, locationId) {
            const location = state.locations.find(function (current) {
                return Number(current.id) === Number(locationId);
            });

            const group = document.createElement("section");
            group.className = "inventory-warehouse-group";

            const locationName = location
                ? location.name
                : "ללא מיקום";

            const locationCode = location?.code
                ? ` (${location.code})`
                : "";

            group.innerHTML = `
                <div class="inventory-warehouse-group-header">
                    <h2>
                        ${escapeHtml(locationName + locationCode)}
                    </h2>

                    <span>
                        ${escapeHtml(items.length)}
                        פריטים
                    </span>
                </div>

                <div class="inventory-warehouse-items"></div>
            `;

            const itemsContainer = group.querySelector(
                ".inventory-warehouse-items"
            );

            items.forEach(function (item) {
                itemsContainer.appendChild(
                    createItemCard(item)
                );
            });

            fragment.appendChild(group);
        });

        elements.groups.appendChild(fragment);
    }

    function createItemCard(item) {
        const card = document.createElement("article");
        card.className = "inventory-warehouse-item";

        const stockState = item.stock_state || {};

        const stockClass =
            stockState.key === "out_of_stock"
                ? "is-danger"
                : stockState.key === "low_stock"
                    ? "is-warning"
                    : "is-success";

        card.innerHTML = `
            <h3>
                ${escapeHtml(item.name_he || "")}
            </h3>

            <span class="inventory-warehouse-item-code">
                ${escapeHtml(item.item_code || "")}
            </span>

            <div class="inventory-warehouse-item-meta">
                ${
                    item.category_name_he
                        ? `
                            <span class="inventory-warehouse-badge">
                                ${escapeHtml(
                                    item.category_name_he
                                )}
                            </span>
                        `
                        : ""
                }

                <span class="inventory-warehouse-badge ${stockClass}">
                    ${escapeHtml(stockState.icon || "📦")}
                    ${escapeHtml(stockState.label || "")}
                </span>

                ${
                    !item.is_active
                        ? `
                            <span class="inventory-warehouse-badge is-danger">
                                מושבת
                            </span>
                        `
                        : ""
                }
            </div>

            <div class="inventory-warehouse-item-footer">
                <div class="inventory-warehouse-item-quantity">
                    ${escapeHtml(
                        formatQuantity(item.quantity)
                    )}
                    ${escapeHtml(item.unit || "")}
                </div>

                <div class="inventory-warehouse-item-actions">
                    <a
                        href="${escapeAttribute(
                            appUrl +
                            "/public/inventory/transactions.php?id=" +
                            encodeURIComponent(item.id)
                        )}"
                        class="button button-secondary"
                    >
                        תנועות
                    </a>

                    <a
                        href="${escapeAttribute(
                            appUrl +
                            "/public/inventory/add.php?id=" +
                            encodeURIComponent(item.id)
                        )}"
                        class="button button-secondary"
                    >
                        עריכה
                    </a>
                </div>
            </div>
        `;

        return card;
    }

    function handleSummaryClick(event) {
        const card = event.target.closest(
            ".inventory-warehouse-summary-card"
        );

        if (!card || !elements.location) {
            return;
        }

        const locationId = String(card.dataset.locationId || "");

        elements.location.value =
            elements.location.value === locationId
                ? ""
                : locationId;

        loadInventory();
    }

    function clearFilters() {
        if (elements.search) {
            elements.search.value = "";
        }

        if (elements.location) {
            elements.location.value = "";
        }

        if (elements.stock) {
            elements.stock.value = "all";
        }

        if (elements.active) {
            elements.active.value = "active";
        }

        loadInventory();
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

            if (elements.groups) {
                elements.groups.hidden = true;
                elements.groups.style.display = "none";
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

    function showToast(message) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");
        toast.className = "inventory-warehouse-toast";

        toast.innerHTML = `
            <span aria-hidden="true">⚠️</span>

            <div class="inventory-warehouse-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="inventory-warehouse-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".inventory-warehouse-toast-close")
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