/**
 * "WhatsApp Ayarları (Business Cloud API)" card, /admin only,
 * scp_manage_notification_settings (Genel Merkez) only - reads/writes
 * Seviye Notifications' seviye/v1/notifications/whatsapp-settings
 * endpoint. Mirrors notifications-settings-panel.js's exact structure for
 * the SMS settings card - same "third-party gateway credentials" shape,
 * kept as its own file/root element since it lives on its own page
 * (/admin/whatsapp-ayarlari), not a section of the SMS page. The access
 * token field is always left blank after load (the server never returns
 * it - see NotificationsSettingsRestController's docblock); submitting
 * with it blank leaves the stored token unchanged.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-whatsapp-settings-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    // Captured once, synchronously, at script load - see
    // notifications-settings-panel.js's own comment for why (several
    // OTHER scripts on this same page localize under the SAME global
    // variable names, whichever loads LAST overwrites them for the whole
    // page).
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-whatsapp-settings-status]');
    var form = root.querySelector('[data-scp-whatsapp-settings-form]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function applySettings(settings) {
        form.phone_number_id.value = settings.phone_number_id;
        form.access_token.value = '';
        setStatus(
            settings.configured
                ? scpPanelTextData.whatsappConfigured
                : scpPanelTextData.whatsappNotConfigured
        );
    }

    function loadSettings() {
        apiFetch('notifications/whatsapp-settings').then(function (result) {
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

        apiFetch('notifications/whatsapp-settings', {
            method: 'PUT',
            body: JSON.stringify({
                phone_number_id: formData.get('phone_number_id'),
                access_token: formData.get('access_token') || undefined
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
