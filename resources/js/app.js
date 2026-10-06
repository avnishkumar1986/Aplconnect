import './bootstrap';
import DataTable from 'datatables.net-dt';
import $ from 'jquery';
import select2 from 'select2';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-responsive-dt';
import 'datatables.net-responsive-dt/css/responsive.dataTables.css';
import 'select2/dist/css/select2.css';

window.$ = window.jQuery = $;
select2($);

const configuredTablePageLength = Number.parseInt(
    document.documentElement.dataset.tablePageLength || '50',
    10,
);
const tablePageLength = Number.isInteger(configuredTablePageLength) && configuredTablePageLength > 0
    ? configuredTablePageLength
    : 50;

const finaliseDataTableLayout = table => {
    table.classList.add('ui-data-table');
    const container = table.closest('.dt-container');
    if (!container) return;

    container.classList.add('unified-datatable');
    const topRow = container.querySelector(':scope > .dt-layout-row:first-child');
    if (!topRow) return;

    const toolbar = table.id
        ? document.querySelector(`[data-datatable-toolbar-for="${CSS.escape(table.id)}"]`)
        : null;
    if (toolbar) {
        [...toolbar.children].forEach(control => {
            control.classList.add('datatable-toolbar-control');
            topRow.append(control);
        });
        toolbar.remove();
    }

    [...topRow.children].forEach(cell => {
        if (!cell.textContent.trim() && !cell.querySelector('input, select, button, a, form')) cell.remove();
    });
};

const initialiseSelect2 = (root = document) => {
    $(root).find('select[data-searchable-select]').each(function () {
        const select = $(this);
        if (select.hasClass('select2-hidden-accessible')) return;
        const modal = select.closest('[data-form-modal-instance]');
        select.select2({
            width: '100%',
            dropdownParent: modal.length ? modal.find('.global-form-dialog') : $(document.body),
            placeholder: select.find('option:first').text(),
            allowClear: !select.prop('required'),
        });
    });
};
window.initialiseSelect2 = initialiseSelect2;

