/**
 * "IP Kısıtlaması" card, /admin only, scp_manage_security_settings
 * (Genel Merkez) only - reads/writes Seviye Security's
 * seviye/v1/security/ip-allowlist endpoint. One entry per textarea line;
 * the server (Seviye\Security\Routing\IpAllowlist::parseEntries()) owns
 * what counts as a valid line, this script only joins/splits on newlines.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-ip-allowlist-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-ip-allowlist-status]');
    var form = root.querySelector('[data-scp-ip-allowlist-form]');
    var textarea = form.querySelector('textarea[name="entries"]');

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

    function loadEntries() {
        apiFetch('security/ip-allowlist').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            textarea.value = result.data.entries.join('\n');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var entries = textarea.value.split('\n');

        apiFetch('security/ip-allowlist', {
            method: 'PUT',
            body: JSON.stringify({ entries: entries })
        }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.saveError, true);
                return;
            }

            textarea.value = result.data.entries.join('\n');
            setStatus(scpPanelText.saved);
        });
    });

    loadEntries();
})();
