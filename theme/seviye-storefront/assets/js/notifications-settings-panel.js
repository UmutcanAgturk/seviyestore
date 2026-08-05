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

    var statusEl = root.querySelector('[data-scp-sms-settings-status]');
    var form = root.querySelector('[data-scp-sms-settings-form]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function applySettings(settings) {
        form.usercode.value = settings.usercode;
        form.msgheader.value = settings.msgheader;
        form.password.value = '';
        setStatus(settings.configured ? scpPanelTextData.smsConfigured : scpPanelTextData.smsNotConfigured);
    }

    function loadSettings() {
        apiFetch('notifications/sms-settings').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
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
                setStatus(scpPanelTextData.saveError, true);
                return;
            }

            applySettings(result.data);
            setStatus(scpPanelTextData.saved);
        });
    });

    loadSettings();
})();
