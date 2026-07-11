document.addEventListener("DOMContentLoaded", function () {
    const appUrl = window.FRC_APP_URL || "";

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || "";

    const listElement =
        document.getElementById("categories-list");

    const loadingElement =
        document.getElementById("categories-loading");

    const emptyElement =
        document.getElementById("categories-empty");

    const countElement =
        document.getElementById("category-count");

    const searchInput =
        document.getElementById("category-search");

    const addButton =
        document.getElementById("add-category-button");

    const modal =
        document.getElementById("category-modal");

    const form =
        document.getElementById("category-form");

    const modalTitle =
        document.getElementById("category-modal-title");

    const categoryIdInput =
        document.getElementById("category-id");

    const nameHeInput =
        document.getElementById("category-name-he");

    const nameEnInput =
        document.getElementById("category-name-en");

    const parentInput =
        document.getElementById("category-parent");

    const iconInput =
        document.getElementById("category-icon");

    const colorInput =
        document.getElementById("category-color");

    const sortOrderInput =
        document.getElementById("category-sort-order");

    const descriptionInput =
        document.getElementById("category-description");

    const saveButton =
        document.getElementById("save-category-button");

    const toastContainer =
        document.getElementById("toast-container");

    let categories = [];

    async function apiRequest(endpoint, options = {}) {
        const response = await fetch(
            `${appUrl}${endpoint}`,
            {
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    ...(options.headers || {})
                },
                ...options
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "אירעה שגיאה."
            );
        }

        return data;
    }

    async function loadCategories() {
        loadingElement.hidden = false;
        emptyElement.hidden = true;
        listElement.innerHTML = "";

        try {
            const response = await apiRequest(
                "/api/categories/list.php",
                {
                    method: "GET"
                }
            );

            categories =
                response.data.categories || [];

            renderCategories();
            populateParentOptions();
        } catch (error) {
            showToast(error.message, "error");
        } finally {
            loadingElement.hidden = true;
        }
    }

    function renderCategories() {
        const query =
            searchInput.value.trim().toLowerCase();

        const filtered = categories.filter(function (category) {
            const text = [
                category.name_he,
                category.name_en,
                category.description,
                category.parent_name
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            return text.includes(query);
        });

        countElement.textContent =
            `${filtered.length} קטגוריות`;

        listElement.innerHTML = "";

        if (filtered.length === 0) {
            emptyElement.hidden = false;
            return;
        }

        emptyElement.hidden = true;

        filtered.forEach(function (category) {
            listElement.appendChild(
                createCategoryRow(category)
            );
        });
    }

    function createCategoryRow(category) {
        const row = document.createElement("article");

        row.className = "category-row";

        if (Number(category.is_active) !== 1) {
            row.classList.add("inactive");
        }

        const depth = Number(category.depth || 0);
        const icon = getCategoryIcon(category.icon);
        const color = category.color || "#2563eb";

        row.innerHTML = `
            <div class="category-main">
                <div
                    class="category-indent"
                    style="width:${depth * 24}px"
                    aria-hidden="true"
                ></div>

                <div
                    class="category-icon"
                    style="
                        background:${hexToRgba(color, 0.14)};
                        color:${escapeHtml(color)};
                    "
                >
                    ${escapeHtml(icon)}
                </div>

                <div class="category-info">
                    <div class="category-name">
                        <span class="category-name-text">
                            ${escapeHtml(category.name_he)}
                        </span>

                        <span
                            class="category-badge ${
                                Number(category.is_active) === 1
                                    ? "active"
                                    : "inactive"
                            }"
                        >
                            ${
                                Number(category.is_active) === 1
                                    ? "פעילה"
                                    : "מושבתת"
                            }
                        </span>
                    </div>

                    ${
                        category.name_en
                            ? `
                                <div
                                    class="category-english-name"
                                    dir="ltr"
                                >
                                    ${escapeHtml(category.name_en)}
                                </div>
                            `
                            : ""
                    }

                    ${
                        category.description
                            ? `
                                <div class="category-description">
                                    ${escapeHtml(category.description)}
                                </div>
                            `
                            : ""
                    }
                </div>
            </div>

            <div class="category-actions">
                <button
                    class="category-action-button"
                    type="button"
                    data-action="add-child"
                    data-id="${Number(category.id)}"
                >
                    תת־קטגוריה
                </button>

                <button
                    class="category-action-button"
                    type="button"
                    data-action="edit"
                    data-id="${Number(category.id)}"
                >
                    עריכה
                </button>

                <button
                    class="category-action-button warning"
                    type="button"
                    data-action="toggle"
                    data-id="${Number(category.id)}"
                >
                    ${
                        Number(category.is_active) === 1
                            ? "השבתה"
                            : "הפעלה"
                    }
                </button>
            </div>
        `;

        return row;
    }

    function populateParentOptions(excludedId = null) {
        parentInput.innerHTML = `
            <option value="">
                קטגוריה ראשית
            </option>
        `;

        categories.forEach(function (category) {
            if (
                excludedId !== null &&
                Number(category.id) === Number(excludedId)
            ) {
                return;
            }

            const option = document.createElement("option");
            const depth = Number(category.depth || 0);

            option.value = category.id;
            option.textContent =
                `${"— ".repeat(depth)}${category.name_he}`;

            parentInput.appendChild(option);
        });
    }

    function openCreateModal(parentId = "") {
        form.reset();

        categoryIdInput.value = "";
        modalTitle.textContent = "הוספת קטגוריה";
        colorInput.value = "#2563eb";
        sortOrderInput.value = "0";

        populateParentOptions();

        parentInput.value = parentId
            ? String(parentId)
            : "";

        openModal();
    }

    function openEditModal(category) {
        form.reset();

        categoryIdInput.value = category.id;
        nameHeInput.value = category.name_he || "";
        nameEnInput.value = category.name_en || "";
        iconInput.value = category.icon || "";
        colorInput.value = category.color || "#2563eb";
        sortOrderInput.value = category.sort_order || 0;
        descriptionInput.value =
            category.description || "";

        modalTitle.textContent = "עריכת קטגוריה";

        populateParentOptions(category.id);

        parentInput.value =
            category.parent_id !== null
                ? String(category.parent_id)
                : "";

        openModal();
    }

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = "hidden";

        setTimeout(function () {
            nameHeInput.focus();
        }, 50);
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = "";
    }

    async function saveCategory(event) {
        event.preventDefault();

        saveButton.disabled = true;
        saveButton.textContent = "שומר...";

        const payload = {
            csrf_token: csrfToken,
            id: categoryIdInput.value || null,
            name_he: nameHeInput.value.trim(),
            name_en: nameEnInput.value.trim(),
            parent_id: parentInput.value || null,
            icon: iconInput.value.trim(),
            color: colorInput.value,
            sort_order:
                Number(sortOrderInput.value || 0),
            description:
                descriptionInput.value.trim()
        };

        try {
            const response = await apiRequest(
                "/api/categories/save.php",
                {
                    method: "POST",
                    body: JSON.stringify(payload)
                }
            );

            showToast(
                response.message,
                "success"
            );

            closeModal();
            await loadCategories();
        } catch (error) {
            showToast(error.message, "error");
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = "שמירה";
        }
    }

    async function toggleCategory(categoryId) {
        const category = categories.find(function (item) {
            return Number(item.id) === Number(categoryId);
        });

        if (!category) {
            return;
        }

        const actionText =
            Number(category.is_active) === 1
                ? "להשבית"
                : "להפעיל";

        const confirmed = window.confirm(
            `האם ${actionText} את הקטגוריה "${category.name_he}"?`
        );

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiRequest(
                "/api/categories/toggle.php",
                {
                    method: "POST",
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        id: categoryId
                    })
                }
            );

            showToast(
                response.message,
                "success"
            );

            await loadCategories();
        } catch (error) {
            showToast(error.message, "error");
        }
    }

    function showToast(message, type = "") {
        const toast = document.createElement("div");

        toast.className = `toast ${type}`;
        toast.textContent = message;

        toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 3500);
    }

    function getCategoryIcon(iconName) {
    const icons = {
        settings: "⚙️",
        bolt: "⚡",
        motion: "⚙️",
        sensors: "📡",
        tools: "🛠️",
        air: "💨",
        battery: "🔋",
        safety: "🦺",
        "3d": "🖨️",
        material: "🧱",
        screw: "🔩",
        category: "🗂️"
    };

    const value = String(iconName || "").trim();

    if (icons[value]) {
        return icons[value];
    }

    /*
     * מאפשר גם שמירת אימוג'י ישירות במסד הנתונים.
     */
    if (value !== "" && value.length <= 4) {
        return value;
    }

    return "🗂️";
}

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function hexToRgba(hex, alpha) {
        const cleanHex = String(hex).replace("#", "");

        if (!/^[0-9a-fA-F]{6}$/.test(cleanHex)) {
            return `rgba(37, 99, 235, ${alpha})`;
        }

        const red =
            parseInt(cleanHex.substring(0, 2), 16);

        const green =
            parseInt(cleanHex.substring(2, 4), 16);

        const blue =
            parseInt(cleanHex.substring(4, 6), 16);

        return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
    }

    addButton.addEventListener("click", function () {
        openCreateModal();
    });

    form.addEventListener(
        "submit",
        saveCategory
    );

    searchInput.addEventListener(
        "input",
        renderCategories
    );

    listElement.addEventListener(
        "click",
        function (event) {
            const button =
                event.target.closest(
                    "[data-action]"
                );

            if (!button) {
                return;
            }

            const categoryId =
                Number(button.dataset.id);

            const category =
                categories.find(function (item) {
                    return (
                        Number(item.id) ===
                        categoryId
                    );
                });

            if (!category) {
                return;
            }

            switch (button.dataset.action) {
                case "edit":
                    openEditModal(category);
                    break;

                case "add-child":
                    openCreateModal(category.id);
                    break;

                case "toggle":
                    toggleCategory(category.id);
                    break;
            }
        }
    );

    document
        .querySelectorAll("[data-close-modal]")
        .forEach(function (element) {
            element.addEventListener(
                "click",
                closeModal
            );
        });

    document.addEventListener(
        "keydown",
        function (event) {
            if (
                event.key === "Escape" &&
                !modal.hidden
            ) {
                closeModal();
            }
        }
    );

    loadCategories();
});