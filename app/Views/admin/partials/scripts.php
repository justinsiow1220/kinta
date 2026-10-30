<script>
    (() => {
        const sidebar = document.getElementById('admin-sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        const mobileViewport = window.matchMedia('(max-width: 768px)');

        const updateToggleState = (expanded) => {
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded ? 'Close navigation menu' : 'Open navigation menu');
            toggle.setAttribute('title', expanded ? 'Close navigation menu' : 'Open navigation menu');
        };

        const setOpen = (open) => {
            sidebar.classList.toggle('is-open', open);
            document.body.classList.toggle('sidebar-expanded', open);
            updateToggleState(open);
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(!sidebar.classList.contains('is-open'));
        });

        sidebar.addEventListener('mouseenter', () => {
            if (!mobileViewport.matches) {
                document.body.classList.add('sidebar-expanded');
                updateToggleState(true);
            }
        });

        sidebar.addEventListener('mouseleave', () => {
            if (!sidebar.classList.contains('is-open') && !sidebar.contains(document.activeElement)) {
                document.body.classList.remove('sidebar-expanded');
                updateToggleState(false);
            }
        });

        sidebar.addEventListener('focusin', () => {
            if (!mobileViewport.matches) {
                document.body.classList.add('sidebar-expanded');
                updateToggleState(true);
            }
        });

        sidebar.addEventListener('focusout', () => {
            window.setTimeout(() => {
                if (!sidebar.classList.contains('is-open') &&
                    !sidebar.matches(':hover') &&
                    !sidebar.contains(document.activeElement)) {
                    document.body.classList.remove('sidebar-expanded');
                    updateToggleState(false);
                }
            }, 0);
        });

        document.addEventListener('click', (event) => {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                if (sidebar.classList.contains('is-open')) {
                    setOpen(false);
                } else if (!sidebar.matches(':hover')) {
                    document.body.classList.remove('sidebar-expanded');
                    updateToggleState(false);
                }

                if (sidebar.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                if (sidebar.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            }
        });

        window.addEventListener('resize', () => {
            setOpen(false);
        });
    })();
</script>

<?php if ($adminModalEnabled ?? false): ?>
    <script>
        (() => {
            window.openAdminModal = (id) => {
                const dialog = document.getElementById(id);
                if (dialog instanceof HTMLDialogElement && !dialog.open) {
                    dialog.showModal();
                }
            };

            document.addEventListener('click', (event) => {
                if (!(event.target instanceof Element)) {
                    return;
                }

                const closeButton = event.target.closest('[data-modal-close]');
                if (closeButton) {
                    closeButton.closest('dialog[data-admin-modal]')?.close();
                }
            });

            document.querySelectorAll('dialog[data-admin-modal]').forEach((dialog) => {
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                });

                if (dialog.dataset.reopen === 'true') {
                    dialog.showModal();
                }
            });
        })();
    </script>
<?php endif ?>

