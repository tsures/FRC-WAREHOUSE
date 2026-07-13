"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.profileConfig || {};
    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");

    const elements = {
        form: document.getElementById("profileForm"),
        fullName: document.getElementById("profileFullName"),
        email: document.getElementById("profileEmail"),
        phone: document.getElementById("profilePhone"),
        saveButton: document.getElementById("saveProfileButton"),
        error: document.getElementById("profileFormError"),
        success: document.getElementById("profileFormSuccess"),
        toastContainer: document.getElementById(
            "profileToastContainer"
        )
    };

    let saving = false;

    elements.form?.addEventListener("submit", saveProfile);

    async function saveProfile(event) {
        event.preventDefault();

        if (saving) {
            return;
        }

        hideMessages();

        const fullName = String(
            elements.fullName?.value || ""
        ).trim();

        const email = String(
            elements.email?.value || ""
        ).trim();

        const phone = String(
            elements.phone?.value || ""
        ).trim();

        if (fullName === "") {
            showError("יש להזין שם מלא.");
            elements.fullName?.focus();
            return;
        }

        saving = true;

        if (elements.saveButton) {
            elements.saveButton.disabled = true;
            elements.saveButton.textContent = "שומר...";
        }

        try {
            const response = await fetch(
                appUrl + "/api/users/profile-update.php",
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
                        full_name: fullName,
                        email: email,
                        phone: phone
                    })
                }
            );

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message ||
                    "לא ניתן לעדכן את הפרופיל."
                );
            }

            showSuccess(
                result.message ||
                "הפרופיל עודכן בהצלחה."
            );

            showToast(
                result.message ||
                "הפרופיל עודכן בהצלחה.",
                false
            );
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : "לא ניתן לעדכן את הפרופיל.";

            showError(message);
            showToast(message, true);
        } finally {
            saving = false;

            if (elements.saveButton) {
                elements.saveButton.disabled = false;
                elements.saveButton.textContent = "שמירת פרטים";
            }
        }
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

    function showError(message) {
        if (!elements.error) {
            return;
        }

        elements.error.textContent = message;
        elements.error.hidden = false;
        elements.error.style.display = "block";
    }

    function showSuccess(message) {
        if (!elements.success) {
            return;
        }

        elements.success.textContent = message;
        elements.success.hidden = false;
        elements.success.style.display = "block";
    }

    function hideMessages() {
        if (elements.error) {
            elements.error.textContent = "";
            elements.error.hidden = true;
            elements.error.style.display = "none";
        }

        if (elements.success) {
            elements.success.textContent = "";
            elements.success.hidden = true;
            elements.success.style.display = "none";
        }
    }

    function showToast(message, isError) {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");
        toast.className = "profile-toast";

        if (isError) {
            toast.classList.add("is-error");
        }

        toast.innerHTML = `
            <span aria-hidden="true">
                ${isError ? "⚠️" : "✅"}
            </span>

            <div class="profile-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="profile-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        toast
            .querySelector(".profile-toast-close")
            ?.addEventListener("click", function () {
                toast.remove();
            });

        elements.toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 6000);
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