"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const config = window.locationsConfig || {};

    const appUrl = String(config.appUrl || "").replace(/\/+$/, "");
    const csrfToken = String(config.csrfToken || "");
    const locationTypes = config.locationTypes || {};

    const state = {
        locations: [],
        flatLocations: [],
        search: "",
        status: "all",
        loading: false,
        saving: false,
        editingLocationId: null,
        debounceTimer: null
    };

    const elements = {
        addButton: document.getElementById("addLocationButton"),
        emptyAddButton: document.getElementById("emptyAddLocationButton"),
        refreshButton: document.getElementById("refreshLocationsButton"),

        searchInput: document.getElementById("locationsSearch"),
        clearSearchButton: document.getElementById("clearLocationsSearch"),
        statusFilter: document.getElementById("locationsStatusFilter"),

        loading: document.getElementById("locationsLoading"),
        emptyState: document.getElementById("locationsEmptyState"),
        emptyMessage: document.getElementById("locationsEmptyMessage"),
        tree: document.getElementById("locationsTree"),

        totalCount: document.getElementById("locationsTotalCount"),
        activeCount: document.getElementById("locationsActiveCount"),
        inactiveCount: document.getElementById("locationsInactiveCount"),
        rootCount: document.getElementById("locationsRootCount"),

        modal: document.getElementById("locationModal"),
        modalTitle: document.getElementById("locationModalTitle"),
        form: document.getElementById("locationForm"),

        idInput: document.getElementById("locationId"),
        nameInput: document.getElementById("locationName"),
        codeInput: document.getElementById("locationCode"),
        typeInput: document.getElementById("locationType"),
        parentInput: document.getElementById("locationParent"),
        sortOrderInput: document.getElementById("locationSortOrder"),
        descriptionInput: document.getElementById("locationDescription"),

        nameError: document.getElementById("locationNameError"),
        codeError: document.getElementById("locationCodeError"),
        typeError: document.getElementById("locationTypeError"),
        generalError: document.getElementById(
            "locationFormGeneralError"
        ),

        saveButton: document.getElementById("saveLocationButton"),
        saveButtonText: document.getElementById(
            "saveLocationButtonText"
        ),
        saveButtonSpinner: document.getElementById(
            "saveLocationButtonSpinner"
        ),

        toastContainer: document.getElementById(
            "locationsToastContainer"
        )
    };

    initialize();

    function initialize() {
        bindEvents();
        loadLocations();
    }

    function bindEvents() {
        if (elements.addButton) {
            elements.addButton.addEventListener("click", function () {
                openAddModal(null);
            });
        }

        if (elements.emptyAddButton) {
            elements.emptyAddButton.addEventListener(
                "click",
                function () {
                    openAddModal(null);
                }
            );
        }

        if (elements.refreshButton) {
            elements.refreshButton.addEventListener(
                "click",
                function () {
                    loadLocations();
                }
            );
        }

        if (elements.searchInput) {
            elements.searchInput.addEventListener(
                "input",
                function () {
                    state.search = elements.searchInput.value.trim();

                    if (elements.clearSearchButton) {
                        elements.clearSearchButton.hidden =
                            state.search === "";
                    }

                    window.clearTimeout(state.debounceTimer);

                    state.debounceTimer = window.setTimeout(
                        function () {
                            loadLocations();
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
                    state.search = "";
                    elements.clearSearchButton.hidden = true;

                    loadLocations();

                    elements.searchInput.focus();
                }
            );
        }

        if (elements.statusFilter) {
            elements.statusFilter.addEventListener(
                "change",
                function () {
                    state.status =
                        elements.statusFilter.value || "all";

                    loadLocations();
                }
            );
        }

        if (elements.form) {
            elements.form.addEventListener(
                "submit",
                handleFormSubmit
            );
        }

        document
            .querySelectorAll("[data-close-location-modal]")
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
                    elements.modal &&
                    !elements.modal.hidden
                ) {
                    closeModal();
                }
            }
        );

        if (elements.tree) {
            elements.tree.addEventListener(
                "click",
                handleTreeClick
            );
        }
    }

    async function loadLocations() {
        if (state.loading) {
            return;
        }

        setLoading(true);

        try {
            const parameters = new URLSearchParams();

            parameters.set("status", state.status);

            if (state.search !== "") {
                parameters.set("search", state.search);
            }

            const url =
                appUrl +
                "/api/locations/list.php?" +
                parameters.toString();

            const response = await fetch(url, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
                }
            });

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message || "לא ניתן לטעון את המיקומים."
                );
            }

            const data = result.data || {};

            state.locations = Array.isArray(data.locations)
                ? data.locations
                : [];

            if (state.search !== "") {
                state.flatLocations = state.locations;
            } else {
                state.flatLocations = flattenLocationTree(
                    state.locations
                );
            }

            updateStatistics(data.meta || {});
            renderLocations();
        } catch (error) {
            state.locations = [];
            state.flatLocations = [];

            renderLocations();

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לטעון את המיקומים.",
                "error"
            );
        } finally {
            setLoading(false);
        }
    }

    function renderLocations() {
        if (!elements.tree || !elements.emptyState) {
            return;
        }

        elements.tree.innerHTML = "";

        const hasLocations =
            Array.isArray(state.locations) &&
            state.locations.length > 0;

        elements.emptyState.hidden = hasLocations;

        if (!hasLocations) {
            updateEmptyStateMessage();
            return;
        }

        if (state.search !== "") {
            renderSearchResults(state.locations);
            return;
        }

        const fragment = document.createDocumentFragment();

        state.locations.forEach(function (location) {
            fragment.appendChild(
                createLocationNode(location)
            );
        });

        elements.tree.appendChild(fragment);
    }

    function renderSearchResults(locations) {
        if (!elements.tree) {
            return;
        }

        const fragment = document.createDocumentFragment();

        locations.forEach(function (location) {
            const node = createLocationNode(
                location,
                true
            );

            fragment.appendChild(node);
        });

        elements.tree.appendChild(fragment);
    }

    function createLocationNode(location, isSearchResult = false) {
        const node = document.createElement("div");

        node.className = "location-node";
        node.dataset.locationId = String(location.id);

        const row = document.createElement("div");

        row.className = "location-row";

        if (!location.is_active) {
            row.classList.add("is-inactive");
        }

        if (isSearchResult) {
            row.classList.add("location-search-result");
        }

        const children = Array.isArray(location.children)
            ? location.children
            : [];

        const hasChildren = children.length > 0;

        const toggleHtml = hasChildren && !isSearchResult
            ? `
                <button
                    type="button"
                    class="location-toggle-children"
                    data-action="toggle-children"
                    data-location-id="${escapeAttribute(location.id)}"
                    aria-expanded="true"
                    aria-label="פתיחה או סגירה של תתי־מיקומים"
                >
                    ‹
                </button>
            `
            : `
                <span class="location-toggle-placeholder"></span>
            `;

        const typeLabel =
            location.type_label ||
            getLocationTypeLabel(location.location_type);

        const typeIcon =
            location.type_icon ||
            getLocationTypeIcon(location.location_type);

        const codeHtml = location.code
            ? `
                <span class="location-code">
                    ${escapeHtml(location.code)}
                </span>
            `
            : "";

        const pathHtml = location.path
            ? `
                <span class="location-path">
                    ${escapeHtml(location.path)}
                </span>
            `
            : "";

        const descriptionHtml = location.description
            ? `
                <p class="location-description">
                    ${escapeHtml(location.description)}
                </p>
            `
            : "";

        const inactiveBadge = !location.is_active
            ? `
                <span class="location-badge is-inactive">
                    מושבת
                </span>
            `
            : "";

        const toggleStatusText = location.is_active
            ? "השבתה"
            : "הפעלה";

        const toggleStatusIcon = location.is_active
            ? "⛔"
            : "✅";

        const toggleStatusClass = location.is_active
            ? "is-danger"
            : "is-success";

        row.innerHTML = `
            <div class="location-main">
                ${toggleHtml}

                <div class="location-icon" aria-hidden="true">
                    ${escapeHtml(typeIcon)}
                </div>

                <div class="location-info">
                    <div class="location-title-line">
                        <span class="location-name">
                            ${escapeHtml(location.name || "")}
                        </span>

                        <span class="location-badge">
                            ${escapeHtml(typeLabel)}
                        </span>

                        ${inactiveBadge}
                    </div>

                    <div class="location-meta">
                        ${codeHtml}
                        ${pathHtml}
                    </div>

                    ${descriptionHtml}
                </div>
            </div>

            <div class="location-actions">
                <button
                    type="button"
                    class="location-action-button"
                    data-action="add-child"
                    data-location-id="${escapeAttribute(location.id)}"
                    title="הוספת תת־מיקום"
                >
                    <span aria-hidden="true">＋</span>
                    <span>תת־מיקום</span>
                </button>

                <button
                    type="button"
                    class="location-action-button"
                    data-action="edit"
                    data-location-id="${escapeAttribute(location.id)}"
                    title="עריכת מיקום"
                >
                    <span aria-hidden="true">✏️</span>
                    <span>עריכה</span>
                </button>

                <button
                    type="button"
                    class="location-action-button ${toggleStatusClass}"
                    data-action="toggle-status"
                    data-location-id="${escapeAttribute(location.id)}"
                    data-is-active="${location.is_active ? "1" : "0"}"
                    title="${toggleStatusText}"
                >
                    <span aria-hidden="true">${toggleStatusIcon}</span>
                    <span>${toggleStatusText}</span>
                </button>
            </div>
        `;

        node.appendChild(row);

        if (hasChildren && !isSearchResult) {
            const childrenContainer =
                document.createElement("div");

            childrenContainer.className =
                "location-children";

            childrenContainer.dataset.childrenOf =
                String(location.id);

            children.forEach(function (child) {
                childrenContainer.appendChild(
                    createLocationNode(child)
                );
            });

            node.appendChild(childrenContainer);
        }

        return node;
    }

    function handleTreeClick(event) {
        const button = event.target.closest(
            "[data-action]"
        );

        if (!button) {
            return;
        }

        const action = button.dataset.action;
        const locationId = Number(
            button.dataset.locationId || 0
        );

        if (locationId <= 0) {
            return;
        }

        if (action === "toggle-children") {
            toggleChildren(button, locationId);
            return;
        }

        if (action === "add-child") {
            openAddModal(locationId);
            return;
        }

        if (action === "edit") {
            openEditModal(locationId);
            return;
        }

        if (action === "toggle-status") {
            toggleLocationStatus(locationId, button);
        }
    }

    function toggleChildren(button, locationId) {
        const childrenContainer = document.querySelector(
            `[data-children-of="${CSS.escape(String(locationId))}"]`
        );

        if (!childrenContainer) {
            return;
        }

        const currentlyExpanded =
            button.getAttribute("aria-expanded") === "true";

        button.setAttribute(
            "aria-expanded",
            currentlyExpanded ? "false" : "true"
        );

        childrenContainer.hidden = currentlyExpanded;
    }

    function openAddModal(parentId) {
        state.editingLocationId = null;

        resetForm();

        if (elements.modalTitle) {
            elements.modalTitle.textContent =
                parentId === null
                    ? "הוספת מיקום"
                    : "הוספת תת־מיקום";
        }

        fillParentOptions(null, parentId);

        if (
            parentId !== null &&
            elements.parentInput
        ) {
            elements.parentInput.value =
                String(parentId);
        }

        openModal();

        window.setTimeout(function () {
            if (elements.nameInput) {
                elements.nameInput.focus();
            }
        }, 50);
    }

    function openEditModal(locationId) {
        const location = findLocationById(locationId);

        if (!location) {
            showToast(
                "המיקום המבוקש לא נמצא.",
                "error"
            );

            return;
        }

        state.editingLocationId = locationId;

        resetForm();

        if (elements.modalTitle) {
            elements.modalTitle.textContent =
                "עריכת מיקום";
        }

        if (elements.idInput) {
            elements.idInput.value =
                String(location.id);
        }

        if (elements.nameInput) {
            elements.nameInput.value =
                location.name || "";
        }

        if (elements.codeInput) {
            elements.codeInput.value =
                location.code || "";
        }

        if (elements.typeInput) {
            elements.typeInput.value =
                location.location_type || "other";
        }

        if (elements.sortOrderInput) {
            elements.sortOrderInput.value =
                String(location.sort_order || 0);
        }

        if (elements.descriptionInput) {
            elements.descriptionInput.value =
                location.description || "";
        }

        fillParentOptions(
            locationId,
            location.parent_id
        );

        openModal();

        window.setTimeout(function () {
            if (elements.nameInput) {
                elements.nameInput.focus();
                elements.nameInput.select();
            }
        }, 50);
    }

    function fillParentOptions(
        excludedLocationId = null,
        selectedParentId = null
    ) {
        if (!elements.parentInput) {
            return;
        }

        elements.parentInput.innerHTML = "";

        const rootOption = document.createElement("option");

        rootOption.value = "";
        rootOption.textContent =
            "ללא מיקום אב — מיקום שורש";

        elements.parentInput.appendChild(rootOption);

        const excludedIds = new Set();

        if (excludedLocationId !== null) {
            excludedIds.add(Number(excludedLocationId));

            getDescendantIds(excludedLocationId).forEach(
                function (id) {
                    excludedIds.add(Number(id));
                }
            );
        }

        state.flatLocations.forEach(function (location) {
            const locationId = Number(location.id);

            if (excludedIds.has(locationId)) {
                return;
            }

            const option = document.createElement("option");

            option.value = String(locationId);

            const depth = Number(location.depth || 0);
            const indent = "— ".repeat(
                Math.max(0, depth)
            );

            const typeLabel =
                location.type_label ||
                getLocationTypeLabel(
                    location.location_type
                );

            option.textContent =
                indent +
                String(location.name || "") +
                " (" +
                typeLabel +
                ")";

            elements.parentInput.appendChild(option);
        });

        if (
            selectedParentId !== null &&
            selectedParentId !== undefined
        ) {
            elements.parentInput.value =
                String(selectedParentId);
        } else {
            elements.parentInput.value = "";
        }
    }

    async function handleFormSubmit(event) {
        event.preventDefault();

        if (state.saving) {
            return;
        }

        clearFormErrors();

        const payload = collectFormData();

        if (!validateForm(payload)) {
            return;
        }

        setSaving(true);

        try {
            const response = await fetch(
                appUrl + "/api/locations/save.php",
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
                    result.message || "לא ניתן לשמור את המיקום."
                );
            }

            closeModal();

            showToast(
                result.message || "המיקום נשמר בהצלחה.",
                "success"
            );

            await loadLocations();
        } catch (error) {
            showGeneralFormError(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשמור את המיקום."
            );
        } finally {
            setSaving(false);
        }
    }

    function collectFormData() {
        const idValue = elements.idInput
            ? elements.idInput.value.trim()
            : "";

        const parentValue = elements.parentInput
            ? elements.parentInput.value.trim()
            : "";

        return {
            csrf_token: csrfToken,
            id: idValue === ""
                ? null
                : Number(idValue),
            parent_id: parentValue === ""
                ? null
                : Number(parentValue),
            name: elements.nameInput
                ? elements.nameInput.value.trim()
                : "",
            code: elements.codeInput
                ? elements.codeInput.value.trim()
                : "",
            location_type: elements.typeInput
                ? elements.typeInput.value
                : "other",
            sort_order: elements.sortOrderInput
                ? Number(
                    elements.sortOrderInput.value || 0
                )
                : 0,
            description: elements.descriptionInput
                ? elements.descriptionInput.value.trim()
                : ""
        };
    }

    function validateForm(payload) {
        let isValid = true;

        if (payload.name === "") {
            setFieldError(
                elements.nameInput,
                elements.nameError,
                "יש להזין שם מיקום."
            );

            isValid = false;
        } else if (payload.name.length > 150) {
            setFieldError(
                elements.nameInput,
                elements.nameError,
                "שם המיקום יכול להכיל עד 150 תווים."
            );

            isValid = false;
        }

        if (payload.code.length > 80) {
            setFieldError(
                elements.codeInput,
                elements.codeError,
                "קוד המיקום יכול להכיל עד 80 תווים."
            );

            isValid = false;
        }

        if (
            payload.code !== "" &&
            !/^[A-Za-z0-9_\-\s]+$/.test(payload.code)
        ) {
            setFieldError(
                elements.codeInput,
                elements.codeError,
                "הקוד יכול להכיל אותיות באנגלית, מספרים, רווח, מקף או קו תחתון."
            );

            isValid = false;
        }

        if (
            !Object.prototype.hasOwnProperty.call(
                locationTypes,
                payload.location_type
            )
        ) {
            setFieldError(
                elements.typeInput,
                elements.typeError,
                "יש לבחור סוג מיקום חוקי."
            );

            isValid = false;
        }

        if (
            !Number.isInteger(payload.sort_order) ||
            payload.sort_order < 0
        ) {
            showGeneralFormError(
                "סדר התצוגה חייב להיות מספר שלם שאינו שלילי."
            );

            isValid = false;
        }

        return isValid;
    }

    async function toggleLocationStatus(
        locationId,
        button
    ) {
        const location = findLocationById(locationId);

        if (!location) {
            showToast(
                "המיקום המבוקש לא נמצא.",
                "error"
            );

            return;
        }

        const actionText = location.is_active
            ? "להשבית"
            : "להפעיל";

        const confirmed = window.confirm(
            `האם אתה בטוח שברצונך ${actionText} את המיקום "${location.name}"?`
        );

        if (!confirmed) {
            return;
        }

        const originalHtml = button.innerHTML;

        button.disabled = true;
        button.innerHTML = "<span>...</span>";

        try {
            const response = await fetch(
                appUrl + "/api/locations/toggle.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json"
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        id: locationId
                    })
                }
            );

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success !== true) {
                throw new Error(
                    result.message ||
                    "לא ניתן לשנות את מצב המיקום."
                );
            }

            showToast(
                result.message ||
                "מצב המיקום עודכן בהצלחה.",
                "success"
            );

            await loadLocations();
        } catch (error) {
            button.disabled = false;
            button.innerHTML = originalHtml;

            showToast(
                error instanceof Error
                    ? error.message
                    : "לא ניתן לשנות את מצב המיקום.",
                "error"
            );
        }
    }

    function openModal() {
        if (!elements.modal) {
            return;
        }

        elements.modal.hidden = false;
        document.body.classList.add(
            "location-modal-open"
        );
    }

    function closeModal() {
        if (!elements.modal || state.saving) {
            return;
        }

        elements.modal.hidden = true;
        document.body.classList.remove(
            "location-modal-open"
        );

        resetForm();
    }

    function resetForm() {
        if (elements.form) {
            elements.form.reset();
        }

        state.editingLocationId = null;

        if (elements.idInput) {
            elements.idInput.value = "";
        }

        if (elements.sortOrderInput) {
            elements.sortOrderInput.value = "0";
        }

        if (elements.typeInput) {
            elements.typeInput.value = "other";
        }

        clearFormErrors();
        setSaving(false);
    }

    function clearFormErrors() {
        const fields = [
            elements.nameInput,
            elements.codeInput,
            elements.typeInput,
            elements.parentInput,
            elements.sortOrderInput,
            elements.descriptionInput
        ];

        fields.forEach(function (field) {
            if (field) {
                field.classList.remove("is-invalid");
            }
        });

        [
            elements.nameError,
            elements.codeError,
            elements.typeError
        ].forEach(function (element) {
            if (element) {
                element.textContent = "";
            }
        });

        if (elements.generalError) {
            elements.generalError.textContent = "";
            elements.generalError.hidden = true;
        }
    }

    function setFieldError(
        input,
        errorElement,
        message
    ) {
        if (input) {
            input.classList.add("is-invalid");
        }

        if (errorElement) {
            errorElement.textContent = message;
        }
    }

    function showGeneralFormError(message) {
        if (!elements.generalError) {
            showToast(message, "error");
            return;
        }

        elements.generalError.textContent = message;
        elements.generalError.hidden = false;
    }

    function setLoading(isLoading) {
        state.loading = isLoading;

        if (elements.loading) {
            elements.loading.hidden = !isLoading;
        }

        if (elements.tree) {
            elements.tree.hidden = isLoading;
        }

        if (
            elements.emptyState &&
            isLoading
        ) {
            elements.emptyState.hidden = true;
        }

        if (elements.refreshButton) {
            elements.refreshButton.disabled = isLoading;
        }
    }

    function setSaving(isSaving) {
        state.saving = isSaving;

        if (elements.saveButton) {
            elements.saveButton.disabled = isSaving;
        }

        if (elements.saveButtonText) {
            elements.saveButtonText.textContent =
                isSaving
                    ? "שומר..."
                    : "שמירת מיקום";
        }

        if (elements.saveButtonSpinner) {
            elements.saveButtonSpinner.hidden =
                !isSaving;
        }
    }

    function updateStatistics(meta) {
        setText(
            elements.totalCount,
            meta.total_count || 0
        );

        setText(
            elements.activeCount,
            meta.active_count || 0
        );

        setText(
            elements.inactiveCount,
            meta.inactive_count || 0
        );

        setText(
            elements.rootCount,
            meta.root_count || 0
        );
    }

    function updateEmptyStateMessage() {
        if (!elements.emptyMessage) {
            return;
        }

        if (state.search !== "") {
            elements.emptyMessage.textContent =
                "לא נמצאו מיקומים התואמים לחיפוש.";
            return;
        }

        if (state.status === "active") {
            elements.emptyMessage.textContent =
                "לא נמצאו מיקומים פעילים.";
            return;
        }

        if (state.status === "inactive") {
            elements.emptyMessage.textContent =
                "לא נמצאו מיקומים מושבתים.";
            return;
        }

        elements.emptyMessage.textContent =
            "עדיין לא נוספו מיקומים למערכת.";
    }

    function flattenLocationTree(
        locations,
        depth = 0,
        result = []
    ) {
        if (!Array.isArray(locations)) {
            return result;
        }

        locations.forEach(function (location) {
            const flattenedLocation = {
                ...location,
                depth: Number(
                    location.depth ?? depth
                )
            };

            delete flattenedLocation.children;

            result.push(flattenedLocation);

            if (
                Array.isArray(location.children) &&
                location.children.length > 0
            ) {
                flattenLocationTree(
                    location.children,
                    depth + 1,
                    result
                );
            }
        });

        return result;
    }

    function findLocationById(locationId) {
        return state.flatLocations.find(
            function (location) {
                return Number(location.id) ===
                    Number(locationId);
            }
        ) || null;
    }

    function getDescendantIds(locationId) {
        const ids = [];

        function walk(locations) {
            locations.forEach(function (location) {
                if (
                    Number(location.parent_id) ===
                    Number(locationId)
                ) {
                    ids.push(Number(location.id));

                    getChildIds(location.id);
                }
            });
        }

        function getChildIds(parentId) {
            state.flatLocations.forEach(
                function (location) {
                    if (
                        Number(location.parent_id) ===
                        Number(parentId)
                    ) {
                        ids.push(Number(location.id));
                        getChildIds(location.id);
                    }
                }
            );
        }

        walk(state.flatLocations);

        return Array.from(new Set(ids));
    }

    function getLocationTypeLabel(type) {
        return Object.prototype.hasOwnProperty.call(
            locationTypes,
            type
        )
            ? String(locationTypes[type])
            : "אחר";
    }

    function getLocationTypeIcon(type) {
        const icons = {
            warehouse: "🏭",
            room: "🚪",
            cabinet: "🗄️",
            shelf: "📚",
            bin: "📦",
            other: "📍"
        };

        return icons[type] || icons.other;
    }

    async function parseJsonResponse(response) {
        const responseText = await response.text();

        if (responseText.trim() === "") {
            throw new Error(
                "השרת החזיר תשובה ריקה."
            );
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

    function showToast(message, type = "success") {
        if (!elements.toastContainer) {
            return;
        }

        const toast = document.createElement("div");

        toast.className =
            "locations-toast " +
            (type === "error"
                ? "is-error"
                : "is-success");

        const icon = type === "error"
            ? "⚠️"
            : "✅";

        toast.innerHTML = `
            <span
                class="locations-toast-icon"
                aria-hidden="true"
            >
                ${icon}
            </span>

            <div class="locations-toast-message">
                ${escapeHtml(message)}
            </div>

            <button
                type="button"
                class="locations-toast-close"
                aria-label="סגירת ההודעה"
            >
                ×
            </button>
        `;

        const closeButton = toast.querySelector(
            ".locations-toast-close"
        );

        if (closeButton) {
            closeButton.addEventListener(
                "click",
                function () {
                    toast.remove();
                }
            );
        }

        elements.toastContainer.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, type === "error" ? 6500 : 4000);
    }

    function setText(element, value) {
        if (element) {
            element.textContent = String(value);
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

    function escapeAttribute(value) {
        return escapeHtml(String(value ?? ""));
    }
});