const initialiseProcurementForms = (root = document) => {
    root.querySelectorAll('[data-procurement-form]').forEach((form) => {
        if (form.dataset.dependenciesInitialized === 'true') return;
        form.dataset.dependenciesInitialized = 'true';

        const company = form.querySelector('[data-company]');
        const plant = form.querySelector('[data-plant]');
        if (company && plant) {
            const plantOptions = [...plant.options].slice(1).map(option => option.cloneNode(true));
            const refreshPlants = () => {
                const selectedPlant = plant.dataset.selected || plant.value;
                const hasCompany = company.value !== '';
                plant.replaceChildren(new Option(hasCompany ? '— Select plant —' : '— Select company first —', ''));
                plantOptions
                    .filter(option => (option.dataset.companyCode ?? option.dataset.companyId) === company.value)
                    .forEach(option => plant.append(option.cloneNode(true)));
                if ([...plant.options].some(option => option.value === selectedPlant)) plant.value = selectedPlant;
                plant.dataset.selected = '';
                plant.disabled = !hasCompany;
                $(plant).trigger('change');
            };
            company.addEventListener('change', refreshPlants);
            $(company).on('select2:select select2:clear', refreshPlants);
            refreshPlants();
        }

        const group = form.querySelector('[data-material-group]');
        const material = form.querySelector('[data-material]');
        if (group && material) {
            const groupOptions = [...group.options].slice(1).map(option => option.cloneNode(true));
            const materialOptions = [...material.options].slice(1).map(option => option.cloneNode(true));
            const dependsOnPlant = group.matches('[data-dependent-on-plant]') && plant;
            const refreshMaterials = () => {
                const selectedMaterial = material.dataset.selected || material.value;
                const hasGroup = group.value !== '';
                material.replaceChildren(new Option(hasGroup ? '— Select material —' : '— Select material group first —', ''));
                materialOptions
                    .filter(option => option.dataset.group === group.value && (!dependsOnPlant || option.dataset.plantCode === plant.value))
                    .forEach(option => material.append(option.cloneNode(true)));
                if ([...material.options].some(option => option.value === selectedMaterial)) material.value = selectedMaterial;
                material.dataset.selected = '';
                material.disabled = !hasGroup;
                $(material).trigger('change');
            };
            group.addEventListener('change', refreshMaterials);
            $(group).on('select2:select select2:clear', refreshMaterials);
            if (dependsOnPlant) {
                const refreshGroups = () => {
                    const selectedGroup = group.value;
                    const hasPlant = plant.value !== '';
                    group.replaceChildren(new Option(hasPlant ? '— Select group —' : '— Select plant first —', ''));
                    groupOptions.filter(option => option.dataset.plantCode === plant.value)
                        .forEach(option => group.append(option.cloneNode(true)));
                    if ([...group.options].some(option => option.value === selectedGroup)) group.value = selectedGroup;
                    group.disabled = !hasPlant;
                    $(group).trigger('change');
                    refreshMaterials();
                };
                plant.addEventListener('change', refreshGroups);
                $(plant).on('select2:select select2:clear', refreshGroups);
                refreshGroups();
            }
            refreshMaterials();
        } else if (material && plant) {
            const materialOptions = [...material.options].slice(1).map(option => option.cloneNode(true));
            const refreshMaterials = () => {
                const selectedMaterial = material.dataset.selected || material.value;
                const hasPlant = plant.value !== '';
                material.replaceChildren(new Option(hasPlant ? '— Select material —' : '— Select plant first —', ''));
                materialOptions.filter(option => option.dataset.plantCode === plant.value)
                    .forEach(option => material.append(option.cloneNode(true)));
                if ([...material.options].some(option => option.value === selectedMaterial)) material.value = selectedMaterial;
                material.dataset.selected = '';
                material.disabled = !hasPlant;
                $(material).trigger('change');
            };
            plant.addEventListener('change', refreshMaterials);
            $(plant).on('select2:select select2:clear', refreshMaterials);
            refreshMaterials();
        }

        const purchaseMaterial = form.querySelector('[data-purchase-material]');
        const vendor = form.querySelector('[data-vendor]');
        if (purchaseMaterial && vendor) {
            const vendorOptions = [...vendor.options].slice(1).map(option => option.cloneNode(true));
            const syncButton = form.querySelector('[data-sync-purchase-vendors]');
            const syncStatus = form.querySelector('[data-vendor-sync-status]');
            const purchaseOrders = form.querySelector('[data-single-vendor-purchase-order], [data-vendor-purchase-orders]');
            const refreshPurchaseOrders = () => {
                if (!purchaseOrders) return;
                const values = (vendor.selectedOptions[0]?.dataset.purchaseOrders || '')
                    .split(',').map(value => value.trim()).filter(Boolean);
                if (purchaseOrders instanceof HTMLSelectElement) {
                    const selectedOrder = purchaseOrders.dataset.selected || purchaseOrders.value;
                    purchaseOrders.replaceChildren(new Option(
                        vendor.value ? '— Select SAP purchase order —' : '— Select vendor first —', ''
                    ));
                    [...new Set(values)].forEach(value => purchaseOrders.append(new Option(value, value)));
                    if ([...purchaseOrders.options].some(option => option.value === selectedOrder)) {
                        purchaseOrders.value = selectedOrder;
                    }
                    purchaseOrders.dataset.selected = '';
                    purchaseOrders.disabled = !vendor.value || values.length === 0;
                    $(purchaseOrders).trigger('change');
                    return;
                }
                purchaseOrders.value = values[0] || '';
                purchaseOrders.placeholder = vendor.value ? 'No SAP purchase order found' : 'Select a vendor';
            };
            const refreshVendors = () => {
                const selectedVendor = vendor.dataset.selected || vendor.value;
                const hasMaterial = purchaseMaterial.value !== '';
                vendor.replaceChildren(new Option(hasMaterial ? '— Select vendor —' : '— Select material first —', ''));
                vendorOptions.filter(option => option.dataset.material === purchaseMaterial.value)
                    .forEach(option => vendor.append(option.cloneNode(true)));
                if ([...vendor.options].some(option => option.value === selectedVendor)) vendor.value = selectedVendor;
                vendor.dataset.selected = '';
                vendor.disabled = !hasMaterial;
                if (syncButton) syncButton.disabled = !hasMaterial;
                $(vendor).trigger('change');
                refreshPurchaseOrders();
            };
            purchaseMaterial.addEventListener('change', refreshVendors);
            $(purchaseMaterial).on('select2:select select2:clear', refreshVendors);
            vendor.addEventListener('change', refreshPurchaseOrders);
            $(vendor).on('select2:select select2:clear', refreshPurchaseOrders);
            syncButton?.addEventListener('click', async () => {
                if (!purchaseMaterial.value || syncButton.disabled) return;
                syncButton.disabled = true;
                if (syncStatus) syncStatus.textContent = 'Importing…';
                try {
                    const response = await fetch(syncButton.dataset.syncUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ material: purchaseMaterial.value }),
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'Unable to import vendors from SAP.');
                    const materialCode = purchaseMaterial.value;
                    for (let index = vendorOptions.length - 1; index >= 0; index -= 1) {
                        if (vendorOptions[index].dataset.material === materialCode) vendorOptions.splice(index, 1);
                    }
                    payload.vendors.forEach(item => {
                        const option = new Option(`${item.vendor_code} - ${item.vendor_name}`, item.vendor_name);
                        option.dataset.material = materialCode;
                        option.dataset.purchaseOrders = item.purchase_orders || '';
                        vendorOptions.push(option);
                    });
                    refreshVendors();
                    if (syncStatus) syncStatus.textContent = payload.message || `${payload.vendors.length} vendor(s) imported.`;
                } catch (error) {
                    if (syncStatus) syncStatus.textContent = error.message;
                } finally {
                    syncButton.disabled = purchaseMaterial.value === '';
                }
            });
            refreshVendors();
        }

        const stockDisplay = form.querySelector('[data-current-stock]');
        if (stockDisplay && plant && material) {
            const stockMap = JSON.parse(form.dataset.stockMap || '{}');
            const quantity = form.elements.quantity_mt;
            const shortageDisplay = form.querySelector('[data-stock-shortage]');
            const purchasePanel = form.querySelector('[data-shortage-purchase]');
            const purchaseLink = form.querySelector('[data-shortage-purchase-link]');
            const refreshStock = () => {
                const selected = plant.value !== '' && material.value !== '';
                const key = material.value + '|' + plant.value;
                const stock = Object.hasOwn(stockMap, key) ? stockMap[key] : 0;
                const shortage = selected && stock !== null ? Math.max(0, Math.round((Number(quantity?.value || 0) - Number(stock)) * 1000) / 1000) : 0;
                stockDisplay.value = selected ? (stock === null ? 'N/A - unit conversion required' : Number(stock).toFixed(3)) : '';
                if (shortageDisplay) shortageDisplay.value = selected ? (stock === null ? 'N/A' : shortage.toFixed(3)) : '';
                if (purchasePanel) purchasePanel.hidden = shortage <= 0;
                if (purchaseLink && shortage > 0) {
                    const url = new URL(purchaseLink.dataset.purchaseUrl, window.location.origin);
                    for (const [key, value] of Object.entries({ company_id: company.value, plant_id: plant.value,
                        material: material.value, quantity_mt: shortage.toFixed(3) })) url.searchParams.set(key, value);
                    purchaseLink.href = url.toString();
                }
            };
            $(plant).on('change', refreshStock);
            $(material).on('change', refreshStock);
            quantity?.addEventListener('input', refreshStock);
            refreshStock();
        }

        const date = form.elements.consumption_date;
        const dayCount = form.querySelector('[data-day-count]');
        if (date && dayCount) {
            const refreshDayCount = () => {
                if (!date.value) { dayCount.value = ''; return; }
                const [year, month, day] = date.value.split('-').map(Number);
                dayCount.value = new Date(year, month, 0).getDate() - day + 1;
            };
            date.addEventListener('change', refreshDayCount);
            refreshDayCount();
        }
    });
};

