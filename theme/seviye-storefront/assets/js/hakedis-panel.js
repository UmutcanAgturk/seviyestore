/**
 * Cari bakiye (hakediş balance) display for /admin (every branch) and
 * /sube (own branch only) - scp_view_hakedis (HQ) and scp_view_own_hakedis
 * (Şube Müdürü + Muhasebe) land in different zones, so this script picks
 * its view based on which one the current user actually holds
 * (scpPanel.canViewAllBranches, localized from PHP).
 *
 * There is no "list every branch's balance" REST endpoint - the HQ view
 * combines the already-public GET /branches with one
 * GET /finance/hakedis/balance/{id} call per branch, client-side.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-hakedis-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-hakedis-status]');
    var ownBlock = root.querySelector('[data-scp-hakedis-own]');
    var ownBalanceEl = root.querySelector('[data-scp-hakedis-own-balance]');
    var allTable = root.querySelector('[data-scp-hakedis-all]');
    var allBody = root.querySelector('[data-scp-hakedis-all-body]');

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function apiFetch(path) {
        return fetch(scpPanel.restUrl + path, {
            headers: { 'X-WP-Nonce': scpPanel.nonce },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        });
    }

    function formatBalance(amount) {
        return Number(amount).toFixed(2) + ' ₺';
    }

    function loadOwnBalance() {
        ownBlock.hidden = false;

        apiFetch('finance/hakedis/balance/me').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            ownBalanceEl.textContent = formatBalance(result.data.balance);
        });
    }

    function loadAllBalances() {
        allTable.hidden = false;

        apiFetch('branches').then(function (branchesResult) {
            if (!branchesResult.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            var branches = branchesResult.data;

            Promise.all(branches.map(function (branch) {
                return apiFetch('finance/hakedis/balance/' + branch.id).then(function (balanceResult) {
                    return { branch: branch, result: balanceResult };
                });
            })).then(function (rows) {
                allBody.innerHTML = '';

                rows.forEach(function (row) {
                    if (!row.result.ok) {
                        return;
                    }

                    var tr = document.createElement('tr');

                    var nameCell = document.createElement('td');
                    nameCell.textContent = row.branch.name;
                    tr.appendChild(nameCell);

                    var balanceCell = document.createElement('td');
                    balanceCell.textContent = formatBalance(row.result.data.balance);
                    tr.appendChild(balanceCell);

                    allBody.appendChild(tr);
                });
            });
        });
    }

    if (scpPanel.canViewAllBranches) {
        loadAllBalances();
    } else {
        loadOwnBalance();
    }
})();
