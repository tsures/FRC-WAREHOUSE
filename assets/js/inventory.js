"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryConfig || {};

    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");
    const isAdmin = Boolean(config.isAdmin);

    const state = {
        items: [],
        options: {
            statuses: {},
            conditions: {},
            units: [],
            categories: [],
            locations: []
        },
        filters: {
            search: "",
            category_id: "",
            location_id: "",
            status: "",
            item_condition: "",
            stock: "all",
            active: "all",
            favorite: false,
            pinned: false
        },
        loading: false,
        debounceTimer: null
    };

    const elements = {
        searchInput: document.getElementById("inventorySearch"),
        clearSearchButton: document.getElementById(
            "clearInventorySearch"
        ),
        refreshButton: document.getElementById(
            "refreshInventoryButton"
        ),
        toggleFiltersButton: document.getElementById(
            "toggleInventoryFilters"
        ),
        filtersPanel: document.getElementById(
            "inventoryFilters"
        ),
        clearFiltersButton: document.getElementById(
            "clearInventoryFilters"
        ),

        categoryFilter: document.getElementById(
            "inventoryCategoryFilter"
        ),
        locationFilter: document.getElementById(
            "inventoryLocationFilter"
        ),
        statusFilter: document.getElementById(
            "inventoryStatusFilter"
        ),
        conditionFilter: document.getElementById(
            "inventoryConditionFilter"
        ),
        stockFilter: document.getElementById(
            "inventoryStockFilter"
        ),
        activeFilter: document.getElementById(
            "inventoryActiveFilter"
        ),
        favoriteFilter: document.getElementById(
            "inventoryFavoriteFilter"
        ),
        pinnedFilter: document.getElementById(
            "inventoryPinnedFilter"
        ),

        loading: document.getElementById(
            "inventoryLoading"
        ),
        emptyState: document.getElementById(
            "inventoryEmptyState"
        ),
        emptyMessage: document.getElementById(
            "inventoryEmptyMessage"
        ),
        resultsSummary: document.getElementById(
            "inventoryResultsSummary"
        ),
        returnedCount: document.getElementById(
            "inventoryReturnedCount"
        ),
        itemsContainer: document.getElementById(
            "inventoryItems"
        ),

        totalCount: document.getElementById(
            "inventoryTotalCount"
        ),
        availableCount: document.getElementById(
            "inventoryAvailableCount"
        ),
        lowStockCount: document.getElementById(
            "inventoryLowStockCount"
        ),
        outOfStockCount: document.getElementById(
            "inventoryOutOfStockCount"
        ),
        brokenCount: document.getElementById(
            "inventoryBrokenCount"
        ),
        pinnedCount: document.getElementById(
            "inventoryPinnedCount"
        ),

        toastContainer: document.getElementById(
            "inventoryToastContainer"
        )
    };

    initialize();

    function initialize() {
        bindEvents();
        loadInventory();
    }

    function bindEvents() {
        if (elements.searchInput) {
            elements.searchInput.addEventListener(
                "input",
                function () {
                    state.filters.search =
                        elements.searchInput.value.trim();

                    if (elements.clearSearchButton) {
                        elements.clearSearchButton.hidden =
                            state.filters.search === "";
                    }

                    window.clearTimeout(
                        state.debounceTimer
                    );

                    state.debounceTimer =
                        window.setTimeout(
                            function () {
                                loadInventory();
                            },
                            350
                        );
                }
            );
        }

        if (elements.clearSearchButton) {
            elements.clearSearchButton.addEventListener(
                "click",
                function () {
                    if (!elements.searchInput) {
                        return;
                    }

                    elements.searchInput.value = "";
                    state.filters.search = "";
                    elements.clearSearchButton.hidden = true;

                    loadInventory();
                    elements.searchInput.focus();
                }
            );
        }

        if (elements.refreshButton) {
            elements.refreshButton.addEventListener(
                "click",
                function () {
                    loadInventory();
                }
            );
        }

        if (
            elements.toggleFiltersButton &&
            elements.filtersPanel
        ) {
            elements.toggleFiltersButton.addEventListener(
                "click",
                function () {
                    const isExpanded =
                        elements.toggleFiltersButton.getAttribute(
                            "aria-expanded"
                        ) === "true";

                    elements.toggleFiltersButton.setAttribute(
                        "aria-expanded",
                        isExpanded ? "false" : "true"
                    );

                    elements.filtersPanel.hidden =
                        isExpanded;
                }
            );
        }

        bindFilter(
            elements.categoryFilter,
            "category_id"
        );

        bindFilter(
            elements.locationFilter,
            "location_id"
        );

        bindFilter(
            elements.statusFilter,
            "status"
        );

        bindFilter(
            elements.conditionFilter,
            "item_condition"
        );

        bindFilter(
            elements.stockFilter,
            "stock"
        );

        bindFilter(
            elements.activeFilter,
            "active"
        );

        if (elements.favoriteFilter) {
            elements.favoriteFilter.addEventListener(
                "change",
                function () {
                    state.filters.favorite =
                        elements.favoriteFilter.checked;

                    loadInventory();
                }
            );
        }

        if (elements.pinnedFilter) {
            elements.pinnedFilter.addEventListener(
                "change",
                function () {
                    state.filters.pinned =
                        elements.pinnedFilter.checked;

                    loadInventory();
                }
            );
        }

        if (elements.clearFiltersButton) {
            elements.clearFiltersButton.addEventListener(
                "click",
                function () {
                    clearFilters();
                }
            );
        }

        if (elements.itemsContainer) {
            elements.itemsContainer.addEventListener(
                "click",
                handleItemAction
            );
        }
    }

    function bindFilter(element, key) {
        if (!element) {
            return;
        }

        element.addEventListener(
            "change",
            function () {
                state.filters[key] = element.value;
                loadInventory();
            }
        );
    }

    async function loadInventory() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters =
                new URLSearchParams();

            Object.entries(
                state.filters
            ).forEach(function ([key, value]) {
                if (typeof value === "boolean") {
                    if (value) {
                        parameters.set(
                            key,
                            "1"
                        );
                    }

                    return;
                }

                if (
                    value !== "" &&
                    value !== null &&
                    value !== undefined
                ) {
                    parameters.set(
                        key,
                        String(value)
                    );
                }
            });

            const response = await fetch(
                appUrl +
                    "/api/inventory/list.php?" +
                    parameters.toString(),
                {
                    method: "GET",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json"
                    }
                }
            );

            const result =
                await parseJsonResponse(
                    response
                );

            if (
                !response.ok ||
                result.success !== true
            ) {
                throw new Error(
                    result.message ||
                        "לא ניתן לטעון את פריטי המלאי."
                );
            }

            const data = result.data || {};

            state.items = Array.isArray(
                data.items
            )
                ? data.items
                : [];

            state.options = {
                statuses:
                    data.options?.statuses || {},
                conditions:
                    data.options?.conditions || {},
                units: Array.isArray(
                    data.options?.units
                )
                    ? data.options.units
                    : [],
                categories: Array.isArray(
                    data.options?.categories
                )
                    ? data.options.categories
                    : [],
                locations: Array.isArray(
                    data.options?.locations
                )
                    ? data.options.locations
                    : []
            };

            fillFilterOptions();

            updateStatistics(
                data.statistics || {}
            );

            renderItems(
                data.meta || {}
            );
        } catch (error) {
            state.items = [];

            renderItems({
                returned_count: 0
            });

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את פריטי המלאי.",
                "error"
            );
        } finally {
            setLoading(false);
        }
    }

    function fillFilterOptions() {
        fillSelect(
            elements.categoryFilter,
            state.options.categories,
            "id",
            function (category) {
                return String(
                    category.name_he || ""
                );
            },
            "כל הקטגוריות"
        );

        fillSelect(
            elements.locationFilter,
            state.options.locations,
            "id",
            function (location) {
                const code = location.code
                    ? ` (${location.code})`
                    : "";

                return (
                    String(
                        location.name || ""
                    ) + code
                );
            },
            "כל המיקומים"
        );

        fillObjectSelect(
            elements.statusFilter,
            state.options.statuses,
            "כל הסטטוסים"
        );

        fillObjectSelect(
            elements.conditionFilter,
            state.options.conditions,
            "כל המצבים"
        );

        restoreFilterValues();
    }

    function fillSelect(
        element,
        items,
        valueKey,
        labelResolver,
        firstLabel
    ) {
        if (!element) {
            return;
        }

        const currentValue =
            element.value;

        element.innerHTML = "";

        const firstOption =
            document.createElement(
                "option"
            );

        firstOption.value = "";
        firstOption.textContent =
            firstLabel;

        element.appendChild(
            firstOption
        );

        items.forEach(function (item) {
            const option =
                document.createElement(
                    "option"
                );

            option.value = String(
                item[valueKey] ?? ""
            );

            option.textContent =
                labelResolver(item);

            element.appendChild(option);
        });

        element.value = currentValue;
    }

    function fillObjectSelect(
        element,
        values,
        firstLabel
    ) {
        if (!element) {
            return;
        }

        const currentValue =
            element.value;

        element.innerHTML = "";

        const firstOption =
            document.createElement(
                "option"
            );

        firstOption.value = "";
        firstOption.textContent =
            firstLabel;

        element.appendChild(
            firstOption
        );

        Object.entries(
            values
        ).forEach(function ([value, label]) {
            const option =
                document.createElement(
                    "option"
                );

            option.value = value;
            option.textContent =
                String(label);

            element.appendChild(
                option
            );
        });

        element.value = currentValue;
    }

    function restoreFilterValues() {
        setElementValue(
            elements.categoryFilter,
            state.filters.category_id
        );

        setElementValue(
            elements.locationFilter,
            state.filters.location_id
        );

        setElementValue(
            elements.statusFilter,
            state.filters.status
        );

        setElementValue(
            elements.conditionFilter,
            state.filters.item_condition
        );

        setElementValue(
            elements.stockFilter,
            state.filters.stock
        );

        setElementValue(
            elements.activeFilter,
            state.filters.active
        );

        if (elements.favoriteFilter) {
            elements.favoriteFilter.checked =
                state.filters.favorite;
        }

        if (elements.pinnedFilter) {
            elements.pinnedFilter.checked =
                state.filters.pinned;
        }
    }

    function renderItems(meta) {
        if (
            !elements.itemsContainer ||
            !elements.emptyState ||
            !elements.resultsSummary
        ) {
            return;
        }

        elements.itemsContainer.innerHTML =
            "";

        const hasItems =
            state.items.length > 0;

        elements.emptyState.hidden =
            hasItems;

        elements.resultsSummary.hidden =
            !hasItems;

        if (elements.returnedCount) {
            elements.returnedCount.textContent =
                String(
                    meta.returned_count ??
                        state.items.length
                );
        }

        if (!hasItems) {
            updateEmptyStateMessage();
            return;
        }

        const fragment =
            document.createDocumentFragment();

        state.items.forEach(
            function (item) {
                fragment.appendChild(
                    createItemCard(item)
                );
            }
        );

        elements.itemsContainer.appendChild(
            fragment
        );
    }

    function createItemCard(item) {
        const card =
            document.createElement(
                "article"
            );

        card.className =
            "inventory-card";

        card.dataset.itemId =
            String(item.id);

        if (!item.is_active) {
            card.classList.add(
                "is-inactive"
            );
        }

        const stockState =
            item.stock_state || {};

        const stockBadgeClass =
            getStockBadgeClass(
                stockState.key
            );

        const descriptionHtml =
            item.description
                ? `
                    <p class="inventory-card-description">
                        ${escapeHtml(item.description)}
                    </p>
                `
                : "";

        const categoryHtml =
            item.category_name_he
                ? createMetaRow(
                    "קטגוריה",
                    item.category_name_he
                )
                : "";

        const locationHtml =
            item.location_name
                ? createMetaRow(
                    "מיקום",
                    item.location_name +
                        (
                            item.location_code
                                ? ` (${item.location_code})`
                                : ""
                        )
                )
                : "";

        const manufacturerHtml =
            item.manufacturer
                ? createMetaRow(
                    "יצרן",
                    item.manufacturer +
                        (
                            item.model
                                ? ` / ${item.model}`
                                : ""
                        )
                )
                : item.model
                    ? createMetaRow(
                        "דגם",
                        item.model
                    )
                    : "";

        const barcodeHtml =
            item.barcode
                ? createMetaRow(
                    "ברקוד",
                    item.barcode
                )
                : "";

        const adminActions =
            isAdmin
                ? `
                   <a
    href="${escapeAttribute(
        appUrl +
            "/public/inventory/transactions.php?id=" +
            encodeURIComponent(
                item.id
            )
    )}"
    class="inventory-action-button"
    title="תנועות מלאי"
>
    <span aria-hidden="true">
        🔄
    </span>

    <span>
        תנועות
    </span>
</a>

                    <button
                        type="button"
                        class="inventory-action-button ${
                            item.is_active
                                ? "is-danger"
                                : "is-success"
                        }"
                        data-action="toggle-status"
                        data-item-id="${escapeAttribute(
                            item.id
                        )}"
                    >
                        <span aria-hidden="true">
                            ${
                                item.is_active
                                    ? "⛔"
                                    : "✅"
                            }
                        </span>

                        <span>
                            ${
                                item.is_active
                                    ? "השבתה"
                                    : "הפעלה"
                            }
                        </span>
                    </button>
                `
                : "";

        card.innerHTML = `
            <div class="inventory-card-header">
                <div class="inventory-card-title-wrap">
                    <h2 class="inventory-card-title">
                        ${escapeHtml(
                            item.name_he || ""
                        )}
                    </h2>

                    <span class="inventory-card-code">
                        ${escapeHtml(
                            item.item_code || ""
                        )}
                    </span>
                </div>

                <div class="inventory-card-markers">
                    ${
                        item.is_pinned
                            ? `
                                <span
                                    class="inventory-marker"
                                    title="פריט נעוץ"
                                >
                                    📌
                                </span>
                            `
                            : ""
                    }

                    ${
                        item.is_favorite
                            ? `
                                <span
                                    class="inventory-marker"
                                    title="פריט מועדף"
                                >
                                    ⭐
                                </span>
                            `
                            : ""
                    }
                </div>
            </div>

            <div class="inventory-card-body">
                ${descriptionHtml}

                <div class="inventory-badges">
                    <span class="inventory-badge is-info">
                        ${escapeHtml(
                            item.status_icon || "📦"
                        )}

                        ${escapeHtml(
                            item.status_label || ""
                        )}
                    </span>

                    <span class="inventory-badge">
                        ${escapeHtml(
                            item.condition_icon || "📦"
                        )}

                        ${escapeHtml(
                            item.condition_label || ""
                        )}
                    </span>

                    <span class="inventory-badge ${stockBadgeClass}">
                        ${escapeHtml(
                            stockState.icon || "📦"
                        )}

                        ${escapeHtml(
                            stockState.label || ""
                        )}
                    </span>

                    ${
                        !item.is_active
                            ? `
                                <span class="inventory-badge is-danger">
                                    מושבת
                                </span>
                            `
                            : ""
                    }
                </div>

                <div class="inventory-quantity-row">
                    ${createQuantityBox(
                        "כמות",
                        formatQuantity(
                            item.quantity
                        ),
                        item.unit
                    )}

                    ${createQuantityBox(
                        "מינימום",
                        formatQuantity(
                            item.minimum_quantity
                        ),
                        item.unit
                    )}

                    ${createQuantityBox(
                        "מקסימום",
                        item.maximum_quantity === null
                            ? "—"
                            : formatQuantity(
                                item.maximum_quantity
                            ),
                        item.maximum_quantity === null
                            ? ""
                            : item.unit
                    )}
                </div>

                <div class="inventory-meta-list">
                    ${categoryHtml}
                    ${locationHtml}
                    ${manufacturerHtml}
                    ${barcodeHtml}
                </div>
            </div>

            <div class="inventory-card-actions">
                <a
                    href="${escapeAttribute(
                        appUrl +
                            "/public/inventory/add.php?id=" +
                            encodeURIComponent(
                                item.id
                            )
                    )}"
                    class="inventory-action-button"
                    title="צפייה בפריט"
                >
                    <span aria-hidden="true">
                        👁️
                    </span>

                    <span>
                        צפייה
                    </span>
                </a>

                ${adminActions}
            </div>
        `;

        return card;
    }

    function createQuantityBox(
        label,
        value,
        unit
    ) {
        return `
            <div class="inventory-quantity-box">
                <span class="inventory-quantity-label">
                    ${escapeHtml(label)}
                </span>

                <strong class="inventory-quantity-value">
                    ${escapeHtml(value)}

                    ${
                        unit
                            ? `
                                <small>
                                    ${escapeHtml(unit)}
                                </small>
                            `
                            : ""
                    }
                </strong>
            </div>
        `;
    }

    function createMetaRow(
        label,
        value
    ) {
        return `
            <div class="inventory-meta-row">
                <span class="inventory-meta-label">
                    ${escapeHtml(label)}:
                </span>

                <span class="inventory-meta-value">
                    ${escapeHtml(value)}
                </span>
            </div>
        `;
    }

    function getStockBadgeClass(
        stockKey
    ) {
        if (
            stockKey ===
            "out_of_stock"
        ) {
            return "is-danger";
        }

        if (
            stockKey ===
            "low_stock"
        ) {
            return "is-warning";
        }

        if (
            stockKey ===
            "normal"
        ) {
            return "is-success";
        }

        return "is-info";
    }

    async function handleItemAction(
        event
    ) {
        const button =
            event.target.closest(
                "[data-action]"
            );

        if (!button) {
            return;
        }

        const action =
            button.dataset.action;

        const itemId = Number(
            button.dataset.itemId || 0
        );

        if (itemId <= 0) {
            return;
        }

        if (
            action ===
            "toggle-status"
        ) {
            await toggleItemStatus(
                itemId,
                button
            );
        }
    }

    async function toggleItemStatus(
        itemId,
        button
    ) {
        const item =
            state.items.find(
                function (currentItem) {
                    return (
                        Number(
                            currentItem.id
                        ) === itemId
                    );
                }
            );

        if (!item) {
            showToast(
                "פריט המלאי לא נמצא.",
                "error"
            );

            return;
        }

        const actionText =
            item.is_active
                ? "להשבית"
                : "להפעיל";

        const confirmed =
            window.confirm(
                `האם אתה בטוח שברצונך ${actionText} את הפריט "${item.name_he}"?`
            );

        if (!confirmed) {
            return;
        }

        const originalHtml =
            button.innerHTML;

        button.disabled = true;
        button.innerHTML =
            "<span>...</span>";

        try {
            const response =
                await fetch(
                    appUrl +
                        "/api/inventory/toggle.php",
                    {
                        method: "POST",
                        credentials:
                            "same-origin",
                        headers: {
                            "Content-Type":
                                "application/json",
                            Accept:
                                "application/json"
                        },
                        body: JSON.stringify(
                            {
                                csrf_token:
                                    csrfToken,
                                id: itemId
                            }
                        )
                    }
                );

            const result =
                await parseJsonResponse(
                    response
                );

            if (
                !response.ok ||
                result.success !== true
            ) {
                throw new Error(
                    result.message ||
                        "לא ניתן לשנות את מצב פריט המלאי."
                );
            }

            showToast(
                result.message ||
                    "מצב פריט המלאי עודכן בהצלחה.",
                "success"
            );

            await loadInventory();
        } catch (error) {
            button.disabled = false;
            button.innerHTML =
                originalHtml;

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשנות את מצב פריט המלאי.",
                "error"
            );
        }
    }

    function clearFilters() {
        state.filters.category_id =
            "";

        state.filters.location_id =
            "";

        state.filters.status =
            "";

        state.filters.item_condition =
            "";

        state.filters.stock =
            "all";

        state.filters.active =
            "all";

        state.filters.favorite =
            false;

        state.filters.pinned =
            false;

        restoreFilterValues();
        loadInventory();
    }

    function updateStatistics(
        statistics
    ) {
        setText(
            elements.totalCount,
            statistics.total_count || 0
        );

        setText(
            elements.availableCount,
            statistics.available_count || 0
        );

        setText(
            elements.lowStockCount,
            statistics.low_stock_count || 0
        );

        setText(
            elements.outOfStockCount,
            statistics.out_of_stock_count || 0
        );

        setText(
            elements.brokenCount,
            statistics.broken_count || 0
        );

        setText(
            elements.pinnedCount,
            statistics.pinned_count || 0
        );
    }

    function updateEmptyStateMessage() {
        if (!elements.emptyMessage) {
            return;
        }

        const hasSearch =
            state.filters.search !== "";

        const hasFilters =
            state.filters.category_id !== "" ||
            state.filters.location_id !== "" ||
            state.filters.status !== "" ||
            state.filters.item_condition !== "" ||
            state.filters.stock !== "all" ||
            state.filters.active !== "all" ||
            state.filters.favorite ||
            state.filters.pinned;

        if (
            hasSearch ||
            hasFilters
        ) {
            elements.emptyMessage.textContent =
                "לא נמצאו פריטים התואמים לחיפוש או למסננים.";

            return;
        }

        elements.emptyMessage.textContent =
            "עדיין לא נוספו פריטים למערכת.";
    }

    function setLoading(
        isLoading
    ) {
        state.loading =
            isLoading;

        if (elements.loading) {
            elements.loading.hidden =
                !isLoading;
        }

        if (
            elements.itemsContainer
        ) {
            elements.itemsContainer.hidden =
                isLoading;
        }

        if (
            elements.emptyState &&
            isLoading
        ) {
            elements.emptyState.hidden =
                true;
        }

        if (
            elements.resultsSummary &&
            isLoading
        ) {
            elements.resultsSummary.hidden =
                true;
        }

        if (
            elements.refreshButton
        ) {
            elements.refreshButton.disabled =
                isLoading;
        }
    }

    async function parseJsonResponse(
        response
    ) {
        const responseText =
            await response.text();

        if (
            responseText.trim() === ""
        ) {
            throw new Error(
                "השרת החזיר תשובה ריקה."
            );
        }

        try {
            return JSON.parse(
                responseText
            );
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

    function showToast(
        message,
        type = "success"
    ) {
        if (
            !elements.toastContainer
        ) {
            return;
        }

        const toast =
            document.createElement(
                "div"
            );

        toast.className =
            "inventory-toast " +
            (
                type === "error"
                    ? "is-error"
                    : "is-success"
            );

        toast.innerHTML = `
            <span
                class="inventory-toast-icon"
                aria-hidden="true"
            >
                ${
                    type === "error"
                        ? "⚠️"
                        : "✅"
                }
            </span>

            <div class="inventory-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="inventory-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        const closeButton =
            toast.querySelector(
                ".inventory-toast-close"
            );

        if (closeButton) {
            closeButton.addEventListener(
                "click",
                function () {
                    toast.remove();
                }
            );
        }

        elements.toastContainer.appendChild(
            toast
        );

        window.setTimeout(
            function () {
                toast.remove();
            },
            type === "error"
                ? 6500
                : 4000
        );
    }

    function formatQuantity(
        value
    ) {
        const number =
            Number(value || 0);

        return new Intl.NumberFormat(
            "he-IL",
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            }
        ).format(number);
    }

    function setElementValue(
        element,
        value
    ) {
        if (element) {
            element.value =
                String(value ?? "");
        }
    }

    function setText(
        element,
        value
    ) {
        if (element) {
            element.textContent =
                String(value);
        }
    }

    function escapeHtml(
        value
    ) {
        return String(value ?? "")
            .replaceAll(
                "&",
                "&amp;"
            )
            .replaceAll(
                "<",
                "&lt;"
            )
            .replaceAll(
                ">",
                "&gt;"
            )
            .replaceAll(
                '"',
                "&quot;"
            )
            .replaceAll(
                "'",
                "&#039;"
            );
    }

    function escapeAttribute(
        value
    ) {
        return escapeHtml(
            String(value ?? "")
        );
    }
});