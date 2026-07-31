/**
 * "API Anahtarları" panel, /admin only, scp_manage_api_keys (Genel Merkez)
 * only - reads/writes Seviye API's seviye/v1/api-keys endpoint. A newly
 * created key's plain value is shown exactly once, right after creation,
 * from the POST response itself - it is never requested from the server
 * again (see ApiKeysRestController's docblock).
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-api-keys-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-api-keys-status]');
    var tableBody = root.querySelector('[data-scp-api-keys-body]');
    var newButton = root.querySelector('[data-scp-new-api-key]');
    var form = root.querySelector('[data-scp-api-key-form]');
    var cancelButton = root.querySelector('[data-scp-cancel-api-key]');
    var reveal = root.querySelector('[data-scp-api-key-reveal]');
    var revealValue = reveal.querySelector('[data-scp-api-key-value]');
    var dismissButton = root.querySelector('[data-scp-dismiss-api-key]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function statusBadgeCell(apiKey) {
        var cell = document.createElement('td');
        var badge = document.createElement('span');
        var isRevoked = Boolean(apiKey.revoked_at);
        badge.className = 'scp-badge ' + (isRevoked ? 'scp-badge--inactive' : 'scp-badge--active');
        badge.textContent = isRevoked ? scpPanelText.apiKeyRevoked : scpPanelText.apiKeyActive;
        cell.appendChild(badge);
        return cell;
    }

    function renderRow(apiKey) {
        var tr = document.createElement('tr');

        [apiKey.label, apiKey.key_prefix, String(apiKey.user_id), apiKey.last_used_at || '-'].forEach(
            function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                tr.appendChild(cell);
            }
        );

        tr.appendChild(statusBadgeCell(apiKey));

        var actionsCell = document.createElement('td');

        if (!apiKey.revoked_at) {
            var revokeButton = document.createElement('button');
            revokeButton.type = 'button';
            revokeButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            revokeButton.textContent = scpPanelText.apiKeyRevokeAction;
            revokeButton.addEventListener('click', function () {
                revoke(apiKey.id);
            });
            actionsCell.appendChild(revokeButton);
        }

        tr.appendChild(actionsCell);

        return tr;
    }

    function loadKeys() {
        apiFetch('api-keys').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            tableBody.innerHTML = '';
            result.data.forEach(function (apiKey) {
                tableBody.appendChild(renderRow(apiKey));
            });
        });
    }

    function revoke(id) {
        if (!window.confirm(scpPanelText.confirmRevokeApiKey)) {
            return;
        }

        apiFetch('api-keys/' + id, { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.saveError, true);
                return;
            }

            loadKeys();
        });
    }

    newButton.addEventListener('click', function () {
        form.hidden = false;
        reveal.hidden = true;
    });

    cancelButton.addEventListener('click', function () {
        form.hidden = true;
        form.reset();
    });

    dismissButton.addEventListener('click', function () {
        reveal.hidden = true;
        revealValue.textContent = '';
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);
        var body = { label: formData.get('label') };
        var userId = formData.get('user_id');

        if (userId) {
            body.user_id = Number(userId);
        }

        apiFetch('api-keys', { method: 'POST', body: JSON.stringify(body) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            form.hidden = true;
            form.reset();
            revealValue.textContent = result.data.key;
            reveal.hidden = false;
            setStatus(scpPanelText.saved);
            loadKeys();
        });
    });

    loadKeys();
})();
