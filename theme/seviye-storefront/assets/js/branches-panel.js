/**
 * Branch management for /admin only - scp_manage_branches is granted
 * exclusively to Genel Merkez/Bölge Müdürü, who are the only roles the
 * admin zone ever admits.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-branches-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-branches-status]');
    var tableBody = root.querySelector('[data-scp-branches-body]');
    var form = root.querySelector('[data-scp-branch-form]');
    var statusField = root.querySelector('[data-scp-branch-status-field]');

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
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    }

    function loadBranches() {
        apiFetch('branches').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            renderBranches(result.data);
        });
    }

    function renderBranches(branches) {
        tableBody.innerHTML = '';

        branches.forEach(function (branch) {
            var row = document.createElement('tr');

            [
                branch.name,
                branch.iban || '',
                String(branch.commission_rate),
                branch.phone || '',
                branch.status
            ].forEach(function (text) {
                var cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });

            var actionsCell = document.createElement('td');
            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelText.edit;
            editButton.addEventListener('click', function () {
                openBranchForm(branch);
            });
            actionsCell.appendChild(editButton);
            row.appendChild(actionsCell);

            tableBody.appendChild(row);
        });
    }

    function openBranchForm(branch) {
        form.hidden = false;
        setStatus('');
        form.reset();
        form.id.value = branch ? branch.id : '';
        form.name.value = branch ? branch.name : '';
        form.iban.value = branch ? (branch.iban || '') : '';
        form.commission_rate.value = branch ? String(branch.commission_rate) : '';
        form.phone.value = branch ? (branch.phone || '') : '';
        form.address.value = branch ? (branch.address || '') : '';

        statusField.hidden = !branch;
        if (branch) {
            form.status.value = branch.status;
        }
    }

    root.querySelector('[data-scp-new-branch]').addEventListener('click', function () {
        openBranchForm(null);
    });

    root.querySelector('[data-scp-cancel-branch]').addEventListener('click', function () {
        form.hidden = true;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = form.id.value;
        var payload = {
            name: form.name.value,
            iban: form.iban.value,
            commission_rate: parseFloat(form.commission_rate.value),
            phone: form.phone.value,
            address: form.address.value
        };

        if (id) {
            payload.status = form.status.value;
        }

        var path = id ? 'branches/' + id : 'branches';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.saved);
            form.hidden = true;
            loadBranches();
        });
    });

    loadBranches();
})();
