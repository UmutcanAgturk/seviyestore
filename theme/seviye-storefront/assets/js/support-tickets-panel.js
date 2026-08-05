/**
 * "Destek Talepleri" - veli self-service card (templates/partials/support-tickets.php,
 * only rendered for scp_submit_support_ticket) + (only when
 * scpPanel.canManageSupportTickets) the staff queue card in
 * templates/zone.php. One script for both, same "HQ-vs-own split in one
 * file" reasoning as privacy-requests-panel.js, since they share the same
 * REST resource (seviye/v1/destek/tickets/*, see
 * plugin/seviye-destek/src/Http/SupportTicketsRestController.php).
 *
 * Expects globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManageSupportTickets }
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

    function statusLabel(status) {
        return scpPanelText['supportStatus_' + status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'closed') {
            return 'scp-badge--inactive';
        }

        if (status === 'answered') {
            return 'scp-badge--active';
        }

        return 'scp-badge--warning';
    }

    function renderMessages(listEl, ticket) {
        listEl.innerHTML = '';

        ticket.messages.forEach(function (message) {
            var item = document.createElement('li');
            item.className = 'scp-list__item scp-support-message'
                + (message.is_staff ? ' scp-support-message--staff' : '');

            var meta = document.createElement('div');
            meta.className = 'scp-support-message__meta';
            meta.textContent = (message.is_staff ? scpPanelTextData.supportStaffLabel : scpPanelTextData.supportVeliLabel)
                + ' — ' + message.created_at;
            item.appendChild(meta);

            var body = document.createElement('div');
            body.textContent = message.message;
            item.appendChild(body);

            listEl.appendChild(item);
        });
    }

    function initSelfService() {
        var root = document.getElementById('scp-support-tickets-panel');

        if (!root) {
            return;
        }

        var statusEl = root.querySelector('[data-scp-support-status]');
        var newForm = root.querySelector('[data-scp-support-new-form]');
        var branchSelect = root.querySelector('[data-scp-support-branch-select]');
        var listTable = root.querySelector('[data-scp-support-list-table]');
        var listBody = root.querySelector('[data-scp-support-list-body]');
        var detail = root.querySelector('[data-scp-support-detail]');
        var detailTitle = root.querySelector('[data-scp-support-detail-title]');
        var detailClose = root.querySelector('[data-scp-support-detail-close]');
        var messagesList = root.querySelector('[data-scp-support-messages]');
        var replyForm = root.querySelector('[data-scp-support-reply-form]');
        var currentTicketId = null;

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.classList.toggle('scp-status--error', Boolean(isError));
        }

        function populateBranches() {
            apiFetch('students/mine').then(function (result) {
                if (!result.ok) {
                    return;
                }

                var seen = {};
                result.data.forEach(function (student) {
                    if (!student.branch_id || seen[student.branch_id]) {
                        return;
                    }

                    seen[student.branch_id] = true;
                    var option = document.createElement('option');
                    option.value = String(student.branch_id);
                    option.textContent = student.branch_name || String(student.branch_id);
                    branchSelect.appendChild(option);
                });
            });
        }

        function renderTickets(tickets) {
            listBody.innerHTML = '';

            if (tickets.length === 0) {
                listTable.hidden = true;
                setStatus(scpPanelTextData.supportNoTickets);
                return;
            }

            listTable.hidden = false;
            setStatus('');

            tickets.forEach(function (ticket) {
                var row = document.createElement('tr');

                var subjectCell = document.createElement('td');
                subjectCell.textContent = ticket.subject;
                row.appendChild(subjectCell);

                var branchCell = document.createElement('td');
                branchCell.textContent = ticket.branch_name || scpPanelTextData.supportGenelMerkezLabel;
                row.appendChild(branchCell);

                var statusCell = document.createElement('td');
                var badge = document.createElement('span');
                badge.className = 'scp-badge ' + statusBadgeClass(ticket.status);
                badge.textContent = statusLabel(ticket.status);
                statusCell.appendChild(badge);
                row.appendChild(statusCell);

                var updatedCell = document.createElement('td');
                updatedCell.textContent = ticket.updated_at;
                row.appendChild(updatedCell);

                var actionsCell = document.createElement('td');
                var detailButton = document.createElement('button');
                detailButton.type = 'button';
                detailButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                detailButton.textContent = scpPanelTextData.details;
                detailButton.addEventListener('click', function () {
                    openDetail(ticket.id);
                });
                actionsCell.appendChild(detailButton);
                row.appendChild(actionsCell);

                listBody.appendChild(row);
            });
        }

        function loadTickets() {
            apiFetch('destek/tickets/mine').then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                renderTickets(result.data);
            });
        }

        function openDetail(id) {
            apiFetch('destek/tickets/' + id).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                currentTicketId = id;
                detailTitle.textContent = result.data.subject;
                renderMessages(messagesList, result.data);
                detail.hidden = false;
            });
        }

        detailClose.addEventListener('click', function () {
            detail.hidden = true;
            currentTicketId = null;
        });

        newForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var formData = new FormData(newForm);
            var payload = {
                subject: formData.get('subject'),
                message: formData.get('message'),
                branch_id: formData.get('branch_id') || null,
            };

            apiFetch('destek/tickets', { method: 'POST', body: JSON.stringify(payload) }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                newForm.reset();
                setStatus(scpPanelTextData.supportTicketCreated);
                loadTickets();
            });
        });

        replyForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (currentTicketId === null) {
                return;
            }

            var message = new FormData(replyForm).get('message');

            apiFetch('destek/tickets/' + currentTicketId + '/messages', {
                method: 'POST',
                body: JSON.stringify({ message: message }),
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                replyForm.reset();
                renderMessages(messagesList, result.data);
                loadTickets();
            });
        });

        populateBranches();
        loadTickets();
    }

    function initQueue() {
        if (!scpPanelData.canManageSupportTickets) {
            return;
        }

        var root = document.getElementById('scp-support-tickets-queue-panel');

        if (!root) {
            return;
        }

        var statusEl = root.querySelector('[data-scp-support-queue-status]');
        var queueTable = root.querySelector('[data-scp-support-queue-table]');
        var queueBody = root.querySelector('[data-scp-support-queue-body]');
        var detail = root.querySelector('[data-scp-support-queue-detail]');
        var detailTitle = root.querySelector('[data-scp-support-queue-detail-title]');
        var detailClose = root.querySelector('[data-scp-support-queue-detail-close]');
        var closeTicketButton = root.querySelector('[data-scp-support-queue-close-ticket]');
        var messagesList = root.querySelector('[data-scp-support-queue-messages]');
        var replyForm = root.querySelector('[data-scp-support-queue-reply-form]');
        var currentTicketId = null;

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.classList.toggle('scp-status--error', Boolean(isError));
        }

        function renderQueue(tickets) {
            queueBody.innerHTML = '';

            if (tickets.length === 0) {
                queueTable.hidden = true;
                setStatus(scpPanelTextData.supportNoTickets);
                return;
            }

            queueTable.hidden = false;
            setStatus('');

            tickets.forEach(function (ticket) {
                var row = document.createElement('tr');

                var subjectCell = document.createElement('td');
                subjectCell.textContent = ticket.subject;
                row.appendChild(subjectCell);

                var branchCell = document.createElement('td');
                branchCell.textContent = ticket.branch_name || scpPanelTextData.supportGenelMerkezLabel;
                row.appendChild(branchCell);

                var statusCell = document.createElement('td');
                var badge = document.createElement('span');
                badge.className = 'scp-badge ' + statusBadgeClass(ticket.status);
                badge.textContent = statusLabel(ticket.status);
                statusCell.appendChild(badge);
                row.appendChild(statusCell);

                var updatedCell = document.createElement('td');
                updatedCell.textContent = ticket.updated_at;
                row.appendChild(updatedCell);

                var actionsCell = document.createElement('td');
                var detailButton = document.createElement('button');
                detailButton.type = 'button';
                detailButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                detailButton.textContent = scpPanelTextData.details;
                detailButton.addEventListener('click', function () {
                    openDetail(ticket.id);
                });
                actionsCell.appendChild(detailButton);
                row.appendChild(actionsCell);

                queueBody.appendChild(row);
            });
        }

        function loadQueue() {
            apiFetch('destek/tickets').then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                renderQueue(result.data);
            });
        }

        function openDetail(id) {
            apiFetch('destek/tickets/' + id).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                    return;
                }

                currentTicketId = id;
                detailTitle.textContent = result.data.subject;
                closeTicketButton.hidden = result.data.status === 'closed';
                renderMessages(messagesList, result.data);
                detail.hidden = false;
            });
        }

        detailClose.addEventListener('click', function () {
            detail.hidden = true;
            currentTicketId = null;
        });

        replyForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (currentTicketId === null) {
                return;
            }

            var message = new FormData(replyForm).get('message');

            apiFetch('destek/tickets/' + currentTicketId + '/messages', {
                method: 'POST',
                body: JSON.stringify({ message: message }),
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                replyForm.reset();
                renderMessages(messagesList, result.data);
                loadQueue();
            });
        });

        closeTicketButton.addEventListener('click', function () {
            if (currentTicketId === null) {
                return;
            }

            apiFetch('destek/tickets/' + currentTicketId + '/close', { method: 'POST', body: JSON.stringify({}) }).then(
                function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                        return;
                    }

                    detail.hidden = true;
                    currentTicketId = null;
                    setStatus(scpPanelTextData.supportTicketClosed);
                    loadQueue();
                }
            );
        });

        loadQueue();
    }

    initSelfService();
    initQueue();
})();
