/**
 * Raporlar (sales report) panel for /admin (every branch, scp_view_reports)
 * and /sube (own branch only, scp_view_own_reports) - mirrors
 * hakedis-panel.js's HQ-vs-own-branch split. The "Getir" button loads the
 * report inline as JSON; the CSV/Excel buttons instead navigate the browser
 * straight to GET seviye/v1/reports/sales?format=csv|xlsx, since a real
 * file download can't be driven through fetch()+JS - the REST nonce rides
 * along as a `_wpnonce` query param instead of the X-WP-Nonce header every
 * other call here uses (see ReportsRestController's class docblock).
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-reports-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-reports-status]');
    var form = root.querySelector('[data-scp-report-form]');
    var branchField = root.querySelector('[data-scp-report-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var table = root.querySelector('[data-scp-reports-table]');
    var tableBody = root.querySelector('[data-scp-reports-body]');
    var csvButton = root.querySelector('[data-scp-report-csv]');
    var xlsxButton = root.querySelector('[data-scp-report-xlsx]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function formatMoney(amount) {
        return Number(amount).toFixed(2) + ' ₺';
    }

    function currentParams() {
        var formData = new FormData(form);
        var params = new URLSearchParams();

        ['product_id', 'category_id', 'from', 'to'].forEach(function (name) {
            var value = formData.get(name);

            if (value) {
                params.set(name, value);
            }
        });

        if (scpPanel.canViewAllBranches && branchSelect.value) {
            params.set('branch_id', branchSelect.value);
        }

        return params;
    }

    function populateBranchSelect(branches) {
        branchField.hidden = false;

        var previouslySelected = branchSelect.value;
        branchSelect.innerHTML = '';

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = scpPanelText.allBranches;
        branchSelect.appendChild(allOption);

        branches.forEach(function (branch) {
            var option = document.createElement('option');
            option.value = String(branch.id);
            option.textContent = branch.name;
            branchSelect.appendChild(option);
        });

        if (previouslySelected) {
            branchSelect.value = previouslySelected;
        }
    }

    function renderRows(rows) {
        tableBody.innerHTML = '';
        table.hidden = rows.length === 0;

        rows.forEach(function (row) {
            var tr = document.createElement('tr');

            [
                row.branch_name,
                row.product_name,
                String(row.order_count),
                formatMoney(row.total_price),
                formatMoney(row.total_vat)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                tr.appendChild(cell);
            });

            tableBody.appendChild(tr);
        });

        setStatus(rows.length === 0 ? scpPanelText.noReportData : '');
    }

    function loadReport() {
        var params = currentParams();
        params.set('format', 'json');

        apiFetch('reports/sales?' + params.toString()).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            renderRows(result.data);
        });
    }

    function downloadReport(format) {
        var params = currentParams();
        params.set('format', format);
        params.set('_wpnonce', scpPanel.nonce);

        window.location.href = scpPanel.restUrl + 'reports/sales?' + params.toString();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadReport();
    });

    csvButton.addEventListener('click', function () {
        downloadReport('csv');
    });

    xlsxButton.addEventListener('click', function () {
        downloadReport('xlsx');
    });

    if (scpPanel.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }

    loadReport();

    // ---- Depo Raporları (faz 3) - yalnızca scp_view_reports'a görünür (bkz. zone.php) ----

    var warehouseForm = root.querySelector('[data-scp-warehouse-report-form]');

    if (warehouseForm) {
        var warehouseTable = root.querySelector('[data-scp-warehouse-report-table]');
        var warehouseTableBody = root.querySelector('[data-scp-warehouse-report-body]');
        var warehouseCsvButton = root.querySelector('[data-scp-warehouse-report-csv]');
        var warehouseXlsxButton = root.querySelector('[data-scp-warehouse-report-xlsx]');

        var warehouseCurrentParams = function () {
            var formData = new FormData(warehouseForm);
            var params = new URLSearchParams();

            ['supplier_id', 'from', 'to'].forEach(function (name) {
                var value = formData.get(name);

                if (value) {
                    params.set(name, value);
                }
            });

            return params;
        };

        var renderWarehouseRows = function (rows) {
            warehouseTableBody.innerHTML = '';
            warehouseTable.hidden = rows.length === 0;

            rows.forEach(function (row) {
                var tr = document.createElement('tr');

                [
                    row.supplier_name,
                    String(row.order_count),
                    formatMoney(row.total_cost),
                    String(row.completed_order_count),
                    row.on_time_rate === null ? '—' : row.on_time_rate + '%'
                ].forEach(function (text) {
                    var cell = document.createElement('td');
                    cell.textContent = text;
                    tr.appendChild(cell);
                });

                warehouseTableBody.appendChild(tr);
            });

            setStatus(rows.length === 0 ? scpPanelText.noReportData : '');
        };

        var loadWarehouseReport = function () {
            var params = warehouseCurrentParams();
            params.set('format', 'json');

            apiFetch('reports/warehouse?' + params.toString()).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                    return;
                }

                renderWarehouseRows(result.data);
            });
        };

        var downloadWarehouseReport = function (format) {
            var params = warehouseCurrentParams();
            params.set('format', format);
            params.set('_wpnonce', scpPanel.nonce);

            window.location.href = scpPanel.restUrl + 'reports/warehouse?' + params.toString();
        };

        warehouseForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loadWarehouseReport();
        });

        warehouseCsvButton.addEventListener('click', function () {
            downloadWarehouseReport('csv');
        });

        warehouseXlsxButton.addEventListener('click', function () {
            downloadWarehouseReport('xlsx');
        });

        loadWarehouseReport();
    }
})();
