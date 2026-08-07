/**
 * Veli's own past orders (/siparislerim) - see
 * plugin/seviye-commerce/src/Http/OrdersRestController.php's
 * seviye/v1/commerce/orders/mine endpoint. Mostly read-only: the one write
 * action is "İade Et" (renderReturnButton()), a self-service return of a
 * completed order within its 14-day window - the server (see
 * OrdersRestController::returnOrder()/OrderPresenter::canReturn()) is what
 * actually enforces both the ownership and the 14-day cutoff; `can_return`
 * here only decides the button's enabled/disabled state so a veli isn't
 * left clicking a button that can only ever 422.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-orders-panel');

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

    var statusEl = root.querySelector('[data-scp-orders-status]');
    var listEl = root.querySelector('[data-scp-orders-list]');
    var summaryToolbar = root.querySelector('[data-scp-spending-summary-toolbar]');
    var summaryYearSelect = root.querySelector('[data-scp-spending-summary-year]');
    var summaryButton = root.querySelector('[data-scp-spending-summary-button]');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function formatMoney(amount) {
        return Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            + ' TRY';
    }

    function metaRow(dl, label, value) {
        var dt = document.createElement('dt');
        dt.textContent = label;
        var dd = document.createElement('dd');
        dd.textContent = value;
        dl.appendChild(dt);
        dl.appendChild(dd);
    }

    function renderItemsTable(items) {
        var wrapper = document.createElement('div');
        wrapper.className = 'scp-table-wrapper';

        var table = document.createElement('table');
        table.className = 'scp-table';

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        [
            scpPanelTextData.orderItemProductLabel,
            scpPanelTextData.orderItemStudentLabel,
            scpPanelTextData.orderItemQuantityLabel,
            scpPanelTextData.orderItemUnitPriceLabel,
            scpPanelTextData.orderItemTaxLabel,
            scpPanelTextData.orderItemTotalLabel
        ].forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        items.forEach(function (item) {
            var row = document.createElement('tr');
            [
                item.name,
                item.student_name || scpPanelTextData.summaryNotSet,
                String(item.quantity),
                formatMoney(item.unit_price),
                formatMoney(item.line_tax),
                formatMoney(item.line_total)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        wrapper.appendChild(table);

        return wrapper;
    }

    /**
     * "Sipariş durumu için görsel zaman çizelgesi" - replaces the old
     * single conditional fulfillment badge with all three steps always
     * visible, so a veli sees exactly where an order stands at a glance
     * rather than inferring "no badge = still preparing".
     */
    function renderFulfillmentTimeline(order) {
        var stages = ['preparing', 'shipped', 'delivered'];
        var labels = [
            scpPanelTextData.fulfillmentStepPreparing,
            scpPanelTextData.fulfillmentStepShipped,
            scpPanelTextData.fulfillmentStepDelivered
        ];
        var currentIndex = stages.indexOf(order.fulfillment_status);

        var list = document.createElement('ol');
        list.className = 'scp-order-timeline';

        labels.forEach(function (label, index) {
            var state = index < currentIndex ? 'done' : (index === currentIndex ? 'active' : 'upcoming');

            var step = document.createElement('li');
            step.className = 'scp-order-timeline__step scp-order-timeline__step--' + state;

            var dot = document.createElement('span');
            dot.className = 'scp-order-timeline__dot';
            step.appendChild(dot);

            var stepLabel = document.createElement('span');
            stepLabel.className = 'scp-order-timeline__label';
            stepLabel.textContent = label;
            step.appendChild(stepLabel);

            list.appendChild(step);
        });

        return list;
    }

    /**
     * "Sipariş numarası kopyalama düğmesi" - `navigator.clipboard` yoksa
     * (çok eski bir tarayıcı, http üzerinden - Clipboard API yalnızca
     * güvenli bağlamlarda çalışır) düğme basitçe render edilmiyor, sessiz
     * bir no-op yerine kafa karıştırıcı bir "çalışmıyor" düğmesi
     * bırakmamak için.
     */
    function renderOrderNumberCopyButton(order) {
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            return null;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-order-copy-btn';
        button.textContent = scpPanelTextData.orderNumberCopyLabel;
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(String(order.number)).then(function () {
                window.scpToast(scpPanelTextData.orderNumberCopied, 'success');
            });
        });

        return button;
    }

    function renderOrderPrintButton(order) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-order-copy-btn';
        button.textContent = scpPanelTextData.orderPrintLabel;
        button.addEventListener('click', function () {
            window.scpPrintOrder(order, scpPanelTextData, formatMoney);
        });

        return button;
    }

    function returnOrder(order) {
        if (!window.confirm(scpPanelTextData.confirmReturnOrder)) {
            return;
        }

        apiFetch('commerce/orders/mine/' + order.id + '/return', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.orderReturned);
            loadOrders();
        });
    }

    /**
     * Only rendered for a completed order at all - a pending/cancelled/
     * already-fully-refunded order has nothing to return. `can_return ===
     * false` (window expired, or nothing left to refund) still renders the
     * button so its presence doesn't just vanish without explanation, but
     * disables it with a title tooltip instead.
     */
    function renderReturnButton(order) {
        if (order.status !== 'completed') {
            return null;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-btn scp-btn--danger scp-btn--small';
        button.textContent = scpPanelTextData.returnOrderAction;

        if (!order.can_return) {
            button.disabled = true;
            button.title = scpPanelTextData.returnWindowExpiredHint;
        } else {
            button.addEventListener('click', function () {
                returnOrder(order);
            });
        }

        return button;
    }

    function renderOrder(order) {
        var card = document.createElement('div');
        card.className = 'scp-card scp-card--nested';

        var header = document.createElement('div');
        header.className = 'scp-card__header';

        var titleGroup = document.createElement('div');
        titleGroup.className = 'scp-card__header-title';

        var title = document.createElement('h3');
        title.textContent = scpPanelTextData.orderNumberLabel + ' #' + order.number;
        titleGroup.appendChild(title);

        var copyButton = renderOrderNumberCopyButton(order);

        if (copyButton) {
            titleGroup.appendChild(copyButton);
        }

        titleGroup.appendChild(renderOrderPrintButton(order));

        header.appendChild(titleGroup);

        var badge = document.createElement('span');
        badge.className = 'scp-badge ' + (order.status === 'completed' ? 'scp-badge--active' : 'scp-badge--info');
        badge.textContent = order.status_label;
        header.appendChild(badge);

        card.appendChild(header);
        card.appendChild(renderFulfillmentTimeline(order));

        var meta = document.createElement('dl');
        meta.className = 'scp-summary-list';
        metaRow(meta, scpPanelTextData.orderDateLabel, order.date || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderPaymentMethodLabel, order.payment_method_title || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderSubtotalLabel, formatMoney(order.subtotal));
        metaRow(meta, scpPanelTextData.orderTaxLabel, formatMoney(order.total_tax));
        metaRow(meta, scpPanelTextData.orderTotalLabel, formatMoney(order.total));

        if (order.tracking_number) {
            metaRow(meta, scpPanelTextData.orderTrackingNumberLabel, order.tracking_number);
        }

        if (order.shipped_at) {
            metaRow(meta, scpPanelTextData.orderShippedAtLabel, order.shipped_at);
        }

        if (order.delivered_at) {
            metaRow(meta, scpPanelTextData.orderDeliveredAtLabel, order.delivered_at);
        }

        card.appendChild(meta);

        card.appendChild(renderItemsTable(order.items));

        var returnButton = renderReturnButton(order);

        if (returnButton) {
            var actions = document.createElement('div');
            actions.className = 'scp-form__actions';
            actions.appendChild(returnButton);
            card.appendChild(actions);
        }

        return card;
    }

    /**
     * "Yıllık harcama özeti" - `commerce/orders/mine` zaten TÜM sipariş
     * geçmişini tek seferde döndürüyor (limit=-1, bkz.
     * OrdersRestController::mine()), bu yüzden yıl bazlı özet için YENİ
     * bir REST endpoint'e gerek yok - bu fonksiyon zaten bellekte olan
     * `orders` dizisini istemci tarafında filtreleyip topluyor. `İptal
     * Edildi` durumundaki siparişler hariç tutuluyor - gerçekleşmemiş bir
     * harcama, "yıllık harcama"nın parçası değil.
     */
    function buildSpendingSummary(orders, year) {
        var summary = { orderCount: 0, subtotal: 0, tax: 0, total: 0, byStudent: {} };

        orders.forEach(function (order) {
            if (!order.date || order.date.slice(0, 4) !== year || order.status === 'cancelled') {
                return;
            }

            summary.orderCount += 1;
            summary.subtotal += Number(order.subtotal);
            summary.tax += Number(order.total_tax);
            summary.total += Number(order.total);

            order.items.forEach(function (item) {
                var studentName = item.student_name || scpPanelTextData.summaryNotSet;
                summary.byStudent[studentName] = (summary.byStudent[studentName] || 0) + Number(item.line_total);
            });
        });

        return summary;
    }

    function populateSpendingSummaryToolbar(orders) {
        var years = [];

        orders.forEach(function (order) {
            var year = order.date ? order.date.slice(0, 4) : null;

            if (year && years.indexOf(year) === -1) {
                years.push(year);
            }
        });

        if (years.length === 0) {
            summaryToolbar.hidden = true;
            return;
        }

        years.sort().reverse();

        summaryYearSelect.innerHTML = '';
        years.forEach(function (year) {
            var option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            summaryYearSelect.appendChild(option);
        });

        summaryToolbar.hidden = false;
    }

    var loadedOrders = [];

    summaryButton.addEventListener('click', function () {
        var year = summaryYearSelect.value;
        var summary = buildSpendingSummary(loadedOrders, year);

        window.scpPrintSpendingSummary(summary, year, scpPanelTextData, formatMoney);
    });

    function loadOrders() {
        apiFetch('commerce/orders/mine').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            listEl.innerHTML = '';
            loadedOrders = result.data;
            populateSpendingSummaryToolbar(loadedOrders);

            if (result.data.length === 0) {
                setStatus('');
                listEl.appendChild(renderEmptyOrdersState());
                return;
            }

            setStatus('');
            result.data.forEach(function (order) {
                listEl.appendChild(renderOrder(order));
            });
        });
    }

    /**
     * "Boş durum illüstrasyonu" - `.scp-empty-state` (panel.css, bölüm
     * 169) bu ana kadar hiçbir yerde KULLANILMIYORDU; bu ilk somut
     * uygulaması. İkon "orders" module tile glifiyle AYNI hand-rolled SVG
     * (bkz. templates/partials/icon.php'nin kendi "orders" yolu) - sabit,
     * kullanıcı girdisi içermeyen bir sabit dize olduğu için innerHTML
     * güvenli.
     */
    function renderEmptyOrdersState() {
        var wrapper = document.createElement('div');
        wrapper.className = 'scp-empty-state';

        var iconWrap = document.createElement('div');
        iconWrap.className = 'scp-empty-state__icon';
        iconWrap.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M9 3v4M15 3v4M4 10h16"/></svg>';
        wrapper.appendChild(iconWrap);

        var heading = document.createElement('h3');
        heading.textContent = scpPanelTextData.noOrdersHeading || scpPanelTextData.noOrders;
        wrapper.appendChild(heading);

        var message = document.createElement('p');
        message.textContent = scpPanelTextData.noOrders;
        wrapper.appendChild(message);

        if (scpPanelTextData.shopUrl) {
            var link = document.createElement('a');
            link.className = 'scp-btn';
            link.href = scpPanelTextData.shopUrl;
            link.textContent = scpPanelTextData.noOrdersShopLink || scpPanelTextData.noOrdersHeading;
            wrapper.appendChild(link);
        }

        return wrapper;
    }

    loadOrders();
})();
