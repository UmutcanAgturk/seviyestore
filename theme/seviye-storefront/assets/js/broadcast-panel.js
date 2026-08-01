/**
 * "Toplu Duyuru" panel for /admin (every branch or all of them,
 * scp_send_broadcast) and /sube (own branch only,
 * scp_send_own_branch_broadcast) - mirrors reports-panel.js's HQ-vs-own-
 * branch split. Posts to
 * plugin/seviye-notifications/src/Http/BroadcastRestController.php's
 * seviye/v1/notifications/broadcast endpoint.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-broadcast-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-broadcast-status]');
    var form = root.querySelector('[data-scp-broadcast-form]');
    var branchField = root.querySelector('[data-scp-broadcast-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function populateBranchSelect(branches) {
        branchField.hidden = false;
        branchSelect.innerHTML = '';

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = scpPanelText.allBranches;
        branchSelect.appendChild(allOption);

        branches.forEach(function (branch) {
            var option = document.createElement('option');
            option.value = String(branch.id);
            option.textContent = branch.name;
            branchSelect.appendChild(option);
        });
    }

    function selectedChannels() {
        var channels = [];

        if (form.channel_email.checked) {
            channels.push('email');
        }

        if (form.channel_panel.checked) {
            channels.push('panel');
        }

        if (form.channel_sms.checked) {
            channels.push('sms');
        }

        return channels;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var payload = {
            subject: form.subject.value,
            body: form.body.value,
            channels: selectedChannels()
        };

        if (scpPanel.canViewAllBranches && branchSelect.value) {
            payload.branch_id = Number(branchSelect.value);
        }

        apiFetch('notifications/broadcast', {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            form.reset();
            form.channel_email.checked = true;
            form.channel_panel.checked = true;
            setStatus(
                result.data.recipient_count
                    + ' ' + scpPanelText.broadcastSentSuffix
            );
        });
    });

    if (scpPanel.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }
})();
