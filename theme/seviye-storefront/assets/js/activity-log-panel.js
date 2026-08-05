/**
 * Aktivite Günlüğü panel for /admin, Genel Merkez only (scp_view_audit_logs -
 * see plugin/seviye-core/src/Rbac/Capability.php, granted since the
 * platform's very first RBAC pass but unused until now). Reads
 * seviye/v1/core/activity-log, backed by
 * Seviye\Core\Logging\RequestActivityLogger (every state-changing seviye/v1
 * request, logged generically) plus the pre-existing
 * Seviye\Security\Auth\AuthService login log entries.
 *
 * ROUTE_LABELS translates the raw "METHOD /seviye/v1/route" messages
 * RequestActivityLogger writes into a short Turkish description, purely
 * for readability - an unrecognised route just falls back to showing the
 * raw method+route, so a newly added endpoint is never hidden, only less
 * prettily labelled until this table is extended.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-activity-log-panel');

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

    var statusEl = root.querySelector('[data-scp-activity-log-status]');
    var form = root.querySelector('[data-scp-activity-log-form]');
    var table = root.querySelector('[data-scp-activity-log-table]');
    var tableBody = root.querySelector('[data-scp-activity-log-body]');
    var apiFetch = scpApiFetch;

    var ROUTE_LABELS = [
        [/^POST \/seviye\/v1\/students$/, 'activityStudentCreated'],
        [/^PUT \/seviye\/v1\/students\/\d+$/, 'activityStudentUpdated'],
        [/^DELETE \/seviye\/v1\/students\/\d+$/, 'activityStudentDeleted'],
        [/^POST \/seviye\/v1\/students\/\d+\/parents$/, 'activityParentLinked'],
        [/^PUT \/seviye\/v1\/students\/\d+\/parents\/\d+$/, 'activityParentUpdated'],
        [/^DELETE \/seviye\/v1\/students\/\d+\/parents\/\d+$/, 'activityParentUnlinked'],
        [/^POST \/seviye\/v1\/commerce\/products$/, 'activityProductCreated'],
        [/^PUT \/seviye\/v1\/commerce\/products\/\d+\/branches/, 'activityProductBranchStatusChanged'],
        [/^PUT \/seviye\/v1\/commerce\/products\/\d+$/, 'activityProductUpdated'],
        [/^DELETE \/seviye\/v1\/commerce\/products\/\d+$/, 'activityProductDeleted'],
        [/^POST \/seviye\/v1\/pricing\/rules$/, 'activityPriceRuleCreated'],
        [/^PUT \/seviye\/v1\/pricing\/rules\/\d+$/, 'activityPriceRuleUpdated'],
        [/^DELETE \/seviye\/v1\/pricing\/rules\/\d+$/, 'activityPriceRuleDeleted'],
        [/^POST \/seviye\/v1\/branches$/, 'activityBranchCreated'],
        [/^PUT \/seviye\/v1\/branches\/\d+$/, 'activityBranchUpdated'],
        [/^POST \/seviye\/v1\/finance\/hakedis\/settlements$/, 'activitySettlementRecorded'],
        [/^POST \/seviye\/v1\/core\/branding$/, 'activityBrandingSaved'],
        [/^DELETE \/seviye\/v1\/core\/branding$/, 'activityBrandingRemoved'],
        [/^Login succeeded\.$/, 'activityLoginSucceeded'],
        [/^Login failed\.$/, 'activityLoginFailed'],
        [/^Login throttled\.$/, 'activityLoginThrottled']
    ];

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function describe(message) {
        for (var i = 0; i < ROUTE_LABELS.length; i++) {
            if (ROUTE_LABELS[i][0].test(message)) {
                return scpPanelText[ROUTE_LABELS[i][1]] + ' (' + message + ')';
            }
        }

        return message;
    }

    function currentParams() {
        var formData = new FormData(form);
        var params = new URLSearchParams();

        ['channel', 'level', 'user_id', 'from', 'to', 'search'].forEach(function (name) {
            var value = formData.get(name);

            if (value) {
                params.set(name, value);
            }
        });

        return params;
    }

    function renderRows(entries) {
        tableBody.innerHTML = '';
        table.hidden = entries.length === 0;

        entries.forEach(function (entry) {
            var tr = document.createElement('tr');

            [
                entry.created_at,
                entry.user_name || (entry.user_id ? '#' + entry.user_id : scpPanelTextData.summaryNotSet),
                entry.channel,
                entry.level,
                describe(entry.message),
                entry.ip_address || ''
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                tr.appendChild(cell);
            });

            tableBody.appendChild(tr);
        });

        setStatus(entries.length === 0 ? scpPanelTextData.noActivityLogData : '');
    }

    function loadEntries() {
        apiFetch('core/activity-log?' + currentParams().toString()).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            renderRows(result.data);
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadEntries();
    });

    loadEntries();
})();
