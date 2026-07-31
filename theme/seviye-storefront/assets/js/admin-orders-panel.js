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
 *   scpPanel     { restUrl, nonce, canViewAllBranches }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-admin-orders-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

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
        card.appendChild(meta);

        card.appendChild(renderItemsTable(order.items));

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
