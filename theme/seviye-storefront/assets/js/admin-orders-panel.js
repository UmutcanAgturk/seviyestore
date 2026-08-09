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
    var bulkActionsEl = root.querySelector('[data-scp-admin-orders-bulk-actions]');
    var statusSelect = form.status;
    var apiFetch = scpApiFetch;

    // "Toplu işlem": seçili sipariş ID'leri, listeyi her yeniden
    // çektiğimizde (loadOrders()) sıfırlanıyor - eski bir filtrede seçilmiş
    // bir sipariş, yeni filtre sonucunda hiç görünmeyebilir/farklı bir
    // duruma geçmiş olabilir, bu yüzden seçim listenin YENİ haliyle
    // tutarsız kalmasın diye her yenilemede temizleniyor.
    var selectedOrderIds = [];

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
        // trackingNumberPrompt itself says "(isteğe bağlı)" (optional), so
        // dismissing the native prompt (Cancel/Esc -> null) when the sender
        // simply has no tracking number yet must NOT silently abort the
        // whole ship action - only an empty tracking number, same as
        // leaving the field blank and clicking OK.
        var trackingNumber = window.prompt(scpPanelTextData.trackingNumberPrompt, '') || '';

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

    /**
     * "Toplu işlem": her sipariş kartına, YALNIZCA
     * `scpPanelData.canUpdateFulfillment` varsa bir seçim kutusu ekliyor -
     * tek bulk eylem ("Teslim Edildi Olarak İşaretle") de bu izne bağlı
     * olduğundan, kutuyu izni olmayan bir kullanıcıya göstermenin anlamı
     * yok. `data-scp-order-checkbox` + `data-order-id`, clearSelection()'ın
     * DOM'daki kutuları seçim dizisiyle yeniden senkronlamasını sağlıyor.
     */
    function renderOrderCheckbox(order) {
        if (!scpPanelData.canUpdateFulfillment) {
            return null;
        }

        var label = document.createElement('label');
        label.className = 'scp-order-select';

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.setAttribute('data-scp-order-checkbox', '');
        checkbox.dataset.orderId = String(order.id);
        checkbox.checked = selectedOrderIds.indexOf(order.id) !== -1;
        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                selectedOrderIds.push(order.id);
            } else {
                selectedOrderIds = selectedOrderIds.filter(function (id) {
                    return id !== order.id;
                });
            }

            updateBulkToolbar();
        });

        label.appendChild(checkbox);

        return label;
    }

    function clearSelection() {
        selectedOrderIds = [];

        Array.prototype.forEach.call(listEl.querySelectorAll('[data-scp-order-checkbox]'), function (checkbox) {
            checkbox.checked = false;
        });

        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        if (!bulkActionsEl) {
            return;
        }

        if (selectedOrderIds.length === 0) {
            bulkActionsEl.hidden = true;
            bulkActionsEl.innerHTML = '';
            return;
        }

        bulkActionsEl.hidden = false;
        bulkActionsEl.innerHTML = '';

        var count = document.createElement('span');
        count.className = 'scp-bulk-actions__count';
        count.textContent = selectedOrderIds.length + ' ' + scpPanelTextData.bulkSelectedSuffix;
        bulkActionsEl.appendChild(count);

        var deliverButton = document.createElement('button');
        deliverButton.type = 'button';
        deliverButton.className = 'scp-btn scp-btn--small';
        deliverButton.textContent = scpPanelTextData.bulkDeliverAction;
        deliverButton.addEventListener('click', bulkDeliverOrders);
        bulkActionsEl.appendChild(deliverButton);

        var clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        clearButton.textContent = scpPanelTextData.bulkClearSelection;
        clearButton.addEventListener('click', clearSelection);
        bulkActionsEl.appendChild(clearButton);
    }

    /**
     * "Teslim Edildi Olarak İşaretle" (toplu) - shipOrder()'ın aksine
     * (kargo takip numarası girişi gerektirdiği için tek tek yapılıyor),
     * deliverOrder()'ın hiçbir ekstra girdiye ihtiyacı yok - bu yüzden
     * TOPLU eylem olarak yalnızca bu sunuluyor. Seçili siparişlerden
     * FULFILLABLE_STATUSES/fulfillment_status kontrolüne uymayanlar
     * (server'ın da zaten reddedeceği) istek gönderilmeden atlanıyor.
     */
    function bulkDeliverOrders() {
        var eligibleIds = [];
        var skipped = 0;

        selectedOrderIds.forEach(function (id) {
            var order = lastLoadedOrders.filter(function (candidate) {
                return candidate.id === id;
            })[0];

            var eligible = order
                && FULFILLABLE_STATUSES.indexOf(order.status) !== -1
                && order.fulfillment_status !== 'delivered';

            if (eligible) {
                eligibleIds.push(id);
            } else {
                skipped += 1;
            }
        });

        if (eligibleIds.length === 0) {
            setStatus(scpPanelTextData.bulkDeliverNoneEligible, true);
            return;
        }

        if (!window.confirm(scpPanelTextData.confirmBulkDeliver)) {
            return;
        }

        Promise.all(eligibleIds.map(function (id) {
            return apiFetch('commerce/orders/' + id + '/deliver', { method: 'POST' });
        })).then(function (results) {
            var failed = results.filter(function (result) {
                return !result.ok;
            }).length;

            loadOrders();

            if (failed > 0) {
                setStatus(scpPanelTextData.saveError, true);
            } else if (skipped > 0) {
                setStatus(
                    eligibleIds.length + ' ' + scpPanelTextData.bulkDeliverDoneSuffix
                        + ' ' + skipped + ' ' + scpPanelTextData.bulkDeliverSkippedSuffix
                );
            } else {
                setStatus(scpPanelTextData.orderDelivered);
            }
        });
    }

    /**
     * "Yazdırılabilir sipariş görünümü" - orders-panel.js'in AYNI
     * düğmesi, bu dosyada da yinelenmiş (bölüm 68). Admin bağlamında
     * ayrıca veli adı da fiş'e ekleniyor - window.scpPrintOrder()'ın
     * `options.customerName` parametresi bunun için var. "Kurumsal
     * siparişlerde PDF'e dijital onay kutusu" - `options.approvalBox: true`
     * yalnızca BURADA (kurumsal/personel bağlamı) geçiliyor, veli fişinde
     * (orders-panel.js) YOK - onay kutusu iç kurumsal onay içindir, velinin
     * kendi fişinde anlamı olmazdı.
     */
    function renderOrderPrintButton(order) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-order-copy-btn';
        button.textContent = scpPanelTextData.orderPrintLabel;
        button.addEventListener('click', function () {
            window.scpPrintOrder(order, scpPanelTextData, formatMoney, {
                customerName: order.customer_name || '',
                approvalBox: true
            });
        });

        return button;
    }

    function renderOrder(order) {
        var card = document.createElement('div');
        card.className = 'scp-card scp-card--nested';

        var header = document.createElement('div');
        header.className = 'scp-card__header';

        var checkbox = renderOrderCheckbox(order);

        if (checkbox) {
            header.appendChild(checkbox);
        }

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

        if (order.delivery_type_label) {
            metaRow(meta, scpPanelTextData.orderDeliveryTypeLabel, order.delivery_type_label);
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

    /**
     * "Aylık takvim görünümü (sipariş/teslimat tarihleri)" - a SEPARATE
     * REST endpoint was NOT added: the calendar is just another rendering
     * of whatever `lastLoadedOrders` the filter form already fetched (same
     * `commerce/orders` call the list view uses), grouped by the day
     * portion of each order's own `date` field (OrderPresenter's
     * `Y-m-d H:i`). Navigating months sets the form's `from`/`to` fields to
     * that month's bounds and re-runs the EXACT SAME `loadOrders()` path a
     * manual date-range search would - no second fetch mechanism to keep
     * in sync with the list view's filters (branch/status/product/etc. all
     * still apply to the calendar too).
     */
    var calendarViewEl = root.querySelector('[data-scp-admin-orders-calendar]');
    var calendarGridEl = root.querySelector('[data-scp-admin-orders-calendar-grid]');
    var calendarTitleEl = root.querySelector('[data-scp-admin-orders-calendar-title]');
    var calendarDayDetailEl = root.querySelector('[data-scp-admin-orders-calendar-day-detail]');
    var calendarDayTitleEl = root.querySelector('[data-scp-admin-orders-calendar-day-title]');
    var calendarDayListEl = root.querySelector('[data-scp-admin-orders-calendar-day-list]');
    var viewToggleButtons = root.querySelectorAll('[data-scp-admin-orders-view]');
    var currentView = 'list';
    var calendarMonth = new Date();
    calendarMonth.setDate(1);

    var MONTH_NAMES = [
        scpPanelTextData.calMonthJan, scpPanelTextData.calMonthFeb, scpPanelTextData.calMonthMar,
        scpPanelTextData.calMonthApr, scpPanelTextData.calMonthMay, scpPanelTextData.calMonthJun,
        scpPanelTextData.calMonthJul, scpPanelTextData.calMonthAug, scpPanelTextData.calMonthSep,
        scpPanelTextData.calMonthOct, scpPanelTextData.calMonthNov, scpPanelTextData.calMonthDec
    ];

    function isoDate(date) {
        return date.getFullYear() + '-'
            + String(date.getMonth() + 1).padStart(2, '0') + '-'
            + String(date.getDate()).padStart(2, '0');
    }

    function setView(view) {
        currentView = view;
        listEl.hidden = view !== 'list';
        calendarViewEl.hidden = view !== 'calendar';

        Array.prototype.forEach.call(viewToggleButtons, function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-scp-admin-orders-view') === view);
        });

        if (view === 'calendar') {
            renderCalendar();
        }
    }

    function goToMonth(monthDate) {
        calendarMonth = monthDate;
        form.from.value = isoDate(new Date(monthDate.getFullYear(), monthDate.getMonth(), 1));
        form.to.value = isoDate(new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0));
        loadOrders();
    }

    function renderCalendarDay(isoDay, ordersForDay) {
        calendarDayDetailEl.hidden = false;
        calendarDayTitleEl.textContent = isoDay + ' (' + ordersForDay.length + ' '
            + scpPanelTextData.overviewOrdersLabel + ')';
        calendarDayListEl.innerHTML = '';
        ordersForDay.forEach(function (order) {
            calendarDayListEl.appendChild(renderOrder(order));
        });
        calendarDayDetailEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderCalendar() {
        calendarDayDetailEl.hidden = true;
        calendarTitleEl.textContent = MONTH_NAMES[calendarMonth.getMonth()] + ' ' + calendarMonth.getFullYear();

        var byDay = {};
        lastLoadedOrders.forEach(function (order) {
            if (!order.date) {
                return;
            }

            var day = order.date.slice(0, 10);
            (byDay[day] = byDay[day] || []).push(order);
        });

        calendarGridEl.innerHTML = '';

        var firstOfMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
        var daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0).getDate();
        // getDay(): 0=Pazar..6=Cmt - grid is Pzt-first, so Pazar (0) needs
        // 6 leading blanks, everything else needs (weekday - 1).
        var leadingBlanks = firstOfMonth.getDay() === 0 ? 6 : firstOfMonth.getDay() - 1;

        for (var i = 0; i < leadingBlanks; i++) {
            calendarGridEl.appendChild(document.createElement('div'));
        }

        for (var day = 1; day <= daysInMonth; day++) {
            var cellDate = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day);
            var cellIso = isoDate(cellDate);
            var dayOrders = byDay[cellIso] || [];

            var cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'scp-order-calendar__day' + (dayOrders.length > 0 ? ' scp-order-calendar__day--has-orders' : '');

            var dayNumber = document.createElement('span');
            dayNumber.className = 'scp-order-calendar__day-number';
            dayNumber.textContent = String(day);
            cell.appendChild(dayNumber);

            if (dayOrders.length > 0) {
                var total = dayOrders.reduce(function (sum, order) {
                    return sum + Number(order.total);
                }, 0);

                var countBadge = document.createElement('span');
                countBadge.className = 'scp-order-calendar__day-count';
                countBadge.textContent = String(dayOrders.length);
                cell.appendChild(countBadge);

                var totalEl = document.createElement('span');
                totalEl.className = 'scp-order-calendar__day-total';
                totalEl.textContent = formatMoney(total);
                cell.appendChild(totalEl);

                cell.addEventListener('click', function () {
                    renderCalendarDay(this.getAttribute('data-scp-day'), byDay[this.getAttribute('data-scp-day')]);
                });
                cell.setAttribute('data-scp-day', cellIso);
            } else {
                cell.disabled = true;
            }

            calendarGridEl.appendChild(cell);
        }
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
            selectedOrderIds = [];
            updateBulkToolbar();

            if (currentView === 'calendar') {
                renderCalendar();
            }

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

    /**
     * "CSV formula injection" koruması - `order.customer_name` WooCommerce
     * checkout'ta velinin kendi girdiği fatura adı/soyadından geliyor
     * (bkz. OrderPresenter::present()), yani `=`, `+`, `-`, `@` ile
     * başlayan bir değer verip Excel/Sheets/LibreOffice'te dosya
     * açıldığında çalışan bir formül/DDE payload'ı yerleştirebilir
     * (OWASP CSV Injection). Bu dört karakterden biriyle başlayan her
     * hücrenin önüne bir tek tırnak ekleniyor - hücreyi metin olarak
     * "sabitliyor", elektronik tablo uygulamalarının çoğu bunu görünür
     * bir önek olarak DEĞİL, salt-metin göstergesi olarak yorumluyor.
     */
    function csvCell(value) {
        var text = value === null || value === undefined ? '' : String(value);

        if (/^[=+\-@\t\r]/.test(text)) {
            text = "'" + text;
        }

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

    Array.prototype.forEach.call(viewToggleButtons, function (button) {
        button.addEventListener('click', function () {
            setView(button.getAttribute('data-scp-admin-orders-view'));
        });
    });

    root.querySelector('[data-scp-admin-orders-calendar-prev]').addEventListener('click', function () {
        goToMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1, 1));
    });

    root.querySelector('[data-scp-admin-orders-calendar-next]').addEventListener('click', function () {
        goToMonth(new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 1));
    });

    root.querySelector('[data-scp-admin-orders-calendar-day-close]').addEventListener('click', function () {
        calendarDayDetailEl.hidden = true;
    });

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
