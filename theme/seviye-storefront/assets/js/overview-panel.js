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
                return Math.round(value) + ' ' + scpPanelTextData.overviewOrdersLabel;
            });
            return;
        }

        countEl.textContent = period.order_count + ' ' + scpPanelTextData.overviewOrdersLabel;
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
     * ciro/sipariş sayısı is shown via a mouse-follow crosshair + tooltip
     * (see wireTrendInteraction() below) instead of a native SVG <title>
     * (which only ever fired for the exact 3px a data point's own <circle>
     * covered, and only after a hover delay) - "mouse ile gezerken
     * grafiğin mouseye göre hareket ve verileri göstermesi".
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
        svg.setAttribute('aria-label', scpPanelTextData.trendChartLabel);

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
            svg.appendChild(circle);
        });

        var crosshair = document.createElementNS(svgNs, 'line');
        crosshair.setAttribute('class', 'scp-trend-chart__crosshair');
        crosshair.setAttribute('y1', String(paddingY));
        crosshair.setAttribute('y2', String(baselineY));
        crosshair.style.display = 'none';
        svg.appendChild(crosshair);

        var hoverPoint = document.createElementNS(svgNs, 'circle');
        hoverPoint.setAttribute('class', 'scp-trend-chart__hover-point');
        hoverPoint.setAttribute('r', '4');
        hoverPoint.style.display = 'none';
        svg.appendChild(hoverPoint);

        // Transparent rect spanning the whole chart, so pointer tracking
        // isn't limited to the exact pixels a 3px <circle> covers.
        var capture = document.createElementNS(svgNs, 'rect');
        capture.setAttribute('class', 'scp-trend-chart__capture');
        capture.setAttribute('x', '0');
        capture.setAttribute('y', '0');
        capture.setAttribute('width', String(width));
        capture.setAttribute('height', String(height));
        svg.appendChild(capture);

        trendSvgHost.appendChild(svg);

        var tooltip = document.createElement('div');
        tooltip.className = 'scp-trend-chart__tooltip';
        tooltip.hidden = true;
        trendSvgHost.appendChild(tooltip);

        wireTrendInteraction(svg, capture, crosshair, hoverPoint, tooltip, coords, width, height);
    }

    /**
     * Tracks the pointer across the capture <rect> and snaps the
     * crosshair/highlighted point/tooltip to the NEAREST of the (up to 30)
     * daily coordinates - there's no continuous data to interpolate
     * between, only one value per day, so "following the mouse" means
     * always showing whichever day's point the cursor is currently
     * closest to.
     */
    function wireTrendInteraction(svg, capture, crosshair, hoverPoint, tooltip, coords, viewBoxWidth, viewBoxHeight) {
        function pointerToViewBoxX(clientX) {
            var rect = svg.getBoundingClientRect();
            var ratio = rect.width > 0 ? (clientX - rect.left) / rect.width : 0;
            return ratio * viewBoxWidth;
        }

        function nearestCoord(viewBoxX) {
            var nearest = coords[0];
            var nearestDistance = Infinity;

            coords.forEach(function (coord) {
                var distance = Math.abs(coord.x - viewBoxX);

                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearest = coord;
                }
            });

            return nearest;
        }

        function showAt(coord) {
            crosshair.setAttribute('x1', coord.x.toFixed(1));
            crosshair.setAttribute('x2', coord.x.toFixed(1));
            crosshair.style.display = '';

            hoverPoint.setAttribute('cx', coord.x.toFixed(1));
            hoverPoint.setAttribute('cy', coord.y.toFixed(1));
            hoverPoint.style.display = '';

            tooltip.innerHTML = '';
            var dateEl = document.createElement('strong');
            dateEl.textContent = formatShortDate(coord.point.date);
            var detailEl = document.createElement('span');
            detailEl.textContent = formatMoney(coord.point.total)
                + ' – ' + coord.point.order_count + ' ' + scpPanelTextData.overviewOrdersLabel;
            tooltip.appendChild(dateEl);
            tooltip.appendChild(detailEl);
            tooltip.hidden = false;

            // The tooltip is a plain HTML element (not SVG), so its
            // position is in the host's own pixel box, not the SVG's
            // (possibly non-uniformly stretched, preserveAspectRatio="none")
            // viewBox units - convert coord.x/y back to a 0..1 ratio first.
            var hostWidth = svg.clientWidth;
            var hostHeight = svg.clientHeight;
            tooltip.style.left = ((coord.x / viewBoxWidth) * hostWidth) + 'px';
            tooltip.style.top = ((coord.y / viewBoxHeight) * hostHeight) + 'px';
        }

        function hide() {
            crosshair.style.display = 'none';
            hoverPoint.style.display = 'none';
            tooltip.hidden = true;
        }

        capture.addEventListener('mousemove', function (event) {
            showAt(nearestCoord(pointerToViewBoxX(event.clientX)));
        });
        capture.addEventListener('mouseleave', hide);

        // Touch: a finger drag across the chart tracks the same way.
        capture.addEventListener('touchmove', function (event) {
            if (event.touches.length === 0) {
                return;
            }

            event.preventDefault();
            showAt(nearestCoord(pointerToViewBoxX(event.touches[0].clientX)));
        }, { passive: false });
        capture.addEventListener('touchend', hide);
    }

    function load() {
        apiFetch('reports/overview').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
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

    /**
     * "Dashboard widget sürükle-bırak yeniden sıralama" - dört widget
     * sarmalayıcısı (bkz. templates/zone.php'nin `data-scp-dashboard-widget`
     * işaretlemesi) kendi aralarında sürüklenip bırakılabiliyor, yeni sıra
     * `localStorage`'a kaydediliyor ve sonraki ziyarette veri henüz
     * yüklenmeden ÖNCE uygulanıyor (widget'lar boşken - `hidden` - yer
     * değiştirmek görsel bir sıçramaya yol açmıyor). Sunucu tarafında
     * hiçbir şey saklanmıyor - bu tamamen bu tarayıcıya özel bir tercih,
     * tıpkı Manuel karanlık mod anahtarının kendi `localStorage.scpTheme`'i
     * gibi.
     */
    function initDashboardWidgetReorder() {
        var container = root.querySelector('[data-scp-dashboard-widgets]');

        if (!container) {
            return;
        }

        var STORAGE_KEY = 'scpOverviewWidgetOrder';
        var draggingEl = null;

        function applyStoredOrder() {
            var stored = null;

            try {
                stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            } catch (e) {
                stored = null;
            }

            if (!Array.isArray(stored)) {
                return;
            }

            stored.forEach(function (widgetId) {
                var widget = container.querySelector('[data-scp-dashboard-widget="' + widgetId + '"]');

                if (widget) {
                    container.appendChild(widget);
                }
            });
        }

        function persistOrder() {
            var order = Array.prototype.map.call(
                container.querySelectorAll('[data-scp-dashboard-widget]'),
                function (widget) {
                    return widget.getAttribute('data-scp-dashboard-widget');
                }
            );

            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(order));
            } catch (e) {
                // Privacy-mode/iframe contexts can block localStorage - the
                // reorder still works for the rest of this page view.
            }
        }

        function elementAfterPointer(y) {
            var widgets = Array.prototype.slice.call(
                container.querySelectorAll('[data-scp-dashboard-widget]:not(.scp-dashboard-widget--dragging)')
            );

            return widgets.reduce(function (closest, widget) {
                var box = widget.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;

                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: widget };
                }

                return closest;
            }, { offset: -Infinity, element: null }).element;
        }

        applyStoredOrder();

        container.querySelectorAll('.scp-dashboard-widget__handle').forEach(function (handle) {
            handle.addEventListener('dragstart', function (event) {
                draggingEl = handle.closest('[data-scp-dashboard-widget]');
                draggingEl.classList.add('scp-dashboard-widget--dragging');
                event.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', function () {
                if (draggingEl) {
                    draggingEl.classList.remove('scp-dashboard-widget--dragging');
                }

                draggingEl = null;
                persistOrder();
            });
        });

        container.addEventListener('dragover', function (event) {
            if (!draggingEl) {
                return;
            }

            event.preventDefault();

            var afterElement = elementAfterPointer(event.clientY);

            if (afterElement === null) {
                container.appendChild(draggingEl);
            } else {
                container.insertBefore(draggingEl, afterElement);
            }
        });
    }

    load();
    initDashboardWidgetReorder();
})();
