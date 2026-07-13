"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("resetPasswordForm");

    const newPassword = document.getElementById(
        "new_password"
    );

    const confirmPassword = document.getElementById(
        "confirm_password"
    );

    const clientError = document.getElementById(
        "resetPasswordClientError"
    );

    document
        .querySelectorAll("[data-password-toggle]")
        .forEach(function (button) {
            button.addEventListener("click", function () {
                const inputId = button.dataset.passwordToggle;
                const input = document.getElementById(inputId);

                if (!input) {
                    return;
                }

                const isPassword =
                    input.type === "password";

                input.type = isPassword
                    ? "text"
                    : "password";

                button.textContent = isPassword
                    ? "🙈"
                    : "👁";

                button.setAttribute(
                    "aria-label",
                    isPassword
                        ? "הסתרת סיסמה"
                        : "הצגת סיסמה"
                );
            });
        });

    form?.addEventListener("submit", function (event) {
        hideError();

        const password = String(
            newPassword?.value || ""
        );

        const confirmation = String(
            confirmPassword?.value || ""
        );

        if (password.length < 8) {
            event.preventDefault();

            showError(
                "הסיסמה החדשה חייבת להכיל לפחות 8 תווים."
            );

            newPassword?.focus();
            return;
        }

        if (password !== confirmation) {
            event.preventDefault();

            showError(
                "אימות הסיסמה אינו תואם."
            );

            confirmPassword?.focus();
        }
    });

    function showError(message) {
        if (!clientError) {
            return;
        }

        clientError.textContent = message;
        clientError.hidden = false;
        clientError.style.display = "block";
    }

    function hideError() {
        if (!clientError) {
            return;
        }

        clientError.textContent = "";
        clientError.hidden = true;
        clientError.style.display = "none";
    }
});