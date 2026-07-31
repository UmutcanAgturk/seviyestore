/**
 * "E-posta Ayarları (Gmail SMTP)" card, /admin only,
 * scp_manage_notification_settings (Genel Merkez) only - reads/writes
 * Seviye Notifications' seviye/v1/notifications/email-settings endpoint.
 * Mirrors notifications-settings-panel.js's SMS counterpart exactly: the
 * app_password field is always left blank after load (the server never
 * returns it), submitting with it blank leaves the stored value unchanged.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-email-settings-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-email-settings-status]');
    var form = root.querySelector('[data-scp-email-settings-form]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function applySettings(settings) {
        form.email.value = settings.email;
        form.app_password.value = '';
        setStatus(settings.configured ? scpPanelText.emailConfigured : scpPanelText.emailNotConfigured);
    }

    function loadSettings() {
        apiFetch('notifications/email-settings').then(function (result) {
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

        apiFetch('notifications/email-settings', {
            method: 'PUT',
            body: JSON.stringify({
                email: formData.get('email'),
                app_password: formData.get('app_password') || undefined
            })
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            applySettings(result.data);
            setStatus(scpPanelText.saved);
        });
    });

    loadSettings();
})();
