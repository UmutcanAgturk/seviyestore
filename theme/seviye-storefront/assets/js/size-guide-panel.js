/**
 * "Beden Rehberi" management for /admin - HQ-only (scp_manage_size_guide).
 * Unlike tax-rates-panel.js/coupons-panel.js, rows have no natural stable
 * id/slug to address individually (bkz.
 * plugin/seviye-commerce/src/Http/SizeGuideRestController.php'nin kendi
 * docblock'u) - bu yüzden satırlar tamamen istemci tarafında
 * eklenir/silinir, "Kaydet" tüm tabloyu tek bir PUT ile gönderir.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-size-guide-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var scpPanelTextData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

    var statusEl = root.querySelector('[data-scp-size-guide-status]');
    var tableBody = root.querySelector('[data-scp-size-guide-body]');

    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function addNumberCell(row, name, value) {
        var cell = document.createElement('td');
        var input = document.createElement('input');
        input.type = 'number';
        input.name = name;
        input.value = value === null || value === undefined ? '' : value;
        cell.appendChild(input);
        row.appendChild(cell);
    }

    function addRow(data) {
        var row = document.createElement('tr');
        row.className = 'scp-size-guide-row';

        var labelCell = document.createElement('td');
        var labelInput = document.createElement('input');
        labelInput.type = 'text';
        labelInput.name = 'label';
        labelInput.placeholder = scpPanelTextData.sizeGuideLabelPlaceholder || '';
        labelInput.value = (data && data.label) || '';
        labelCell.appendChild(labelInput);
        row.appendChild(labelCell);

        addNumberCell(row, 'age_min', data ? data.age_min : null);
        addNumberCell(row, 'age_max', data ? data.age_max : null);
        addNumberCell(row, 'height_min_cm', data ? data.height_min_cm : null);
        addNumberCell(row, 'height_max_cm', data ? data.height_max_cm : null);

        var actionsCell = document.createElement('td');
        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        removeButton.textContent = scpPanelTextData.remove;
        removeButton.addEventListener('click', function () {
            row.remove();
        });
        actionsCell.appendChild(removeButton);
        row.appendChild(actionsCell);

        tableBody.appendChild(row);
    }

    function renderRows(rows) {
        tableBody.innerHTML = '';
        rows.forEach(function (row) {
            addRow(row);
        });
    }

    function fieldValue(row, name) {
        var input = row.querySelector('[name="' + name + '"]');
        return input ? input.value : '';
    }

    function collectRows() {
        return Array.prototype.map.call(tableBody.querySelectorAll('.scp-size-guide-row'), function (row) {
            return {
                label: fieldValue(row, 'label').trim(),
                age_min: fieldValue(row, 'age_min') === '' ? null : parseInt(fieldValue(row, 'age_min'), 10),
                age_max: fieldValue(row, 'age_max') === '' ? null : parseInt(fieldValue(row, 'age_max'), 10),
                height_min_cm: fieldValue(row, 'height_min_cm') === '' ? null : parseInt(fieldValue(row, 'height_min_cm'), 10),
                height_max_cm: fieldValue(row, 'height_max_cm') === '' ? null : parseInt(fieldValue(row, 'height_max_cm'), 10),
            };
        }).filter(function (row) {
            return row.label !== '';
        });
    }

    function loadSizeGuide() {
        apiFetch('commerce/size-guide').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
                return;
            }

            renderRows(result.data.rows || []);
        });
    }

    root.querySelector('[data-scp-add-size-guide-row]').addEventListener('click', function () {
        addRow(null);
    });

    root.querySelector('[data-scp-save-size-guide]').addEventListener('click', function () {
        var rows = collectRows();

        apiFetch('commerce/size-guide', { method: 'PUT', body: JSON.stringify({ rows: rows }) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            setStatus(scpPanelTextData.sizeGuideSaved);
            renderRows(result.data.rows || []);
        });
    });

    loadSizeGuide();
})();