const initialiseDataTables = () => {
    const table = document.querySelector('#users-datatable');
    if (!table || table.dataset.initialized === 'true') return;
    table.dataset.initialized = 'true';

    const dataTable = new DataTable(table, {
        responsive: {
            details: { type: 'column', target: 0 },
        },
        layout: {
            topStart: 'search',
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        pageLength: tablePageLength,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[2, 'asc']],
        autoWidth: false,
        columnDefs: [
            { className: 'dtr-control', orderable: false, searchable: false, targets: 0 },
            { orderable: false, searchable: false, targets: [1, -1] },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search employee ID or name...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            paginate: { previous: '‹', next: '›' },
        },
    });
    finaliseDataTableLayout(table);

    const bulkForm = document.querySelector('[data-user-bulk-form]');
    const selectAll = table.querySelector('[data-select-all-users]');
    const selectedCount = bulkForm?.querySelector('[data-selected-count]');
    const applyButton = bulkForm?.querySelector('button[type="submit"]');
    const allCheckboxes = () => dataTable.rows().nodes().toArray()
        .flatMap(row => [...row.querySelectorAll('[data-user-select]')]);
    const refreshBulkState = () => {
        const boxes = allCheckboxes();
        const checked = boxes.filter(box => box.checked);
        if (selectedCount) selectedCount.textContent = `${checked.length} selected`;
        if (applyButton) applyButton.disabled = checked.length === 0;
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    };
    selectAll?.addEventListener('change', () => {
        allCheckboxes().forEach(box => { box.checked = selectAll.checked; });
        refreshBulkState();
    });
    table.addEventListener('change', event => {
        if (event.target.matches('[data-user-select]')) refreshBulkState();
    });
    dataTable.on('draw', refreshBulkState);
    bulkForm?.addEventListener('submit', event => {
        bulkForm.querySelectorAll('input[name="user_ids[]"]').forEach(input => input.remove());
        const ids = allCheckboxes().filter(box => box.checked).map(box => box.value);
        if (!ids.length) { event.preventDefault(); return; }
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = id;
            bulkForm.appendChild(input);
        });
    });
};

