/**
 * Sipariş Yönetimi panel for /admin (every branch, scp_view_orders) and
 * /sube (own branch only, scp_view_own_branch_orders) - mirrors
 * reports-panel.js's HQ-vs-own-branch split and orders-panel.js's order
 * card rendering, with the buyer's (veli) name/e-posta shown since this is
 * an admin view of OTHER people's orders, unlike orders-panel.js's "my own
 * orders" page. See
 * plugin/seviye-commerce/src/Http/AdminOrdersRestController.php's
 * seviye/v1/commerce/orders endpoint.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches, canCancelOrders, canRefundOrders, canUpdateFulfillment }
 *   scpPanelText { ...translated UI strings }
 *
 * "İade/iptal akışı": cancel/refund buttons call
 * AdminOrdersRestController::cancel()/refund() - which status a given order
 * is eligible for mirrors that controller's own CANCELLABLE_STATUSES/
 * `completed`-only rule exactly (see CANCELLABLE_STATUSES below); the
 * server re-checks both the capability AND the status regardless of what
 * this file shows/hides, so a stale client can never bypass either rule.
 *
 * "Kargoya verildi/teslim edildi": ship/deliver buttons call
 * AdminOrdersRestController::ship()/deliver() - mirrors
 * FULFILLABLE_STATUSES the same way; the fulfillment timeline/meta rows
 * come from OrderPresenter merging in OrderFulfillment::present() (see
 * `order.fulfillment_status`/`tracking_number`/`shipped_at`/`delivered_at`).
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-admin-orders-panel');

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

    var CANCELLABLE_STATUSES = ['pending', 'processing', 'on-hold', 'failed'];
    var FULFILLABLE_STATUSES = ['processing', 'on-hold', 'completed'];

    var statusEl = root.querySelector('[data-scp-admin-orders-status]');
    var form = root.querySelector('[data-scp-admin-orders-form]');
    var branchField = root.querySelector('[data-scp-admin-orders-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var listEl = root.querySelector('[data-scp-admin-orders-list]');
    var statusTabsEl = root.querySelector('[data-scp-admin-orders-status-tabs]');
    var exportButton = root.querySelector('[data-scp-admin-orders-export]');
    var statusSelect = form.status;
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function formatMoney(amount) {
        return Number(amount).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            + ' TRY';
    }

    function currentParams() {
        var formData = new FormData(form);
        var params = new URLSearchParams();

        ['product_id', 'student_id', 'status', 'from', 'to', 'search'].forEach(function (name) {
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

    function cancelOrder(order) {
        if (!window.confirm(scpPanelTextData.confirmCancelOrder)) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/cancel', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.orderCancelled);
            loadOrders();
        });
    }

    function refundOrder(order) {
        var remaining = order.total - order.refunded_total;
        var input = window.prompt(scpPanelTextData.refundAmountPrompt, remaining.toFixed(2));

        if (input === null) {
            return;
        }

        var body = {};
        var trimmed = input.trim();

        if (trimmed !== '') {
            body.amount = Number(trimmed.replace(',', '.'));
        }

        apiFetch('commerce/orders/' + order.id + '/refund', {
            method: 'POST',
            body: JSON.stringify(body)
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.orderRefunded);
            loadOrders();
        });
    }

    function shipOrder(order) {
        var trackingNumber = window.prompt(scpPanelTextData.trackingNumberPrompt, '');

        if (trackingNumber === null) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/ship', {
            method: 'POST',
            body: JSON.stringify({ tracking_number: trackingNumber.trim() })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.orderShipped);
            loadOrders();
        });
    }

    function deliverOrder(order) {
        if (!window.confirm(scpPanelTextData.confirmDeliverOrder)) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/deliver', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.orderDelivered);
            loadOrders();
        });
    }

    function renderOrderActions(order) {
        var actions = document.createElement('div');
        actions.className = 'scp-form__actions';
        var hasAction = false;

        if (scpPanelData.canCancelOrders && CANCELLABLE_STATUSES.indexOf(order.status) !== -1) {
            var cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            cancelButton.textContent = scpPanelTextData.cancelOrderAction;
            cancelButton.addEventListener('click', function () {
                cancelOrder(order);
            });
            actions.appendChild(cancelButton);
            hasAction = true;
        }

        if (scpPanelData.canRefundOrders && order.status === 'completed' && order.total - order.refunded_total > 0) {
            var refundButton = document.createElement('button');
            refundButton.type = 'button';
            refundButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            refundButton.textContent = scpPanelTextData.refundOrderAction;
            refundButton.addEventListener('click', function () {
                refundOrder(order);
            });
            actions.appendChild(refundButton);
            hasAction = true;
        }

        var canUpdateFulfillment = scpPanelData.canUpdateFulfillment && FULFILLABLE_STATUSES.indexOf(order.status) !== -1
            && order.fulfillment_status !== 'delivered';

        if (canUpdateFulfillment) {
            var shipButton = document.createElement('button');
            shipButton.type = 'button';
            shipButton.className = 'scp-btn scp-btn--small';
            shipButton.textContent = scpPanelTextData.shipOrderAction;
            shipButton.addEventListener('click', function () {
                shipOrder(order);
            });
            actions.appendChild(shipButton);
            hasAction = true;

            var deliverButton = document.createElement('button');
            deliverButton.type = 'button';
            deliverButton.className = 'scp-btn scp-btn--small';
            deliverButton.textContent = scpPanelTextData.deliverOrderAction;
            deliverButton.addEventListener('click', function () {
                deliverOrder(order);
            });
            actions.appendChild(deliverButton);
            hasAction = true;
        }

        return hasAction ? actions : null;
    }

    /**
     * "Sipariş durumu için görsel zaman çizelgesi" - replaces the old
     * single conditional fulfillment badge with all three steps always
     * visible. Same markup/behaviour as orders-panel.js's (veli's own
     * read-only order history) own copy of this function - duplicated
     * rather than shared, same as every other small WP-context helper in
     * this theme (see e.g. StudentsRestController::uniqueLoginFor()'s
     * PHP-side precedent for the same call).
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
     * "Sipariş numarası kopyalama düğmesi" - orders-panel.js'in AYNI
     * fonksiyonu, bu dosyada da yinelenmiş (bu kod tabanının küçük,
     * sayfa-bağlamına-özel yardımcıları paylaşılan bir dosya yerine
     * yinelemesi kuralına uygun - bkz. bölüm 68). `navigator.clipboard`
     * yoksa (eski tarayıcı, güvenli olmayan bağlam) düğme hiç render
     * edilmiyor.
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

        header.appendChild(titleGroup);

        var badge = document.createElement('span');
        badge.className = 'scp-badge ' + (order.status === 'completed' ? 'scp-badge--active' : 'scp-badge--info');
        badge.textContent = order.status_label;
        header.appendChild(badge);

        card.appendChild(header);
        card.appendChild(renderFulfillmentTimeline(order));

        var meta = document.createElement('dl');
        meta.className = 'scp-summary-list';
        metaRow(meta, scpPanelTextData.orderCustomerLabel, order.customer_name || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderCustomerEmailLabel, order.customer_email || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderDateLabel, order.date || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderPaymentMethodLabel, order.payment_method_title || scpPanelTextData.summaryNotSet);
        metaRow(meta, scpPanelTextData.orderSubtotalLabel, formatMoney(order.subtotal));
        metaRow(meta, scpPanelTextData.orderTaxLabel, formatMoney(order.total_tax));
        metaRow(meta, scpPanelTextData.orderTotalLabel, formatMoney(order.total));

        if (order.refunded_total > 0) {
            metaRow(meta, scpPanelTextData.orderRefundedTotalLabel, formatMoney(order.refunded_total));
        }

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

        var actions = renderOrderActions(order);

        if (actions) {
            card.appendChild(actions);
        }

        return card;
    }

    /**
     * "Sipariş listesi durum sekmeleri" - a faster, more visual shortcut
     * to the SAME `name="status"` dropdown the filter form already has
     * (built FROM that dropdown's own `<option>`s, not a second hardcoded
     * status list) - clicking a tab just sets the dropdown's value and
     * re-fetches through the exact same `currentParams()`/`loadOrders()`
     * path the form's own submit already uses; no separate client-side
     * filtering or new REST call shape.
     */
    function renderStatusTabs() {
        if (!statusTabsEl) {
            return;
        }

        statusTabsEl.innerHTML = '';

        Array.prototype.forEach.call(statusSelect.options, function (option) {
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'scp-status-tabs__tab';
            tab.textContent = option.textContent;
            tab.setAttribute('aria-pressed', String(option.value === statusSelect.value));

            tab.addEventListener('click', function () {
                statusSelect.value = option.value;
                updateActiveStatusTab();
                loadOrders();
            });

            statusTabsEl.appendChild(tab);
        });
    }

    function updateActiveStatusTab() {
        if (!statusTabsEl) {
            return;
        }

        Array.prototype.forEach.call(statusTabsEl.children, function (tab, index) {
            var isActive = statusSelect.options[index].value === statusSelect.value;
            tab.classList.toggle('scp-status-tabs__tab--active', isActive);
            tab.setAttribute('aria-pressed', String(isActive));
        });
    }

    // "Sipariş listesi CSV dışa aktarma" - exports whatever is CURRENTLY
    // loaded (i.e. whatever the filter form's own query already narrowed
    // it down to), not a separate REST call - lastLoadedOrders is just
    // the same array loadOrders() already fetched and rendered.
    var lastLoadedOrders = [];

    function loadOrders() {
        var params = currentParams();

        apiFetch('commerce/orders?' + params.toString()).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            listEl.innerHTML = '';
            lastLoadedOrders = result.data;

            if (result.data.length === 0) {
                setStatus(scpPanelTextData.noOrders);
                return;
            }

            setStatus('');
            result.data.forEach(function (order) {
                listEl.appendChild(renderOrder(order));
            });
        });
    }

    function csvCell(value) {
        var text = value === null || value === undefined ? '' : String(value);

        return '"' + text.replace(/"/g, '""') + '"';
    }

    function exportOrdersToCsv() {
        if (lastLoadedOrders.length === 0) {
            setStatus(scpPanelTextData.noOrders);
            return;
        }

        var headers = [
            scpPanelTextData.orderNumberLabel,
            scpPanelTextData.orderCustomerLabel,
            scpPanelTextData.orderCustomerEmailLabel,
            scpPanelTextData.orderDateLabel,
            scpPanelTextData.orderStatusLabel,
            scpPanelTextData.orderSubtotalLabel,
            scpPanelTextData.orderTaxLabel,
            scpPanelTextData.orderTotalLabel
        ];

        var rows = lastLoadedOrders.map(function (order) {
            return [
                order.number,
                order.customer_name || '',
                order.customer_email || '',
                order.date || '',
                order.status_label || order.status,
                order.subtotal,
                order.total_tax,
                order.total
            ].map(csvCell).join(',');
        });

        // "﻿" (UTF-8 BOM) so Excel (still the most likely tool this
        // gets opened in) detects the encoding correctly instead of
        // mangling Turkish characters.
        var csvContent = '﻿' + headers.map(csvCell).join(',') + '\r\n' + rows.join('\r\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'siparisler-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        updateActiveStatusTab();
        loadOrders();
    });

    exportButton.addEventListener('click', exportOrdersToCsv);

    if (scpPanelData.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }

    renderStatusTabs();
    loadOrders();
})();
