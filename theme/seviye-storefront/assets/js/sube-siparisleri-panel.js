/**
 * Şube Siparişleri paneli - /admin (Genel Merkez/Bölge Müdürü) ve /sube
 * (Şube Müdürü) için.
 *
 * scpPanel.canManageAll (Genel Merkez/Bölge Müdürü) görür: şube seçici,
 * ücretsiz kota yönetimi, onay bekleyen siparişleri onaylama/reddetme.
 * scpPanel.canManageOwn (Şube Müdürü) görür: kendi şubesi için sipariş
 * oluşturma/düzenleme/gönderme, onaylanmış (kota aşan) siparişler için kart
 * ile ödeme linki. Sipariş listesi + detay bölümü ikisi de görür, yalnızca
 * detay içindeki aksiyon düğmeleri role göre değişir.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManageAll, canManageOwn }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-sube-siparisleri-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    // Captured once, synchronously, at script load - several other
    // unconditionally-enqueued scripts localize under the SAME global
    // names, last one wins - bkz. depo-panel.js'in aynı notu.
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};
    var apiFetch = scpApiFetch;

    var statusEl = root.querySelector('[data-scp-sso-status]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    // ---- Şube seçici (yalnızca canManageAll) ----

    var branchField = root.querySelector('[data-scp-sso-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var branchCache = {};

    function branchQueryString() {
        if (!scpPanelData.canManageAll || !branchSelect.value) {
            return '';
        }

        return '?branch_id=' + encodeURIComponent(branchSelect.value);
    }

    function branchLabel(row) {
        return row.branch_name || String(row.branch_id);
    }

    if (scpPanelData.canManageAll) {
        branchField.hidden = false;

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = scpPanelTextData.allBranches;
        branchSelect.appendChild(allOption);

        apiFetch('branches').then(function (result) {
            if (!result.ok) {
                return;
            }

            branchCache = {};
            result.data.forEach(function (branch) {
                branchCache[branch.id] = branch;

                var option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.name;
                branchSelect.appendChild(option);
            });

            populateQuotaBranchSelect();
        });

        branchSelect.addEventListener('change', function () {
            loadQuotas();
            loadBranchOrders();
        });
    }

    // ---- Ücretsiz Kota Yönetimi (yalnızca canManageAll) ----

    var quotaSection = root.querySelector('[data-scp-sso-quota-section]');
    var quotasBody = root.querySelector('[data-scp-quotas-body]');
    var quotaForm = root.querySelector('[data-scp-quota-form]');

    function populateQuotaBranchSelect() {
        var select = quotaForm.querySelector('[name="branch_id"]');
        select.innerHTML = '';

        Object.keys(branchCache).forEach(function (id) {
            var option = document.createElement('option');
            option.value = id;
            option.textContent = branchCache[id].name;
            select.appendChild(option);
        });
    }

    function loadQuotas() {
        if (!scpPanelData.canManageAll) {
            return;
        }

        apiFetch('sube-siparis/quotas' + branchQueryString()).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderQuotas(result.data);
        });
    }

    function renderQuotas(quotas) {
        quotasBody.innerHTML = '';

        if (quotas.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 6;
            emptyCell.textContent = scpPanelTextData.noQuotas;
            emptyRow.appendChild(emptyCell);
            quotasBody.appendChild(emptyRow);
            return;
        }

        quotas.forEach(function (quota) {
            var row = document.createElement('tr');

            [
                quota.branch_name,
                String(quota.product_id),
                String(quota.free_quantity),
                String(quota.consumed_quantity),
                String(quota.remaining_quantity)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            var actionsCell = document.createElement('td');
            var deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            deleteButton.textContent = scpPanelTextData.remove;
            deleteButton.addEventListener('click', function () {
                if (!window.confirm(scpPanelTextData.confirmDeleteQuota)) {
                    return;
                }

                apiFetch('sube-siparis/quotas/' + quota.id, { method: 'DELETE' }).then(function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    setStatus(scpPanelTextData.quotaDeleted);
                    loadQuotas();
                });
            });
            actionsCell.appendChild(deleteButton);
            row.appendChild(actionsCell);

            quotasBody.appendChild(row);
        });
    }

    if (scpPanelData.canManageAll) {
        quotaSection.hidden = false;

        root.querySelector('[data-scp-new-quota]').addEventListener('click', function () {
            quotaForm.hidden = false;
            setStatus('');
            quotaForm.reset();
        });

        root.querySelector('[data-scp-cancel-quota]').addEventListener('click', function () {
            quotaForm.hidden = true;
        });

        quotaForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var payload = {
                branch_id: parseInt(quotaForm.branch_id.value, 10),
                product_id: parseInt(quotaForm.product_id.value, 10),
                free_quantity: parseInt(quotaForm.free_quantity.value, 10)
            };

            apiFetch('sube-siparis/quotas', { method: 'POST', body: JSON.stringify(payload) }).then(
                function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    setStatus(scpPanelTextData.saved);
                    quotaForm.hidden = true;
                    loadQuotas();
                }
            );
        });
    }

    // ---- Sipariş Oluştur (yalnızca canManageOwn) ----

    var createSection = root.querySelector('[data-scp-sso-create-section]');
    var branchOrderForm = root.querySelector('[data-scp-branch-order-form]');
    var branchOrderItemsBody = root.querySelector('[data-scp-branch-order-items]');

    function addBoItemRow(productId, quantity) {
        var row = document.createElement('tr');

        var productCell = document.createElement('td');
        var productInput = document.createElement('input');
        productInput.type = 'number';
        productInput.min = '1';
        productInput.required = true;
        productInput.value = productId || '';
        productInput.setAttribute('data-scp-bo-item-product', '');
        productCell.appendChild(productInput);
        row.appendChild(productCell);

        var quantityCell = document.createElement('td');
        var quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.min = '1';
        quantityInput.required = true;
        quantityInput.value = quantity || '';
        quantityInput.setAttribute('data-scp-bo-item-quantity', '');
        quantityCell.appendChild(quantityInput);
        row.appendChild(quantityCell);

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

        branchOrderItemsBody.appendChild(row);
    }

    function openBranchOrderForm(order) {
        createSection.hidden = false;
        branchOrderForm.hidden = false;
        boDetail.hidden = true;
        setStatus('');
        branchOrderForm.reset();
        branchOrderForm.id.value = order ? order.id : '';
        branchOrderForm.note.value = order ? order.note || '' : '';
        branchOrderItemsBody.innerHTML = '';

        if (order) {
            order.items.forEach(function (item) {
                addBoItemRow(item.product_id, item.quantity_requested);
            });
        } else {
            addBoItemRow();
        }
    }

    if (scpPanelData.canManageOwn) {
        root.querySelector('[data-scp-new-branch-order]').addEventListener('click', function () {
            openBranchOrderForm(null);
        });

        root.querySelector('[data-scp-cancel-branch-order-form]').addEventListener('click', function () {
            branchOrderForm.hidden = true;
        });

        root.querySelector('[data-scp-add-bo-item]').addEventListener('click', function () {
            addBoItemRow();
        });

        branchOrderForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var items = [];
            branchOrderItemsBody.querySelectorAll('tr').forEach(function (row) {
                var productInput = row.querySelector('[data-scp-bo-item-product]');
                var quantityInput = row.querySelector('[data-scp-bo-item-quantity]');

                if (!productInput.value || !quantityInput.value) {
                    return;
                }

                items.push({
                    product_id: parseInt(productInput.value, 10),
                    quantity_requested: parseInt(quantityInput.value, 10)
                });
            });

            var id = branchOrderForm.id.value;
            var payload = { note: branchOrderForm.note.value, items: items };
            var path = id ? 'sube-siparis/orders/' + id : 'sube-siparis/orders';
            var method = id ? 'PUT' : 'POST';

            apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.saved);
                branchOrderForm.hidden = true;
                loadBranchOrders();
            });
        });
    }

    // ---- Siparişler (liste + detay, her iki rol de görür) ----

    var boTableBody = root.querySelector('[data-scp-branch-orders-body]');
    var boBranchColHeader = root.querySelector('[data-scp-bo-branch-col-header]');
    var statusFilter = root.querySelector('[data-scp-bo-status-filter]');
    var boDetail = root.querySelector('[data-scp-bo-detail]');
    var boDetailTitle = root.querySelector('[data-scp-bo-detail-title]');
    var boDetailItemsBody = root.querySelector('[data-scp-bo-detail-items]');
    var boRejectedReason = root.querySelector('[data-scp-bo-rejected-reason]');
    var boSubmitButton = root.querySelector('[data-scp-bo-submit]');
    var boEditButton = root.querySelector('[data-scp-bo-edit]');
    var boCancelButton = root.querySelector('[data-scp-bo-cancel]');
    var boApproveButton = root.querySelector('[data-scp-bo-approve]');
    var boRejectButton = root.querySelector('[data-scp-bo-reject]');
    var boPayButton = root.querySelector('[data-scp-bo-pay]');
    var currentBranchOrderId = null;

    boBranchColHeader.hidden = !scpPanelData.canManageAll;

    function statusLabel(status) {
        return scpPanelTextData['boStatus_' + status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'completed') {
            return 'scp-badge--active';
        }

        if (status === 'rejected' || status === 'cancelled') {
            return 'scp-badge--inactive';
        }

        if (status === 'submitted' || status === 'awaiting_payment') {
            return 'scp-badge--warning';
        }

        return 'scp-badge--info';
    }

    function loadBranchOrders() {
        var query = branchQueryString();
        var statusParam = statusFilter.value
            ? (query ? '&' : '?') + 'status=' + encodeURIComponent(statusFilter.value)
            : '';

        apiFetch('sube-siparis/orders' + query + statusParam).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderBranchOrders(result.data);
        });
    }

    function renderBranchOrders(orders) {
        boTableBody.innerHTML = '';

        if (orders.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = scpPanelData.canManageAll ? 6 : 5;
            emptyCell.textContent = scpPanelTextData.noBranchOrders;
            emptyRow.appendChild(emptyCell);
            boTableBody.appendChild(emptyRow);
            return;
        }

        orders.forEach(function (order) {
            var row = document.createElement('tr');

            var idCell = document.createElement('td');
            idCell.textContent = '#' + order.id;
            row.appendChild(idCell);

            if (scpPanelData.canManageAll) {
                var branchCell = document.createElement('td');
                branchCell.textContent = branchLabel(order);
                row.appendChild(branchCell);
            }

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'scp-badge ' + statusBadgeClass(order.status);
            badge.textContent = statusLabel(order.status);
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var dateCell = document.createElement('td');
            dateCell.textContent = order.created_at;
            row.appendChild(dateCell);

            var totalCell = document.createElement('td');
            totalCell.textContent = order.total_paid_amount ? String(order.total_paid_amount) : '—';
            row.appendChild(totalCell);

            var actionsCell = document.createElement('td');
            var detailButton = document.createElement('button');
            detailButton.type = 'button';
            detailButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            detailButton.textContent = scpPanelTextData.details;
            detailButton.addEventListener('click', function () {
                openBranchOrderDetail(order.id);
            });
            actionsCell.appendChild(detailButton);
            row.appendChild(actionsCell);

            boTableBody.appendChild(row);
        });
    }

    statusFilter.addEventListener('change', loadBranchOrders);

    function openBranchOrderDetail(id) {
        apiFetch('sube-siparis/orders/' + id).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderBranchOrderDetail(result.data);
        });
    }

    function renderBranchOrderDetail(order) {
        currentBranchOrderId = order.id;
        branchOrderForm.hidden = true;
        boDetail.hidden = false;

        boDetailTitle.textContent = '#' + order.id
            + (scpPanelData.canManageAll ? ' — ' + branchLabel(order) : '')
            + ' (' + statusLabel(order.status) + ')';

        boRejectedReason.hidden = order.status !== 'rejected' || !order.rejected_reason;
        boRejectedReason.textContent = order.rejected_reason || '';

        var isOwnDraft = scpPanelData.canManageOwn && order.status === 'draft';
        var isOwnOrHqCancellable = (scpPanelData.canManageOwn || scpPanelData.canManageAll)
            && (order.status === 'draft' || order.status === 'submitted');

        boSubmitButton.hidden = !isOwnDraft;
        boEditButton.hidden = !isOwnDraft;
        boCancelButton.hidden = !isOwnOrHqCancellable;
        boApproveButton.hidden = !(scpPanelData.canManageAll && order.status === 'submitted');
        boRejectButton.hidden = !(scpPanelData.canManageAll && order.status === 'submitted');
        boPayButton.hidden = !(scpPanelData.canManageOwn && order.status === 'awaiting_payment');

        boDetailItemsBody.innerHTML = '';

        order.items.forEach(function (item) {
            var row = document.createElement('tr');

            [
                String(item.product_id),
                String(item.quantity_requested),
                String(item.free_quantity_applied),
                String(item.paid_quantity),
                item.unit_price !== null ? String(item.unit_price) : '—',
                String(item.paid_amount)
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            boDetailItemsBody.appendChild(row);
        });
    }

    root.querySelector('[data-scp-close-bo-detail]').addEventListener('click', function () {
        boDetail.hidden = true;
        currentBranchOrderId = null;
    });

    if (scpPanelData.canManageOwn) {
        boSubmitButton.addEventListener('click', function () {
            if (!currentBranchOrderId) {
                return;
            }

            apiFetch('sube-siparis/orders/' + currentBranchOrderId + '/submit', { method: 'POST' }).then(
                function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    setStatus(scpPanelTextData.branchOrderSubmitted);
                    renderBranchOrderDetail(result.data);
                    loadBranchOrders();
                }
            );
        });

        boEditButton.addEventListener('click', function () {
            apiFetch('sube-siparis/orders/' + currentBranchOrderId).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                openBranchOrderForm(result.data);
            });
        });

        boPayButton.addEventListener('click', function () {
            if (!currentBranchOrderId) {
                return;
            }

            apiFetch('sube-siparis/orders/' + currentBranchOrderId + '/payment-url').then(function (result) {
                if (!result.ok || !result.data.payment_url) {
                    setStatus(scpPanelTextData.paymentUrlUnavailable, true);
                    return;
                }

                window.location.href = result.data.payment_url;
            });
        });
    }

    if (scpPanelData.canManageAll || scpPanelData.canManageOwn) {
        boCancelButton.addEventListener('click', function () {
            if (!currentBranchOrderId || !window.confirm(scpPanelTextData.confirmCancelBranchOrder)) {
                return;
            }

            apiFetch('sube-siparis/orders/' + currentBranchOrderId + '/cancel', { method: 'POST' }).then(
                function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    setStatus(scpPanelTextData.branchOrderCancelled);
                    renderBranchOrderDetail(result.data);
                    loadBranchOrders();
                }
            );
        });
    }

    if (scpPanelData.canManageAll) {
        boApproveButton.addEventListener('click', function () {
            if (!currentBranchOrderId) {
                return;
            }

            apiFetch('sube-siparis/orders/' + currentBranchOrderId + '/approve', { method: 'POST' }).then(
                function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    setStatus(
                        result.data.has_paid_portion
                            ? scpPanelTextData.branchOrderApprovedAwaitingPayment
                            : scpPanelTextData.branchOrderApproved
                    );
                    renderBranchOrderDetail(result.data);
                    loadBranchOrders();
                    loadQuotas();
                }
            );
        });

        boRejectButton.addEventListener('click', function () {
            if (!currentBranchOrderId) {
                return;
            }

            var reason = window.prompt(scpPanelTextData.promptRejectReason, '');

            if (reason === null) {
                return;
            }

            if (!reason.trim()) {
                setStatus(scpPanelTextData.rejectReasonRequired, true);
                return;
            }

            apiFetch('sube-siparis/orders/' + currentBranchOrderId + '/reject', {
                method: 'POST',
                body: JSON.stringify({ reason: reason.trim() })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.branchOrderRejected);
                renderBranchOrderDetail(result.data);
                loadBranchOrders();
            });
        });
    }

    loadQuotas();
    loadBranchOrders();
})();
