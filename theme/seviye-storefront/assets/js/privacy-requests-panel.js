/**
 * "Verilerim (KVKK)" self-service card (every zone, never capability-gated -
 * see templates/partials/privacy-requests.php) + (only when
 * scpPanel.canManagePrivacyRequests) the admin review queue card in
 * templates/zone.php. One script for both, like reports-panel.js's
 * HQ-vs-own split, since they share the same REST resource
 * (seviye/v1/privacy/requests/*, see
 * plugin/seviye-security/src/Http/PrivacyRequestsRestController.php).
 *
 * The export button bypasses scpApiFetch (which always expects a JSON
 * {ok, data} envelope) - the export endpoint is a POST that returns a raw
 * file download, the same "special content-type/response, own raw fetch()"
 * reasoning scpUploadMedia() already documents in scp-api-fetch.js.
 *
 * Expects globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManagePrivacyRequests }
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

    function initSelfService() {
        var root = document.getElementById('scp-privacy-requests-panel');

        if (!root) {
            return;
        }

        var statusEl = root.querySelector('[data-scp-privacy-status]');
        var exportButton = root.querySelector('[data-scp-privacy-export]');
        var deletionForm = root.querySelector('[data-scp-privacy-deletion-form]');
        var historyTable = root.querySelector('[data-scp-privacy-history-table]');
        var historyBody = root.querySelector('[data-scp-privacy-history-body]');

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.classList.toggle('scp-status--error', Boolean(isError));
        }

        function loadHistory() {
            apiFetch('privacy/requests/mine').then(function (result) {
                if (!result.ok) {
                    return;
                }

                historyBody.innerHTML = '';
                historyTable.hidden = result.data.length === 0;

                result.data.forEach(function (item) {
                    var row = document.createElement('tr');
                    [
                        item.type === 'export' ? scpPanelTextData.privacyTypeExport : scpPanelTextData.privacyTypeDeletion,
                        scpPanelText['privacyStatus_' + item.status] || item.status,
                        item.requested_at,
                        item.resolution_note || ''
                    ].forEach(function (text) {
                        var cell = document.createElement('td');
                        cell.textContent = text;
                        row.appendChild(cell);
                    });
                    historyBody.appendChild(row);
                });
            });
        }

        exportButton.addEventListener('click', function () {
            setStatus('');

            fetch(scpPanelData.restUrl + 'privacy/requests/export', {
                method: 'POST',
                headers: { 'X-WP-Nonce': scpPanelData.nonce },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (data) {
                        setStatus((data && data.message) || scpPanelTextData.loadError, true);
                    });
                }

                return response.blob().then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'veri-ihraci.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    setStatus(scpPanelTextData.privacyExported);
                    loadHistory();
                });
            });
        });

        deletionForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!window.confirm(scpPanelTextData.confirmPrivacyDeletion)) {
                return;
            }

            var formData = new FormData(deletionForm);

            apiFetch('privacy/requests/deletion', {
                method: 'POST',
                body: JSON.stringify({ note: formData.get('note') || '' })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                deletionForm.reset();
                setStatus(scpPanelTextData.privacyDeletionRequested);
                loadHistory();
            });
        });

        loadHistory();
    }

    function initAdminQueue() {
        var root = document.getElementById('scp-privacy-requests-queue-panel');

        if (!root || !scpPanelData.canManagePrivacyRequests) {
            return;
        }

        var statusEl = root.querySelector('[data-scp-privacy-queue-status]');
        var table = root.querySelector('[data-scp-privacy-queue-table]');
        var body = root.querySelector('[data-scp-privacy-queue-body]');

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.classList.toggle('scp-status--error', Boolean(isError));
        }

        function resolve(id, action) {
            var resolutionNote = window.prompt(scpPanelTextData.privacyResolutionNotePrompt, '') || '';

            apiFetch('privacy/requests/' + id + '/' + action, {
                method: 'POST',
                body: JSON.stringify({ resolution_note: resolutionNote })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.saved);
                load();
            });
        }

        function renderRow(item) {
            var row = document.createElement('tr');
            [item.user_display_name || '', item.user_email || '', item.requested_at, item.note || ''].forEach(
                function (text) {
                    var cell = document.createElement('td');
                    cell.textContent = text;
                    row.appendChild(cell);
                }
            );

            var actionsCell = document.createElement('td');

            var approveButton = document.createElement('button');
            approveButton.type = 'button';
            approveButton.className = 'scp-btn scp-btn--small';
            approveButton.textContent = scpPanelTextData.privacyApproveAction;
            approveButton.addEventListener('click', function () {
                if (window.confirm(scpPanelTextData.confirmPrivacyApprove)) {
                    resolve(item.id, 'approve');
                }
            });
            actionsCell.appendChild(approveButton);

            var rejectButton = document.createElement('button');
            rejectButton.type = 'button';
            rejectButton.className = 'scp-btn scp-btn--danger scp-btn--small';
            rejectButton.textContent = scpPanelTextData.privacyRejectAction;
            rejectButton.addEventListener('click', function () {
                resolve(item.id, 'reject');
            });
            actionsCell.appendChild(rejectButton);

            row.appendChild(actionsCell);

            return row;
        }

        function load() {
            apiFetch('privacy/requests').then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                body.innerHTML = '';
                table.hidden = result.data.length === 0;
                setStatus(result.data.length === 0 ? scpPanelTextData.noPrivacyRequests : '');
                result.data.forEach(function (item) {
                    body.appendChild(renderRow(item));
                });
            });
        }

        load();
    }

    initSelfService();
    initAdminQueue();
})();
