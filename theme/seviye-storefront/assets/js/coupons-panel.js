/**
 * Coupon/campaign code management for /admin - "OKUL2026" gibi zaman
 * sınırlı, tek kullanımlık promosyon kodları, HQ-only (scp_manage_coupons,
 * unlike Products/Pricing there is no branch-scoped tier - see
 * CouponCapability). Customers apply a code through WooCommerce's own
 * native "Kupon Kodu Uygula" form on the cart page - no theme work needed
 * there, this panel only manages which codes exist.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-coupons-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-coupons-status]');
    var tableBody = root.querySelector('[data-scp-coupons-body]');
    var form = root.querySelector('[data-scp-coupon-form]');

    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function discountLabel(coupon) {
        if (coupon.discount_type === 'percent') {
            return coupon.amount + '%';
        }

        return coupon.amount + ' TRY';
    }

    function usageLabel(coupon) {
        var used = coupon.usage_count || 0;

        return coupon.usage_limit ? used + ' / ' + coupon.usage_limit : String(used);
    }

    function loadCoupons() {
        apiFetch('commerce/coupons').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            renderCoupons(result.data);
        });
    }

    function renderCoupons(coupons) {
        tableBody.innerHTML = '';

        coupons.forEach(function (coupon) {
            var row = document.createElement('tr');

            [coupon.code, discountLabel(coupon), usageLabel(coupon), coupon.expiry_date || '—'].forEach(
                function (text) {
                    var cell = document.createElement('td');
                    cell.textContent = text;
                    row.appendChild(cell);
                }
            );

            var actionsCell = document.createElement('td');

            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelText.edit;
            editButton.addEventListener('click', function () {
                openCouponForm(coupon);
            });
            actionsCell.appendChild(editButton);

            var deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            deleteButton.textContent = scpPanelText.remove;
            deleteButton.addEventListener('click', function () {
                if (!window.confirm(scpPanelText.confirmDeleteCoupon)) {
                    return;
                }

                apiFetch('commerce/coupons/' + coupon.id, { method: 'DELETE' }).then(function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                        return;
                    }

                    setStatus(scpPanelText.couponDeleted);
                    loadCoupons();
                });
            });
            actionsCell.appendChild(deleteButton);

            row.appendChild(actionsCell);
            tableBody.appendChild(row);
        });
    }

    function openCouponForm(coupon) {
        form.hidden = false;
        setStatus('');
        form.reset();
        form.id.value = coupon ? coupon.id : '';
        form.code.value = coupon ? coupon.code : '';
        form.discount_type.value = coupon ? coupon.discount_type : 'percent';
        form.amount.value = coupon ? coupon.amount : '';
        form.usage_limit.value = coupon && coupon.usage_limit ? coupon.usage_limit : '';
        form.expiry_date.value = coupon ? (coupon.expiry_date || '') : '';
        form.description.value = coupon ? coupon.description : '';
    }

    root.querySelector('[data-scp-new-coupon]').addEventListener('click', function () {
        openCouponForm(null);
    });

    root.querySelector('[data-scp-cancel-coupon]').addEventListener('click', function () {
        form.hidden = true;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = form.id.value;
        var payload = {
            code: form.code.value,
            discount_type: form.discount_type.value,
            amount: parseFloat(form.amount.value),
            usage_limit: form.usage_limit.value ? parseInt(form.usage_limit.value, 10) : null,
            expiry_date: form.expiry_date.value,
            description: form.description.value
        };

        var path = id ? 'commerce/coupons/' + id : 'commerce/coupons';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.couponSaved);
            form.hidden = true;
            loadCoupons();
        });
    });

    loadCoupons();
})();
