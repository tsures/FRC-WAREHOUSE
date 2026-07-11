document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("password");
    const passwordToggle = document.getElementById("password-toggle");
    const loginForm = document.getElementById("login-form");
    const submitButton = document.getElementById("login-submit");

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener("click", function () {
            const passwordVisible =
                passwordInput.type === "text";

            passwordInput.type = passwordVisible
                ? "password"
                : "text";

            passwordToggle.textContent = passwordVisible
                ? "👁"
                : "🙈";

            passwordToggle.setAttribute(
                "aria-label",
                passwordVisible
                    ? "הצגת סיסמה"
                    : "הסתרת סיסמה"
            );
        });
    }

    if (loginForm && submitButton) {
        loginForm.addEventListener("submit", function () {
            submitButton.disabled = true;
            submitButton.textContent = "מתחבר...";
        });
    }
});