const initialiseApiIntegrationDataTable = () => {
    const table = document.querySelector('#api-integrations-datatable');
    if (!table || table.dataset.initialized === 'true') return;
    table.dataset.initialized = 'true';

    new DataTable(table, {
        responsive: { details: { type: 'column', target: 0 } },
        layout: {
            topStart: 'search',
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        pageLength: tablePageLength,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'asc']],
        autoWidth: false,
        columnDefs: [
            { className: 'dtr-control', orderable: false, searchable: false, targets: 0 },
            { orderable: false, searchable: false, targets: [1, -1] },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search API integrations...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            emptyTable: 'No API integrations configured.',
            paginate: { previous: '‹', next: '›' },
        },
    });
    finaliseDataTableLayout(table);
};

const initialiseApiRunDataTable = () => {
    const table = document.querySelector('#api-runs-datatable');
    if (!table || table.dataset.initialized === 'true') return;
    table.dataset.initialized = 'true';

    new DataTable(table, {
        responsive: { details: { type: 'column', target: 0 } },
        layout: { topStart: 'search', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        pageLength: tablePageLength,
        lengthMenu: [10, 25, 50, 100],
        order: [[4, 'desc']],
        autoWidth: false,
        columnDefs: [
            { className: 'dtr-control', orderable: false, searchable: false, targets: 0 },
            { orderable: false, searchable: false, targets: 1 },
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search API run logs...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ runs',
            emptyTable: 'No API runs in the last 24 hours.',
            paginate: { previous: '‹', next: '›' },
        },
    });
    finaliseDataTableLayout(table);
};

const initialiseMaterialsDataTable = () => {
    const table = document.querySelector('#materials-datatable');
    if (!table || table.dataset.initialized === 'true') return;
    table.dataset.initialized = 'true';
    const escapeHtml = value => {
        const element = document.createElement('span');
        element.textContent = value ?? '';
        return element.innerHTML;
    };
    const columns = [
        { data: null, defaultContent: '' },
        { data: 'sno' },
        { data: null, render: (data, type, row) => type === 'display' ? `<strong>${escapeHtml(row.label)}</strong><small class="block text-slate-400">${escapeHtml(row.key)}</small>` : row.label },
        { data: 'mtart', defaultContent: '—' },
        { data: 'matkl', defaultContent: '—' },
        { data: 'meins', defaultContent: '—' },
        { data: 'plant_name', defaultContent: '—' },
        { data: 'current_stock', className: 'text-right' },
        { data: 'stock_value', className: 'text-right' },
        { data: 'status', render: data => `<span class="font-semibold ${data === 'Active' ? 'text-emerald-700' : 'text-slate-400'}">${escapeHtml(data)}</span>` },
    ];
    if (table.dataset.showRemarks === '1') columns.push({ data: 'remarks', defaultContent: '-', orderable: false, render: (data, type) => type === 'display' ? escapeHtml(data) : data });
    if (table.dataset.actions === '1') columns.push({ data: null, render: (data, type, row) => {
        if (type !== 'display') return '';
        const edit = row.edit_url ? `<a class="edit user-action-button user-action-edit" data-form-modal data-modal-title="Edit Material" href="${escapeHtml(row.edit_url)}" aria-label="Edit material" title="Edit material"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="m16.5 3.5 4 4L8 20l-5 1 1-5Z"/></svg></a>` : '';
        const remove = row.delete_url ? `<form method="POST" action="${escapeHtml(row.delete_url)}" onsubmit="return confirm('Delete this material?')"><input type="hidden" name="_token" value="${escapeHtml(document.querySelector('meta[name=csrf-token]')?.content)}"><input type="hidden" name="_method" value="DELETE"><button class="delete user-action-button user-action-delete" aria-label="Delete material" title="Delete material"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/></svg></button></form>` : '';
        return `<div class="row-actions">${edit}${remove}</div>`;
    } });

    new DataTable(table, {
        processing: true,
        serverSide: true,
        responsive: { details: { type: 'column', target: 0 } },
        ajax: table.dataset.source,
        layout: { topStart: 'search', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        pageLength: tablePageLength,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'asc']],
        autoWidth: false,
        columnDefs: [
            { className: 'dtr-control', orderable: false, searchable: false, targets: 0 },
            { orderable: false, searchable: false, targets: table.dataset.actions === '1' ? [1, -1] : [1] },
        ],
        columns,
        language: {
            search: '',
            searchPlaceholder: 'Search materials...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ materials',
            processing: 'Loading materials...',
            emptyTable: 'No synchronized procurement stock found.',
            paginate: { previous: '‹', next: '›' },
        },
    });
    finaliseDataTableLayout(table);
};

