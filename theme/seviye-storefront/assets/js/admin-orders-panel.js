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
 * FULFILLABLE_STATUSES the same way; the fulfillment badge/meta rows come
 * from OrderPresenter merging in OrderFulfillment::present() (see
 * `order.fulfillment_status`/`tracking_number`/`shipped_at`/`delivered_at`).
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-admin-orders-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var CANCELLABLE_STATUSES = ['pending', 'processing', 'on-hold', 'failed'];
    var FULFILLABLE_STATUSES = ['processing', 'on-hold', 'completed'];

    var statusEl = root.querySelector('[data-scp-admin-orders-status]');
    var form = root.querySelector('[data-scp-admin-orders-form]');
    var branchField = root.querySelector('[data-scp-admin-orders-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var listEl = root.querySelector('[data-scp-admin-orders-list]');
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

    function cancelOrder(order) {
        if (!window.confirm(scpPanelText.confirmCancelOrder)) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/cancel', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.orderCancelled);
            loadOrders();
        });
    }

    function refundOrder(order) {
        var remaining = order.total - order.refunded_total;
        var input = window.prompt(scpPanelText.refundAmountPrompt, remaining.toFixed(2));

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
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.orderRefunded);
            loadOrders();
        });
    }

    function shipOrder(order) {
        var trackingNumber = window.prompt(scpPanelText.trackingNumberPrompt, '');

        if (trackingNumber === null) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/ship', {
            method: 'POST',
            body: JSON.stringify({ tracking_number: trackingNumber.trim() })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.orderShipped);
            loadOrders();
        });
    }

    function deliverOrder(order) {
        if (!window.confirm(scpPanelText.confirmDeliverOrder)) {
            return;
        }

        apiFetch('commerce/orders/' + order.id + '/deliver', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.orderDelivered);
            loadOrders();
        });
    }

    function renderOrderActions(order) {
        var actions = document.createElement('div');
        actions.className = 'scp-form__actions';
        var hasAction = false;

        if (scpPanel.canCancelOrders && CANCELLABLE_STATUSES.indexOf(order.status) !== -1) {
            var cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            cancelButton.textContent = scpPanelText.cancelOrderAction;
            cancelButton.addEventListener('click', function () {
                cancelOrder(order);
            });
            actions.appendChild(cancelButton);
            hasAction = true;
        }

        if (scpPanel.canRefundOrders && order.status === 'completed' && order.total - order.refunded_total > 0) {
            var refundButton = document.createElement('button');
            refundButton.type = 'button';
            refundButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            refundButton.textContent = scpPanelText.refundOrderAction;
            refundButton.addEventListener('click', function () {
                refundOrder(order);
            });
            actions.appendChild(refundButton);
            hasAction = true;
        }

        var canUpdateFulfillment = scpPanel.canUpdateFulfillment && FULFILLABLE_STATUSES.indexOf(order.status) !== -1
            && order.fulfillment_status !== 'delivered';

        if (canUpdateFulfillment) {
            var shipButton = document.createElement('button');
            shipButton.type = 'button';
            shipButton.className = 'scp-btn scp-btn--small';
            shipButton.textContent = scpPanelText.shipOrderAction;
            shipButton.addEventListener('click', function () {
                shipOrder(order);
            });
            actions.appendChild(shipButton);
            hasAction = true;

            var deliverButton = document.createElement('button');
            deliverButton.type = 'button';
            deliverButton.className = 'scp-btn scp-btn--small';
            deliverButton.textContent = scpPanelText.deliverOrderAction;
            deliverButton.addEventListener('click', function () {
                deliverOrder(order);
            });
            actions.appendChild(deliverButton);
            hasAction = true;
        }

        return hasAction ? actions : null;
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
        metaRow(meta, scpPanelText.orderCustomerLabel, order.customer_name || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderCustomerEmailLabel, order.customer_email || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderDateLabel, order.date || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderPaymentMethodLabel, order.payment_method_title || scpPanelText.summaryNotSet);
        metaRow(meta, scpPanelText.orderSubtotalLabel, formatMoney(order.subtotal));
        metaRow(meta, scpPanelText.orderTaxLabel, formatMoney(order.total_tax));
        metaRow(meta, scpPanelText.orderTotalLabel, formatMoney(order.total));

        if (order.refunded_total > 0) {
            metaRow(meta, scpPanelText.orderRefundedTotalLabel, formatMoney(order.refunded_total));
        }

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

        var actions = renderOrderActions(order);

        if (actions) {
            card.appendChild(actions);
        }

        return card;
    }

    function loadOrders() {
        var params = currentParams();

        apiFetch('commerce/orders?' + params.toString()).then(function (result) {
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

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadOrders();
    });

    if (scpPanel.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }

    loadOrders();
})();
