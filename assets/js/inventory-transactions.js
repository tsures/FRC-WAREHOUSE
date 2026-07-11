"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryTransactionsConfig || {};

    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");
    const itemId = Number(config.itemId || 0);
    const isAdmin = Boolean(config.isAdmin);
    const unit = String(config.unit || "");

    const form = document.getElementById("inventoryTransactionForm");
    const typeInput = document.getElementById("transactionType");

    const quantityGroup = document.getElementById(
        "transactionQuantityGroup"
    );

    const quantityInput = document.getElementById(
        "transactionQuantity"
    );

    const newQuantityGroup = document.getElementById(
        "transactionNewQuantityGroup"
    );

    const newQuantityInput = document.getElementById(
        "transactionNewQuantity"
    );

    const locationGroup = document.getElementById(
        "transactionLocationGroup"
    );

    const locationInput = document.getElementById(
        "transactionToLocation"
    );

    const referenceInput = document.getElementById(
        "transactionReference"
    );

    const notesInput = document.getElementById(
        "transactionNotes"
    );

    const errorBox = document.getElementById(
        "transactionFormError"
    );

    const saveButton = document.getElementById(
        "saveTransactionButton"
    );

    const saveButtonText = document.getElementById(
        "saveTransactionButtonText"
    );

    const saveSpinner = document.getElementById(
        "saveTransactionSpinner"
    );

    const refreshButton = document.getElementById(
        "refreshTransactionsButton"
    );

    const loading = document.getElementById(
        "transactionsLoading"
    );

    const emptyState = document.getElementById(
        "transactionsEmptyState"
    );

    const tableWrapper = document.getElementById(
        "transactionsTableWrapper"
    );

    const tableBody = document.getElementById(
        "transactionsTableBody"
    );

    const currentQuantity = document.getElementById(
        "currentInventoryQuantity"
    );

    const toastContainer = document.getElementById(
        "transactionToastContainer"
    );

    let saving = false;

    if (typeInput) {
        typeInput.addEventListener(
            "change",
            updateVisibleFields
        );
    }

    if (form && isAdmin) {
        form.addEventListener(
            "submit",
            handleSubmit
        );
    }

    if (refreshButton) {
        refreshButton.addEventListener(
            "click",
            loadTransactions
        );
    }

    updateVisibleFields();
    loadTransactions();

    function updateVisibleFields() {
        const type = String(
            typeInput?.value || ""
        );

        const isAdjustment =
            type === "adjustment";

        const isTransfer =
            type === "transfer";

        const hidesQuantity =
            isAdjustment ||
            isTransfer ||
            type === "retire";

        if (quantityGroup) {
            quantityGroup.hidden =
                hidesQuantity;
        }

        if (newQuantityGroup) {
            newQuantityGroup.hidden =
                !isAdjustment;
        }

        if (locationGroup) {
            locationGroup.hidden =
                !isTransfer;
        }

        if (quantityInput) {
            quantityInput.required =
                !hidesQuantity;
        }

        if (newQuantityInput) {
            newQuantityInput.required =
                isAdjustment;
        }

        if (locationInput) {
            locationInput.required =
                isTransfer;
        }

        hideError();
    }

    async function handleSubmit(event) {
        event.preventDefault();

        if (saving) {
            return;
        }

        hideError();

        const payload = {
            csrf_token: csrfToken,
            item_id: itemId,
            transaction_type: String(
                typeInput?.value || ""
            ),
            quantity:
                quantityInput?.value || null,
            new_quantity:
                newQuantityInput?.value || null,
            to_location_id:
                locationInput?.value || null,
            reference_number: String(
                referenceInput?.value || ""
            ).trim(),
            notes: String(
                notesInput?.value || ""
            ).trim()
        };

        if (
            payload.transaction_type === ""
        ) {
            showError(
                "יש לבחור סוג תנועה."
            );

            return;
        }

        if (
            ![
                "adjustment",
                "transfer",
                "retire"
            ].includes(
                payload.transaction_type
            ) &&
            (
                payload.quantity === null ||
                Number(payload.quantity) <= 0
            )
        ) {
            showError(
                "יש להזין כמות גדולה מאפס."
            );

            return;
        }

        if (
            payload.transaction_type ===
                "adjustment" &&
            (
                payload.new_quantity === null ||
                Number(
                    payload.new_quantity
                ) < 0
            )
        ) {
            showError(
                "יש להזין כמות חדשה שאינה שלילית."
            );

            return;
        }

        if (
            payload.transaction_type ===
                "transfer" &&
            !payload.to_location_id
        ) {
            showError(
                "יש לבחור מיקום יעד."
            );

            return;
        }

        await saveTransaction(
            payload
        );
    }

    async function saveTransaction(
        payload
    ) {
        setSaving(true);

        try {
            const response = await fetch(
                appUrl +
                    "/api/inventory/transaction.php",
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
                        payload
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
                        "לא ניתן לשמור את התנועה."
                );
            }

            showToast(
                result.message ||
                    "התנועה נשמרה בהצלחה.",
                "success"
            );

            if (form) {
                form.reset();
            }

            updateVisibleFields();

            await loadTransactions();
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

    async function loadTransactions() {
        setHistoryLoading(true);

        try {
            const response = await fetch(
                appUrl +
                    "/api/inventory/transactions.php?item_id=" +
                    encodeURIComponent(
                        itemId
                    ) +
                    "&limit=200",
                {
                    method: "GET",
                    credentials:
                        "same-origin",
                    headers: {
                        Accept:
                            "application/json"
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
                        "לא ניתן לטעון את התנועות."
                );
            }

            const data =
                result.data || {};

            const item =
                data.item || {};

            const transactions =
                Array.isArray(
                    data.transactions
                )
                    ? data.transactions
                    : [];

            if (currentQuantity) {
                currentQuantity.textContent =
                    formatQuantity(
                        item.quantity || 0
                    );
            }

            renderTransactions(
                transactions
            );
        } catch (error) {
            renderTransactions([]);

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את התנועות.",
                "error"
            );
        } finally {
            setHistoryLoading(false);
        }
    }

  function renderTransactions(transactions) {
    if (!tableBody || !emptyState || !tableWrapper) {
        return;
    }

    tableBody.innerHTML = "";

    const hasTransactions = transactions.length > 0;

    emptyState.hidden = hasTransactions;
    emptyState.style.display = hasTransactions
        ? "none"
        : "block";

    tableWrapper.hidden = !hasTransactions;
    tableWrapper.style.display = hasTransactions
        ? "block"
        : "none";

    if (!hasTransactions) {
        return;
    }

    const fragment = document.createDocumentFragment();

    transactions.forEach(function (transaction) {
        const row = document.createElement("tr");
        const change = Number(transaction.quantity_change || 0);

        const changeClass = change > 0
            ? "transaction-change-positive"
            : change < 0
                ? "transaction-change-negative"
                : "transaction-change-neutral";

        const locationText = getLocationText(transaction);

        row.innerHTML = `
            <td>${escapeHtml(formatDateTime(transaction.created_at))}</td>

            <td>
                <span class="transaction-type-badge">
                    ${escapeHtml(transaction.transaction_icon || "📦")}
                    ${escapeHtml(transaction.transaction_label || "")}
                </span>
            </td>

            <td class="${changeClass}">
                ${escapeHtml(formatSignedQuantity(change))}
                ${escapeHtml(unit)}
            </td>

            <td>
                ${escapeHtml(formatQuantity(transaction.quantity_before))}
            </td>

            <td>
                ${escapeHtml(formatQuantity(transaction.quantity_after))}
            </td>

            <td>
                ${escapeHtml(locationText)}
            </td>

            <td>
                ${escapeHtml(transaction.reference_number || "—")}
            </td>

            <td>
                ${escapeHtml(transaction.created_by_name || "מערכת")}
            </td>

            <td>
                ${escapeHtml(transaction.notes || "—")}
            </td>
        `;

        fragment.appendChild(row);
    });

    tableBody.appendChild(fragment);
}
    function getLocationText(
        transaction
    ) {
        const fromName =
            transaction.from_location_name ||
            "";

        const toName =
            transaction.to_location_name ||
            "";

        if (
            fromName &&
            toName
        ) {
            return (
                fromName +
                " ← " +
                toName
            );
        }

        if (toName) {
            return toName;
        }

        if (fromName) {
            return fromName;
        }

        return "—";
    }

    function setSaving(
        isSaving
    ) {
        saving = isSaving;

        if (saveButton) {
            saveButton.disabled =
                isSaving;
        }

        if (saveButtonText) {
            saveButtonText.textContent =
                isSaving
                    ? "שומר..."
                    : "שמירת תנועה";
        }

        if (saveSpinner) {
            saveSpinner.hidden =
                !isSaving;
        }
    }

 function setHistoryLoading(isLoading) {
    if (loading) {
        loading.hidden = !isLoading;
        loading.style.display = isLoading
            ? "flex"
            : "none";
    }

    if (refreshButton) {
        refreshButton.disabled = isLoading;
    }

    if (isLoading) {
        if (emptyState) {
            emptyState.hidden = true;
            emptyState.style.display = "none";
        }

        if (tableWrapper) {
            tableWrapper.hidden = true;
            tableWrapper.style.display = "none";
        }
    }
}

    function showError(message) {
        if (!errorBox) {
            showToast(
                message,
                "error"
            );

            return;
        }

        errorBox.textContent =
            message;

        errorBox.hidden = false;

        errorBox.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }

    function hideError() {
        if (errorBox) {
            errorBox.textContent = "";
            errorBox.hidden = true;
        }
    }

    async function parseJsonResponse(
        response
    ) {
        const text =
            await response.text();

        if (
            text.trim() === ""
        ) {
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

    function formatSignedQuantity(
        value
    ) {
        const number =
            Number(value || 0);

        const formatted =
            formatQuantity(
                Math.abs(number)
            );

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

        const normalized =
            String(value).replace(
                " ",
                "T"
            );

        const date =
            new Date(normalized);

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return String(value);
        }

        return new Intl.DateTimeFormat(
            "he-IL",
            {
                dateStyle: "short",
                timeStyle: "short"
            }
        ).format(date);
    }

    function showToast(
        message,
        type = "success"
    ) {
        if (!toastContainer) {
            return;
        }

        const toast =
            document.createElement(
                "div"
            );

        toast.className =
            "transaction-toast " +
            (
                type === "error"
                    ? "is-error"
                    : "is-success"
            );

        toast.innerHTML = `
            <span aria-hidden="true">
                ${
                    type === "error"
                        ? "⚠️"
                        : "✅"
                }
            </span>

            <div class="transaction-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="transaction-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(
                ".transaction-toast-close"
            )
            ?.addEventListener(
                "click",
                function () {
                    toast.remove();
                }
            );

        toastContainer.appendChild(
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

    function escapeHtml(value) {
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
});