<?php if ($masterDataModule ?? false): ?>
    <script
        src="https://cdn.datatables.net/3.1.4/js/dataTables.js"
        onload="window.kintaDataTablesCoreLoaded = true"
        onerror="window.kintaDataTablesCoreLoaded = false"
    ></script>
    <script>
        (() => {
            const table = document.querySelector('[data-master-data-table]');
            const module = table?.dataset.masterModule;
            const moduleConfig = {
                activities: {
                    label: 'Activity',
                    pluralLabel: 'Activities',
                    idColumn: 0,
                    statusColumn: 3,
                    rowFields: ['sector_name', 'name'],
                    filters: [
                        { key: 'id', label: 'Activity ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any ID' },
                        { key: 'sector', label: 'Sector', column: 1, type: 'select', exact: true, fromColumn: true },
                        { key: 'name', label: 'Activity', column: 2, type: 'text', exact: false, placeholder: 'Any activity' },
                        { key: 'status', label: 'Status', column: 3, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                machines: {
                    label: 'Machine',
                    pluralLabel: 'Machines',
                    idColumn: 0,
                    statusColumn: 3,
                    rowFields: ['machine_code', 'machine_name'],
                    filters: [
                        { key: 'id', label: 'Machine ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any ID' },
                        { key: 'machine_code', label: 'Machine code', column: 1, type: 'text', exact: false, placeholder: 'Any machine code' },
                        { key: 'machine_name', label: 'Machine name', column: 2, type: 'text', exact: false, placeholder: 'Any machine name' },
                        { key: 'status', label: 'Status', column: 3, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                sectors: {
                    label: 'Sector',
                    pluralLabel: 'Sectors',
                    idColumn: 0,
                    statusColumn: 2,
                    rowFields: ['name'],
                    filters: [
                        { key: 'id', label: 'Sector ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any ID' },
                        { key: 'name', label: 'Sector name', column: 1, type: 'text', exact: false, placeholder: 'Any sector name' },
                        { key: 'status', label: 'Status', column: 2, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                workforce: {
                    label: 'Workforce member',
                    pluralLabel: 'Workforce',
                    idColumn: 0,
                    statusColumn: 4,
                    idDisplayField: 'staff_id',
                    rowFields: ['name', 'role', 'username'],
                    nullableFields: ['username'],
                    emptyDisplayValues: { username: '-' },
                    readOnlyRoles: ['admin'],
                    readOnlyMessage: 'Provisioned manually',
                    filters: [
                        { key: 'staff_id', label: 'Staff ID', column: 0, type: 'text', exact: true, placeholder: 'Any staff ID' },
                        { key: 'name', label: 'Name', column: 1, type: 'text', exact: false, placeholder: 'Any name' },
                        { key: 'role', label: 'Role', column: 2, type: 'select', exact: true, options: [['', 'Any role'], ['admin', 'admin'], ['supervisor', 'supervisor'], ['operator', 'operator'], ['qc', 'qc']] },
                        { key: 'username', label: 'Username', column: 3, type: 'text', exact: false, placeholder: 'Any username' },
                        { key: 'status', label: 'Status', column: 4, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                'sales-orders': {
                    label: 'Sales Order',
                    pluralLabel: 'Sales Orders',
                    idColumn: 0,
                    statusColumn: 4,
                    rowFields: ['so_number', 'customer_name', 'batch_qty'],
                    filters: [
                        { key: 'id', label: 'Sales Order ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any ID' },
                        { key: 'so_number', label: 'S/O number', column: 1, type: 'text', exact: false, placeholder: 'Any S/O number' },
                        { key: 'customer_name', label: 'Customer', column: 2, type: 'text', exact: false, placeholder: 'Any customer' },
                        { key: 'batch_qty', label: 'Batch quantity', column: 3, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any quantity' },
                        { key: 'status', label: 'Status', column: 4, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                problems: {
                    label: 'Problem',
                    pluralLabel: 'Problems',
                    idColumn: 0,
                    statusColumn: 3,
                    rowFields: ['code', 'name'],
                    filters: [
                        { key: 'id', label: 'Problem ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any ID' },
                        { key: 'code', label: 'Problem code', column: 1, type: 'text', exact: false, placeholder: 'Any problem code' },
                        { key: 'name', label: 'Problem name', column: 2, type: 'text', exact: false, placeholder: 'Any problem name' },
                        { key: 'status', label: 'Status', column: 3, type: 'select', exact: true, options: [['', 'Any status'], ['Active', 'Active'], ['Inactive', 'Inactive'], ['Unknown', 'Unknown']] },
                    ],
                },
                'operation-reports': {
                    label: 'Process record',
                    pluralLabel: 'Process records',
                    idColumn: 0,
                    hiddenColumns: [8],
                    filters: [
                        { key: 'process_id', label: 'Process ID', column: 0, type: 'text', exact: true, inputMode: 'numeric', placeholder: 'Any process ID' },
                        { key: 'so_number', label: 'S/O number', column: 1, type: 'text', exact: false, placeholder: 'Any S/O number' },
                        { key: 'machine', label: 'Machine', column: 2, type: 'select', exact: true, fromColumn: true },
                        { key: 'sector_activity', label: 'Sector / activity', column: 3, type: 'select', exact: true, fromColumn: true },
                        { key: 'supervisor', label: 'Supervisor', column: 4, type: 'select', exact: true, fromColumn: true },
                        { key: 'status', label: 'Status', column: 5, type: 'select', exact: true, fromColumn: true },
                        { key: 'started_date', label: 'Started date', column: 8, type: 'date', exact: true },
                    ],
                },
            }[module];

            if (moduleConfig) {
                window.kintaMasterModuleConfig = moduleConfig;
            }

            if (!table ||
                !moduleConfig ||
                !window.DataTable ||
                window.kintaDataTablesCoreLoaded !== true ||
                window.DataTable.isDataTable(table)) {
                return;
            }

            window.kintaMasterDataTable = new window.DataTable(table, {
                paging: true,
                searching: true,
                ordering: true,
                responsive: false,
                scrollX: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    lengthMenu: 'Show _MENU_',
                },
                order: [[moduleConfig.idColumn, 'asc']],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                        searchable: false,
                    },
                    ...(moduleConfig.hiddenColumns || []).map((column) => ({
                        targets: column,
                        visible: false,
                    })),
                ],
            });

            if (module === 'activities') {
                window.kintaActivitiesDataTable = window.kintaMasterDataTable;
            }

            const wrapper = document.getElementById(`${table.id}_wrapper`);
            const searchControl = wrapper?.querySelector('.dt-search');
            if (!searchControl) {
                return;
            }
            wrapper.classList.add('master-data-table-wrapper');

            const makeIconButton = (iconName, label) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'kinta-table-icon-button';
                button.setAttribute('aria-label', label);
                button.title = label;

                const icon = document.createElement('i');
                icon.className = `bi ${iconName}`;
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
                return button;
            };

            searchControl.classList.add('kinta-table-search-control');
            const filterButton = makeIconButton('bi-funnel', `Open ${moduleConfig.label} filters`);
            filterButton.setAttribute('aria-expanded', 'false');
            filterButton.setAttribute('aria-controls', `${module}-filter-panel`);

            const filterPanel = document.createElement('div');
            filterPanel.id = `${module}-filter-panel`;
            filterPanel.className = 'kinta-filter-panel';
            filterPanel.hidden = true;
            filterPanel.setAttribute('role', 'region');
            filterPanel.setAttribute('aria-label', `Filter ${moduleConfig.pluralLabel}`);

            const addFilterField = (labelText, control) => {
                const label = document.createElement('label');
                label.className = 'kinta-filter-field';
                label.append(document.createTextNode(labelText), control);
                filterPanel.appendChild(label);
            };

            const filters = [];
            let filterSelect2Initialized = false;
            moduleConfig.filters.forEach((filter) => {
                const control = document.createElement(filter.type === 'select' ? 'select' : 'input');
                if (filter.type === 'text') {
                    control.type = 'search';
                    if (filter.inputMode) control.inputMode = filter.inputMode;
                    control.placeholder = filter.placeholder || '';
                } else if (filter.type === 'date') {
                    control.type = 'date';
                }
                control.setAttribute('aria-label', filter.label);

                if (filter.type === 'select') {
                    const options = filter.fromColumn
                        ? [['', `Any ${filter.label.toLowerCase()}`], ...Array.from(new Set(
                            window.kintaMasterDataTable.column(filter.column).nodes().toArray()
                                .map((cell) => cell.textContent.trim())
                                .filter(Boolean),
                        )).sort((left, right) => left.localeCompare(right)).map((value) => [value, value])]
                        : filter.options;
                    options.forEach(([value, label]) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        control.appendChild(option);
                    });
                }

                addFilterField(filter.label, control);
                filters.push({ ...filter, control });
            });

            const clearFiltersButton = document.createElement('button');
            clearFiltersButton.type = 'button';
            clearFiltersButton.className = 'kinta-filter-clear';
            clearFiltersButton.textContent = 'Clear filters';
            filterPanel.appendChild(clearFiltersButton);

            const exactColumnSearch = (columnIndex, value) => {
                const escaped = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                window.kintaMasterDataTable.column(columnIndex)
                    .search(value ? `^\\s*${escaped}\\s*$` : '', true, false);
            };

            const applyFilters = () => {
                filters.forEach((filter) => {
                    const value = filter.control.value.trim();
                    if (filter.exact) {
                        exactColumnSearch(filter.column, value);
                    } else {
                        window.kintaMasterDataTable.column(filter.column).search(value);
                    }
                });
                window.kintaMasterDataTable.draw();

                const active = filters.some((filter) => Boolean(filter.control.value.trim()));
                filterButton.classList.toggle('is-active', active);
            };

            filters.forEach(({ control }) => {
                control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', applyFilters);
            });

            const initializeFilterSelect2 = async () => {
                if (filterSelect2Initialized || !window.kintaEnsureSelect2) {
                    return;
                }

                const ready = await window.kintaEnsureSelect2();
                if (!ready || !window.jQuery?.fn.select2) {
                    return;
                }

                filters.filter(({ control }) => control.tagName === 'SELECT').forEach(({ control }) => {
                    if (!window.jQuery(control).hasClass('select2-hidden-accessible')) {
                        window.jQuery(control).select2({
                            width: '100%',
                            minimumResultsForSearch: 0,
                            dropdownParent: window.jQuery(filterPanel),
                        });
                    }
                    window.jQuery(control).on('change.kintaMasterFilters', applyFilters);
                });
                filterSelect2Initialized = true;
            };

            clearFiltersButton.addEventListener('click', () => {
                filters.forEach(({ control }) => {
                    control.value = '';
                    if (control.tagName === 'SELECT' && window.jQuery?.fn.select2 &&
                        window.jQuery(control).hasClass('select2-hidden-accessible')) {
                        window.jQuery(control).val('').trigger('change.select2');
                    }
                });
                applyFilters();
                filters[0]?.control.focus();
            });

            const setFilterPanelOpen = (open) => {
                filterPanel.hidden = !open;
                filterButton.setAttribute('aria-expanded', String(open));
                if (open) {
                    filters[0]?.control.focus();
                    initializeFilterSelect2();
                }
            };

            filterButton.addEventListener('click', () => {
                setFilterPanelOpen(filterPanel.hidden);
            });
            filterPanel.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setFilterPanelOpen(false);
                    filterButton.focus();
                }
            });
            document.addEventListener('click', (event) => {
                if (!filterPanel.hidden && !searchControl.contains(event.target)) {
                    setFilterPanelOpen(false);
                }
            });

            searchControl.append(filterButton, filterPanel);
        })();
    </script>
    <script>
        (() => {
            const select2Controls = [
                { control: document.getElementById('field-sector_id'), dialog: '#activity-form-modal' },
                { control: document.getElementById('wf-role'), dialog: '#workforce-form-modal' },
            ].filter(({ control }) => control);
            const loadScript = (url) => new Promise((resolve) => {
                const script = document.createElement('script');
                script.src = url;
                script.onload = () => resolve(true);
                script.onerror = () => resolve(false);
                document.head.appendChild(script);
            });

            let select2Promise = null;
            window.kintaEnsureSelect2 = () => {
                if (window.jQuery?.fn?.select2) {
                    return Promise.resolve(true);
                }

                if (select2Promise) {
                    return select2Promise;
                }

                select2Promise = (async () => {
                    if (!window.jQuery) {
                        const jqueryLoaded = await loadScript('https://code.jquery.com/jquery-3.7.1.min.js');
                        if (!jqueryLoaded || !window.jQuery) {
                            return;
                        }
                    }

                    if (!window.jQuery.fn.select2) {
                        const select2Loaded = await loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js');
                        if (!select2Loaded) {
                            return;
                        }
                    }

                    return Boolean(window.jQuery?.fn?.select2);
                })();

                return select2Promise;
            };

            if (select2Controls.length === 0) {
                return;
            }

            (async () => {
                const ready = await window.kintaEnsureSelect2();
                if (!ready) return;

                select2Controls.forEach(({ control, dialog }) => {
                    if (window.jQuery(control).hasClass('select2-hidden-accessible')) return;
                    window.jQuery(control).select2({
                        width: '100%',
                        minimumResultsForSearch: 0,
                        dropdownParent: window.jQuery(dialog),
                    });
                });
            })();
        })();
    </script>
    <script>
        (() => {
            const form = document.getElementById('edit-form');
            const module = form?.dataset.masterModule;
            const dataTableApi = window.kintaMasterDataTable?.table?.();
            const table = dataTableApi?.node?.() || Array.from(
                document.querySelectorAll('table[data-master-data-table]'),
            ).find((candidate) => candidate.dataset.masterModule === module && candidate.tBodies.length > 0) || null;
            const moduleConfig = window.kintaMasterModuleConfig;
            const dialog = form ? document.getElementById(form.dataset.modalId) : null;
            const modalError = dialog?.querySelector('[role="alert"]');
            const successMessage = document.getElementById(`${module}-ajax-success`);
            const pageErrorMessage = document.getElementById(`${module}-ajax-error`);

            if (!form || !table || !dialog || !form.hasAttribute('data-master-ajax') ||
                !moduleConfig || !['activities', 'machines', 'sectors', 'sales-orders', 'problems', 'workforce'].includes(module) || !window.fetch || !window.FormData) {
                return;
            }

            if (window.kintaMasterAjaxInitialized) {
                return;
            }

            window.kintaMasterAjaxInitialized = true;
            window.kintaMasterMutationPending = false;
            const submitButton = dialog.querySelector('[data-master-submit]') || form.querySelector('button[type="submit"]');

            const showModalError = (message) => {
                modalError.textContent = message;
                modalError.hidden = false;
            };

            const clearModalError = () => {
                modalError.textContent = '';
                modalError.hidden = true;
            };

            const showSuccess = (message) => {
                pageErrorMessage.hidden = true;
                pageErrorMessage.textContent = '';
                successMessage.textContent = message;
                successMessage.hidden = false;
            };

            const showPageError = (message) => {
                successMessage.hidden = true;
                successMessage.textContent = '';
                pageErrorMessage.textContent = message;
                pageErrorMessage.hidden = false;
            };

            const setPending = (pending) => {
                window.kintaMasterMutationPending = pending;
                if (module === 'activities') window.kintaActivitiesMutationPending = pending;
                form.setAttribute('aria-busy', pending ? 'true' : 'false');
                table.setAttribute('aria-busy', pending ? 'true' : 'false');
                submitButton.disabled = pending;
                table.querySelectorAll('[data-master-status-form] button[type="submit"], [data-master-status-form] button:not([type])').forEach((button) => {
                    button.disabled = pending;
                });
            };

            const updateCsrfTokens = (token) => {
                if (!token || typeof token.name !== 'string' || token.name === '' ||
                    typeof token.hash !== 'string' || token.hash === '') {
                    return false;
                }

                const previousName = form.dataset.csrfName;
                const inputs = new Set();
                const collectInputs = (root) => {
                    root.querySelectorAll('input[type="hidden"]').forEach((input) => {
                        if (input.name === previousName || input.name === token.name) {
                            inputs.add(input);
                        }
                    });
                };

                collectInputs(document);
                if (window.kintaMasterDataTable) {
                    window.kintaMasterDataTable.rows().nodes().each((rowNode) => collectInputs(rowNode));
                }

                inputs.forEach((input) => {
                    input.name = token.name;
                    input.value = token.hash;
                    input.defaultValue = token.hash;
                });

                let formToken = Array.from(form.querySelectorAll('input[type="hidden"]'))
                    .find((input) => input.name === token.name);

                if (!formToken) {
                    formToken = document.createElement('input');
                    formToken.type = 'hidden';
                    form.appendChild(formToken);
                }

                formToken.name = token.name;
                formToken.value = token.hash;
                formToken.defaultValue = token.hash;
                form.dataset.csrfName = token.name;

                return true;
            };

            const requestJson = async (url, body, expectedStatus, operationLabel = 'save') => {
                let response;

                try {
                    response = await fetch(url, {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                } catch (error) {
                    return {
                        success: false,
                        uncertain: true,
                        message: `The network request failed, so the ${moduleConfig.label.toLowerCase()} ${operationLabel} result is uncertain. Check the table before retrying. This page will not retry automatically.`,
                    };
                }

                const responseUrl = new URL(response.url, window.location.href);
                if (response.redirected && responseUrl.pathname.endsWith('/admin/login')) {
                    return {
                        success: false,
                        message: 'Your admin session expired. Sign in again, then refresh this page to check the result before retrying.',
                    };
                }

                if (response.redirected) {
                    return {
                        success: false,
                        uncertain: true,
                        message: `The server redirected the request unexpectedly (HTTP ${response.status}). Check this table before retrying.`,
                    };
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.toLowerCase().includes('application/json')) {
                    if (response.status === 401 || response.status === 403) {
                        return {
                            success: false,
                            uncertain: false,
                            message: response.status === 401
                                ? 'Your admin session is no longer authorized. Sign in again, then refresh this page to confirm the current state.'
                                : 'The request was forbidden (HTTP 403). The session or CSRF token may have expired. Refresh, sign in again if needed, then check the table.',
                        };
                    }

                    return {
                        success: false,
                        uncertain: true,
                        message: `The server returned an unexpected response (HTTP ${response.status}). The operation result is uncertain; check the table before retrying.`,
                    };
                }

                const result = await response.json().catch(() => null);
                if (!result || typeof result !== 'object') {
                    return {
                        success: false,
                        uncertain: true,
                        message: `The server returned invalid JSON (HTTP ${response.status}). The operation result is uncertain; check the table before retrying.`,
                    };
                }

                const csrfUpdated = result.csrf ? updateCsrfTokens(result.csrf) : false;
                if (result.success === true && response.ok && response.status === expectedStatus) {
                    if (!csrfUpdated) {
                        return {
                            success: false,
                            confirmed: true,
                            result,
                            message: 'The server confirmed the change, but its refreshed security token was missing. Refresh this page and check the result before continuing.',
                        };
                    }

                    return { success: true, result, csrfUpdated };
                }

                if (result.saved === true) {
                    return {
                        success: false,
                        confirmed: true,
                        message: result.message || `The server saved the ${moduleConfig.label.toLowerCase()}, but its details could not be loaded. Refresh and check the table before retrying.`,
                    };
                }

                return {
                    success: false,
                    validation: response.status === 422,
                    uncertain: !response.ok && response.status >= 500,
                    message: typeof result.message === 'string'
                        ? result.message
                        : `The server could not confirm the operation (HTTP ${response.status}).`,
                    csrfUpdated,
                };
            };

            const appendCell = (row, value) => {
                const cell = document.createElement('td');
                cell.textContent = value == null ? '' : String(value);
                row.appendChild(cell);
                return cell;
            };

            const buildMasterRow = (record, toggleUrl, csrf) => {
                if (!record || !Number.isInteger(Number(record.id)) || Number(record.id) < 1 ||
                    !moduleConfig.rowFields.every((field) =>
                        typeof record[field] === 'string' ||
                        (moduleConfig.nullableFields?.includes(field) && record[field] == null)) ||
                    typeof record.status !== 'string') {
                    throw new Error(`The server response did not include a valid ${moduleConfig.label} row.`);
                }

                const row = document.createElement('tr');
                row.id = `${module}-row-${Number(record.id)}`;
                appendCell(row, record[moduleConfig.idDisplayField] ?? record.id);
                moduleConfig.rowFields.forEach((field) => {
                    appendCell(row, record[field] ?? moduleConfig.emptyDisplayValues?.[field] ?? '');
                });

                const status = String(record.status ?? '').toLowerCase().trim();
                const statusLabel = status === 'active' ? 'Active' : status === 'inactive' ? 'Inactive' : 'Unknown';
                const statusClass = status === 'active'
                    ? 'kinta-status-active'
                    : status === 'inactive' ? 'kinta-status-inactive' : 'kinta-status-neutral';
                const statusCell = appendCell(row, '');
                statusCell.dataset.order = statusLabel;
                statusCell.dataset.search = statusLabel;

                const badge = document.createElement('span');
                badge.className = `kinta-status-badge ${statusClass}`;
                badge.textContent = statusLabel;
                statusCell.appendChild(badge);

                const actionsCell = document.createElement('td');
                if (moduleConfig.readOnlyRoles?.includes(record.role)) {
                    const note = document.createElement('span');
                    note.className = 'muted';
                    note.textContent = moduleConfig.readOnlyMessage || 'Read only';
                    actionsCell.appendChild(note);
                    row.appendChild(actionsCell);
                    return row;
                }

                const editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'kinta-master-edit-button';
                editButton.setAttribute('aria-label', `Edit ${moduleConfig.label}`);
                editButton.title = `Edit ${moduleConfig.label}`;
                const editIcon = document.createElement('i');
                editIcon.className = 'bi bi-pencil';
                editIcon.setAttribute('aria-hidden', 'true');
                editButton.appendChild(editIcon);
                editButton.addEventListener('click', () => editRecord(record));
                actionsCell.appendChild(editButton);

                const actionUrl = new URL(toggleUrl, window.location.href);
                if (actionUrl.origin !== window.location.origin) {
                    throw new Error('The server returned an invalid status-action URL.');
                }

                const toggleForm = document.createElement('form');
                toggleForm.className = 'inline';
                toggleForm.method = 'post';
                toggleForm.action = actionUrl.href;
                toggleForm.dataset.masterStatusForm = 'true';

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = csrf.name;
                csrfInput.value = csrf.hash;
                csrfInput.defaultValue = csrf.hash;
                toggleForm.appendChild(csrfInput);

                const toggleButton = document.createElement('button');
                toggleButton.type = 'submit';
                toggleButton.textContent = status === 'active' ? 'Deactivate' : 'Reactivate';
                toggleForm.appendChild(toggleButton);
                actionsCell.appendChild(toggleForm);
                row.appendChild(actionsCell);

                return row;
            };

            const findRowNode = (recordId) => {
                const rowId = `${module}-row-${Number(recordId)}`;
                const visibleRow = document.getElementById(rowId);
                if (visibleRow && table.contains(visibleRow)) {
                    return visibleRow;
                }

                if (window.kintaMasterDataTable) {
                    return window.kintaMasterDataTable.rows().nodes().toArray()
                        .find((rowNode) => rowNode.id === rowId) || null;
                }

                return document.getElementById(rowId);
            };

            const replaceMasterRow = (record, toggleUrl, csrf) => {
                const currentRow = findRowNode(record.id);
                if (!currentRow) {
                    return false;
                }

                const replacement = buildMasterRow(record, toggleUrl, csrf);
                if (window.kintaMasterDataTable) {
                    window.kintaMasterDataTable.row(currentRow).remove();
                    window.kintaMasterDataTable.row.add(replacement).draw(false);
                } else {
                    currentRow.replaceWith(replacement);
                }

                return true;
            };

            const updateStatusRow = (recordId, status) => {
                if (status !== 'active' && status !== 'inactive') {
                    return false;
                }

                const row = findRowNode(recordId);
                if (!row) {
                    return false;
                }

                const statusCell = row.cells[moduleConfig.statusColumn];
                const badge = statusCell?.querySelector('.kinta-status-badge');
                const statusForm = row.querySelector('[data-master-status-form]');
                const actionButton = statusForm?.querySelector('button[type="submit"], button:not([type])');
                if (!statusCell || !badge || !statusForm || !actionButton) {
                    return false;
                }

                const label = status === 'active' ? 'Active' : 'Inactive';
                badge.textContent = label;
                badge.className = `kinta-status-badge ${status === 'active' ? 'kinta-status-active' : 'kinta-status-inactive'}`;
                statusCell.dataset.order = label;
                statusCell.dataset.search = label;
                actionButton.textContent = status === 'active' ? 'Deactivate' : 'Reactivate';
                actionButton.setAttribute('aria-label', `${actionButton.textContent} ${moduleConfig.label.toLowerCase()}`);
                actionButton.title = actionButton.textContent;

                if (window.kintaMasterDataTable) {
                    window.kintaMasterDataTable.row(row).invalidate('dom');
                    window.kintaMasterDataTable.draw(false);
                }

                return true;
            };

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (window.kintaMasterMutationPending) {
                    return;
                }

                setPending(true);
                clearModalError();
                successMessage.hidden = true;
                pageErrorMessage.hidden = true;

                let serverConfirmedMutation = false;
                try {
                    const isEdit = document.getElementById('record-id').value !== '';
                    const outcome = await requestJson(form.action, new FormData(form), isEdit ? 200 : 201);

                    if (!outcome.success) {
                        if (outcome.confirmed) {
                            serverConfirmedMutation = true;
                            dialog.close();
                            resetForm();
                            showSuccess(outcome.message);
                        } else {
                            showModalError(outcome.csrfUpdated
                                ? outcome.message
                                : `${outcome.message} Refresh the page before retrying.`);
                        }
                        return;
                    }

                    const result = outcome.result;
                    serverConfirmedMutation = true;
                    let rowUpdated = false;
                    if (isEdit) {
                        rowUpdated = replaceMasterRow(result.record, result.toggle_url, result.csrf);
                    } else {
                        const newRow = buildMasterRow(result.record, result.toggle_url, result.csrf);
                        if (window.kintaMasterDataTable) {
                            window.kintaMasterDataTable.row.add(newRow).draw(false);
                        } else {
                            table.tBodies[0].appendChild(newRow);
                        }
                        rowUpdated = true;
                    }

                    dialog.close();
                    resetForm();
                    showSuccess(rowUpdated
                        ? result.message || `${moduleConfig.label} saved successfully.`
                        : `The server confirmed the ${moduleConfig.label.toLowerCase()} was updated, but this row could not be refreshed. Reload and check the table before editing it again.`);
                } catch (error) {
                    if (serverConfirmedMutation) {
                        dialog.close();
                        resetForm();
                        showSuccess(`The server confirmed the ${moduleConfig.label.toLowerCase()} change, but this page could not refresh the row. Reload and check the table before continuing.`);
                    } else {
                        showModalError('The operation could not be completed in this page. Its result may be uncertain; refresh and check the table before retrying.');
                    }
                } finally {
                    setPending(false);
                }
            });

            document.addEventListener('submit', async (event) => {
                if (!(event.target instanceof HTMLFormElement)) {
                    return;
                }

                const statusForm = event.target;
                if (!statusForm.matches('form[data-master-status-form]')) {
                    return;
                }

                const ownerTable = statusForm.closest('table[data-master-data-table]');
                if (!ownerTable || ownerTable.dataset.masterModule !== module) {
                    return;
                }

                event.preventDefault();
                if (window.kintaMasterMutationPending) {
                    return;
                }

                const statusRequestBody = new FormData(statusForm);
                setPending(true);
                successMessage.hidden = true;
                pageErrorMessage.hidden = true;

                let serverConfirmedMutation = false;
                try {
                    const outcome = await requestJson(statusForm.action, statusRequestBody, 200, 'status change');
                    if (!outcome.success) {
                        if (outcome.confirmed) {
                            serverConfirmedMutation = true;
                            const changed = outcome.result
                                ? updateStatusRow(outcome.result.record_id, outcome.result.status)
                                : false;
                            const confirmedMessage = outcome.result?.message || `${moduleConfig.label} status updated.`;
                            showSuccess(changed
                                ? `${confirmedMessage} Refresh the page before making another change because the security token was not refreshed.`
                                : outcome.message);
                        } else {
                            showPageError(`${outcome.message}${outcome.uncertain ? ' Check the row before retrying.' : ''}`);
                        }
                        return;
                    }

                    serverConfirmedMutation = true;
                    const changed = updateStatusRow(outcome.result.record_id, outcome.result.status);
                    showSuccess(changed
                        ? outcome.result.message || `${moduleConfig.label} status updated.`
                        : `The server confirmed the status change, but the row could not be refreshed. Reload and check the ${moduleConfig.label.toLowerCase()} table.`);
                } catch (error) {
                    showPageError(serverConfirmedMutation
                        ? `The server confirmed the status change, but this page could not refresh the row. Reload and check the ${moduleConfig.label.toLowerCase()} table.`
                        : `The status request failed, so its result is uncertain. Refresh and check the ${moduleConfig.label.toLowerCase()} before retrying. This page will not retry automatically.`);
                } finally {
                    setPending(false);
                }
            }, true);
        })();
    </script>
<?php endif ?>
