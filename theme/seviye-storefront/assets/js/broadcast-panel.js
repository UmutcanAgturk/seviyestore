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
    var previewButton = root.querySelector('[data-scp-broadcast-preview]');
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

    /**
     * "Toplu duyuru/e-posta gönderiminde önizleme modu" - gerçek gönderim
     * ucuna (`notifications/broadcast`) HİÇ İSTEK ATMIYOR; başlık/mesaj/
     * kanal seçimleri zaten form'un kendisinde bulunduğu için tamamen
     * istemci tarafında, `.scp-modal`/`.scp-modal-overlay` kalıbını
     * (bölüm 89'un klavye kısayolları yardımıyla AYNI) yeniden kullanan
     * bir modal açıyor. Her seçili kanal için AYRI bir kart - e-posta
     * bir "zarf" görünümünde (başlık + gövde), panel bildirimi ZATEN VAR
     * olan `.scp-notification-item` görünümünü taklit ediyor, SMS/WhatsApp
     * ise 160 karakter sınırını aşan mesajlarda kesileceğini gösteren düz
     * bir baloncuk.
     */
    function openPreview() {
        var subject = form.subject.value.trim();
        var body = form.body.value.trim();
        var channels = selectedChannels();

        var overlay = document.createElement('div');
        overlay.className = 'scp-modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'scp-modal scp-broadcast-preview-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        var title = document.createElement('h2');
        title.textContent = scpPanelTextData.broadcastPreviewTitle;
        modal.appendChild(title);

        if (channels.length === 0) {
            var noChannel = document.createElement('p');
            noChannel.className = 'scp-status scp-status--error';
            noChannel.textContent = scpPanelTextData.broadcastPreviewNoChannel;
            modal.appendChild(noChannel);
        }

        if (channels.indexOf('email') !== -1) {
            var emailCard = document.createElement('div');
            emailCard.className = 'scp-broadcast-preview-card scp-broadcast-preview-card--email';

            var emailLabel = document.createElement('span');
            emailLabel.className = 'scp-broadcast-preview-card__label';
            emailLabel.textContent = scpPanelTextData.broadcastPreviewEmailLabel;
            emailCard.appendChild(emailLabel);

            var emailSubject = document.createElement('strong');
            emailSubject.textContent = subject || scpPanelTextData.broadcastPreviewEmptySubject;
            emailCard.appendChild(emailSubject);

            var emailBody = document.createElement('p');
            emailBody.textContent = body || scpPanelTextData.broadcastPreviewEmptyBody;
            emailCard.appendChild(emailBody);

            modal.appendChild(emailCard);
        }

        if (channels.indexOf('panel') !== -1) {
            var panelCard = document.createElement('div');
            panelCard.className = 'scp-broadcast-preview-card scp-broadcast-preview-card--panel';

            var panelLabel = document.createElement('span');
            panelLabel.className = 'scp-broadcast-preview-card__label';
            panelLabel.textContent = scpPanelTextData.broadcastPreviewPanelLabel;
            panelCard.appendChild(panelLabel);

            var panelSubject = document.createElement('strong');
            panelSubject.textContent = subject || scpPanelTextData.broadcastPreviewEmptySubject;
            panelCard.appendChild(panelSubject);

            var panelBody = document.createElement('p');
            panelBody.textContent = body || scpPanelTextData.broadcastPreviewEmptyBody;
            panelCard.appendChild(panelBody);

            modal.appendChild(panelCard);
        }

        ['sms', 'whatsapp'].forEach(function (channel) {
            if (channels.indexOf(channel) === -1) {
                return;
            }

            var card = document.createElement('div');
            card.className = 'scp-broadcast-preview-card scp-broadcast-preview-card--' + channel;

            var label = document.createElement('span');
            label.className = 'scp-broadcast-preview-card__label';
            label.textContent = channel === 'sms'
                ? scpPanelTextData.broadcastPreviewSmsLabel
                : scpPanelTextData.broadcastPreviewWhatsappLabel;
            card.appendChild(label);

            var text = (subject ? subject + ': ' : '') + body;
            var bubble = document.createElement('p');
            bubble.textContent = text || scpPanelTextData.broadcastPreviewEmptyBody;
            card.appendChild(bubble);

            if (text.length > 160) {
                var truncationNote = document.createElement('small');
                truncationNote.textContent = scpPanelTextData.broadcastPreviewTruncationNote;
                card.appendChild(truncationNote);
            }

            modal.appendChild(card);
        });

        var actions = document.createElement('div');
        actions.className = 'scp-modal__actions';

        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'scp-btn';
        closeButton.textContent = scpPanelTextData.broadcastPreviewClose;
        actions.appendChild(closeButton);
        modal.appendChild(actions);

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
            }
        }

        closeButton.addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        closeButton.focus();
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

    if (previewButton) {
        previewButton.addEventListener('click', openPreview);
    }

    if (scpPanelData.canViewAllBranches) {
        apiFetch('branches').then(function (result) {
            if (result.ok) {
                populateBranchSelect(result.data);
            }
        });
    }

    loadScheduled();
})();
