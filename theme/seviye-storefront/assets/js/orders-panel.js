/**
 * Veli's own past orders (/siparislerim) - see
 * plugin/seviye-commerce/src/Http/OrdersRestController.php's
 * seviye/v1/commerce/orders/mine endpoint. Read-only, no forms: this page
 * only ever shows the current user's own order history.
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

    var statusEl = root.querySelector('[data-scp-orders-status]');
    var listEl = root.querySelector('[data-scp-orders-list]');
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
            scpPanelText.orderItemProductLabel,
            scpPanelText.orderItemStudentLabel,
            scpPanelText.orderItemQuantityLabel,
            scpPanelText.orderItemUnitPriceLabel,
            scpPanelText.orderItemTaxLabel,
            scpPanelText.orderItemTotalLabel
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
                item.student_name || scpPanelText.summaryNotSet,
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

    function renderOrder(order) {
        var card = document.createElement('div');
        card.className = 'scp-card scp-card--nested';

        var header = document.createElement('div');
        header.className = 'scp-card__header';

        var title = document.createElement('h3');
        title.textContent = scpPanelText.orderNumberLabel + ' #' + order.number;
        header.appendChild(title);

        var badge = document.createElement('span');
        badge.className = 'scp-badge ' + (order.status === 'completed' ? 'scp-badge--active' : 'scp-badge--info');
        badge.textContent = order.status_label;
        header.appendChild(badge);

        if (order.fulfillment_status !== 'preparing') {
            var fulfillmentBadge = document.createElement('span');
            fulfillmentBadge.className = 'scp-badge '
                + (order.fulfillment_status === 'delivered' ? 'scp-badge--active' : 'scp-badge--info');
            fulfillmentBadge.textContent = order.fulfillment_status_label;
            header.appendChild(fulfillmentBadge);
        }

        card.appendChild(header);

        var meta = document.createElement('dl');
        meta.className = 'scp-summary-list';
        metaRow(meta, scpPanelText.orderDateLabel, order.date || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderPaymentMethodLabel, order.payment_method_title || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderSubtotalLabel, formatMoney(order.subtotal));
        metaRow(meta, scpPanelText.orderTaxLabel, formatMoney(order.total_tax));
        metaRow(meta, scpPanelText.orderTotalLabel, formatMoney(order.total));

        if (order.tracking_number) {
            metaRow(meta, scpPanelText.orderTrackingNumberLabel, order.tracking_number);
        }

        if (order.shipped_at) {
            metaRow(meta, scpPanelText.orderShippedAtLabel, order.shipped_at);
        }

        if (order.delivered_at) {
            metaRow(meta, scpPanelText.orderDeliveredAtLabel, order.delivered_at);
        }

        card.appendChild(meta);

        card.appendChild(renderItemsTable(order.items));

        return card;
    }

    function loadOrders() {
        apiFetch('commerce/orders/mine').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            listEl.innerHTML = '';

            if (result.data.length === 0) {
                setStatus(scpPanelText.noOrders);
                return;
            }

            setStatus('');
            result.data.forEach(function (order) {
                listEl.appendChild(renderOrder(order));
            });
        });
    }

    loadOrders();
})();
