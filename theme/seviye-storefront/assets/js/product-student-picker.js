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
})();
