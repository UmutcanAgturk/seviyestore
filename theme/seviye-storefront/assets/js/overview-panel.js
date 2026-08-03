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
    var trendEl = root.querySelector('[data-scp-overview-trend]');
    var trendSvgHost = root.querySelector('[data-scp-overview-trend-chart]');
    var trendFromEl = root.querySelector('[data-scp-overview-trend-from]');
    var trendToEl = root.querySelector('[data-scp-overview-trend-to]');

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

        var countEl = root.querySelector('[data-scp-overview-' + prefix + '-count]');

        if (typeof window.scpAnimateCounter === 'function') {
            window.scpAnimateCounter(countEl, period.order_count, function (value) {
                return Math.round(value) + ' ' + scpPanelText.overviewOrdersLabel;
            });
            return;
        }

        countEl.textContent = period.order_count + ' ' + scpPanelText.overviewOrdersLabel;
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

    function formatShortDate(isoDate) {
        var parts = isoDate.split('-');
        return parts[2] + '.' + parts[1];
    }

    /**
     * A hand-rolled SVG line+area chart rather than a charting library - the
     * same "avoid a heavy dependency for a narrow, well-understood need"
     * reasoning XlsxExporter documents for its own zip writer (see
     * docs/ARCHITECTURE.md bölüm 16): 30 points, one line, is not worth a
     * bundled dependency. No axes/gridlines by design (this is a compact
     * trend indicator, not an analytical chart) - each point's exact date/
     * ciro/sipariş sayısı is available via its native SVG <title> hover
     * tooltip, and the two range labels below anchor the endpoints.
     */
    function renderTrend(points) {
        if (!points || points.length === 0) {
            trendEl.hidden = true;
            return;
        }

        trendEl.hidden = false;
        trendSvgHost.innerHTML = '';
        trendFromEl.textContent = formatShortDate(points[0].date);
        trendToEl.textContent = formatShortDate(points[points.length - 1].date);

        var width = 600;
        var height = 160;
        var paddingX = 8;
        var paddingY = 16;
        var svgNs = 'http://www.w3.org/2000/svg';

        var max = points.reduce(function (acc, p) {
            return Math.max(acc, p.total);
        }, 0);

        var stepX = points.length > 1 ? (width - paddingX * 2) / (points.length - 1) : 0;
        var scaleY = max > 0 ? (height - paddingY * 2) / max : 0;
        var baselineY = height - paddingY;

        var coords = points.map(function (point, index) {
            return {
                x: paddingX + stepX * index,
                y: baselineY - point.total * scaleY,
                point: point,
            };
        });

        var linePath = coords.map(function (coord, index) {
            return (index === 0 ? 'M' : 'L') + coord.x.toFixed(1) + ',' + coord.y.toFixed(1);
        }).join(' ');

        var lastCoord = coords[coords.length - 1];
        var areaPath = linePath
            + ' L' + lastCoord.x.toFixed(1) + ',' + baselineY
            + ' L' + coords[0].x.toFixed(1) + ',' + baselineY
            + ' Z';

        var svg = document.createElementNS(svgNs, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.setAttribute('class', 'scp-trend-chart__svg');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', scpPanelText.trendChartLabel);

        var area = document.createElementNS(svgNs, 'path');
        area.setAttribute('d', areaPath);
        area.setAttribute('class', 'scp-trend-chart__area');
        svg.appendChild(area);

        var line = document.createElementNS(svgNs, 'path');
        line.setAttribute('d', linePath);
        line.setAttribute('class', 'scp-trend-chart__line');
        svg.appendChild(line);

        coords.forEach(function (coord) {
            var circle = document.createElementNS(svgNs, 'circle');
            circle.setAttribute('cx', coord.x.toFixed(1));
            circle.setAttribute('cy', coord.y.toFixed(1));
            circle.setAttribute('r', '3');
            circle.setAttribute('class', 'scp-trend-chart__point');

            var title = document.createElementNS(svgNs, 'title');
            title.textContent = coord.point.date + ': ' + formatMoney(coord.point.total)
                + ' (' + coord.point.order_count + ' ' + scpPanelText.overviewOrdersLabel + ')';
            circle.appendChild(title);

            svg.appendChild(circle);
        });

        trendSvgHost.appendChild(svg);
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
            renderTrend(result.data.daily_trend);
        });
    }

    load();
})();
