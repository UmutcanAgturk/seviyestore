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

    fetch(scpPanel.restUrl + 'students/mine', {
        headers: { 'X-WP-Nonce': scpPanel.nonce },
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
                emptyOption.textContent = scpPanelText.noChildren;
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
