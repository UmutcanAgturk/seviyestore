/**
 * Depo (warehouse) panel for /admin (Genel Merkez/Bölge Müdürü) and /sube
 * (Şube Müdürü/Depo role).
 *
 * Faz 4: "Genel Merkez'in kendi deposu devam eder, şube kendi ürününü
 * eklemişse şubenin kendi deposundan görünür" - artık TEK bir depo değil,
 * platform-wide (scpPanel.canViewAllBranches) kullanıcı bir depo seçici
 * görür (Tüm depolar / Genel Merkez / bir şube), own-branch kullanıcı hiç
 * görmez ve REST tarafı onu zaten kendi şubesine kilitler (bkz.
 * PurchaseOrdersRestController::resolveBranchScope() ve kardeşleri).
 *
 * Three pieces on one panel: Tedarikçiler (supplier - listesi herkese açık,
 * CRUD yalnızca scpPanel.canManageSuppliers), Satın Alma Siparişleri
 * (purchase order create/list), and a per-order detail view where mal
 * kabul (receiving stock) happens.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches, canManageSuppliers, canReceiveStock }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-depo-panel');

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

    var statusEl = root.querySelector('[data-scp-depo-status]');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    // ---- Depo seçici (Faz 4) ----
    //
    // Üç durumlu: '' (Tüm depolar - filtre yok), 'hq' (yalnızca Genel
    // Merkez deposu), ya da bir şube ID'si (yalnızca o şubenin deposu) -
    // bkz. PurchaseOrdersRestController::resolveBranchScope()'un aynı
    // üç durumlu sentinel'i. Own-branch kullanıcıda bu alan hiç
    // gösterilmez - REST tarafı zaten onu tek bir depoya kilitliyor.

    var branchField = root.querySelector('[data-scp-depo-branch-field]');
    var branchSelect = branchField.querySelector('select');

    function branchQueryString() {
        if (!scpPanelData.canViewAllBranches || !branchSelect.value) {
            return '';
        }

        return '?branch_id=' + encodeURIComponent(branchSelect.value);
    }

    function branchLabel(row) {
        return row.branch_name || scpPanelTextData.hqBranch || '';
    }

    if (scpPanelData.canViewAllBranches) {
        branchField.hidden = false;

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = scpPanelTextData.allBranches;
        branchSelect.appendChild(allOption);

        var hqOption = document.createElement('option');
        hqOption.value = 'hq';
        hqOption.textContent = scpPanelTextData.hqBranch;
        branchSelect.appendChild(hqOption);

        apiFetch('branches').then(function (result) {
            if (!result.ok) {
                return;
            }

            result.data.forEach(function (branch) {
                var option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });
        });

        branchSelect.addEventListener('change', function () {
            loadPurchaseOrders();
            loadStockCounts();
            loadPurchaseSuggestions();
            loadStockTransfers();
        });
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
                setStatus(scpPanelTextData.loadError, true);
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

            var portalCell = document.createElement('td');
            var portalBadge = document.createElement('span');
            var isLinked = Boolean(supplier.portal_user_email);
            portalBadge.className = 'scp-badge ' + (isLinked ? 'scp-badge--active' : 'scp-badge--inactive');
            portalBadge.textContent = isLinked
                ? supplier.portal_user_email
                : scpPanelTextData.supplierPortalNotLinked;
            portalCell.appendChild(portalBadge);
            row.appendChild(portalCell);

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            var isActive = supplier.status === 'active';
            badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
            badge.textContent = isActive ? scpPanelTextData.statusActive : scpPanelTextData.statusInactive;
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var actionsCell = document.createElement('td');

            if (scpPanelData.canManageSuppliers) {
                var editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                editButton.textContent = scpPanelTextData.edit;
                editButton.addEventListener('click', function () {
                    openSupplierForm(supplier);
                });
                actionsCell.appendChild(editButton);
            }

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
        supplierForm.user_email.value = supplier ? supplier.portal_user_email || '' : '';
        supplierStatusField.hidden = !supplier;
        deleteSupplierButton.hidden = !supplier;

        if (supplier) {
            supplierForm.status.value = supplier.status;
        }
    }

    var newSupplierButton = root.querySelector('[data-scp-new-supplier]');
    newSupplierButton.hidden = !scpPanelData.canManageSuppliers;
    newSupplierButton.addEventListener('click', function () {
        openSupplierForm(null);
    });

    root.querySelector('[data-scp-cancel-supplier]').addEventListener('click', function () {
        supplierForm.hidden = true;
    });

    deleteSupplierButton.addEventListener('click', function () {
        var id = supplierForm.id.value;

        if (!id || !window.confirm(scpPanelTextData.confirmDeleteSupplier)) {
            return;
        }

        apiFetch('depo/suppliers/' + id, { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.supplierDeleted);
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
            address: supplierForm.address.value,
            user_email: supplierForm.user_email.value
        };

        if (id) {
            payload.status = supplierForm.status.value;
        }

        var path = id ? 'depo/suppliers/' + id : 'depo/suppliers';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.saved);
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
        apiFetch('depo/purchase-orders' + branchQueryString()).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
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

            var branchCell = document.createElement('td');
            branchCell.textContent = branchLabel(order);
            row.appendChild(branchCell);

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
            detailButton.textContent = scpPanelTextData.details;
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
        removeButton.textContent = scpPanelTextData.remove;
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
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.saved);
            purchaseOrderForm.hidden = true;
            loadPurchaseOrders();
        });
    });

    function openPurchaseOrderDetail(id) {
        apiFetch('depo/purchase-orders/' + id).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
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
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.saved);
                renderPurchaseOrderDetail(result.data);
                loadPurchaseOrders();
            }
        );
    });

    poCancelButton.addEventListener('click', function () {
        if (!currentPurchaseOrderId || !window.confirm(scpPanelTextData.confirmCancelPurchaseOrder)) {
            return;
        }

        apiFetch('depo/purchase-orders/' + currentPurchaseOrderId + '/cancel', { method: 'POST' }).then(
            function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.saved);
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
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.stockReceived);
            renderPurchaseOrderDetail(result.data);
            loadPurchaseOrders();
        });
    });

    // ---- Stok Sayımı (faz 2) ----

    var stockCountTableBody = root.querySelector('[data-scp-stock-counts-body]');
    var stockCountDetail = root.querySelector('[data-scp-stock-count-detail]');
    var stockCountDetailTitle = root.querySelector('[data-scp-stock-count-detail-title]');
    var stockCountItemsBody = root.querySelector('[data-scp-stock-count-items]');
    var stockCountInputHeader = root.querySelector('[data-scp-stock-count-input-header]');
    var stockCountCompleteButton = root.querySelector('[data-scp-stock-count-complete]');
    var currentStockCountId = null;

    function stockCountStatusLabel(status) {
        return scpPanelText['stockCountStatus_' + status] || status;
    }

    function loadStockCounts() {
        apiFetch('depo/stock-counts' + branchQueryString()).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderStockCounts(result.data);
        });
    }

    function renderStockCounts(counts) {
        stockCountTableBody.innerHTML = '';

        counts.forEach(function (count) {
            var row = document.createElement('tr');

            var idCell = document.createElement('td');
            idCell.textContent = String(count.id);
            row.appendChild(idCell);

            var branchCell = document.createElement('td');
            branchCell.textContent = branchLabel(count);
            row.appendChild(branchCell);

            [stockCountStatusLabel(count.status), count.started_at, String(count.items.length)].forEach(
                function (text) {
                    var cell = document.createElement('td');
                    cell.textContent = text;
                    row.appendChild(cell);
                }
            );

            var actionsCell = document.createElement('td');
            var detailButton = document.createElement('button');
            detailButton.type = 'button';
            detailButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            detailButton.textContent = scpPanelTextData.details;
            detailButton.addEventListener('click', function () {
                openStockCountDetail(count.id);
            });
            actionsCell.appendChild(detailButton);
            row.appendChild(actionsCell);

            stockCountTableBody.appendChild(row);
        });
    }

    root.querySelector('[data-scp-new-stock-count]').addEventListener('click', function () {
        apiFetch('depo/stock-counts' + branchQueryString(), { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.saved);
            loadStockCounts();
            renderStockCountDetail(result.data);
        });
    });

    function openStockCountDetail(id) {
        apiFetch('depo/stock-counts/' + id).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderStockCountDetail(result.data);
        });
    }

    function renderStockCountDetail(stockCount) {
        currentStockCountId = stockCount.id;
        stockCountDetail.hidden = false;

        var isOpen = stockCount.status === 'open';
        stockCountDetailTitle.textContent = '#' + stockCount.id + ' — ' + stockCountStatusLabel(stockCount.status);
        stockCountCompleteButton.hidden = !isOpen;
        stockCountInputHeader.hidden = !isOpen;

        stockCountItemsBody.innerHTML = '';

        stockCount.items.forEach(function (item) {
            var row = document.createElement('tr');

            [item.product_id, item.expected_quantity].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = String(value);
                row.appendChild(cell);
            });

            var countedCell = document.createElement('td');

            if (isOpen) {
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.value = item.counted_quantity === null ? '' : String(item.counted_quantity);
                input.addEventListener('change', function () {
                    if (input.value === '') {
                        return;
                    }

                    apiFetch('depo/stock-counts/' + stockCount.id + '/items/' + item.id, {
                        method: 'PUT',
                        body: JSON.stringify({ counted_quantity: parseInt(input.value, 10) })
                    }).then(function (result) {
                        if (!result.ok) {
                            setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                            return;
                        }

                        varianceCell.textContent = String(result.data.variance);
                        setStatus(scpPanelTextData.saved);
                    });
                });
                countedCell.appendChild(input);
            } else {
                countedCell.textContent = item.counted_quantity === null ? '—' : String(item.counted_quantity);
            }

            row.appendChild(countedCell);

            var varianceCell = document.createElement('td');
            varianceCell.textContent = item.variance === null ? '—' : String(item.variance);
            row.appendChild(varianceCell);

            stockCountItemsBody.appendChild(row);
        });
    }

    root.querySelector('[data-scp-close-stock-count-detail]').addEventListener('click', function () {
        stockCountDetail.hidden = true;
        currentStockCountId = null;
    });

    stockCountCompleteButton.addEventListener('click', function () {
        if (!currentStockCountId || !window.confirm(scpPanelTextData.confirmCompleteStockCount)) {
            return;
        }

        apiFetch('depo/stock-counts/' + currentStockCountId + '/complete', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.stockCountCompleted);
            renderStockCountDetail(result.data);
            loadStockCounts();
        });
    });

    // ---- Satın Alma Önerileri (faz 2) ----

    var suggestionTableBody = root.querySelector('[data-scp-purchase-suggestions-body]');
    var convertSuggestionForm = root.querySelector('[data-scp-convert-suggestion-form]');

    function suggestionStatusLabel(status) {
        return scpPanelText['suggestionStatus_' + status] || status;
    }

    function loadPurchaseSuggestions() {
        apiFetch('depo/purchase-suggestions' + branchQueryString()).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderPurchaseSuggestions(result.data);
        });
    }

    function renderPurchaseSuggestions(suggestions) {
        suggestionTableBody.innerHTML = '';

        suggestions.forEach(function (suggestion) {
            var row = document.createElement('tr');

            var productCell = document.createElement('td');
            productCell.textContent = String(suggestion.product_id);
            row.appendChild(productCell);

            var branchCell = document.createElement('td');
            branchCell.textContent = branchLabel(suggestion);
            row.appendChild(branchCell);

            [
                String(suggestion.suggested_quantity),
                suggestion.reason || '',
                suggestionStatusLabel(suggestion.status)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            var actionsCell = document.createElement('td');

            if (suggestion.status === 'pending') {
                var convertButton = document.createElement('button');
                convertButton.type = 'button';
                convertButton.className = 'scp-btn scp-btn--small';
                convertButton.textContent = scpPanelTextData.convertToOrder;
                convertButton.addEventListener('click', function () {
                    openConvertSuggestionForm(suggestion);
                });
                actionsCell.appendChild(convertButton);

                var dismissButton = document.createElement('button');
                dismissButton.type = 'button';
                dismissButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                dismissButton.textContent = scpPanelTextData.dismiss;
                dismissButton.addEventListener('click', function () {
                    if (!window.confirm(scpPanelTextData.confirmDismissSuggestion)) {
                        return;
                    }

                    apiFetch('depo/purchase-suggestions/' + suggestion.id + '/dismiss', { method: 'POST' }).then(
                        function (result) {
                            if (!result.ok) {
                                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                                return;
                            }

                            setStatus(scpPanelTextData.suggestionDismissed);
                            loadPurchaseSuggestions();
                        }
                    );
                });
                actionsCell.appendChild(dismissButton);
            }

            row.appendChild(actionsCell);
            suggestionTableBody.appendChild(row);
        });
    }

    function openConvertSuggestionForm(suggestion) {
        convertSuggestionForm.hidden = false;
        convertSuggestionForm.suggestion_id.value = String(suggestion.id);
        convertSuggestionForm.quantity.value = String(suggestion.suggested_quantity);

        var select = convertSuggestionForm.querySelector('[name="supplier_id"]');
        select.innerHTML = '';

        Object.keys(supplierCache)
            .map(function (key) {
                return supplierCache[key];
            })
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

    root.querySelector('[data-scp-cancel-convert-suggestion]').addEventListener('click', function () {
        convertSuggestionForm.hidden = true;
    });

    convertSuggestionForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var suggestionId = convertSuggestionForm.suggestion_id.value;
        var payload = {
            supplier_id: parseInt(convertSuggestionForm.supplier_id.value, 10),
            quantity: convertSuggestionForm.quantity.value ? parseInt(convertSuggestionForm.quantity.value, 10) : null
        };

        apiFetch('depo/purchase-suggestions/' + suggestionId + '/convert', {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.suggestionConverted);
            convertSuggestionForm.hidden = true;
            loadPurchaseSuggestions();
            loadPurchaseOrders();
        });
    });

    // ---- Depo Transferleri ----

    var transferTableBody = root.querySelector('[data-scp-stock-transfers-body]');
    var transferForm = root.querySelector('[data-scp-stock-transfer-form]');

    function transferStatusLabel(status) {
        return scpPanelText['transferStatus_' + status] || status;
    }

    function transferStatusBadgeClass(status) {
        if (status === 'completed') {
            return 'scp-badge--active';
        }

        if (status === 'cancelled') {
            return 'scp-badge--inactive';
        }

        return 'scp-badge--info';
    }

    function loadStockTransfers() {
        apiFetch('depo/stock-transfers' + branchQueryString()).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderStockTransfers(result.data);
        });
    }

    function renderStockTransfers(transfers) {
        transferTableBody.innerHTML = '';

        transfers.forEach(function (transfer) {
            var row = document.createElement('tr');

            [
                String(transfer.from_product_id),
                transfer.from_branch_name,
                String(transfer.to_product_id),
                transfer.to_branch_name,
                String(transfer.quantity)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'scp-badge ' + transferStatusBadgeClass(transfer.status);
            badge.textContent = transferStatusLabel(transfer.status);
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var actionsCell = document.createElement('td');

            if (transfer.status === 'pending') {
                var completeButton = document.createElement('button');
                completeButton.type = 'button';
                completeButton.className = 'scp-btn scp-btn--small';
                completeButton.textContent = scpPanelTextData.complete;
                completeButton.addEventListener('click', function () {
                    if (!window.confirm(scpPanelTextData.confirmCompleteStockTransfer)) {
                        return;
                    }

                    apiFetch('depo/stock-transfers/' + transfer.id + '/complete', { method: 'POST' }).then(
                        function (result) {
                            if (!result.ok) {
                                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                                return;
                            }

                            setStatus(scpPanelTextData.stockTransferCompleted);
                            loadStockTransfers();
                        }
                    );
                });
                actionsCell.appendChild(completeButton);

                var cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                cancelButton.textContent = scpPanelTextData.cancelStockTransfer;
                cancelButton.addEventListener('click', function () {
                    if (!window.confirm(scpPanelTextData.confirmCancelStockTransfer)) {
                        return;
                    }

                    apiFetch('depo/stock-transfers/' + transfer.id + '/cancel', { method: 'POST' }).then(
                        function (result) {
                            if (!result.ok) {
                                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                                return;
                            }

                            setStatus(scpPanelTextData.saved);
                            loadStockTransfers();
                        }
                    );
                });
                actionsCell.appendChild(cancelButton);
            }

            row.appendChild(actionsCell);
            transferTableBody.appendChild(row);
        });
    }

    root.querySelector('[data-scp-new-stock-transfer]').addEventListener('click', function () {
        transferForm.hidden = false;
        setStatus('');
        transferForm.reset();
    });

    root.querySelector('[data-scp-cancel-stock-transfer-form]').addEventListener('click', function () {
        transferForm.hidden = true;
    });

    transferForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var payload = {
            from_product_id: parseInt(transferForm.from_product_id.value, 10),
            to_product_id: parseInt(transferForm.to_product_id.value, 10),
            quantity: parseInt(transferForm.quantity.value, 10),
            note: transferForm.note.value
        };

        apiFetch('depo/stock-transfers', { method: 'POST', body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.saved);
            transferForm.hidden = true;
            loadStockTransfers();
        });
    });

    loadSuppliers().then(loadPurchaseOrders);
    loadStockCounts();
    loadPurchaseSuggestions();
    loadStockTransfers();
})();
