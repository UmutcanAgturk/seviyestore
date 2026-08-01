/**
 * Depo (warehouse) panel for /admin (Genel Merkez/Bölge Müdürü) and /sube
 * (Depo role) - "Tek bir depo vardır" (see Seviye\Depo\DepoModule's
 * docblock), so unlike the students/branches panels there is no branch
 * scoping here at all; the same markup/script serves both zones because
 * seviye/v1/depo/* is capability-gated only, never zone-scoped.
 *
 * Three pieces on one panel: Tedarikçiler (supplier CRUD), Satın Alma
 * Siparişleri (purchase order create/list), and a per-order detail view
 * where mal kabul (receiving stock) happens.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-depo-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-depo-status]');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    // ---- Tedarikçiler ----

    var supplierTableBody = root.querySelector('[data-scp-suppliers-body]');
    var supplierForm = root.querySelector('[data-scp-supplier-form]');
    var supplierStatusField = root.querySelector('[data-scp-supplier-status-field]');
    var deleteSupplierButton = root.querySelector('[data-scp-delete-supplier]');
    var supplierCache = {};

    function loadSuppliers() {
        return apiFetch('depo/suppliers').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return [];
            }

            supplierCache = {};
            result.data.forEach(function (supplier) {
                supplierCache[supplier.id] = supplier;
            });

            renderSuppliers(result.data);
            populateSupplierSelect(result.data);

            return result.data;
        });
    }

    function renderSuppliers(suppliers) {
        supplierTableBody.innerHTML = '';

        suppliers.forEach(function (supplier) {
            var row = document.createElement('tr');

            [supplier.name, supplier.contact_name || '', supplier.phone || '', supplier.email || ''].forEach(
                function (text) {
                    var cell = document.createElement('td');
                    cell.textContent = text;
                    row.appendChild(cell);
                }
            );

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            var isActive = supplier.status === 'active';
            badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
            badge.textContent = isActive ? scpPanelText.statusActive : scpPanelText.statusInactive;
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var actionsCell = document.createElement('td');
            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelText.edit;
            editButton.addEventListener('click', function () {
                openSupplierForm(supplier);
            });
            actionsCell.appendChild(editButton);
            row.appendChild(actionsCell);

            supplierTableBody.appendChild(row);
        });
    }

    function populateSupplierSelect(suppliers) {
        var select = purchaseOrderForm.querySelector('[name="supplier_id"]');
        select.innerHTML = '';

        suppliers
            .filter(function (supplier) {
                return supplier.status === 'active';
            })
            .forEach(function (supplier) {
                var option = document.createElement('option');
                option.value = String(supplier.id);
                option.textContent = supplier.name;
                select.appendChild(option);
            });
    }

    function openSupplierForm(supplier) {
        supplierForm.hidden = false;
        setStatus('');
        supplierForm.reset();
        supplierForm.id.value = supplier ? supplier.id : '';
        supplierForm.name.value = supplier ? supplier.name : '';
        supplierForm.contact_name.value = supplier ? supplier.contact_name || '' : '';
        supplierForm.phone.value = supplier ? supplier.phone || '' : '';
        supplierForm.email.value = supplier ? supplier.email || '' : '';
        supplierForm.tax_number.value = supplier ? supplier.tax_number || '' : '';
        supplierForm.address.value = supplier ? supplier.address || '' : '';
        supplierStatusField.hidden = !supplier;
        deleteSupplierButton.hidden = !supplier;

        if (supplier) {
            supplierForm.status.value = supplier.status;
        }
    }

    root.querySelector('[data-scp-new-supplier]').addEventListener('click', function () {
        openSupplierForm(null);
    });

    root.querySelector('[data-scp-cancel-supplier]').addEventListener('click', function () {
        supplierForm.hidden = true;
    });

    deleteSupplierButton.addEventListener('click', function () {
        var id = supplierForm.id.value;

        if (!id || !window.confirm(scpPanelText.confirmDeleteSupplier)) {
            return;
        }

        apiFetch('depo/suppliers/' + id, { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.supplierDeleted);
            supplierForm.hidden = true;
            loadSuppliers();
        });
    });

    supplierForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = supplierForm.id.value;
        var payload = {
            name: supplierForm.name.value,
            contact_name: supplierForm.contact_name.value,
            phone: supplierForm.phone.value,
            email: supplierForm.email.value,
            tax_number: supplierForm.tax_number.value,
            address: supplierForm.address.value
        };

        if (id) {
            payload.status = supplierForm.status.value;
        }

        var path = id ? 'depo/suppliers/' + id : 'depo/suppliers';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.saved);
            supplierForm.hidden = true;
            loadSuppliers();
        });
    });

    // ---- Satın Alma Siparişleri ----

    var poTableBody = root.querySelector('[data-scp-purchase-orders-body]');
    var purchaseOrderForm = root.querySelector('[data-scp-purchase-order-form]');
    var poItemsBody = root.querySelector('[data-scp-purchase-order-items]');
    var poDetail = root.querySelector('[data-scp-po-detail]');
    var poDetailTitle = root.querySelector('[data-scp-po-detail-title]');
    var poDetailItemsBody = root.querySelector('[data-scp-po-detail-items]');
    var poReceiveHeader = root.querySelector('[data-scp-po-receive-header]');
    var poReceiveButton = root.querySelector('[data-scp-po-receive]');
    var poSendButton = root.querySelector('[data-scp-po-send]');
    var poCancelButton = root.querySelector('[data-scp-po-cancel]');
    var currentPurchaseOrderId = null;

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

    function loadPurchaseOrders() {
        apiFetch('depo/purchase-orders').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            renderPurchaseOrders(result.data);
        });
    }

    function renderPurchaseOrders(orders) {
        poTableBody.innerHTML = '';

        orders.forEach(function (order) {
            var row = document.createElement('tr');

            var codeCell = document.createElement('td');
            codeCell.textContent = order.code;
            row.appendChild(codeCell);

            var supplierCell = document.createElement('td');
            var supplier = supplierCache[order.supplier_id];
            supplierCell.textContent = supplier ? supplier.name : String(order.supplier_id);
            row.appendChild(supplierCell);

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'scp-badge ' + statusBadgeClass(order.status);
            badge.textContent = statusLabel(order.status);
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var dateCell = document.createElement('td');
            dateCell.textContent = order.expected_date || '—';
            row.appendChild(dateCell);

            var actionsCell = document.createElement('td');
            var detailButton = document.createElement('button');
            detailButton.type = 'button';
            detailButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            detailButton.textContent = scpPanelText.details;
            detailButton.addEventListener('click', function () {
                openPurchaseOrderDetail(order.id);
            });
            actionsCell.appendChild(detailButton);
            row.appendChild(actionsCell);

            poTableBody.appendChild(row);
        });
    }

    function addPoItemRow() {
        var row = document.createElement('tr');

        var productCell = document.createElement('td');
        var productInput = document.createElement('input');
        productInput.type = 'number';
        productInput.min = '1';
        productInput.required = true;
        productInput.setAttribute('data-scp-po-item-product', '');
        productCell.appendChild(productInput);
        row.appendChild(productCell);

        var quantityCell = document.createElement('td');
        var quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.min = '1';
        quantityInput.required = true;
        quantityInput.setAttribute('data-scp-po-item-quantity', '');
        quantityCell.appendChild(quantityInput);
        row.appendChild(quantityCell);

        var costCell = document.createElement('td');
        var costInput = document.createElement('input');
        costInput.type = 'number';
        costInput.min = '0';
        costInput.step = '0.01';
        costInput.setAttribute('data-scp-po-item-cost', '');
        costCell.appendChild(costInput);
        row.appendChild(costCell);

        var removeCell = document.createElement('td');
        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        removeButton.textContent = scpPanelText.remove;
        removeButton.addEventListener('click', function () {
            row.remove();
        });
        removeCell.appendChild(removeButton);
        row.appendChild(removeCell);

        poItemsBody.appendChild(row);
    }

    function openPurchaseOrderForm() {
        purchaseOrderForm.hidden = false;
        poDetail.hidden = true;
        setStatus('');
        purchaseOrderForm.reset();
        poItemsBody.innerHTML = '';
        addPoItemRow();
    }

    root.querySelector('[data-scp-new-purchase-order]').addEventListener('click', openPurchaseOrderForm);

    root.querySelector('[data-scp-cancel-purchase-order]').addEventListener('click', function () {
        purchaseOrderForm.hidden = true;
    });

    root.querySelector('[data-scp-add-po-item]').addEventListener('click', addPoItemRow);

    purchaseOrderForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var items = [];
        poItemsBody.querySelectorAll('tr').forEach(function (row) {
            var productInput = row.querySelector('[data-scp-po-item-product]');
            var quantityInput = row.querySelector('[data-scp-po-item-quantity]');
            var costInput = row.querySelector('[data-scp-po-item-cost]');

            if (!productInput.value || !quantityInput.value) {
                return;
            }

            items.push({
                product_id: parseInt(productInput.value, 10),
                quantity_ordered: parseInt(quantityInput.value, 10),
                unit_cost: costInput.value ? parseFloat(costInput.value) : null
            });
        });

        var payload = {
            supplier_id: parseInt(purchaseOrderForm.supplier_id.value, 10),
            expected_date: purchaseOrderForm.expected_date.value,
            note: purchaseOrderForm.note.value,
            items: items
        };

        apiFetch('depo/purchase-orders', { method: 'POST', body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.saved);
            purchaseOrderForm.hidden = true;
            loadPurchaseOrders();
        });
    });

    function openPurchaseOrderDetail(id) {
        apiFetch('depo/purchase-orders/' + id).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            renderPurchaseOrderDetail(result.data);
        });
    }

    function renderPurchaseOrderDetail(order) {
        currentPurchaseOrderId = order.id;
        purchaseOrderForm.hidden = true;
        poDetail.hidden = false;

        var supplier = supplierCache[order.supplier_id];
        poDetailTitle.textContent = order.code + ' — ' + (supplier ? supplier.name : '') + ' (' + statusLabel(order.status) + ')';

        var canReceive = order.status === 'sent' || order.status === 'partially_received';
        poReceiveHeader.hidden = !canReceive;
        poReceiveButton.hidden = !canReceive;
        poSendButton.hidden = order.status !== 'draft';
        poCancelButton.hidden = order.status === 'completed' || order.status === 'cancelled';

        poDetailItemsBody.innerHTML = '';

        order.items.forEach(function (item) {
            var row = document.createElement('tr');
            row.setAttribute('data-scp-po-item-id', String(item.id));

            [item.product_id, item.quantity_ordered, item.quantity_received, item.remaining_quantity].forEach(
                function (value) {
                    var cell = document.createElement('td');
                    cell.textContent = String(value);
                    row.appendChild(cell);
                }
            );

            var receiveCell = document.createElement('td');

            if (canReceive && item.remaining_quantity > 0) {
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.max = String(item.remaining_quantity);
                input.setAttribute('data-scp-po-receive-quantity', '');
                receiveCell.appendChild(input);
            }

            if (canReceive) {
                row.appendChild(receiveCell);
            }

            poDetailItemsBody.appendChild(row);
        });
    }

    root.querySelector('[data-scp-close-po-detail]').addEventListener('click', function () {
        poDetail.hidden = true;
        currentPurchaseOrderId = null;
    });

    poSendButton.addEventListener('click', function () {
        if (!currentPurchaseOrderId) {
            return;
        }

        apiFetch('depo/purchase-orders/' + currentPurchaseOrderId + '/send', { method: 'POST' }).then(
            function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.saved);
                renderPurchaseOrderDetail(result.data);
                loadPurchaseOrders();
            }
        );
    });

    poCancelButton.addEventListener('click', function () {
        if (!currentPurchaseOrderId || !window.confirm(scpPanelText.confirmCancelPurchaseOrder)) {
            return;
        }

        apiFetch('depo/purchase-orders/' + currentPurchaseOrderId + '/cancel', { method: 'POST' }).then(
            function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.saved);
                renderPurchaseOrderDetail(result.data);
                loadPurchaseOrders();
            }
        );
    });

    poReceiveButton.addEventListener('click', function () {
        if (!currentPurchaseOrderId) {
            return;
        }

        var items = [];
        poDetailItemsBody.querySelectorAll('tr').forEach(function (row) {
            var input = row.querySelector('[data-scp-po-receive-quantity]');

            if (!input || !input.value || parseInt(input.value, 10) <= 0) {
                return;
            }

            items.push({
                item_id: parseInt(row.getAttribute('data-scp-po-item-id'), 10),
                quantity_received: parseInt(input.value, 10)
            });
        });

        if (items.length === 0) {
            return;
        }

        apiFetch('depo/purchase-orders/' + currentPurchaseOrderId + '/receive', {
            method: 'POST',
            body: JSON.stringify({ items: items })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.stockReceived);
            renderPurchaseOrderDetail(result.data);
            loadPurchaseOrders();
        });
    });

    loadSuppliers().then(loadPurchaseOrders);
})();
