"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryMovementConfig || {};

    const appUrl = String(
        config.appUrl || ""
    ).replace(/\/+$/, "");

    const state = {
        selectedItem: null,
        searchTimer: null,
        searchController: null,
        saving: false,
        formToken: String(config.formToken || "")
    };

    const elements = {
        form: document.getElementById(
            "inventoryMovementForm"
        ),

        typeOptions: document.querySelectorAll(
            'input[name="transaction_type"]'
        ),

        typeCards: document.querySelectorAll(
            ".inventory-movement-type"
        ),

        search: document.getElementById(
            "inventoryMovementSearch"
        ),

        searchSpinner: document.getElementById(
            "inventoryMovementSearchSpinner"
        ),

        searchResults: document.getElementById(
            "inventoryMovementSearchResults"
        ),

        itemId: document.getElementById(
            "inventoryMovementItemId"
        ),

        selectedItem: document.getElementById(
            "inventoryMovementSelectedItem"
        ),

        selectedName: document.getElementById(
            "inventorySelectedItemName"
        ),

        selectedCode: document.getElementById(
            "inventorySelectedItemCode"
        ),

        selectedQuantity: document.getElementById(
            "inventorySelectedItemQuantity"
        ),

        selectedUnit: document.getElementById(
            "inventorySelectedItemUnit"
        ),

        selectedLocation: document.getElementById(
            "inventorySelectedItemLocation"
        ),

        selectedShelf: document.getElementById(
            "inventorySelectedItemShelf"
        ),

        changeItem: document.getElementById(
            "changeInventoryMovementItem"
        ),

        quantity: document.getElementById(
            "inventoryMovementQuantity"
        ),

        quantityHint: document.getElementById(
            "inventoryMovementQuantityHint"
        ),

        reference: document.getElementById(
            "inventoryMovementReference"
        ),

        notes: document.getElementById(
            "inventoryMovementNotes"
        ),

        error: document.getElementById(
            "inventoryMovementError"
        ),

        success: document.getElementById(
            "inventoryMovementSuccess"
        ),

        saveButton: document.getElementById(
            "saveInventoryMovementButton"
        ),

        saveButtonText: document.getElementById(
            "saveInventoryMovementButtonText"
        ),

        saveSpinner: document.getElementById(
            "saveInventoryMovementSpinner"
        ),

        resetButton: document.getElementById(
            "resetInventoryMovementForm"
        ),

        lastAction: document.getElementById(
            "inventoryMovementLastAction"
        ),

        lastActionTitle: document.getElementById(
            "inventoryLastActionTitle"
        ),

        lastActionDetails: document.getElementById(
            "inventoryLastActionDetails"
        )
    };

    bindEvents();
    updateTransactionType();

    function bindEvents() {
        elements.typeOptions.forEach(function (input) {
            input.addEventListener(
                "change",
                updateTransactionType
            );
        });

        elements.search?.addEventListener(
            "input",
            handleSearchInput
        );

        elements.search?.addEventListener(
            "focus",
            function () {
                const value = elements.search.value.trim();

                if (value.length >= 2) {
                    searchItems(value);
                }
            }
        );

        elements.changeItem?.addEventListener(
            "click",
            clearSelectedItem
        );

        elements.form?.addEventListener(
            "submit",
            saveMovement
        );

        elements.resetButton?.addEventListener(
            "click",
            function (event) {
                event.preventDefault();
                resetForm();
            }
        );

        document.addEventListener(
            "click",
            function (event) {
                if (
                    !elements.searchResults?.contains(
                        event.target
                    ) &&
                    event.target !== elements.search
                ) {
                    hideSearchResults();
                }
            }
        );
    }

    function getTransactionType() {
        return document.querySelector(
            'input[name="transaction_type"]:checked'
        )?.value || "remove";
    }

    function updateTransactionType() {
        const type = getTransactionType();

        elements.typeCards.forEach(function (card) {
            const input = card.querySelector(
                'input[name="transaction_type"]'
            );

            card.classList.toggle(
                "is-selected",
                input?.checked === true
            );
        });

        if (elements.saveButtonText) {
            elements.saveButtonText.textContent =
                type === "remove"
                    ? "הוצאת ציוד"
                    : "מילוי מלאי";
        }

        updateQuantityRules();
        hideMessages();
    }

    function handleSearchInput() {
        window.clearTimeout(state.searchTimer);

        const search = String(
            elements.search?.value || ""
        ).trim();

        if (search.length < 2) {
            hideSearchResults();

            if (state.searchController) {
                state.searchController.abort();
            }

            return;
        }

        state.searchTimer = window.setTimeout(
            function () {
                searchItems(search);
            },
            300
        );
    }

    async function searchItems(search) {
        if (state.searchController) {
            state.searchController.abort();
        }

        state.searchController =
            new AbortController();

        setSearchLoading(true);

        try {
            const parameters = new URLSearchParams({
                search: search,
                active: "active"
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
                    },
                    signal: state.searchController.signal
                }
            );

            const result = await parseJsonResponse(
                response
            );

            if (
                !response.ok ||
                result.success !== true
            ) {
                throw new Error(
                    result.message ||
                    "לא ניתן לחפש פריטים."
                );
            }

            const items = Array.isArray(
                result.data?.items
            )
                ? result.data.items
                : [];

            renderSearchResults(items);
        } catch (error) {
            if (error?.name === "AbortError") {
                return;
            }

            showError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לחפש פריטים."
            );

            hideSearchResults();
        } finally {
            setSearchLoading(false);
        }
    }

    function renderSearchResults(items) {
        if (!elements.searchResults) {
            return;
        }

        elements.searchResults.innerHTML = "";

        if (items.length === 0) {
            elements.searchResults.innerHTML = `
                <div class="inventory-search-result-empty">
                    לא נמצאו פריטים מתאימים.
                </div>
            `;

            showSearchResults();
            return;
        }

        const fragment =
            document.createDocumentFragment();

        items.slice(0, 30).forEach(function (item) {
            const button =
                document.createElement("button");

            button.type = "button";
            button.className =
                "inventory-search-result";

            button.innerHTML = `
                <span class="inventory-search-result-main">
                    <span class="inventory-search-result-name">
                        ${escapeHtml(item.name_he || "")}
                    </span>

                    <span class="inventory-search-result-code">
                        ${escapeHtml(item.item_code || "")}
                    </span>
                </span>

                <span class="inventory-search-result-stock">
                    ${formatQuantity(item.quantity)}
                    ${escapeHtml(item.unit || "")}
                </span>
            `;

            button.addEventListener(
                "click",
                function () {
                    selectItem(item);
                }
            );

            fragment.appendChild(button);
        });

        elements.searchResults.appendChild(
            fragment
        );

        showSearchResults();
    }

    function selectItem(item) {
        state.selectedItem = item;

        if (elements.itemId) {
            elements.itemId.value =
                String(item.id || "");
        }

        setText(
            elements.selectedName,
            item.name_he || "ללא שם"
        );

        setText(
            elements.selectedCode,
            item.item_code || ""
        );

        setText(
            elements.selectedQuantity,
            formatQuantity(item.quantity)
        );

        setText(
            elements.selectedUnit,
            item.unit || "—"
        );

        const locationParts = [
            item.location_name || "",
            item.location_code
                ? "(" + item.location_code + ")"
                : ""
        ].filter(Boolean);

        setText(
            elements.selectedLocation,
            locationParts.join(" ") || "ללא מיקום"
        );

        const shelfParts = [];

        if (item.shelf) {
            shelfParts.push(
                "מדף " + item.shelf
            );
        }

        if (item.bin) {
            shelfParts.push(
                "תא " + item.bin
            );
        }

        setText(
            elements.selectedShelf,
            shelfParts.join(" / ") || "לא הוגדר"
        );

        if (elements.selectedItem) {
            elements.selectedItem.hidden = false;
        }

        if (elements.search) {
            elements.search.value =
                item.name_he || item.item_code || "";
        }

        hideSearchResults();
        hideMessages();
        updateQuantityRules();

        elements.quantity?.focus();
    }

    function clearSelectedItem() {
        state.selectedItem = null;

        if (elements.itemId) {
            elements.itemId.value = "";
        }

        if (elements.search) {
            elements.search.value = "";
            elements.search.focus();
        }

        if (elements.selectedItem) {
            elements.selectedItem.hidden = true;
        }

        if (elements.quantity) {
            elements.quantity.value = "";
            elements.quantity.removeAttribute("max");
        }

        updateQuantityRules();
        hideMessages();
    }

    function updateQuantityRules() {
        if (!elements.quantity) {
            return;
        }

        const type = getTransactionType();
        const item = state.selectedItem;

        if (!item) {
            elements.quantity.removeAttribute("max");

            setText(
                elements.quantityHint,
                "בחר פריט כדי לראות את הכמות הזמינה."
            );

            return;
        }

        const quantity = Number(
            item.quantity || 0
        );

        const unit = item.unit || "";

        if (type === "remove") {
            elements.quantity.max =
                String(quantity);

            setText(
                elements.quantityHint,
                "ניתן להוציא עד " +
                    formatQuantity(quantity) +
                    " " +
                    unit
            );
        } else {
            elements.quantity.removeAttribute("max");

            setText(
                elements.quantityHint,
                "הכמות תתווסף למלאי הקיים."
            );
        }
    }

    async function saveMovement(event) {
        event.preventDefault();

        if (state.saving) {
            return;
        }

        hideMessages();

        const item = state.selectedItem;
        const type = getTransactionType();

        const quantity = Number(
            elements.quantity?.value || 0
        );

        if (!item || Number(item.id) <= 0) {
            showError("יש לבחור פריט מלאי.");
            elements.search?.focus();
            return;
        }

        if (
            !Number.isFinite(quantity) ||
            quantity <= 0
        ) {
            showError(
                "יש להזין כמות גדולה מאפס."
            );

            elements.quantity?.focus();
            return;
        }

        const currentQuantity = Number(
            item.quantity || 0
        );

        if (
            type === "remove" &&
            quantity > currentQuantity
        ) {
            showError(
                "לא ניתן להוציא יותר מ־" +
                    formatQuantity(currentQuantity) +
                    " " +
                    String(item.unit || "") +
                    "."
            );

            elements.quantity?.focus();
            return;
        }

        setSaving(true);

        try {
            const response = await fetch(
                appUrl +
                    "/api/inventory/quick-transaction.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-Requested-With":
                            "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        csrf_token:
                            String(config.csrfToken || ""),
                        form_token: state.formToken,
                        item_id: Number(item.id),
                        transaction_type: type,
                        quantity: quantity,
                        reference_number: String(
                            elements.reference?.value || ""
                        ).trim(),
                        notes: String(
                            elements.notes?.value || ""
                        ).trim()
                    })
                }
            );

            const result = await parseJsonResponse(
                response
            );

            if (
                !response.ok ||
                result.success !== true
            ) {
                throw new Error(
                    result.message ||
                    "לא ניתן לשמור את התנועה."
                );
            }

            const data = result.data || {};

            state.formToken = String(
                data.form_token || state.formToken
            );

            state.selectedItem.quantity = Number(
                data.quantity_after || 0
            );

            setText(
                elements.selectedQuantity,
                formatQuantity(
                    state.selectedItem.quantity
                )
            );

            updateQuantityRules();

            if (elements.quantity) {
                elements.quantity.value = "";
            }

            if (elements.reference) {
                elements.reference.value = "";
            }

            if (elements.notes) {
                elements.notes.value = "";
            }

            showSuccess(
                result.message ||
                "תנועת המלאי נשמרה בהצלחה."
            );

            showLastAction(
                type,
                quantity,
                data
            );
        } catch (error) {
            showError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשמור את התנועה."
            );
        } finally {
            setSaving(false);
        }
    }

    function showLastAction(
        type,
        quantity,
        data
    ) {
        if (!elements.lastAction) {
            return;
        }

        const actionText =
            type === "remove"
                ? "הוצאת ציוד הושלמה"
                : "מילוי מלאי הושלם";

        const details =
            formatQuantity(quantity) +
            " " +
            String(data.unit || "") +
            " | יתרה חדשה: " +
            formatQuantity(
                data.quantity_after || 0
            );

        setText(
            elements.lastActionTitle,
            actionText
        );

        setText(
            elements.lastActionDetails,
            details
        );

        elements.lastAction.hidden = false;
    }

    function resetForm() {
        clearSelectedItem();

        const removeOption =
            document.querySelector(
                'input[name="transaction_type"][value="remove"]'
            );

        if (removeOption) {
            removeOption.checked = true;
        }

        if (elements.reference) {
            elements.reference.value = "";
        }

        if (elements.notes) {
            elements.notes.value = "";
        }

        updateTransactionType();
        hideMessages();
    }

    function setSaving(value) {
        state.saving = value;

        if (elements.saveButton) {
            elements.saveButton.disabled = value;
        }

        if (elements.saveSpinner) {
            elements.saveSpinner.hidden = !value;
        }

        if (elements.saveButtonText && value) {
            elements.saveButtonText.textContent =
                "שומר...";
        } else {
            updateTransactionType();
        }
    }

    function setSearchLoading(value) {
        if (elements.searchSpinner) {
            elements.searchSpinner.hidden = !value;
        }
    }

    function showSearchResults() {
        if (elements.searchResults) {
            elements.searchResults.hidden = false;
        }
    }

    function hideSearchResults() {
        if (elements.searchResults) {
            elements.searchResults.hidden = true;
        }
    }

    function showError(message) {
        if (!elements.error) {
            return;
        }

        if (elements.success) {
            elements.success.hidden = true;
            elements.success.textContent = "";
        }

        elements.error.textContent = message;
        elements.error.hidden = false;
    }

    function showSuccess(message) {
        if (!elements.success) {
            return;
        }

        if (elements.error) {
            elements.error.hidden = true;
            elements.error.textContent = "";
        }

        elements.success.textContent = message;
        elements.success.hidden = false;
    }

    function hideMessages() {
        if (elements.error) {
            elements.error.hidden = true;
            elements.error.textContent = "";
        }

        if (elements.success) {
            elements.success.hidden = true;
            elements.success.textContent = "";
        }
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        if (text.trim() === "") {
            throw new Error(
                "השרת החזיר תשובה ריקה."
            );
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error(
                "Invalid JSON response:",
                text
            );

            throw new Error(
                "השרת החזיר תשובה שאינה תקינה."
            );
        }
    }

    function formatQuantity(value) {
        const number = Number(value || 0);

        return new Intl.NumberFormat(
            "he-IL",
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            }
        ).format(number);
    }

    function setText(element, value) {
        if (element) {
            element.textContent =
                String(value ?? "");
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
