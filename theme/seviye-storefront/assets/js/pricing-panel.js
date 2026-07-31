/**
 * Price rule management for /admin (every branch) and /sube (own branch
 * only) - scp_manage_pricing is granted to Şube Müdürü too, unlike
 * scp_manage_branches, so this panel (unlike the branches one) renders in
 * both zones. Looks up rules by a plain numeric product id (see the
 * "Ürünler" panel for the catalog itself).
 *
 * The GENERAL scope option is narrower than "every other HQ action" here:
 * gated on scpPanel.canManageBasePricing (Genel Merkez/Sistem only), NOT
 * scpPanel.canManageAllBranches (which Bölge Müdürü also has) - a
 * BRANCH/STUDENT rule may never undercut its product's GENERAL rule, so
 * only whoever may SET that floor gets the option at all.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce, canManageAllBranches, canManageBasePricing }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-pricing-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var lookupForm = root.querySelector('[data-scp-price-lookup-form]');
    var statusEl = root.querySelector('[data-scp-pricing-status]');
    var resultsBlock = root.querySelector('[data-scp-price-rules-results]');
    var tableBody = root.querySelector('[data-scp-price-rules-body]');
    var form = root.querySelector('[data-scp-price-rule-form]');
    var scopeSelect = form.querySelector('[name="scope"]');
    var targetField = root.querySelector('[data-scp-price-target-field]');
    var targetLabel = root.querySelector('[data-scp-price-target-label]');
    var targetInput = targetField.querySelector('input');
    var statusField = root.querySelector('[data-scp-price-status-field]');
    var deleteButton = root.querySelector('[data-scp-delete-price-rule]');

    var currentProductId = null;

    if (!scpPanel.canManageBasePricing) {
        var generalOption = scopeSelect.querySelector('[data-scp-scope-general]');

        if (generalOption) {
            generalOption.remove();
        }
    }

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function statusBadgeCell(status) {
        var cell = document.createElement('td');
        var badge = document.createElement('span');
        var isActive = status === 'active';
        badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
        badge.textContent = isActive ? scpPanelText.statusActive : scpPanelText.statusInactive;
        cell.appendChild(badge);
        return cell;
    }

    var apiFetch = scpApiFetch;

    function scopeLabel(scope) {
        if (scope === 'branch') {
            return scpPanelText.scopeBranch;
        }

        if (scope === 'student') {
            return scpPanelText.scopeStudent;
        }

        return scpPanelText.scopeGeneral;
    }

    function loadPriceRules() {
        apiFetch('pricing/rules?product_id=' + currentProductId).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                resultsBlock.hidden = true;
                return;
            }

            setStatus('');
            resultsBlock.hidden = false;
            form.hidden = true;
            renderPriceRules(result.data);
        });
    }

    function renderPriceRules(rules) {
        tableBody.innerHTML = '';

        rules.forEach(function (rule) {
            var row = document.createElement('tr');

            var scopeCell = document.createElement('td');
            scopeCell.textContent = scopeLabel(rule.scope);
            row.appendChild(scopeCell);

            var targetCell = document.createElement('td');
            var targetId = rule.branch_id !== null ? rule.branch_id : rule.student_id;
            targetCell.textContent = targetId !== null ? String(targetId) : '';
            row.appendChild(targetCell);

            var priceCell = document.createElement('td');
            priceCell.textContent = String(rule.price);
            row.appendChild(priceCell);

            row.appendChild(statusBadgeCell(rule.status));

            var actionsCell = document.createElement('td');

            if (rule.scope !== 'general' || scpPanel.canManageBasePricing) {
                var editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                editButton.textContent = scpPanelText.edit;
                editButton.addEventListener('click', function () {
                    openPriceRuleForm(rule);
                });
                actionsCell.appendChild(editButton);
            }

            row.appendChild(actionsCell);

            tableBody.appendChild(row);
        });
    }

    function updateTargetField(scope) {
        if (scope === 'general' || (scope === 'branch' && !scpPanel.canManageAllBranches)) {
            targetField.hidden = true;
            targetInput.required = false;
            return;
        }

        targetField.hidden = false;
        targetInput.required = true;
        targetLabel.textContent = scope === 'branch' ? scpPanelText.branchIdLabel : scpPanelText.studentIdLabel;
    }

    function openPriceRuleForm(rule) {
        form.hidden = false;
        setStatus('');
        form.reset();
        form.id.value = rule ? rule.id : '';
        form.price.value = rule ? String(rule.price) : '';
        scopeSelect.disabled = Boolean(rule);
        statusField.hidden = !rule;
        deleteButton.hidden = !rule;

        if (rule) {
            scopeSelect.value = rule.scope;
            form.status.value = rule.status;
            targetInput.value = String(rule.branch_id !== null ? rule.branch_id : (rule.student_id || ''));
        } else {
            targetInput.value = '';
        }

        updateTargetField(scopeSelect.value);
    }

    lookupForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var productId = parseInt(lookupForm.product_id.value, 10);

        if (!productId) {
            return;
        }

        currentProductId = productId;
        loadPriceRules();
    });

    scopeSelect.addEventListener('change', function () {
        updateTargetField(scopeSelect.value);
    });

    root.querySelector('[data-scp-new-price-rule]').addEventListener('click', function () {
        openPriceRuleForm(null);
    });

    root.querySelector('[data-scp-cancel-price-rule]').addEventListener('click', function () {
        form.hidden = true;
    });

    deleteButton.addEventListener('click', function () {
        var id = form.id.value;

        if (!id || !window.confirm(scpPanelText.confirmDeletePriceRule)) {
            return;
        }

        apiFetch('pricing/rules/' + id, { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.priceRuleDeleted);
            form.hidden = true;
            loadPriceRules();
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = form.id.value;
        var payload = { price: parseFloat(form.price.value) };
        var path = 'pricing/rules';
        var method = 'POST';

        if (id) {
            payload.status = form.status.value;
            path = 'pricing/rules/' + id;
            method = 'PUT';
        } else {
            payload.product_id = currentProductId;
            payload.scope = scopeSelect.value;

            if (targetInput.value) {
                payload.target_id = parseInt(targetInput.value, 10);
            }
        }

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus(scpPanelText.saved);
            form.hidden = true;
            loadPriceRules();
        });
    });
})();
