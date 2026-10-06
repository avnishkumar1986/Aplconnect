document.addEventListener("DOMContentLoaded", () => {
    /**
     * One visual contract for every operational data table. Report-style
     * planning sheets deliberately keep their matrix formatting.
     */
    const enhanceCorporateTables = (scope = document) => {
        scope
            .querySelectorAll(
                "#app-shell-content main table.table, #app-shell-content main table.crud-table, #app-shell-content main table.module-table, #app-shell-content main table.action-log-table, #app-shell-content main table.org-table",
            )
            .forEach((table) => {
                if (
                    table.matches(
                        ".planning-table, .balance-table, .sheet, .wizard-record-table",
                    )
                )
                    return;
                table.classList.add("ui-data-table");
                table.setAttribute("data-uniform-table", "true");
                table.querySelectorAll("thead th").forEach((heading) => {
                    heading.setAttribute("scope", "col");
                });
                const parent = table.parentElement;
                if (parent && !parent.classList.contains("dt-layout-cell")) {
                    parent.classList.add("ui-data-table-scroll");
                }
            });
    };
    enhanceCorporateTables();

    const normaliseDataTableLayout = (container) => {
        if (!(container instanceof HTMLElement)) return;

        const table = container.querySelector("table.dataTable");
        if (!table) return;

        container.classList.add("unified-datatable");
        table.classList.add("ui-data-table");

        const topRow = Array.from(container.children).find((element) =>
            element.classList.contains("dt-layout-row"),
        );
        if (!topRow) return;

        const toolbar = table.id
            ? document.querySelector(
                  `[data-datatable-toolbar-for="${CSS.escape(table.id)}"]`,
              )
            : null;

        if (toolbar) {
            Array.from(toolbar.children).forEach((control) => {
                control.classList.add("datatable-toolbar-control");
                topRow.append(control);
            });
            toolbar.remove();
        }

        Array.from(topRow.children).forEach((cell) => {
            if (
                !cell.textContent.trim() &&
                !cell.querySelector("input, select, button, a, form")
            ) {
                cell.remove();
            }
        });
    };

    const normaliseAllDataTables = (root = document) => {
        if (root instanceof HTMLElement && root.matches(".dt-container")) {
            normaliseDataTableLayout(root);
        }
        root.querySelectorAll?.(".dt-container").forEach(normaliseDataTableLayout);
    };

    normaliseAllDataTables();
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) normaliseAllDataTables(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    const refreshBulkForm = (form) => {
        if (!form) return;
        const table = form.closest(".crud-list-card")?.querySelector("table");
        const boxes = [...(table?.querySelectorAll("[data-bulk-select]") || [])];
        const checked = boxes.filter((box) => box.checked);
        const selectAll = table?.querySelector("[data-bulk-select-all]");
        const count = form.querySelector("[data-bulk-count]");
        const apply = form.querySelector('button[type="submit"]');
        if (count) count.textContent = `${checked.length} selected`;
        if (apply) apply.disabled = checked.length === 0;
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    };
    document.addEventListener("change", (event) => {
        if (!event.target.matches("[data-bulk-select], [data-bulk-select-all]")) return;
        const card = event.target.closest(".crud-list-card");
        const form = card?.querySelector("[data-table-bulk-form]");
        if (event.target.matches("[data-bulk-select-all]")) {
            card?.querySelectorAll("[data-bulk-select]").forEach((box) => {
                box.checked = event.target.checked;
            });
        }
        refreshBulkForm(form);
    });
    document.querySelectorAll("[data-table-bulk-form]").forEach((form) => {
        refreshBulkForm(form);
        form.addEventListener("submit", (event) => {
            form.querySelectorAll('input[name="ids[]"]').forEach((input) => input.remove());
            const checked = form.closest(".crud-list-card")?.querySelectorAll("[data-bulk-select]:checked") || [];
            if (!checked.length) { event.preventDefault(); return; }
            checked.forEach((box) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "ids[]";
                input.value = box.value;
                form.appendChild(input);
            });
        });
    });
    const buttons = [...document.querySelectorAll("[data-ui-dropdown]")];
    const close = (except = null) =>
        buttons.forEach((b) => {
            const p = document.getElementById(b.dataset.uiDropdown);
            if (p && p !== except) {
                p.classList.add("hidden");
                b.setAttribute("aria-expanded", "false");
            }
        });
    buttons.forEach((b) =>
        b.addEventListener("click", (e) => {
            e.stopPropagation();
            const p = document.getElementById(b.dataset.uiDropdown);
            if (!p) return;
            const open = p.classList.contains("hidden");
            close(p);
            p.classList.toggle("hidden", !open);
            b.setAttribute("aria-expanded", String(open));
        }),
    );
    document.querySelectorAll("[data-ui-sidebar-group]").forEach((b) =>
        b.addEventListener("click", () => {
            const p = document.getElementById(b.dataset.uiSidebarGroup);
            if (!p) return;
            const open = p.classList.contains("hidden");
            p.classList.toggle("hidden", !open);
            b.setAttribute("aria-expanded", String(open));
            b.querySelector("svg:last-child")?.classList.toggle(
                "rotate-90",
                open,
            );
        }),
    );
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".dropdown-panel")) close();
    });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") close();
    });
    const enhanceActionViews = (scope = document) =>
        scope.querySelectorAll(".crud-table tbody tr").forEach((row) => {
            const details = row.querySelector(".view-detail");
            const actions = row.querySelector(".row-actions");
            if (!details || !actions || actions.querySelector(".view")) return;
            const url = new URL(details.href, location.href);
            url.searchParams.set("readonly", "1");
            const view = document.createElement("a");
            view.href = url.href;
            view.className = "view";
            if (!url.pathname.match(/\/admin\/users\/\d+\/?$/)) {
                view.dataset.formModal = "";
                view.dataset.modalTitle = "View record";
            }
            view.setAttribute("aria-label", "View record");
            view.innerHTML =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>';
            actions.prepend(view);
        });
    enhanceActionViews();
    const toggleResources = new Set([
        "addresses",
        "contacts",
        "accounts",
        "users",
        "user-types",
        "companies",
    ]);
    const enhanceStatusToggles = (scope = document) =>
        scope
            .querySelectorAll(".status-pill:not([data-status-enhanced])")
            .forEach((pill) => {
                const edit = pill
                    .closest("tr")
                    ?.querySelector(".row-actions .edit");
                if (!edit) return;
                const match = new URL(edit.href, location.href).pathname.match(
                    /\/admin\/([^/]+)\/(\d+)\/edit$/,
                );
                if (!match || !toggleResources.has(match[1])) return;
                const active = pill.classList.contains("active");
                const button = document.createElement("button");
                button.type = "button";
                button.className = `status-toggle ${active ? "active" : "inactive"}`;
                button.dataset.statusUrl = `/admin/status/${match[1]}/${match[2]}`;
                button.setAttribute("aria-pressed", String(active));
                button.setAttribute(
                    "aria-label",
                    `${active ? "Deactivate" : "Activate"} record`,
                );
                button.innerHTML = `<span class="status-switch"><i></i></span><span class="status-toggle-label">${active ? "Active" : "Inactive"}</span>`;
                pill.replaceWith(button);
            });
    enhanceStatusToggles();
    const liveSearch = document.querySelector("[data-live-search]");
    let searchTimer = null,
        searchRequest = null;
    const loadCrudResults = async (url) => {
        const results = document.querySelector("[data-crud-results]");
        if (!results) return;
        searchRequest?.abort();
        searchRequest = new AbortController();
        results.classList.add("is-loading");
        try {
            const response = await fetch(url, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                signal: searchRequest.signal,
            });
            if (!response.ok) throw new Error("Search failed");
            const html = await response.text();
            const page = new DOMParser().parseFromString(html, "text/html");
            const next = page.querySelector("[data-crud-results]");
            if (next) {
                results.replaceWith(next);
                enhanceCorporateTables(next);
                refreshBulkForm(document.querySelector("[data-table-bulk-form]"));
                enhanceActionViews(next);
                enhanceStatusToggles(next);
                history.replaceState({}, "", url);
            }
        } catch (error) {
            if (error.name !== "AbortError")
                results.classList.remove("is-loading");
        }
    };
    liveSearch?.addEventListener("submit", (e) => {
        e.preventDefault();
        const url = new URL(location.href);
        const value = liveSearch.querySelector("[name=q]").value.trim();
        value ? url.searchParams.set("q", value) : url.searchParams.delete("q");
        url.searchParams.delete("page");
        loadCrudResults(url);
    });
    liveSearch?.querySelector("[name=q]")?.addEventListener("input", (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const url = new URL(location.href);
            const value = e.target.value.trim();
            value
                ? url.searchParams.set("q", value)
                : url.searchParams.delete("q");
            url.searchParams.delete("page");
            loadCrudResults(url);
        }, 300);
    });
    document.addEventListener("click", (e) => {
        const link = e.target.closest(".crud-pagination a");
        if (!link) return;
        e.preventDefault();
        loadCrudResults(new URL(link.href, location.href));
    });
    document.addEventListener("click", (e) => {
        const link = e.target.closest("[data-sort-link]");
        if (!link) return;
        e.preventDefault();
        loadCrudResults(new URL(link.href, location.href));
    });
    document.addEventListener("click", async (e) => {
        const button = e.target.closest(".status-toggle");
        if (!button?.dataset.statusUrl) return;
        e.preventDefault();
        button.disabled = true;
        button.classList.add("is-saving");
        try {
            const response = await fetch(button.dataset.statusUrl, {
                method: "PATCH",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN":
                        document.querySelector("meta[name=csrf-token]")
                            ?.content || "",
                },
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(data.message || "Unable to update status.");
            button.classList.toggle("active", data.active);
            button.classList.toggle("inactive", !data.active);
            button.setAttribute("aria-pressed", String(data.active));
            button.setAttribute(
                "aria-label",
                `${data.active ? "Deactivate" : "Activate"} record`,
            );
            button.setAttribute(
                "title",
                `Click to ${data.active ? "deactivate" : "activate"} module`,
            );
            button.querySelector(".status-toggle-label").textContent =
                data.label;
            const moduleView = button.closest("tr")?.querySelector("[data-module-view]");
            if (moduleView) moduleView.dataset.status = data.label;
            if (data.module_slug === "procurement") {
                window.location.reload();
                return;
            }
        } catch (error) {
            window.alert(error.message);
        } finally {
            button.disabled = false;
            button.classList.remove("is-saving");
        }
    });

    const moduleDialog = document.querySelector("[data-module-dialog]");
    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-module-view]");
        if (!button || !moduleDialog) return;
        moduleDialog.querySelector("[data-detail-name]").textContent = button.dataset.name || "Module";
        moduleDialog.querySelector("[data-detail-version]").textContent = `v${button.dataset.version || "—"}`;
        moduleDialog.querySelector("[data-detail-status]").textContent = button.dataset.status || "—";
        moduleDialog.querySelector("[data-detail-source]").textContent = button.dataset.source || "—";
        moduleDialog.querySelector("[data-detail-path]").textContent = button.dataset.path || "—";
        moduleDialog.querySelector("[data-detail-description]").textContent = button.dataset.description || "No description provided.";
        if (typeof moduleDialog.showModal === "function") moduleDialog.showModal();
        else moduleDialog.setAttribute("open", "");
    });
    moduleDialog?.querySelectorAll("[data-module-close]").forEach((button) =>
        button.addEventListener("click", () => moduleDialog.close?.()),
    );
    moduleDialog?.addEventListener("click", (event) => {
        if (event.target === moduleDialog) moduleDialog.close?.();
    });
});
