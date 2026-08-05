/**
 * Veli home screen: own children (read-only, from Seviye Students) and own
 * profile (Seviye Parents). Two independent sections - either can be
 * absent from the markup depending on which capability the current user
 * actually holds, so every lookup here is null-guarded.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    if (typeof scpPanel === 'undefined') {
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

    var apiFetch = scpApiFetch;

    function initChildren() {
        var status = document.querySelector('[data-scp-children-status]');
        var list = document.querySelector('[data-scp-children-list]');

        if (!list) {
            return;
        }

        apiFetch('students/mine').then(function (result) {
            if (!result.ok) {
                status.textContent = scpPanelTextData.loadError;
                status.classList.add('scp-status--error');
                return;
            }

            if (result.data.length === 0) {
                status.textContent = scpPanelTextData.noChildren;
                return;
            }

            result.data.forEach(function (student) {
                var item = document.createElement('li');
                var label = document.createElement('span');
                label.textContent = student.first_name + ' ' + student.last_name
                    + ' — ' + student.class_name + ' (' + (student.branch_name || '') + ')';
                item.appendChild(label);
                list.appendChild(item);
            });
        });
    }

    function initProfile() {
        var status = document.querySelector('[data-scp-profile-status]');
        var form = document.querySelector('[data-scp-profile-form]');

        if (!form) {
            return;
        }

        function applyProfile(profile) {
            form.username.value = profile.username || '';
            form.email.value = profile.email || '';
            form.phone.value = profile.phone || '';
            form.notification_preference.value = profile.notification_preference;
            form.kvkk_consent.checked = Boolean(profile.kvkk_consent_given);
            form.kvkk_consent.disabled = Boolean(profile.kvkk_consent_given);
        }

        apiFetch('parents/me').then(function (result) {
            if (result.ok) {
                applyProfile(result.data);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            apiFetch('parents/me', {
                method: 'PUT',
                body: JSON.stringify({
                    email: form.email.value,
                    phone: form.phone.value,
                    notification_preference: form.notification_preference.value,
                    kvkk_consent: form.kvkk_consent.checked
                })
            }).then(function (result) {
                if (!result.ok) {
                    status.textContent = (result.data && result.data.message) || scpPanelTextData.saveError;
                    status.classList.add('scp-status--error');
                    return;
                }

                status.classList.remove('scp-status--error');
                status.textContent = scpPanelTextData.profileSaved;
                applyProfile(result.data);
            });
        });
    }

    function initAddress() {
        var status = document.querySelector('[data-scp-address-status]');
        var form = document.querySelector('[data-scp-address-form]');

        if (!form) {
            return;
        }

        var fields = ['first_name', 'last_name', 'address_1', 'address_2', 'state', 'city', 'postcode', 'country'];

        function applyAddress(addresses) {
            fields.forEach(function (field) {
                form['billing_' + field].value = (addresses.billing && addresses.billing[field]) || '';
                form['shipping_' + field].value = (addresses.shipping && addresses.shipping[field]) || '';
            });
            form.billing_phone.value = (addresses.billing && addresses.billing.phone) || '';
        }

        apiFetch('commerce/customer/me/addresses').then(function (result) {
            if (result.ok) {
                applyAddress(result.data);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var billing = { phone: form.billing_phone.value };
            var shipping = {};

            fields.forEach(function (field) {
                billing[field] = form['billing_' + field].value;
                shipping[field] = form['shipping_' + field].value;
            });

            apiFetch('commerce/customer/me/addresses', {
                method: 'PUT',
                body: JSON.stringify({ billing: billing, shipping: shipping })
            }).then(function (result) {
                if (!result.ok) {
                    status.textContent = scpPanelTextData.saveError;
                    status.classList.add('scp-status--error');
                    return;
                }

                status.classList.remove('scp-status--error');
                status.textContent = scpPanelTextData.addressSaved;
                applyAddress(result.data);
            });
        });
    }

    initChildren();
    initProfile();
    initAddress();
})();
