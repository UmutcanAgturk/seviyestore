/**
 * "Genel Bakış" (overview dashboard) card, /admin and /sube -
 * scp_view_reports (HQ, sees branch_breakdown) or scp_view_own_reports
 * (Şube Müdürü, no branch_breakdown - already their own single branch).
 * Reads seviye/v1/reports/overview
 * (plugin/seviye-reports/src/Http/OverviewRestController.php). Read-only,
 * no form - just today/week/month stat tiles, top products, and (HQ only)
 * a per-branch breakdown, all over the trailing 30 days.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-overview-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-overview-status]');
    var statsEl = root.querySelector('[data-scp-overview-stats]');
    var productsTable = root.querySelector('[data-scp-overview-products-table]');
    var productsBody = root.querySelector('[data-scp-overview-products-body]');
    var branchSection = root.querySelector('[data-scp-overview-branch-section]');
    var branchesTable = root.querySelector('[data-scp-overview-branches-table]');
    var branchesBody = root.querySelector('[data-scp-overview-branches-body]');

    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function formatMoney(amount) {
        return Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            + ' TRY';
    }

    function applyPeriod(prefix, period) {
        root.querySelector('[data-scp-overview-' + prefix + '-total]').textContent = formatMoney(period.total);
        root.querySelector('[data-scp-overview-' + prefix + '-count]').textContent = period.order_count
            + ' ' + scpPanelText.overviewOrdersLabel;
    }

    function renderProducts(products) {
        productsBody.innerHTML = '';
        productsTable.hidden = products.length === 0;

        products.forEach(function (product) {
            var row = document.createElement('tr');
            [product.name, String(product.quantity), formatMoney(product.revenue)].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });
            productsBody.appendChild(row);
        });
    }

    function renderBranches(branches) {
        branchSection.hidden = branches.length === 0;
        branchesBody.innerHTML = '';
        branchesTable.hidden = branches.length === 0;

        branches.forEach(function (branch) {
            var row = document.createElement('tr');
            [branch.branch_name, String(branch.order_count), formatMoney(branch.total)].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });
            branchesBody.appendChild(row);
        });
    }

    function load() {
        apiFetch('reports/overview').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            setStatus('');
            statsEl.hidden = false;
            applyPeriod('today', result.data.today);
            applyPeriod('week', result.data.week);
            applyPeriod('month', result.data.month);
            renderProducts(result.data.top_products);
            renderBranches(result.data.branch_breakdown);
        });
    }

    load();
})();
