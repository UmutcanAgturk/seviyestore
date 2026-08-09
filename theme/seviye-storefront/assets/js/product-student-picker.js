/**
 * Populates the student picker rendered by
 * inc/woocommerce.php's scp_render_student_picker() (single product page
 * only) from seviye/v1/students/mine - the same endpoint
 * parent-dashboard.js uses to list a Veli's own children. The selected
 * option's value becomes the `scp_student_id` field WooCommerce's own
 * add-to-cart form POST already carries (it's a plain <select> inside
 * form.cart, no extra JS needed for that part).
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var select = document.querySelector('[data-scp-student-picker] select');

    if (!select || typeof scpPanel === 'undefined') {
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

    fetch(scpPanelData.restUrl + 'students/mine', {
        headers: { 'X-WP-Nonce': scpPanelData.nonce },
        credentials: 'same-origin'
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            select.innerHTML = '';

            if (!result.ok || !Array.isArray(result.data) || result.data.length === 0) {
                var emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = scpPanelTextData.noChildren;
                select.appendChild(emptyOption);
                select.disabled = true;

                return;
            }

            result.data.forEach(function (student) {
                var option = document.createElement('option');
                option.value = String(student.id);
                option.textContent = student.first_name + ' ' + student.last_name;
                select.appendChild(option);
            });
        });

    select.addEventListener('change', function () {
        scpSuggestPastSize(select.value);
    });

    var pastSizeHintEl = null;

    /**
     * "Öğrenci bazlı beden geçmişi" - seçilen öğrenci bu ürünü (aynı
     * product_id - WC varyasyonlarda bu her zaman PARENT ürünün ID'si,
     * bkz. OrderPresenter::presentItem()) DAHA ÖNCE aldıysa ve o
     * siparişte bir "Beden" (`pa_beden`) varyant bilgisi kayıtlıysa,
     * WooCommerce'in kendi varyant formunun (`select[name="attribute_pa_beden"]`)
     * yanına bir ipucu + "Uygula" düğmesi ekliyor. OTOMATİK SEÇMİYOR -
     * kullanıcının WC'nin kendi varyant formunu beklenmedik şekilde
     * değiştirmesini istemiyoruz, yalnızca ÖNERİYOR.
     */
    function scpSuggestPastSize(studentId) {
        if (pastSizeHintEl) {
            pastSizeHintEl.remove();
            pastSizeHintEl = null;
        }

        var sizeSelect = document.querySelector('select[name="attribute_pa_beden"]');
        var form = document.querySelector('form.variations_form.cart');

        if (!studentId || !sizeSelect || !form || !form.dataset.productId) {
            return;
        }

        var productId = parseInt(form.dataset.productId, 10);

        fetch(scpPanelData.restUrl + 'commerce/orders/mine', {
            headers: { 'X-WP-Nonce': scpPanelData.nonce },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.ok ? response.json() : [];
            })
            .then(function (orders) {
                if (!Array.isArray(orders)) {
                    return;
                }

                var pastSize = null;

                orders.some(function (order) {
                    return (order.items || []).some(function (item) {
                        if (
                            item.product_id === productId
                            && String(item.student_id) === String(studentId)
                            && item.attributes && item.attributes.beden
                        ) {
                            pastSize = item.attributes.beden;
                            return true;
                        }

                        return false;
                    });
                });

                if (!pastSize) {
                    return;
                }

                var matchingOption = Array.prototype.filter.call(sizeSelect.options, function (option) {
                    return option.textContent.trim().toLowerCase() === pastSize.toLowerCase();
                })[0];

                if (!matchingOption) {
                    return;
                }

                var hint = document.createElement('p');
                hint.className = 'scp-past-size-hint';

                var text = document.createElement('span');
                text.textContent = (scpPanelTextData.pastSizeHint || 'Geçen sefer bu öğrenci için %s bedeni alınmıştı.')
                    .replace('%s', pastSize);
                hint.appendChild(text);

                var applyButton = document.createElement('button');
                applyButton.type = 'button';
                applyButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                applyButton.textContent = scpPanelTextData.pastSizeApply || 'Uygula';
                applyButton.addEventListener('click', function () {
                    if (typeof jQuery !== 'undefined') {
                        jQuery(sizeSelect).val(matchingOption.value).trigger('change');
                    } else {
                        sizeSelect.value = matchingOption.value;
                        sizeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                hint.appendChild(applyButton);

                sizeSelect.insertAdjacentElement('afterend', hint);
                pastSizeHintEl = hint;
            });
    }
})();
