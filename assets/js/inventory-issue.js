"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryIssueConfig || {};

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
            "inventoryIssueForm"
        ),

        operationInputs: document.querySelectorAll(
            'input[name="inventory_issue_operation"]'
        ),

        operationCards: document.querySelectorAll(
            ".inventory-issue-operation"
        ),

        pageTitle: document.getElementById(
            "inventoryIssuePageTitle"
        ),

        pageDescription: document.getElementById(
            "inventoryIssuePageDescription"
        ),

        detailsIcon: document.getElementById(
            "inventoryIssueDetailsIcon"
        ),

        detailsTitle: document.getElementById(
            "inventoryIssueDetailsTitle"
        ),

        detailsDescription: document.getElementById(
            "inventoryIssueDetailsDescription"
        ),

        quantityLabel: document.getElementById(
            "inventoryIssueQuantityLabel"
        ),

        recipientLabel: document.getElementById(
            "inventoryIssueRecipientLabel"
        ),

        departmentLabel: document.getElementById(
            "inventoryIssueDepartmentLabel"
        ),

        purposeLabel: document.getElementById(
            "inventoryIssuePurposeLabel"
        ),

        search: document.getElementById(
            "inventoryIssueSearch"
        ),

        searchSpinner: document.getElementById(
            "inventoryIssueSearchSpinner"
        ),

        searchResults: document.getElementById(
            "inventoryIssueSearchResults"
        ),

        itemId: document.getElementById(
            "inventoryIssueItemId"
        ),

        selectedItem: document.getElementById(
            "inventoryIssueSelectedItem"
        ),

        itemName: document.getElementById(
            "inventoryIssueItemName"
        ),

        itemCode: document.getElementById(
            "inventoryIssueItemCode"
        ),

        itemQuantity: document.getElementById(
            "inventoryIssueItemQuantity"
        ),

        itemUnit: document.getElementById(
            "inventoryIssueItemUnit"
        ),

        itemLocation: document.getElementById(
            "inventoryIssueItemLocation"
        ),

        itemShelf: document.getElementById(
            "inventoryIssueItemShelf"
        ),

        changeItem: document.getElementById(
            "inventoryIssueChangeItem"
        ),

        quantity: document.getElementById(
            "inventoryIssueQuantity"
        ),

        quantityHint: document.getElementById(
            "inventoryIssueQuantityHint"
        ),

        recipient: document.getElementById(
            "inventoryIssueRecipient"
        ),

        department: document.getElementById(
            "inventoryIssueDepartment"
        ),

        reference: document.getElementById(
            "inventoryIssueReference"
        ),

        purpose: document.getElementById(
            "inventoryIssuePurpose"
        ),

        notes: document.getElementById(
            "inventoryIssueNotes"
        ),

        error: document.getElementById(
            "inventoryIssueError"
        ),

        success: document.getElementById(
            "inventoryIssueSuccess"
        ),

        clearButton: document.getElementById(
            "inventoryIssueClearButton"
        ),

        submitButton: document.getElementById(
            "inventoryIssueSubmitButton"
        ),

        submitText: document.getElementById(
            "inventoryIssueSubmitText"
        ),

        submitSpinner: document.getElementById(
            "inventoryIssueSubmitSpinner"
        )
    };

    bindEvents();
    updateOperationUi();

    function bindEvents() {
        elements.operationInputs.forEach(
            function (input) {
                input.addEventListener(
                    "change",
                    handleOperationChange
                );
            }
        );

        elements.search?.addEventListener(
            "input",
            handleSearchInput
        );

        elements.search?.addEventListener(
            "focus",
            function () {
                const value =
                    elements.search.value.trim();

                if (value.length >= 2) {
                    searchItems(value);
                }
            }
        );

        elements.changeItem?.addEventListener(
            "click",
            clearSelectedItem
        );

        elements.clearButton?.addEventListener(
            "click",
            resetForm
        );

        elements.form?.addEventListener(
            "submit",
            submitTransaction
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

    function getOperation() {
        return document.querySelector(
            'input[name="inventory_issue_operation"]:checked'
        )?.value || "remove";
    }

    function handleOperationChange() {
        updateOperationUi();
        hideMessage();

        const searchValue = String(
            elements.search?.value || ""
        ).trim();

        if (searchValue.length >= 2) {
            searchItems(searchValue);
        }
    }

    function updateOperationUi() {
        const operation = getOperation();
        const isRemove = operation === "remove";

        elements.operationCards.forEach(
            function (card) {
                const input = card.querySelector(
                    'input[name="inventory_issue_operation"]'
                );

                card.classList.toggle(
                    "is-selected",
                    input?.checked === true
                );
            }
        );

        setText(
            elements.pageTitle,
            isRemove
                ? "הוצאת ציוד מהמחסן"
                : "הוספת מלאי לפריט קיים"
        );

        setText(
            elements.pageDescription,
            isRemove
                ? "בחר פריט, הזן כמות ופרטי מסירה, ואשר את ההוצאה."
                : "בחר פריט קיים, הזן את הכמות שהתקבלה ואשר את הוספת המלאי."
        );

        setText(
            elements.detailsIcon,
            isRemove ? "📤" : "📥"
        );

        setText(
            elements.detailsTitle,
            isRemove
                ? "פרטי ההוצאה"
                : "פרטי הוספת המלאי"
        );

        setText(
            elements.detailsDescription,
            isRemove
                ? "הכמות ופרטי האדם או הגורם שמקבל את הציוד."
                : "הכמות ופרטי המקור שממנו התקבל המלאי."
        );

        setText(
            elements.quantityLabel,
            isRemove
                ? "כמות להוצאה"
                : "כמות להוספה"
        );

        setText(
            elements.recipientLabel,
            isRemove
                ? "נמסר ל־"
                : "התקבל מ־"
        );

        setText(
            elements.departmentLabel,
            isRemove
                ? "מחלקה"
                : "ספק / מחלקה"
        );

        setText(
            elements.purposeLabel,
            isRemove
                ? "מטרת ההוצאה"
                : "סיבת ההוספה"
        );

        if (elements.recipient) {
            elements.recipient.placeholder =
                isRemove
                    ? "שם עובד, צוות או מחלקה"
                    : "שם ספק, עובד או מחלקה";
        }

        if (elements.department) {
            elements.department.placeholder =
                isRemove
                    ? "לדוגמה: ייצור, אחזקה, איכות"
                    : "לדוגמה: ספק ציוד או מחלקת רכש";
        }

        if (elements.purpose) {
            elements.purpose.placeholder =
                isRemove
                    ? "לדוגמה: התקנה במכונה, טיפול תקלה, פרויקט..."
                    : "לדוגמה: קבלה מספק, החזרה, השלמת מלאי...";
        }

        setText(
            elements.submitText,
            isRemove
                ? "הוצאת ציוד"
                : "הוספת מלאי"
        );

        updateQuantityRules();
    }

    function handleSearchInput() {
        window.clearTimeout(
            state.searchTimer
        );

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
        hideMessage();

        try {
            const parameters =
                new URLSearchParams({
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
                    signal:
                        state.searchController.signal
                }
            );

            const result = await readJson(
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

            const allItems = Array.isArray(
                result.data?.items
            )
                ? result.data.items
                : [];

            const operation = getOperation();

            const filteredItems =
                allItems.filter(
                    function (item) {
                        if (
                            item.is_active === false
                        ) {
                            return false;
                        }

                        if (operation === "add") {
                            return true;
                        }

                        return (
                            Number(
                                item.quantity || 0
                            ) > 0 &&
                            item.is_available !== false
                        );
                    }
                );

            renderSearchResults(
                filteredItems
            );
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
                <div class="inventory-issue-result-empty">
                    לא נמצאו פריטים מתאימים.
                </div>
            `;

            elements.searchResults.hidden = false;
            return;
        }

        const fragment =
            document.createDocumentFragment();

        items.slice(0, 30).forEach(
            function (item) {
                const button =
                    document.createElement(
                        "button"
                    );

                button.type = "button";

                button.className =
                    "inventory-issue-result";

                button.innerHTML = `
                    <span class="inventory-issue-result-main">
                        <span class="inventory-issue-result-name">
                            ${escapeHtml(
                                item.name_he || ""
                            )}
                        </span>

                        <span class="inventory-issue-result-code">
                            ${escapeHtml(
                                item.item_code || ""
                            )}
                        </span>
                    </span>

                    <span class="inventory-issue-result-stock">
                        ${formatQuantity(
                            item.quantity
                        )}
                        ${escapeHtml(
                            item.unit || ""
                        )}
                    </span>
                `;

                button.addEventListener(
                    "click",
                    function () {
                        selectItem(item);
                    }
                );

                fragment.appendChild(
                    button
                );
            }
        );

        elements.searchResults.appendChild(
            fragment
        );

        elements.searchResults.hidden = false;
    }

    function selectItem(item) {
        state.selectedItem = item;

        if (elements.itemId) {
            elements.itemId.value =
                String(item.id || "");
        }

        setText(
            elements.itemName,
            item.name_he || "ללא שם"
        );

        setText(
            elements.itemCode,
            item.item_code || ""
        );

        setText(
            elements.itemQuantity,
            formatQuantity(
                item.quantity
            )
        );

        setText(
            elements.itemUnit,
            item.unit || "—"
        );

        const location = [
            item.location_name || "",
            item.location_code
                ? "(" +
                    item.location_code +
                    ")"
                : ""
        ]
            .filter(Boolean)
            .join(" ");

        setText(
            elements.itemLocation,
            location || "לא הוגדר"
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
            elements.itemShelf,
            shelfParts.join(" / ") ||
                "לא הוגדר"
        );

        if (elements.selectedItem) {
            elements.selectedItem.hidden =
                false;
        }

        if (elements.search) {
            elements.search.value =
                item.name_he ||
                item.item_code ||
                "";
        }

        if (elements.quantity) {
            elements.quantity.value = "";
        }

        hideSearchResults();
        hideMessage();
        updateQuantityRules();

        elements.quantity?.focus();
    }

    function updateQuantityRules() {
        if (!elements.quantity) {
            return;
        }

        const item = state.selectedItem;
        const operation = getOperation();

        if (!item) {
            elements.quantity.removeAttribute(
                "max"
            );

            setText(
                elements.quantityHint,
                "בחר פריט כדי לראות את המלאי הקיים."
            );

            return;
        }

        const currentQuantity = Number(
            item.quantity || 0
        );

        const unit = String(
            item.unit || ""
        );

        if (operation === "remove") {
            elements.quantity.max =
                String(currentQuantity);

            setText(
                elements.quantityHint,
                "ניתן להוציא עד " +
                    formatQuantity(
                        currentQuantity
                    ) +
                    " " +
                    unit
            );
        } else {
            elements.quantity.removeAttribute(
                "max"
            );

            setText(
                elements.quantityHint,
                "המלאי הקיים הוא " +
                    formatQuantity(
                        currentQuantity
                    ) +
                    " " +
                    unit +
                    ". הכמות שתוזן תתווסף אליו."
            );
        }
    }

    function clearSelectedItem() {
        state.selectedItem = null;

        if (elements.itemId) {
            elements.itemId.value = "";
        }

        if (elements.selectedItem) {
            elements.selectedItem.hidden =
                true;
        }

        if (elements.search) {
            elements.search.value = "";
            elements.search.focus();
        }

        if (elements.quantity) {
            elements.quantity.value = "";

            elements.quantity.removeAttribute(
                "max"
            );
        }

        setText(
            elements.quantityHint,
            "בחר פריט כדי לראות את המלאי הקיים."
        );

        hideSearchResults();
        hideMessage();
    }

    async function submitTransaction(event) {
        event.preventDefault();

        if (state.saving) {
            return;
        }

        hideMessage();

        const operation = getOperation();
        const item = state.selectedItem;

        const quantity = Number(
            elements.quantity?.value || 0
        );

        const recipient = String(
            elements.recipient?.value || ""
        ).trim();

        const department = String(
            elements.department?.value || ""
        ).trim();

        const reference = String(
            elements.reference?.value || ""
        ).trim();

        const purpose = String(
            elements.purpose?.value || ""
        ).trim();

        const extraNotes = String(
            elements.notes?.value || ""
        ).trim();

        if (
            !item ||
            Number(item.id) <= 0
        ) {
            showError(
                "יש לבחור פריט מהמלאי."
            );

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
            operation === "remove" &&
            quantity > currentQuantity
        ) {
            showError(
                "לא ניתן להוציא יותר מ־" +
                    formatQuantity(
                        currentQuantity
                    ) +
                    " " +
                    String(item.unit || "") +
                    "."
            );

            elements.quantity?.focus();
            return;
        }

        if (recipient === "") {
            showError(
                operation === "remove"
                    ? "יש להזין למי הציוד נמסר."
                    : "יש להזין ממי התקבל המלאי."
            );

            elements.recipient?.focus();
            return;
        }

        if (purpose === "") {
            showError(
                operation === "remove"
                    ? "יש להזין את מטרת ההוצאה."
                    : "יש להזין את סיבת הוספת המלאי."
            );

            elements.purpose?.focus();
            return;
        }

        const notesParts =
            operation === "remove"
                ? [
                    "נמסר ל: " +
                        recipient,

                    department !== ""
                        ? "מחלקה: " +
                            department
                        : "",

                    "מטרת ההוצאה: " +
                        purpose,

                    extraNotes !== ""
                        ? "הערות: " +
                            extraNotes
                        : ""
                ]
                : [
                    "התקבל מ: " +
                        recipient,

                    department !== ""
                        ? "ספק / מחלקה: " +
                            department
                        : "",

                    "סיבת ההוספה: " +
                        purpose,

                    extraNotes !== ""
                        ? "הערות: " +
                            extraNotes
                        : ""
                ];

        setSaving(true);

        try {
            const response = await fetch(
                appUrl +
                    "/api/inventory/quick-transaction.php",
                {
                    method: "POST",
                    credentials: "same-origin",

                    headers: {
                        Accept:
                            "application/json",

                        "Content-Type":
                            "application/json",

                        "X-Requested-With":
                            "XMLHttpRequest"
                    },

                    body: JSON.stringify({
                        csrf_token:
                            String(
                                config.csrfToken ||
                                    ""
                            ),

                        form_token:
                            state.formToken,

                        item_id:
                            Number(item.id),

                        transaction_type:
                            operation,

                        quantity:
                            quantity,

                        reference_number:
                            reference,

                        notes:
                            notesParts
                                .filter(Boolean)
                                .join("\n")
                    })
                }
            );

            const result = await readJson(
                response
            );

            if (
                !response.ok ||
                result.success !== true
            ) {
                throw new Error(
                    result.message ||
                    "לא ניתן לשמור את הפעולה."
                );
            }

            const data =
                result.data || {};

            state.formToken = String(
                data.form_token ||
                    state.formToken
            );

            state.selectedItem.quantity =
                Number(
                    data.quantity_after || 0
                );

            setText(
                elements.itemQuantity,
                formatQuantity(
                    state.selectedItem
                        .quantity
                )
            );

            clearDetailFields();
            updateQuantityRules();

            showSuccess(
                operation === "remove"
                    ? (
                        "הציוד הוצא בהצלחה. " +
                        "היתרה החדשה היא " +
                        formatQuantity(
                            data.quantity_after ||
                                0
                        ) +
                        " " +
                        String(
                            data.unit || ""
                        ) +
                        "."
                    )
                    : (
                        "המלאי נוסף בהצלחה. " +
                        "היתרה החדשה היא " +
                        formatQuantity(
                            data.quantity_after ||
                                0
                        ) +
                        " " +
                        String(
                            data.unit || ""
                        ) +
                        "."
                    )
            );

            if (
                operation === "remove" &&
                state.selectedItem.quantity <= 0
            ) {
                state.selectedItem = null;

                if (elements.selectedItem) {
                    elements.selectedItem.hidden =
                        true;
                }
            }
        } catch (error) {
            showError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשמור את הפעולה."
            );
        } finally {
            setSaving(false);
        }
    }

    function clearDetailFields() {
        if (elements.quantity) {
            elements.quantity.value = "";
        }

        if (elements.recipient) {
            elements.recipient.value = "";
        }

        if (elements.department) {
            elements.department.value = "";
        }

        if (elements.reference) {
            elements.reference.value = "";
        }

        if (elements.purpose) {
            elements.purpose.value = "";
        }

        if (elements.notes) {
            elements.notes.value = "";
        }
    }

    function resetForm() {
        clearSelectedItem();
        clearDetailFields();
        updateOperationUi();
        hideMessage();
    }

    function setSaving(value) {
        state.saving = value;

        if (elements.submitButton) {
            elements.submitButton.disabled =
                value;
        }

        if (elements.submitSpinner) {
            elements.submitSpinner.hidden =
                !value;
        }

        if (elements.submitText) {
            elements.submitText.textContent =
                value
                    ? "שומר..."
                    : (
                        getOperation() ===
                        "remove"
                            ? "הוצאת ציוד"
                            : "הוספת מלאי"
                    );
        }
    }

    function setSearchLoading(value) {
        if (elements.searchSpinner) {
            elements.searchSpinner.hidden =
                !value;
        }
    }

    function hideSearchResults() {
        if (elements.searchResults) {
            elements.searchResults.hidden =
                true;
        }
    }

    function showError(message) {
        if (elements.success) {
            elements.success.hidden = true;
            elements.success.textContent = "";
        }

        if (elements.error) {
            elements.error.textContent =
                message;

            elements.error.hidden = false;
        }
    }

    function showSuccess(message) {
        if (elements.error) {
            elements.error.hidden = true;
            elements.error.textContent = "";
        }

        if (elements.success) {
            elements.success.textContent =
                message;

            elements.success.hidden = false;
        }
    }

    function hideMessage() {
        if (elements.error) {
            elements.error.hidden = true;
            elements.error.textContent = "";
        }

        if (elements.success) {
            elements.success.hidden = true;
            elements.success.textContent = "";
        }
    }

    async function readJson(response) {
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
        return new Intl.NumberFormat(
            "he-IL",
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            }
        ).format(
            Number(value || 0)
        );
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