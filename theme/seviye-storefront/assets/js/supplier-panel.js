/**
 * "Tedarikçi portalı" (/tedarikci) - a supplier's own purchase orders. See
 * plugin/seviye-depo/src/Http/PurchaseOrdersRestController.php's /mine and
 * /{id}/mark-shipped endpoints. Read-only except for "gönderildi" - the
 * supplier never edits quantities/items, only the school's own mal kabul
 * flow (admin-orders-panel.js) does that.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-supplier-panel');

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

    var statusEl = root.querySelector('[data-scp-supplier-status]');
    var tableBody = root.querySelector('[data-scp-supplier-orders-body]');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function statusLabel(status) {
        return scpPanelText['poStatus_' + status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'completed') {
            return 'scp-badge--active';
        }

        if (status === 'cancelled') {
            return 'scp-badge--inactive';
        }

        if (status === 'sent' || status === 'partially_received') {
            return 'scp-badge--warning';
        }

        return 'scp-badge--info';
    }

    function itemsSummary(items) {
        var totalOrdered = items.reduce(function (sum, item) {
            return sum + item.quantity_ordered;
        }, 0);

        return items.length + ' ' + scpPanelTextData.supplierItemsLabel + ' (' + totalOrdered + ' '
            + scpPanelTextData.supplierUnitsLabel + ')';
    }

    function canMarkShipped(order) {
        return (order.status === 'sent' || order.status === 'partially_received') && !order.supplier_shipped_at;
    }

    function renderOrders(orders) {
        tableBody.innerHTML = '';

        if (orders.length === 0) {
            setStatus(scpPanelTextData.supplierNoOrders);
            return;
        }

        setStatus('');

        orders.forEach(function (order) {
            var row = document.createElement('tr');

            var codeCell = document.createElement('td');
            codeCell.textContent = order.code;
            row.appendChild(codeCell);

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'scp-badge ' + statusBadgeClass(order.status);
            badge.textContent = statusLabel(order.status);
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var dateCell = document.createElement('td');
            dateCell.textContent = order.expected_date || '—';
            row.appendChild(dateCell);

            var shippedCell = document.createElement('td');
            shippedCell.textContent = order.supplier_shipped_at || '—';
            row.appendChild(shippedCell);

            var itemsCell = document.createElement('td');
            itemsCell.textContent = itemsSummary(order.items);
            row.appendChild(itemsCell);

            var actionsCell = document.createElement('td');

            if (canMarkShipped(order)) {
                var shipButton = document.createElement('button');
                shipButton.type = 'button';
                shipButton.className = 'scp-btn scp-btn--small';
                shipButton.textContent = scpPanelTextData.supplierMarkShippedAction;
                shipButton.addEventListener('click', function () {
                    markShipped(order.id, shipButton);
                });
                actionsCell.appendChild(shipButton);
            }

            row.appendChild(actionsCell);

            tableBody.appendChild(row);
        });
    }

    function markShipped(id, button) {
        button.disabled = true;

        apiFetch('depo/purchase-orders/' + id + '/mark-shipped', { method: 'POST' }).then(function (result) {
            button.disabled = false;

            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.supplierMarkedShipped);
            loadOrders();
        });
    }

    function loadOrders() {
        apiFetch('depo/purchase-orders/mine').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderOrders(result.data);
        });
    }

    loadOrders();
})();
