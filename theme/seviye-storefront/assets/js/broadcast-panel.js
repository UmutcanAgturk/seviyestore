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

    var statusEl = root.querySelector('[data-scp-broadcast-status]');
    var form = root.querySelector('[data-scp-broadcast-form]');
    var branchField = root.querySelector('[data-scp-broadcast-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var scheduledStatusEl = root.querySelector('[data-scp-broadcast-scheduled-status]');
    var scheduledTable = root.querySelector('[data-scp-broadcast-scheduled-table]');
    var scheduledBody = root.querySelector('[data-scp-broadcast-scheduled-body]');
    var apiFetch = scpApiFetch;
    var branchNames = {};

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function setScheduledStatus(message, isError) {
        scheduledStatusEl.textContent = message || '';
        scheduledStatusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function populateBranchSelect(branches) {
        branchField.hidden = false;
        branchSelect.innerHTML = '';

        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = scpPanelTextData.allBranches;
        branchSelect.appendChild(allOption);

        branches.forEach(function (branch) {
            var option = document.createElement('option');
            option.value = String(branch.id);
            option.textContent = branch.name;
            branchSelect.appendChild(option);
            branchNames[branch.id] = branch.name;
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

        if (form.channel_whatsapp.checked) {
            channels.push('whatsapp');
        }

        return channels;
    }

    function scheduledStatusLabel(status) {
        return scpPanelText['broadcastScheduledStatus_' + status] || status;
    }

    function scheduledStatusBadgeClass(status) {
        if (status === 'sent') {
            return 'scp-badge--active';
        }

        if (status === 'cancelled') {
            return 'scp-badge--inactive';
        }

        return 'scp-badge--warning';
    }

    function renderScheduled(broadcasts) {
        scheduledBody.innerHTML = '';

        if (broadcasts.length === 0) {
            scheduledTable.hidden = true;
            setScheduledStatus(scpPanelTextData.broadcastNoScheduled);
            return;
        }

        scheduledTable.hidden = false;
        setScheduledStatus('');

        broadcasts.forEach(function (broadcast) {
            var row = document.createElement('tr');

            var subjectCell = document.createElement('td');
            subjectCell.textContent = broadcast.subject;
            row.appendChild(subjectCell);

            var branchCell = document.createElement('td');
            branchCell.textContent = broadcast.branch_id
                ? (branchNames[broadcast.branch_id] || String(broadcast.branch_id))
                : scpPanelTextData.allBranches;
            row.appendChild(branchCell);

            var scheduledAtCell = document.createElement('td');
            scheduledAtCell.textContent = broadcast.scheduled_at;
            row.appendChild(scheduledAtCell);

            var statusCell = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'scp-badge ' + scheduledStatusBadgeClass(broadcast.status);
            badge.textContent = scheduledStatusLabel(broadcast.status);
            statusCell.appendChild(badge);
            row.appendChild(statusCell);

            var actionsCell = document.createElement('td');

            if (broadcast.status === 'pending') {
                var cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                cancelButton.textContent = scpPanelTextData.broadcastCancelScheduled;
                cancelButton.addEventListener('click', function () {
                    apiFetch('notifications/broadcast/scheduled/' + broadcast.id, { method: 'DELETE' }).then(
                        function (result) {
                            if (!result.ok) {
                                setScheduledStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                                return;
                            }

                            loadScheduled();
                        }
                    );
                });
                actionsCell.appendChild(cancelButton);
            }

            row.appendChild(actionsCell);
            scheduledBody.appendChild(row);
        });
    }

    function loadScheduled() {
        apiFetch('notifications/broadcast/scheduled').then(function (result) {
            if (!result.ok) {
                setScheduledStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderScheduled(result.data);
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var payload = {
            subject: form.subject.value,
            body: form.body.value,
            channels: selectedChannels()
        };

        if (scpPanelData.canViewAllBranches && branchSelect.value) {
            payload.branch_id = Number(branchSelect.value);
        }

        if (form.scheduled_at.value) {
            payload.scheduled_at = form.scheduled_at.value;
        }

        apiFetch('notifications/broadcast', {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            form.reset();
            form.channel_email.checked = true;
            form.channel_panel.checked = true;

            if (result.data.scheduled_at) {
                setStatus(scpPanelTextData.broadcastScheduled);
                loadScheduled();
                return;
            }

            setStatus(
                result.data.recipient_count
                    + ' ' + scpPanelTextData.broadcastSentSuffix
            );
        });
    });

    if (scpPanelData.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }

    loadScheduled();
})();
