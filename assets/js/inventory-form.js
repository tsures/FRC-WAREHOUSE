"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.inventoryFormConfig || {};

    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");
    const isEditMode = Boolean(config.isEditMode);

    const form = document.getElementById("inventoryItemForm");
    const saveButton = document.getElementById("saveInventoryItemButton");
    const saveButtonText = document.getElementById(
        "saveInventoryItemButtonText"
    );
    const saveSpinner = document.getElementById(
        "saveInventoryItemSpinner"
    );
    const generalError = document.getElementById(
        "inventoryFormGeneralError"
    );
    const toastContainer = document.getElementById(
        "inventoryFormToastContainer"
    );

    const fields = {
        id: document.getElementById("inventoryItemId"),
        item_code: document.getElementById("inventoryItemCode"),
        name_he: document.getElementById("inventoryNameHe"),
        name_en: document.getElementById("inventoryNameEn"),
        unit: document.getElementById("inventoryUnit"),
        description: document.getElementById("inventoryDescription"),
        category_id: document.getElementById("inventoryCategory"),
        location_id: document.getElementById("inventoryLocation"),
        supplier_id: document.getElementById("inventorySupplier"),
        manufacturer: document.getElementById("inventoryManufacturer"),
        model: document.getElementById("inventoryModel"),
        shelf: document.getElementById("inventoryShelf"),
        bin: document.getElementById("inventoryBin"),
        quantity: document.getElementById("inventoryQuantity"),
        minimum_quantity: document.getElementById(
            "inventoryMinimumQuantity"
        ),
        maximum_quantity: document.getElementById(
            "inventoryMaximumQuantity"
        ),
        item_condition: document.getElementById("inventoryCondition"),
        status: document.getElementById("inventoryStatus"),
        is_available: document.getElementById("inventoryIsAvailable"),
        is_favorite: document.getElementById("inventoryIsFavorite"),
        is_pinned: document.getElementById("inventoryIsPinned"),
        is_active: document.getElementById("inventoryIsActive"),
        barcode: document.getElementById("inventoryBarcode"),
        qr_code: document.getElementById("inventoryQrCode"),
        purchase_date: document.getElementById("inventoryPurchaseDate"),
        purchase_price: document.getElementById("inventoryPurchasePrice"),
        notes: document.getElementById("inventoryNotes"),
        keywords: document.getElementById("inventoryKeywords")
    };

    const errors = {
        item_code: document.getElementById("inventoryItemCodeError"),
        name_he: document.getElementById("inventoryNameHeError"),
        quantity: document.getElementById("inventoryQuantityError"),
        barcode: document.getElementById("inventoryBarcodeError"),
        qr_code: document.getElementById("inventoryQrCodeError")
    };

    let saving = false;

    if (!form) {
        return;
    }

    form.addEventListener("submit", handleSubmit);

    function handleSubmit(event) {
        event.preventDefault();

        if (saving) {
            return;
        }

        clearErrors();

        const payload = collectPayload();

        if (!validatePayload(payload)) {
            return;
        }

        saveItem(payload);
    }

    function collectPayload() {
        return {
            csrf_token: csrfToken,
            id: nullableInteger(fields.id?.value),
            item_code: valueOf("item_code"),
            name_he: valueOf("name_he"),
            name_en: valueOf("name_en"),
            unit: valueOf("unit"),
            description: valueOf("description"),
            category_id: nullableInteger(fields.category_id?.value),
            location_id: nullableInteger(fields.location_id?.value),
            supplier_id: nullableInteger(fields.supplier_id?.value),
            manufacturer: valueOf("manufacturer"),
            model: valueOf("model"),
            shelf: valueOf("shelf"),
            bin: valueOf("bin"),
            quantity: numericValue(fields.quantity?.value, 0),
            minimum_quantity: numericValue(
                fields.minimum_quantity?.value,
                0
            ),
            maximum_quantity:
                fields.maximum_quantity?.value === ""
                    ? null
                    : numericValue(fields.maximum_quantity?.value, 0),
            item_condition: valueOf("item_condition"),
            status: valueOf("status"),
            is_available: Boolean(fields.is_available?.checked),
            is_favorite: Boolean(fields.is_favorite?.checked),
            is_pinned: Boolean(fields.is_pinned?.checked),
            is_active: Boolean(fields.is_active?.checked),
            barcode: valueOf("barcode"),
            qr_code: valueOf("qr_code"),
            purchase_date: valueOf("purchase_date"),
            purchase_price:
                fields.purchase_price?.value === ""
                    ? null
                    : numericValue(fields.purchase_price?.value, 0),
            notes: valueOf("notes"),
            keywords: valueOf("keywords")
        };
    }

    function validatePayload(payload) {
        let valid = true;

        if (payload.item_code === "") {
            setFieldError(
                fields.item_code,
                errors.item_code,
                "יש להזין קוד פריט."
            );
            valid = false;
        } else if (!/^[A-Za-z0-9_\-\s]+$/.test(payload.item_code)) {
            setFieldError(
                fields.item_code,
                errors.item_code,
                "קוד הפריט יכול להכיל אותיות באנגלית, מספרים, רווח, מקף או קו תחתון."
            );
            valid = false;
        }

        if (payload.name_he === "") {
            setFieldError(
                fields.name_he,
                errors.name_he,
                "יש להזין שם פריט בעברית."
            );
            valid = false;
        }

        if (!Number.isFinite(payload.quantity) || payload.quantity < 0) {
            setFieldError(
                fields.quantity,
                errors.quantity,
                "הכמות חייבת להיות מספר שאינו שלילי."
            );
            valid = false;
        }

        if (
            !Number.isFinite(payload.minimum_quantity) ||
            payload.minimum_quantity < 0
        ) {
            showGeneralError(
                "כמות המינימום חייבת להיות מספר שאינו שלילי."
            );
            valid = false;
        }

        if (
            payload.maximum_quantity !== null &&
            (
                !Number.isFinite(payload.maximum_quantity) ||
                payload.maximum_quantity < 0
            )
        ) {
            showGeneralError(
                "כמות המקסימום חייבת להיות מספר שאינו שלילי."
            );
            valid = false;
        }

        if (
            payload.maximum_quantity !== null &&
            payload.maximum_quantity < payload.minimum_quantity
        ) {
            showGeneralError(
                "כמות המקסימום אינה יכולה להיות קטנה מכמות המינימום."
            );
            valid = false;
        }

        if (payload.unit === "") {
            showGeneralError("יש להזין יחידת מידה.");
            fields.unit?.classList.add("is-invalid");
            valid = false;
        }

        if (payload.barcode.length > 100) {
            setFieldError(
                fields.barcode,
                errors.barcode,
                "ה־Barcode יכול להכיל עד 100 תווים."
            );
            valid = false;
        }

        if (payload.qr_code.length > 190) {
            setFieldError(
                fields.qr_code,
                errors.qr_code,
                "ה־QR Code יכול להכיל עד 190 תווים."
            );
            valid = false;
        }

        return valid;
    }

    async function saveItem(payload) {
        setSaving(true);

        try {
            const response = await fetch(
                appUrl + "/api/inventory/save.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json"
                    },
                    body: JSON.stringify(payload)
                }
            );

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message || "לא ניתן לשמור את פריט המלאי."
                );
            }

            showToast(
                result.message || "פריט המלאי נשמר בהצלחה.",
                "success"
            );

            window.setTimeout(function () {
                window.location.href =
                    appUrl + "/public/inventory/";
            }, 700);
        } catch (error) {
            showGeneralError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשמור את פריט המלאי."
            );
        } finally {
            setSaving(false);
        }
    }

    function valueOf(key) {
        return String(fields[key]?.value || "").trim();
    }

    function nullableInteger(value) {
        const normalized = String(value || "").trim();

        if (normalized === "") {
            return null;
        }

        const number = Number(normalized);

        return Number.isInteger(number) && number > 0
            ? number
            : null;
    }

    function numericValue(value, fallback) {
        const normalized = String(value ?? "").trim();

        if (normalized === "") {
            return fallback;
        }

        return Number(normalized);
    }

    function clearErrors() {
        Object.values(fields).forEach(function (field) {
            field?.classList.remove("is-invalid");
        });

        Object.values(errors).forEach(function (errorElement) {
            if (errorElement) {
                errorElement.textContent = "";
            }
        });

        if (generalError) {
            generalError.textContent = "";
            generalError.hidden = true;
        }
    }

    function setFieldError(input, errorElement, message) {
        input?.classList.add("is-invalid");

        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    function showGeneralError(message) {
        if (!generalError) {
            showToast(message, "error");
            return;
        }

        generalError.textContent = message;
        generalError.hidden = false;
        generalError.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }

    function setSaving(isSaving) {
        saving = isSaving;

        if (saveButton) {
            saveButton.disabled = isSaving;
        }

        if (saveButtonText) {
            saveButtonText.textContent = isSaving
                ? "שומר..."
                : isEditMode
                    ? "שמירת שינויים"
                    : "הוספת פריט";
        }

        if (saveSpinner) {
            saveSpinner.hidden = !isSaving;
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
            throw new Error("השרת החזיר תשובה שאינה תקינה.");
        }
    }

    function showToast(message, type = "success") {
        if (!toastContainer) {
            return;
        }

        const toast = document.createElement("div");

        toast.className =
            "inventory-form-toast " +
            (type === "error" ? "is-error" : "is-success");

        toast.innerHTML = `
            <span aria-hidden="true">
                ${type === "error" ? "⚠️" : "✅"}
            </span>

            <div class="inventory-form-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="inventory-form-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".inventory-form-toast-close")
            ?.addEventListener("click", function () {
                toast.remove();
            });

        toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, type === "error" ? 6500 : 4000);
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