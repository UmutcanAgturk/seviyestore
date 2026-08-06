/**
 * "Vergi Oranları" management for /admin - HQ-only
 * (scp_manage_tax_rates). Defines named tax rate classes (isim + yüzde)
 * that the Ürünler edit page's own "Vergi Oranı" dropdown then lets
 * anyone editing a product pick from - see
 * plugin/seviye-commerce/src/Support/TaxRateGateway.php for how this maps
 * onto WooCommerce's own tax engine. Structure copied from
 * coupons-panel.js.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-tax-rates-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    // Captured once, synchronously, at script load - see
    // coupons-panel.js's own comment on why (bölüm 68 - scpPanel/
    // scpPanelText are shared globals several other scripts on this same
    // page also localize under the same names).
    var scpPanelData = scpPanel;
    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-tax-rates-status]');
    var tableBody = root.querySelector('[data-scp-tax-rates-body]');
    var form = root.querySelector('[data-scp-tax-rate-form]');
    var nameField = form.querySelector('[data-scp-tax-rate-name-field]');

    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function loadTaxRates() {
        apiFetch('commerce/tax-rates').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderTaxRates(result.data);
        });
    }

    function renderTaxRates(rates) {
        tableBody.innerHTML = '';

        rates.forEach(function (rate) {
            var row = document.createElement('tr');

            var nameCell = document.createElement('td');
            nameCell.textContent = rate.name;
            row.appendChild(nameCell);

            var percentCell = document.createElement('td');
            percentCell.textContent = rate.percent + '%';
            row.appendChild(percentCell);

            var inUseCell = document.createElement('td');
            inUseCell.textContent = rate.in_use ? scpPanelTextData.yes : scpPanelTextData.no;
            row.appendChild(inUseCell);

            var actionsCell = document.createElement('td');

            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            editButton.textContent = scpPanelTextData.edit;
            editButton.addEventListener('click', function () {
                openTaxRateForm(rate);
            });
            actionsCell.appendChild(editButton);

            if (!rate.is_standard) {
                var deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                deleteButton.textContent = scpPanelTextData.remove;
                deleteButton.disabled = Boolean(rate.in_use);
                deleteButton.addEventListener('click', function () {
                    if (!window.confirm(scpPanelTextData.confirmDeleteTaxRate)) {
                        return;
                    }

                    apiFetch('commerce/tax-rates/' + rate.slug, { method: 'DELETE' }).then(function (result) {
                        if (!result.ok) {
                            setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                            return;
                        }

                        setStatus(scpPanelTextData.taxRateDeleted);
                        loadTaxRates();
                    });
                });
                actionsCell.appendChild(deleteButton);
            }

            row.appendChild(actionsCell);
            tableBody.appendChild(row);
        });
    }

    function openTaxRateForm(rate) {
        form.hidden = false;
        setStatus('');
        form.reset();
        form.slug.value = rate ? rate.slug : '';
        form.name.value = rate ? rate.name : '';
        form.percent.value = rate ? rate.percent : '';

        // The "Standart" class' name is fixed (WooCommerce always has it,
        // renaming it here would only rename Seviye's own label for it,
        // not the underlying WC class) - only its percent is editable.
        var isStandard = Boolean(rate && rate.is_standard);
        nameField.hidden = isStandard;
        form.name.required = !isStandard;
    }

    root.querySelector('[data-scp-new-tax-rate]').addEventListener('click', function () {
        openTaxRateForm(null);
    });

    root.querySelector('[data-scp-cancel-tax-rate]').addEventListener('click', function () {
        form.hidden = true;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var slug = form.slug.value;
        var percent = parseFloat(form.percent.value);

        if (isNaN(percent) || percent < 0) {
            setStatus(scpPanelTextData.saveError, true);
            return;
        }

        var path = slug ? 'commerce/tax-rates/' + slug : 'commerce/tax-rates';
        var method = slug ? 'PUT' : 'POST';
        var payload = slug ? { percent: percent } : { name: form.name.value, percent: percent };

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.taxRateSaved);
            form.hidden = true;
            loadTaxRates();
        });
    });

    loadTaxRates();
})();
