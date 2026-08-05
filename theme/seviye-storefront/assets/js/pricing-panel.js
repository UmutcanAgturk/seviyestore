/**
 * Price rule management for /admin (every branch) and /sube (own branch
 * only) - scp_manage_pricing is granted to Şube Müdürü too, unlike
 * scp_manage_branches, so this panel (unlike the branches one) renders in
 * both zones. Looks up rules by picking a product from the catalog (a
 * <datalist>-backed search box, populated from commerce/products) instead
 * of typing its raw id by hand - "ürün id'si girmek yerine direkt ürün
 * seçilip fiyat güncellemesi yapılsın".
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
    var productSearchInput = lookupForm.querySelector('[name="product_search"]');
    var productOptionsList = root.querySelector('#scp-pricing-product-options');
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
    var importForm = root.querySelector('[data-scp-price-import-form]');
    var importFileInput = importForm.querySelector('[name="import_file"]');
    var importResult = root.querySelector('[data-scp-price-import-result]');
    var importSummary = root.querySelector('[data-scp-price-import-summary]');
    var importErrorsList = root.querySelector('[data-scp-price-import-errors]');
    var importTemplateLink = root.querySelector('[data-scp-download-price-import-template]');

    var currentProductId = null;
    var productIdsByLabel = {};

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

    /**
     * "Ürün id'si girmek yerine direkt ürün seçilip fiyat güncellemesi
     * yapılsın" - populates the lookup field's <datalist> with every
     * product's "Ad (#id)" label, so picking one from the browser's own
     * autocomplete resolves straight to an id via productIdsByLabel; no
     * separate autocomplete widget needed.
     */
    function loadProductOptions() {
        apiFetch('commerce/products').then(function (result) {
            if (!result.ok) {
                return;
            }

            productOptionsList.innerHTML = '';
            productIdsByLabel = {};

            result.data.forEach(function (product) {
                var label = product.name + ' (#' + product.id + ')';
                productIdsByLabel[label] = product.id;

                var option = document.createElement('option');
                option.value = label;
                productOptionsList.appendChild(option);
            });
        });
    }

    /**
     * An exact datalist match resolves directly; otherwise (the user typed
     * a bare id, or a label the list doesn't have) falls back to the last
     * run of digits in the input - covers "#123", "123", or a half-typed
     * label ending in the id.
     */
    function resolveProductId(typed) {
        if (Object.prototype.hasOwnProperty.call(productIdsByLabel, typed)) {
            return productIdsByLabel[typed];
        }

        var match = typed.match(/(\d+)\D*$/);

        return match ? parseInt(match[1], 10) : null;
    }

    loadProductOptions();

    lookupForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var productId = resolveProductId(productSearchInput.value.trim());

        if (!productId) {
            setStatus(scpPanelText.pricingProductNotFound, true);
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

    // ---- Toplu İçe Aktarma (CSV veya Excel) ----

    importTemplateLink.addEventListener('click', function (event) {
        event.preventDefault();

        var csv = '﻿product_id,scope,target_id,price\n'
            + '123,general,,99.90\n';
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'fiyat-kurali-ice-aktarma-sablonu.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });

    function renderPriceImportResult(data) {
        importResult.hidden = false;
        importSummary.textContent = scpPanelText.pricingImportSummary
            .replace('%1$d', String(data.imported_count))
            .replace('%2$d', String(data.error_count));

        importErrorsList.innerHTML = '';
        (data.errors || []).forEach(function (error) {
            var item = document.createElement('li');
            item.textContent = scpPanelText.importErrorLine
                .replace('%1$d', String(error.line))
                .replace('%2$s', error.message);
            importErrorsList.appendChild(item);
        });
    }

    /**
     * A base64-encoded .xlsx binary can't go through btoa(String.fromCharCode
     * (...allBytes)) in one call without risking a call-stack overflow on a
     * large-ish file - chunking keeps each String.fromCharCode.apply() call
     * small regardless of file size.
     */
    function arrayBufferToBase64(buffer) {
        var bytes = new Uint8Array(buffer);
        var chunkSize = 0x8000;
        var chunks = [];

        for (var i = 0; i < bytes.length; i += chunkSize) {
            chunks.push(String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize)));
        }

        return window.btoa(chunks.join(''));
    }

    function submitImport(payload) {
        apiFetch('pricing/rules/import', {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                return;
            }

            setStatus('');
            renderPriceImportResult(result.data);
            importForm.reset();
        });
    }

    importForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var file = importFileInput.files[0];

        if (!file) {
            return;
        }

        importResult.hidden = true;
        setStatus(scpPanelText.importing);

        var isXlsx = /\.xlsx$/i.test(file.name)
            || file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        var reader = new FileReader();

        if (isXlsx) {
            reader.onload = function () {
                submitImport({ xlsx_base64: arrayBufferToBase64(reader.result) });
            };
            reader.readAsArrayBuffer(file);
        } else {
            reader.onload = function () {
                submitImport({ csv: String(reader.result) });
            };
            reader.readAsText(file, 'UTF-8');
        }
    });
})();
