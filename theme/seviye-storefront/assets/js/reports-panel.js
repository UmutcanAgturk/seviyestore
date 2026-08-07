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

    // Captured once, synchronously, at script load - NOT re-read later
    // via the bare `scpPanel`/`scpPanelText` globals, which several
    // OTHER scripts on this same page (account-security.js,
    // privacy-requests-panel.js, support-tickets-panel.js - all
    // unconditionally enqueued on every admin/sube page) also localize
    // under the SAME global variable names. Whichever of those loads
    // LAST overwrites `window.scpPanel`/`window.scpPanelText` for the
    // whole page; code that reads the bare global later (inside an
    // apiFetch().then() callback, a click handler, ...) would silently
    // see THAT other script's narrower data instead of this page's own
    // - capturing a local reference immediately, before any later
    // script's localize tag runs, avoids that.
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-reports-status]');
    var form = root.querySelector('[data-scp-report-form]');
    var branchField = root.querySelector('[data-scp-report-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var table = root.querySelector('[data-scp-reports-table]');
    var tableBody = root.querySelector('[data-scp-reports-body]');
    var csvButton = root.querySelector('[data-scp-report-csv]');
    var xlsxButton = root.querySelector('[data-scp-report-xlsx]');
    var comparisonChart = root.querySelector('[data-scp-comparison-chart]');
    var comparisonChartHost = root.querySelector('[data-scp-comparison-chart-host]');
    var comparisonModeButtons = root.querySelectorAll('[data-scp-comparison-mode]');
    var comparisonMode = 'branch';
    var lastReportRows = [];

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

        if (scpPanelData.canViewAllBranches && branchSelect.value) {
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
        allOption.textContent = scpPanelTextData.allBranches;
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

        setStatus(rows.length === 0 ? scpPanelTextData.noReportData : '');

        lastReportRows = rows;
        renderComparisonChart();
    }

    /**
     * "Şube/ürün performans karşılaştırma grafiği" - re-aggregates the SAME
     * rows the table above already holds (branch_id/name, product_id/name,
     * total_price per branch+product pair) rather than a new endpoint -
     * everything a comparison needs is already in `lastReportRows`. Plain
     * CSS width-percentage bars rather than SVG - a horizontal bar list is
     * simpler as flexbox than as hand-rolled SVG, and it reads better with
     * long şube/ürün names than a vertical bar chart would (see
     * overview-panel.js's SVG line chart for the "no library" reasoning
     * this still follows).
     */
    function aggregateReportRows(rows, idKey, nameKey) {
        var totals = {};
        var order = [];

        rows.forEach(function (row) {
            var id = row[idKey];

            if (!(id in totals)) {
                totals[id] = { name: row[nameKey], total: 0 };
                order.push(id);
            }

            totals[id].total += row.total_price;
        });

        return order
            .map(function (id) {
                return totals[id];
            })
            .sort(function (a, b) {
                return b.total - a.total;
            })
            .slice(0, 10);
    }

    function renderComparisonChart() {
        if (lastReportRows.length === 0) {
            comparisonChart.hidden = true;
            return;
        }

        var items = comparisonMode === 'branch'
            ? aggregateReportRows(lastReportRows, 'branch_id', 'branch_name')
            : aggregateReportRows(lastReportRows, 'product_id', 'product_name');

        comparisonChart.hidden = false;
        comparisonChartHost.innerHTML = '';

        var max = items.reduce(function (acc, item) {
            return Math.max(acc, item.total);
        }, 0);

        items.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'scp-comparison-chart__row';

            var label = document.createElement('span');
            label.className = 'scp-comparison-chart__label';
            label.textContent = item.name;
            row.appendChild(label);

            var track = document.createElement('div');
            track.className = 'scp-comparison-chart__track';

            var bar = document.createElement('div');
            bar.className = 'scp-comparison-chart__bar';
            bar.style.width = (max > 0 ? (item.total / max * 100) : 0) + '%';
            track.appendChild(bar);
            row.appendChild(track);

            var value = document.createElement('span');
            value.className = 'scp-comparison-chart__value';
            value.textContent = formatMoney(item.total);
            row.appendChild(value);

            comparisonChartHost.appendChild(row);
        });
    }

    comparisonModeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            comparisonMode = button.getAttribute('data-scp-comparison-mode');
            comparisonModeButtons.forEach(function (btn) {
                btn.classList.toggle('scp-btn--active', btn === button);
            });
            renderComparisonChart();
        });
    });

    function loadReport() {
        var params = currentParams();
        params.set('format', 'json');

        apiFetch('reports/sales?' + params.toString()).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderRows(result.data);
        });
    }

    function downloadReport(format) {
        var params = currentParams();
        params.set('format', format);
        params.set('_wpnonce', scpPanelData.nonce);

        window.location.href = scpPanelData.restUrl + 'reports/sales?' + params.toString();
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

    if (scpPanelData.canViewAllBranches) {
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
        var warehouseBranchField = root.querySelector('[data-scp-warehouse-report-branch-field]');

        if (warehouseBranchField) {
            var warehouseBranchSelect = warehouseBranchField.querySelector('select');

            var allOption = document.createElement('option');
            allOption.value = '';
            allOption.textContent = scpPanelTextData.allBranches;
            warehouseBranchSelect.appendChild(allOption);

            var hqOption = document.createElement('option');
            hqOption.value = 'hq';
            hqOption.textContent = scpPanelTextData.hqBranch;
            warehouseBranchSelect.appendChild(hqOption);

            apiFetch('branches').then(function (result) {
                if (!result.ok) {
                    return;
                }

                result.data.forEach(function (branch) {
                    var option = document.createElement('option');
                    option.value = String(branch.id);
                    option.textContent = branch.name;
                    warehouseBranchSelect.appendChild(option);
                });
            });
        }

        var warehouseCurrentParams = function () {
            var formData = new FormData(warehouseForm);
            var params = new URLSearchParams();

            ['supplier_id', 'branch_id', 'from', 'to'].forEach(function (name) {
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
                    row.branch_name,
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

            setStatus(rows.length === 0 ? scpPanelTextData.noReportData : '');
        };

        var loadWarehouseReport = function () {
            var params = warehouseCurrentParams();
            params.set('format', 'json');

            apiFetch('reports/warehouse?' + params.toString()).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                renderWarehouseRows(result.data);
            });
        };

        var downloadWarehouseReport = function (format) {
            var params = warehouseCurrentParams();
            params.set('format', format);
            params.set('_wpnonce', scpPanelData.nonce);

            window.location.href = scpPanelData.restUrl + 'reports/warehouse?' + params.toString();
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
