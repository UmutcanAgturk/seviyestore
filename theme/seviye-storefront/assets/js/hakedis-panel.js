/**
 * Cari bakiye (hakediş balance) display + tahsilat (settlement) history for
 * /admin (every branch) and /sube (own branch only) - scp_view_hakedis (HQ)
 * and scp_view_own_hakedis (Şube Müdürü + Muhasebe) land in different
 * zones, so this script picks its view based on which one the current user
 * actually holds (scpPanel.canViewAllBranches, localized from PHP).
 * Recording a new settlement is gated separately (scpPanel.canRecordSettlement,
 * Genel Merkez / Muhasebe only) - a Bölge Müdürü sees the same balances and
 * settlement history a Muhasebe user does, but never the record form,
 * exactly mirroring what the REST layer itself permits.
 *
 * There is no "list every branch's balance" REST endpoint - the HQ view
 * combines the already-public GET /branches with one
 * GET /finance/hakedis/balance/{id} call per branch, client-side.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canViewAllBranches, canRecordSettlement }
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
    var ownAccruedEl = root.querySelector('[data-scp-hakedis-own-accrued]');
    var ownSettledEl = root.querySelector('[data-scp-hakedis-own-settled]');
    var ownBalanceEl = root.querySelector('[data-scp-hakedis-own-balance]');
    var allTable = root.querySelector('[data-scp-hakedis-all]');
    var allBody = root.querySelector('[data-scp-hakedis-all-body]');
    var branchField = root.querySelector('[data-scp-settlement-branch-field]');
    var branchSelect = branchField.querySelector('select');
    var settlementsStatusEl = root.querySelector('[data-scp-settlements-status]');
    var settlementsList = root.querySelector('[data-scp-settlements-list]');
    var settlementForm = root.querySelector('[data-scp-settlement-form]');

    var ownBranchId = null;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function setSettlementsStatus(message, isError) {
        settlementsStatusEl.textContent = message || '';
        settlementsStatusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    var apiFetch = scpApiFetch;

    function formatMoney(amount) {
        return Number(amount).toFixed(2) + ' ₺';
    }

    function methodLabel(method) {
        if (method === 'bank_transfer') {
            return scpPanelText.methodBankTransfer;
        }

        if (method === 'cash') {
            return scpPanelText.methodCash;
        }

        return scpPanelText.methodOther;
    }

    function loadOwnBalance() {
        ownBlock.hidden = false;

        apiFetch('finance/hakedis/balance/me').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            ownAccruedEl.textContent = formatMoney(result.data.accrued);
            ownSettledEl.textContent = formatMoney(result.data.settled);
            ownBalanceEl.textContent = formatMoney(result.data.balance);
            ownBranchId = result.data.branch_id;

            loadSettlements(ownBranchId);
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

            populateBranchSelect(branches);

            if (branches.length > 0) {
                loadSettlements(Number(branchSelect.value));
            }

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

                    [
                        row.branch.name,
                        formatMoney(row.result.data.accrued),
                        formatMoney(row.result.data.settled),
                        formatMoney(row.result.data.balance)
                    ].forEach(function (text) {
                        var cell = document.createElement('td');
                        cell.textContent = text;
                        tr.appendChild(cell);
                    });

                    allBody.appendChild(tr);
                });
            });
        });
    }

    function populateBranchSelect(branches) {
        branchField.hidden = false;

        var previouslySelected = branchSelect.value;
        branchSelect.innerHTML = '';

        branches.forEach(function (branch) {
            var option = document.createElement('option');
            option.value = String(branch.id);
            option.textContent = branch.name;
            branchSelect.appendChild(option);
        });

        if (previouslySelected) {
            branchSelect.value = previouslySelected;
        }
    }

    function currentBranchId() {
        return scpPanel.canViewAllBranches ? Number(branchSelect.value) : ownBranchId;
    }

    function loadSettlements(branchId) {
        setSettlementsStatus('');
        settlementsList.innerHTML = '';

        apiFetch('finance/hakedis/settlements/' + branchId).then(function (result) {
            if (!result.ok) {
                setSettlementsStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                return;
            }

            if (result.data.length === 0) {
                setSettlementsStatus(scpPanelText.noSettlements);
                return;
            }

            result.data.forEach(function (settlement) {
                var li = document.createElement('li');

                var label = document.createElement('span');
                label.textContent = formatMoney(settlement.amount) + ' - ' + methodLabel(settlement.method)
                    + (settlement.note ? ' (' + settlement.note + ')' : '');
                li.appendChild(label);

                var date = document.createElement('span');
                date.textContent = settlement.created_at;
                li.appendChild(date);

                settlementsList.appendChild(li);
            });
        });
    }

    function initSettlementForm() {
        if (!scpPanel.canRecordSettlement) {
            return;
        }

        settlementForm.hidden = false;

        settlementForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var branchId = currentBranchId();

            if (!branchId) {
                return;
            }

            var formData = new FormData(settlementForm);

            apiFetch('finance/hakedis/settlements', {
                method: 'POST',
                body: JSON.stringify({
                    branch_id: branchId,
                    amount: Number(formData.get('amount')),
                    method: formData.get('method'),
                    note: formData.get('note') || null
                })
            }).then(function (result) {
                if (!result.ok) {
                    setSettlementsStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                settlementForm.reset();
                setStatus(scpPanelText.settlementRecorded);

                if (scpPanel.canViewAllBranches) {
                    loadAllBalances();
                } else {
                    loadOwnBalance();
                }
            });
        });
    }

    if (scpPanel.canViewAllBranches) {
        branchSelect.addEventListener('change', function () {
            loadSettlements(Number(branchSelect.value));
        });

        loadAllBalances();
    } else {
        loadOwnBalance();
    }

    initSettlementForm();
})();
