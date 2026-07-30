/**
 * "SMS Ayarları (NetGSM)" card, /admin only,
 * scp_manage_notification_settings (Genel Merkez) only - reads/writes
 * Seviye Notifications' seviye/v1/notifications/sms-settings endpoint.
 * The password field is always left blank after load (the server never
 * returns it - see NotificationsSettingsRestController's docblock);
 * submitting with it blank leaves the stored password unchanged.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-sms-settings-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-sms-settings-status]');
    var form = root.querySelector('[data-scp-sms-settings-form]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function apiFetch(path, options) {
        options = options || {};
        options.headers = Object.assign(
            { 'Content-Type': 'application/json', 'X-WP-Nonce': scpPanel.nonce },
            options.headers || {}
        );
        options.credentials = 'same-origin';

        return fetch(scpPanel.restUrl + path, options).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        });
    }

    function applySettings(settings) {
        form.usercode.value = settings.usercode;
        form.msgheader.value = settings.msgheader;
        form.password.value = '';
        setStatus(settings.configured ? scpPanelText.smsConfigured : scpPanelText.smsNotConfigured);
    }

    function loadSettings() {
        apiFetch('notifications/sms-settings').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            applySettings(result.data);
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);

        apiFetch('notifications/sms-settings', {
            method: 'PUT',
            body: JSON.stringify({
                usercode: formData.get('usercode'),
                msgheader: formData.get('msgheader'),
                password: formData.get('password') || undefined
            })
        }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.saveError, true);
                return;
            }

            applySettings(result.data);
            setStatus(scpPanelText.saved);
        });
    });

    loadSettings();
})();
