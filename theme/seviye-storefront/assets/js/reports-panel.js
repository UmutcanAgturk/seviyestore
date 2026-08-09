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
    var periodDeltaEl = root.querySelector('[data-scp-report-period-delta]');
    var periodDeltaTotalEl = root.querySelector('[data-scp-report-period-delta-total]');
    var periodDeltaBadgeEl = root.querySelector('[data-scp-report-period-delta-badge]');
    var periodDeltaHintEl = root.querySelector('[data-scp-report-period-delta-hint]');
    var comparisonChart = root.querySelector('[data-scp-comparison-chart]');
    var comparisonChartHost = root.querySelector('[data-scp-comparison-chart-host]');
    var comparisonModeButtons = root.querySelectorAll('[data-scp-comparison-mode]');
    var comparisonViewButtons = root.querySelectorAll('[data-scp-comparison-view]');
    var comparisonHeatmapWrap = root.querySelector('[data-scp-comparison-heatmap]');
    var comparisonHeatmapTable = root.querySelector('[data-scp-comparison-heatmap-table]');
    var comparisonMode = 'branch';
    var comparisonView = 'bar';
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

        comparisonChart.hidden = false;

        if (comparisonView === 'heatmap') {
            comparisonChartHost.hidden = true;
            comparisonHeatmapWrap.hidden = false;
            renderComparisonHeatmap();
            return;
        }

        comparisonChartHost.hidden = false;
        comparisonHeatmapWrap.hidden = true;

        var items = comparisonMode === 'branch'
            ? aggregateReportRows(lastReportRows, 'branch_id', 'branch_name')
            : aggregateReportRows(lastReportRows, 'product_id', 'product_name');

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

    /**
     * "Şube performans karşılaştırmasında heatmap" - AYNI `lastReportRows`
     * (branch_id/name + product_id/name + total_price per pair) yeniden
     * kullanılıyor, yeni bir REST çağrısı yok; tek fark çubuk grafiğin
     * TEK bir boyutu (ya şube ya ürün) aggregate etmesine karşılık
     * heatmap'in İKİ boyutu AYNI ANDA bir matrise dökmesi - bu yüzden
     * ayrı bir görselleştirme fonksiyonu, `comparisonMode`'dan (Şubelere/
     * Ürünlere Göre) bağımsız. Satır/sütun sayısı ilk 8 şube × ilk 8 ürünle
     * sınırlı (toplam ciroya göre) - üstel şekilde büyüyen bir tabloyu
     * okunaksız kılmamak için, tıpkı aggregateReportRows()'un kendi
     * "ilk 10" sınırı gibi. Hücre arka plan opaklığı o hücrenin toplamının
     * MATRİSTEKİ en yüksek hücreye oranı - satır/sütun bazlı değil, tüm
     * matris için TEK bir ölçek, aksi halde düşük hacimli bir şube/ürün
     * kendi satırı/sütunu içinde yanıltıcı biçimde "koyu" görünürdü.
     */
    function renderComparisonHeatmap() {
        var branchTotals = {};
        var branchNames = {};
        var productTotals = {};
        var productNames = {};
        var cellTotals = {};

        lastReportRows.forEach(function (row) {
            branchNames[row.branch_id] = row.branch_name;
            branchTotals[row.branch_id] = (branchTotals[row.branch_id] || 0) + row.total_price;

            productNames[row.product_id] = row.product_name;
            productTotals[row.product_id] = (productTotals[row.product_id] || 0) + row.total_price;

            var key = row.branch_id + ':' + row.product_id;
            cellTotals[key] = (cellTotals[key] || 0) + row.total_price;
        });

        function topIdsByTotal(totals) {
            return Object.keys(totals)
                .sort(function (a, b) {
                    return totals[b] - totals[a];
                })
                .slice(0, 8);
        }

        var topBranchIds = topIdsByTotal(branchTotals);
        var topProductIds = topIdsByTotal(productTotals);

        var overallMax = 0;
        topBranchIds.forEach(function (branchId) {
            topProductIds.forEach(function (productId) {
                overallMax = Math.max(overallMax, cellTotals[branchId + ':' + productId] || 0);
            });
        });

        comparisonHeatmapTable.innerHTML = '';

        var headRow = document.createElement('tr');
        headRow.appendChild(document.createElement('th'));
        topProductIds.forEach(function (productId) {
            var th = document.createElement('th');
            th.textContent = productNames[productId];
            headRow.appendChild(th);
        });
        comparisonHeatmapTable.appendChild(headRow);

        topBranchIds.forEach(function (branchId) {
            var row = document.createElement('tr');

            var rowHead = document.createElement('th');
            rowHead.textContent = branchNames[branchId];
            row.appendChild(rowHead);

            topProductIds.forEach(function (productId) {
                var cell = document.createElement('td');
                var value = cellTotals[branchId + ':' + productId] || 0;
                var intensity = overallMax > 0 ? value / overallMax : 0;

                cell.className = 'scp-heatmap__cell';
                cell.style.backgroundColor = 'rgba(37, 99, 235, ' + (0.08 + intensity * 0.72) + ')';
                cell.textContent = value > 0 ? formatMoney(value) : '–';
                cell.title = branchNames[branchId] + ' × ' + productNames[productId] + ': ' + formatMoney(value);

                row.appendChild(cell);
            });

            comparisonHeatmapTable.appendChild(row);
        });
    }

    comparisonViewButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            comparisonView = button.getAttribute('data-scp-comparison-view');
            comparisonViewButtons.forEach(function (btn) {
                btn.classList.toggle('is-active', btn === button);
            });
            renderComparisonChart();
        });
    });

    comparisonModeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            comparisonMode = button.getAttribute('data-scp-comparison-mode');
            comparisonModeButtons.forEach(function (btn) {
                btn.classList.toggle('scp-btn--active', btn === button);
            });
            renderComparisonChart();
        });
    });

    function sumTotal(rows) {
        return rows.reduce(function (sum, row) {
            return sum + Number(row.total_price);
        }, 0);
    }

    /**
     * "Raporlarda önceki döneme göre yüzdesel değişim rozeti" - yalnızca
     * form'un `from`/`to` alanları İKİSİ de doldurulmuşsa anlamlı bir
     * "önceki dönem" var demektir (filtresiz/açık uçlu bir sorguda
     * karşılaştırılacak eşdeğer bir önceki aralık tanımsız). Önceki dönem,
     * AYNI gün sayısında, `from`'un HEMEN öncesinde biten ikinci bir
     * `reports/sales` isteğiyle (aynı ürün/kategori/şube filtreleriyle)
     * çekiliyor - yeni bir REST ucu yok, yalnızca tarihleri kaydırılmış
     * AYNI endpoint'e ikinci bir çağrı.
     */
    function loadPeriodDelta(params, currentTotal) {
        var from = params.get('from');
        var to = params.get('to');

        if (!from || !to) {
            periodDeltaEl.hidden = true;
            return;
        }

        var fromDate = new Date(from + 'T00:00:00');
        var toDate = new Date(to + 'T00:00:00');
        var spanDays = Math.max(1, Math.round((toDate - fromDate) / 86400000) + 1);

        var prevTo = new Date(fromDate);
        prevTo.setDate(prevTo.getDate() - 1);
        var prevFrom = new Date(prevTo);
        prevFrom.setDate(prevFrom.getDate() - (spanDays - 1));

        function isoDate(date) {
            return date.getFullYear() + '-'
                + String(date.getMonth() + 1).padStart(2, '0') + '-'
                + String(date.getDate()).padStart(2, '0');
        }

        var prevParams = new URLSearchParams(params);
        prevParams.set('from', isoDate(prevFrom));
        prevParams.set('to', isoDate(prevTo));

        apiFetch('reports/sales?' + prevParams.toString()).then(function (result) {
            if (!result.ok) {
                periodDeltaEl.hidden = true;
                return;
            }

            var previousTotal = sumTotal(result.data);

            periodDeltaEl.hidden = false;
            periodDeltaTotalEl.textContent = formatMoney(currentTotal);
            periodDeltaHintEl.textContent = scpPanelTextData.reportPeriodPreviousHint
                .replace('%1$s', isoDate(prevFrom))
                .replace('%2$s', isoDate(prevTo));

            if (previousTotal <= 0) {
                periodDeltaBadgeEl.textContent = '';
                periodDeltaBadgeEl.className = 'scp-badge';
                return;
            }

            var percentChange = Math.round(((currentTotal - previousTotal) / previousTotal) * 1000) / 10;
            var isPositive = percentChange >= 0;
            periodDeltaBadgeEl.textContent = (isPositive ? '+' : '') + percentChange + '%';
            periodDeltaBadgeEl.className = 'scp-badge ' + (isPositive ? 'scp-badge--positive' : 'scp-badge--negative');
        });
    }

    function loadReport() {
        var params = currentParams();
        params.set('format', 'json');

        apiFetch('reports/sales?' + params.toString()).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderRows(result.data);
            loadPeriodDelta(params, sumTotal(result.data));
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
