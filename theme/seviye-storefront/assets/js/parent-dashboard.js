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

    var apiFetch = scpApiFetch;

    function initChildren() {
        var status = document.querySelector('[data-scp-children-status]');
        var list = document.querySelector('[data-scp-children-list]');

        if (!list) {
            return;
        }

        apiFetch('students/mine').then(function (result) {
            if (!result.ok) {
                status.textContent = scpPanelText.loadError;
                status.classList.add('scp-status--error');
                return;
            }

            if (result.data.length === 0) {
                status.textContent = scpPanelText.noChildren;
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
                    phone: form.phone.value,
                    notification_preference: form.notification_preference.value,
                    kvkk_consent: form.kvkk_consent.checked
                })
            }).then(function (result) {
                if (!result.ok) {
                    status.textContent = scpPanelText.saveError;
                    status.classList.add('scp-status--error');
                    return;
                }

                status.classList.remove('scp-status--error');
                status.textContent = scpPanelText.profileSaved;
                applyProfile(result.data);
            });
        });
    }

    initChildren();
    initProfile();
})();