const initialiseSimpleDataTables = () => document.querySelectorAll('[data-simple-datatable]').forEach(table => {
    if (table.dataset.initialized === 'true') return;
    table.dataset.initialized = 'true';
    const nonOrderableColumns = [];
    if (table.hasAttribute('data-sno')) nonOrderableColumns.push(table.hasAttribute('data-responsive-control') ? 1 : 0);
    if (table.hasAttribute('data-actions')) nonOrderableColumns.push(-1);
    new DataTable(table, {
        responsive: table.hasAttribute('data-responsive-control') ? { details: { type: 'column', target: 0 } } : true,
        pageLength: tablePageLength,
        lengthMenu: [10, 25, 50, 100],
        order: [[Number(table.dataset.orderColumn ?? 0), 'desc']],
        columnDefs: [
            ...(table.hasAttribute('data-responsive-control') ? [{ className: 'dtr-control', orderable: false, searchable: false, targets: 0 }] : []),
            ...(nonOrderableColumns.length ? [{ orderable: false, searchable: false, targets: nonOrderableColumns }] : []),
        ],
        layout: { topStart: 'search', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' },
        language: { search: '', searchPlaceholder: table.dataset.searchPlaceholder || 'Search...', emptyTable: 'No records found.', paginate: { previous: '‹', next: '›' } },
    });
    finaliseDataTableLayout(table);
    if (table.dataset.tableToolbar) {
        const toolbar = table.closest('.planning-panel, .card')?.querySelector(table.dataset.tableToolbar);
        const topRow = table.closest('.dt-container')?.querySelector(':scope > .dt-layout-row:first-child');
        if (toolbar && topRow) topRow.append(toolbar);
    }
    if (table.hasAttribute('data-page-length-bottom')) {
        const container = table.closest('.dt-container');
        const length = container?.querySelector('.dt-length')?.closest('.dt-layout-cell');
        const bottomRow = container?.querySelector(':scope > .dt-layout-row:last-child');
        if (length && bottomRow) bottomRow.prepend(length);
    }
});

const initialiseAdminShell = () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openButton = document.getElementById('sidebar-open');
    const closeButton = document.getElementById('sidebar-close');

    if (!sidebar || !overlay || !openButton) return;

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        openButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    };

    openButton.addEventListener('click', open);
    closeButton?.addEventListener('click', () => {
        if (window.matchMedia('(min-width: 1024px)').matches) document.body.classList.toggle('sidebar-compact');
        else close();
    });
    overlay.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', (event) => event.key === 'Escape' && close());
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => event.matches && close());
    const closeDropdowns = (except = null) => document.querySelectorAll('[data-dropdown-trigger]').forEach((button) => {
        const panel = document.getElementById(button.dataset.dropdownTrigger);
        if (panel && panel !== except) { panel.classList.add('hidden'); button.setAttribute('aria-expanded', 'false'); }
    });
    document.querySelectorAll('[data-dropdown-trigger]').forEach((button) => button.addEventListener('click', (event) => {
        event.stopPropagation(); const panel = document.getElementById(button.dataset.dropdownTrigger); const opening = panel?.classList.contains('hidden'); closeDropdowns(panel); panel?.classList.toggle('hidden', !opening); button.setAttribute('aria-expanded', String(opening));
    }));
    document.querySelectorAll('[data-sidebar-group]').forEach((button) => button.addEventListener('click', () => {
        const panel = document.getElementById(button.dataset.sidebarGroup); const opening = panel?.classList.contains('hidden'); panel?.classList.toggle('hidden'); button.setAttribute('aria-expanded', String(opening)); button.querySelector('svg')?.classList.toggle('rotate-180', opening);
    }));
    document.addEventListener('click', () => closeDropdowns());
    document.addEventListener('keydown', (event) => event.key === 'Escape' && closeDropdowns());
};

const initialiseGlobalFormModal = () => {
    const template = document.querySelector('[data-form-modal-template]');
    if (!template || template.dataset.initialized === 'true') return;
    template.dataset.initialized = 'true';
    let modalSequence = 0;

    const createModalForForm = (link, url) => {
        const modal = template.content.firstElementChild.cloneNode(true);
        const content = modal.querySelector('[data-global-form-content]');
        const loading = modal.querySelector('[data-global-form-loading]');
        const dialog = modal.querySelector('.global-form-dialog');
        const title = modal.querySelector('[data-global-form-title]');
        const titleId = `form-modal-title-${++modalSequence}`;
        title.id = titleId;
        title.textContent = link.dataset.modalTitle || 'Manage record';
        dialog.setAttribute('aria-labelledby', titleId);
        const resourceNeedsWideModal = /\/admin\/(users|companies)(?:\/|$)/.test(url.pathname);
        modal.dataset.requestedSize = link.dataset.modalSize || (resourceNeedsWideModal ? 'wide' : 'default');
        dialog.dataset.formSize = modal.dataset.requestedSize === 'default' ? 'standard' : modal.dataset.requestedSize;
        modal.dataset.formUrl = url.href;
        document.body.appendChild(modal);
        modal.classList.add('is-measuring');

        const requestController = new AbortController();
        const classifyDialog = () => {
            if (modal.dataset.requestedSize !== 'default') return;
            const isWizard = Boolean(content.querySelector('.user-wizard, .company-wizard-form'));
            const fieldCount = content.querySelectorAll('.form-field').length;
            dialog.dataset.formSize = isWizard || fieldCount > 10 ? 'wide' : fieldCount <= 4 ? 'compact' : 'standard';
        };
        const close = () => {
            requestController.abort();
            modal.remove();
            if (!document.querySelector('[data-form-modal-instance].is-open')) document.body.classList.remove('modal-open');
        };
        modal.querySelectorAll('[data-global-form-close]').forEach((element) => element.addEventListener('click', close));
        content.addEventListener('click', (event) => {
            const cancel = event.target.closest('a.btn-secondary');
            if (cancel && !cancel.hasAttribute('data-keep-navigation')) {
                event.preventDefault();
                close();
            }
        });
        fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: requestController.signal,
            }).then(async (response) => {
            if (!response.ok) throw new Error(`Unable to load form (${response.status}).`);
            const formDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const page = formDocument.querySelector('.modal-form-page');
            if (!page) throw new Error('The requested page did not return a modal form.');
            // Keep the modal page wrapper: it is the styling and sizing boundary
            // used by both the dialog CSS and resize observer.
            content.replaceChildren(page);
            formDocument.querySelectorAll('script:not([src])').forEach((script) => {
                const type = (script.getAttribute('type') || '').toLowerCase();
                if ((!type || type === 'text/javascript') && script.textContent.trim()) Function(script.textContent)();
            });
                    window.mountAdminForms?.();
                    window.mountCompanyForms?.();
                    initialiseProcurementForms(content);
            window.initialiseSelect2?.(content);
            classifyDialog();
            loading?.classList.add('hidden');
            content.classList.add('is-ready');
            requestAnimationFrame(() => {
                modal.classList.remove('is-measuring');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            });
        }).catch((error) => {
            if (error.name === 'AbortError') return;
            loading?.classList.add('hidden');
            content.innerHTML = `<div class="global-form-load-error"><strong>Unable to load this form.</strong><p>${error.message}</p></div>`;
            content.classList.add('is-ready');
            modal.classList.remove('is-measuring');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        });
        return { modal, close };
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-form-modal]');
        if (!link) return;
        event.preventDefault();
        const url = new URL(link.href, window.location.href);
        url.searchParams.set('modal', '1');
        createModalForForm(link, url);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openModals = [...document.querySelectorAll('[data-form-modal-instance].is-open')];
        openModals.at(-1)?.querySelector('[data-global-form-close]')?.click();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initialiseAdminShell();
        initialiseGlobalFormModal();
        initialiseDataTables();
        initialiseApiIntegrationDataTable();
        initialiseApiRunDataTable();
        initialiseMaterialsDataTable();
        initialiseSimpleDataTables();
        initialiseProcurementForms();
        initialiseSelect2();
    }, { once: true });
} else {
    initialiseAdminShell();
    initialiseGlobalFormModal();
    initialiseDataTables();
    initialiseApiIntegrationDataTable();
    initialiseApiRunDataTable();
    initialiseMaterialsDataTable();
    initialiseSimpleDataTables();
    initialiseProcurementForms();
    initialiseSelect2();
}
