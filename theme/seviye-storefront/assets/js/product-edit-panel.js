/**
 * A single product's own edit/create page (/admin/urunler/{id},
 * /admin/urunler/yeni, /sube/urunler/{id}, /sube/urunler/yeni - see
 * inc/zones.php, templates/product-edit.php). Everything ABOUT one product
 * (name/price/stock/image/category/variants/price rules) lives here now,
 * moved off the Ürünler list (see products-panel.js) - "ürüne tıklandığında
 * o ürünün düzenleme sayfası gelsin, tüm düzenlemeler orada yapılabilsin".
 *
 * inc/zones.php only checks scp_manage_products (the general capability) to
 * let a request reach this page at all - it does NOT re-check per-product
 * ownership (that would mean parsing the id and calling into Commerce from
 * theme routing code just to redirect, for a case the REST layer already
 * refuses safely). So a Şube Müdürü CAN land here for a product they don't
 * own (e.g. a stale link, or typing the URL) - the server-computed
 * `can_manage` flag on the fetched product (see
 * ProductsRestController::serialize()) is what actually decides here:
 * false disables the whole page to read-only and explains why, rather than
 * silently letting a PUT/DELETE 403 later.
 *
 * "Bedenler"/"Renkler" (visible only when CREATING - an existing product's
 * variant structure can't change afterwards) produce a variable WooCommerce
 * product; the "Varyantlar" panel below then edits each generated
 * variation's own price/stock.
 *
 * The "Fiyat Kuralları" panel (gated on scpPanel.canManagePricing, a
 * DIFFERENT capability than canManageProducts - see inc/assets.php) manages
 * this product's own price rules (general/branch/student, see
 * plugin/seviye-pricing) right here instead of the separate manual
 * product-id lookup the standalone "Fiyat Kuralları" admin panel still
 * offers. Talks to seviye/v1/pricing/rules/* directly - a plain
 * cross-plugin REST call from the theme, not a PHP dependency between
 * Commerce and Pricing. Hidden entirely for a brand-new (not yet saved)
 * product, since a price rule needs a real product id to attach to.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce, productsBasePath,
 *                  canManageAllBranches, canManagePricing,
 *                  canManageBasePricing }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-product-edit-panel');

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

    var apiFetch = scpApiFetch;
    var statusEl = root.querySelector('[data-scp-product-edit-status]');
    var form = root.querySelector('[data-scp-product-form]');
    var deleteButton = form.querySelector('[data-scp-delete-product]');
    var manageStockCheckbox = form.querySelector('[data-scp-manage-stock]');
    var stockQuantityField = form.querySelector('[data-scp-stock-quantity-field]');
    var lowStockField = form.querySelector('[data-scp-low-stock-field]');
    var imageInput = form.querySelector('[data-scp-product-image-input]');
    var imagePreview = form.querySelector('[data-scp-product-image-preview]');
    var imageStatus = form.querySelector('[data-scp-product-image-status]');
    var variantFields = form.querySelector('[data-scp-variant-fields]');
    var variantHint = form.querySelector('[data-scp-variant-hint]');
    var variantLockedNotice = form.querySelector('[data-scp-variant-locked-notice]');
    var gradeLevelCheckboxes = form.querySelectorAll('[name="grade_levels[]"]');
    var taxClassSelect = form.querySelector('[data-scp-tax-class-select]');
    var variationsPanel = root.querySelector('[data-scp-product-variations-panel]');
    var variationsList = root.querySelector('[data-scp-product-variations-list]');
    var saveVariationsButton = root.querySelector('[data-scp-save-variations]');
    var pricingPanel = root.querySelector('[data-scp-product-pricing-panel]');
    var priceRuleForm = pricingPanel ? pricingPanel.querySelector('[data-scp-product-price-rule-form]') : null;
    var priceRulesBody = pricingPanel ? pricingPanel.querySelector('[data-scp-product-price-rules-body]') : null;
    var priceScopeSelect = priceRuleForm ? priceRuleForm.querySelector('[name="scope"]') : null;
    var priceTargetField = pricingPanel ? pricingPanel.querySelector('[data-scp-product-price-target-field]') : null;
    var priceTargetLabel = pricingPanel ? pricingPanel.querySelector('[data-scp-product-price-target-label]') : null;
    var priceTargetInput = priceTargetField ? priceTargetField.querySelector('input') : null;
    var priceStatusField = pricingPanel ? pricingPanel.querySelector('[data-scp-product-price-status-field]') : null;
    var deletePriceRuleButton = pricingPanel
        ? pricingPanel.querySelector('[data-scp-delete-product-price-rule]')
        : null;

    var productIdAttr = root.getAttribute('data-scp-product-id');
    var productId = productIdAttr ? parseInt(productIdAttr, 10) : null;
    var currentProduct = null;

    // "Ürün ürün vergilendirme" - the option list itself loads
    // asynchronously (commerce/tax-rates) independently of the product
    // fetch below, so whichever of the two finishes LAST is what actually
    // sets the select's value: applyPendingTaxClass() is a no-op until
    // BOTH populateForm() has recorded which slug the product wants AND
    // loadTaxRates() has populated the <option>s to select among.
    var pendingTaxClass = null;

    function applyPendingTaxClass() {
        if (pendingTaxClass === null || !taxClassSelect.options.length) {
            return;
        }

        taxClassSelect.value = pendingTaxClass;
    }

    function loadTaxRates() {
        apiFetch('commerce/tax-rates').then(function (result) {
            if (!result.ok) {
                return;
            }

            taxClassSelect.innerHTML = '';

            result.data.forEach(function (rate) {
                var option = document.createElement('option');
                option.value = rate.slug;
                option.textContent = rate.name + ' (%' + rate.percent + ')';
                taxClassSelect.appendChild(option);
            });

            applyPendingTaxClass();
        });
    }

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function setFormDisabled(disabled) {
        Array.prototype.forEach.call(form.elements, function (el) {
            el.disabled = disabled;
        });
    }

    if (pricingPanel && !scpPanelData.canManageBasePricing) {
        var generalOption = priceScopeSelect.querySelector('[data-scp-product-scope-general]');

        if (generalOption) {
            generalOption.remove();
        }
    }

    function priceScopeLabel(scope) {
        if (scope === 'branch') {
            return scpPanelTextData.scopeBranch;
        }

        if (scope === 'student') {
            return scpPanelTextData.scopeStudent;
        }

        return scpPanelTextData.scopeGeneral;
    }

    function loadProductPriceRules(id) {
        apiFetch('pricing/rules?product_id=' + id).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            priceRuleForm.hidden = true;
            renderProductPriceRules(result.data);
        });
    }

    function renderProductPriceRules(rules) {
        priceRulesBody.innerHTML = '';

        rules.forEach(function (rule) {
            var row = document.createElement('tr');

            var scopeCell = document.createElement('td');
            scopeCell.textContent = priceScopeLabel(rule.scope);
            row.appendChild(scopeCell);

            var targetCell = document.createElement('td');
            var targetId = rule.branch_id !== null ? rule.branch_id : rule.student_id;
            targetCell.textContent = targetId !== null ? String(targetId) : '';
            row.appendChild(targetCell);

            var priceCell = document.createElement('td');
            priceCell.textContent = String(rule.price);
            row.appendChild(priceCell);

            var statusCellEl = document.createElement('td');
            var badge = document.createElement('span');
            var isActive = rule.status === 'active';
            badge.className = 'scp-badge ' + (isActive ? 'scp-badge--active' : 'scp-badge--inactive');
            badge.textContent = isActive ? scpPanelTextData.statusActive : scpPanelTextData.statusInactive;
            statusCellEl.appendChild(badge);
            row.appendChild(statusCellEl);

            var ruleActionsCell = document.createElement('td');

            if (currentProduct && currentProduct.can_manage && (rule.scope !== 'general' || scpPanelData.canManageBasePricing)) {
                var editRuleButton = document.createElement('button');
                editRuleButton.type = 'button';
                editRuleButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                editRuleButton.textContent = scpPanelTextData.edit;
                editRuleButton.addEventListener('click', function () {
                    openProductPriceRuleForm(rule);
                });
                ruleActionsCell.appendChild(editRuleButton);
            }

            row.appendChild(ruleActionsCell);
            priceRulesBody.appendChild(row);
        });
    }

    function updatePriceTargetField(scope) {
        if (scope === 'general' || (scope === 'branch' && !scpPanelData.canManageAllBranches)) {
            priceTargetField.hidden = true;
            priceTargetInput.required = false;
            return;
        }

        priceTargetField.hidden = false;
        priceTargetInput.required = true;
        priceTargetLabel.textContent = scope === 'branch' ? scpPanelTextData.branchIdLabel : scpPanelTextData.studentIdLabel;
    }

    function openProductPriceRuleForm(rule) {
        priceRuleForm.hidden = false;
        setStatus('');
        priceRuleForm.reset();
        priceRuleForm.id.value = rule ? rule.id : '';
        priceRuleForm.price.value = rule ? String(rule.price) : '';
        priceScopeSelect.disabled = Boolean(rule);
        priceStatusField.hidden = !rule;
        deletePriceRuleButton.hidden = !rule;

        if (rule) {
            priceScopeSelect.value = rule.scope;
            priceRuleForm.status.value = rule.status;
            priceTargetInput.value = String(rule.branch_id !== null ? rule.branch_id : (rule.student_id || ''));
        } else {
            priceTargetInput.value = '';
        }

        updatePriceTargetField(priceScopeSelect.value);
    }

    if (pricingPanel) {
        priceScopeSelect.addEventListener('change', function () {
            updatePriceTargetField(priceScopeSelect.value);
        });

        pricingPanel.querySelector('[data-scp-new-product-price-rule]').addEventListener('click', function () {
            openProductPriceRuleForm(null);
        });

        pricingPanel.querySelector('[data-scp-cancel-product-price-rule]').addEventListener('click', function () {
            priceRuleForm.hidden = true;
        });

        deletePriceRuleButton.addEventListener('click', function () {
            var id = priceRuleForm.id.value;

            if (!id || !window.confirm(scpPanelTextData.confirmDeletePriceRule)) {
                return;
            }

            apiFetch('pricing/rules/' + id, { method: 'DELETE' }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.priceRuleDeleted);
                priceRuleForm.hidden = true;
                loadProductPriceRules(currentProduct.id);
            });
        });

        priceRuleForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var id = priceRuleForm.id.value;
            var payload = { price: parseFloat(priceRuleForm.price.value) };
            var path = 'pricing/rules';
            var method = 'POST';

            if (id) {
                payload.status = priceRuleForm.status.value;
                path = 'pricing/rules/' + id;
                method = 'PUT';
            } else {
                payload.product_id = currentProduct.id;
                payload.scope = priceScopeSelect.value;

                if (priceTargetInput.value) {
                    payload.target_id = parseInt(priceTargetInput.value, 10);
                }
            }

            apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.saved);
                priceRuleForm.hidden = true;
                loadProductPriceRules(currentProduct.id);
            });
        });
    }

    function renderVariationRow(variation, readOnly) {
        var row = document.createElement('tr');
        row.setAttribute('data-scp-variation-id', String(variation.id));

        var labelCell = document.createElement('td');
        labelCell.textContent = variation.label;
        row.appendChild(labelCell);

        var priceCell = document.createElement('td');
        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.value = variation.price;
        priceInput.disabled = readOnly;
        priceInput.setAttribute('data-scp-variation-price', '');
        priceCell.appendChild(priceInput);
        row.appendChild(priceCell);

        var stockCell = document.createElement('td');
        var stockInput = document.createElement('input');
        stockInput.type = 'number';
        stockInput.min = '0';
        stockInput.step = '1';
        stockInput.value = variation.stock_quantity || 0;
        stockInput.disabled = readOnly;
        stockInput.setAttribute('data-scp-variation-stock', '');
        stockCell.appendChild(stockInput);
        row.appendChild(stockCell);

        return row;
    }

    function loadVariations(id, readOnly) {
        variationsList.innerHTML = '';

        apiFetch('commerce/products/' + id + '/variations').then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            result.data.forEach(function (variation) {
                variationsList.appendChild(renderVariationRow(variation, readOnly));
            });
        });
    }

    if (saveVariationsButton) {
        saveVariationsButton.addEventListener('click', function () {
            if (!currentProduct) {
                return;
            }

            var rows = [];

            variationsList.querySelectorAll('tr').forEach(function (row) {
                rows.push({
                    id: parseInt(row.getAttribute('data-scp-variation-id'), 10),
                    price: parseFloat(row.querySelector('[data-scp-variation-price]').value) || 0,
                    stock_quantity: parseInt(row.querySelector('[data-scp-variation-stock]').value, 10) || 0
                });
            });

            apiFetch('commerce/products/' + currentProduct.id + '/variations', {
                method: 'PUT',
                body: JSON.stringify({ variations: rows })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.productSaved);
            });
        });
    }

    function populateForm(product) {
        currentProduct = product;

        var isVariable = Boolean(product && product.type === 'variable');
        var canManage = !product || Boolean(product.can_manage);

        setStatus('');
        currentProduct = product;
        form.id.value = product ? product.id : '';
        form.name.value = product ? product.name : '';
        form.description.value = product ? product.description : '';
        form.category.value = product && product.category ? product.category : '';
        form.image_id.value = product && product.image_id ? product.image_id : '';

        pendingTaxClass = product && product.tax_class ? product.tax_class : 'standard';
        applyPendingTaxClass();

        variantFields.hidden = Boolean(product);
        variantHint.hidden = Boolean(product);
        variantLockedNotice.hidden = !isVariable;

        form.price.disabled = isVariable;
        form.price.value = product && !isVariable ? product.price : '';
        manageStockCheckbox.disabled = isVariable;
        manageStockCheckbox.checked = Boolean(product && !isVariable && product.manage_stock);
        stockQuantityField.hidden = !manageStockCheckbox.checked;
        lowStockField.hidden = !manageStockCheckbox.checked;
        form.stock_quantity.value = product && !isVariable && product.manage_stock ? product.stock_quantity : '';
        form.low_stock_amount.value = product && !isVariable && product.low_stock_amount !== null
            ? product.low_stock_amount
            : '';

        if (product && product.image_url) {
            imagePreview.src = product.image_url;
            imagePreview.hidden = false;
        } else {
            imagePreview.hidden = true;
        }

        var gradeLevels = product && product.grade_levels ? product.grade_levels : [];
        Array.prototype.forEach.call(gradeLevelCheckboxes, function (checkbox) {
            checkbox.checked = gradeLevels.indexOf(checkbox.value) !== -1;
        });

        // "Ürün eğer Genel Merkez'den oluşturulduysa silemez" - can_manage
        // already encodes exactly that rule (see
        // ProductsRestController::canManageProductFully()).
        deleteButton.hidden = !product || !canManage;

        variationsPanel.hidden = !isVariable;

        if (isVariable && product) {
            loadVariations(product.id, !canManage);
        }

        if (saveVariationsButton) {
            saveVariationsButton.hidden = !canManage;
        }

        if (pricingPanel) {
            pricingPanel.hidden = !product || !canManage;

            if (product && canManage) {
                loadProductPriceRules(product.id);
            }
        }

        if (product && !canManage) {
            setFormDisabled(true);
            var owner = product.owner_branch_name || scpPanelTextData.productOwnerHq;
            setStatus(scpPanelTextData.productNotManageable.replace('%s', owner), true);
        }
    }

    manageStockCheckbox.addEventListener('change', function () {
        stockQuantityField.hidden = !manageStockCheckbox.checked;
        lowStockField.hidden = !manageStockCheckbox.checked;
    });

    imageInput.addEventListener('change', function () {
        var file = imageInput.files[0];

        if (!file) {
            return;
        }

        imageStatus.textContent = scpPanelTextData.uploadingImage;

        scpUploadMedia(file).then(function (result) {
            if (!result.ok) {
                imageStatus.textContent = scpPanelTextData.imageUploadError;
                return;
            }

            imageStatus.textContent = '';
            form.image_id.value = result.data.id;
            imagePreview.src = result.data.source_url;
            imagePreview.hidden = false;
        });
    });

    deleteButton.addEventListener('click', function () {
        var id = form.id.value;

        if (!id || !window.confirm(scpPanelTextData.confirmDeleteProduct)) {
            return;
        }

        apiFetch('commerce/products/' + id, { method: 'DELETE' }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            window.location.href = scpPanelData.productsBasePath;
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var id = form.id.value;
        var isVariable = Boolean(currentProduct && currentProduct.type === 'variable');
        var payload = {
            name: form.name.value,
            description: form.description.value,
            price: isVariable ? 0 : parseFloat(form.price.value) || 0,
            category: form.category.value,
            tax_class: taxClassSelect.value,
            manage_stock: isVariable ? false : manageStockCheckbox.checked,
            grade_levels: Array.prototype.filter.call(gradeLevelCheckboxes, function (checkbox) {
                return checkbox.checked;
            }).map(function (checkbox) {
                return checkbox.value;
            })
        };

        if (form.image_id.value) {
            payload.image_id = parseInt(form.image_id.value, 10);
        }

        if (!isVariable && manageStockCheckbox.checked) {
            payload.stock_quantity = parseInt(form.stock_quantity.value, 10) || 0;

            if (form.low_stock_amount.value) {
                payload.low_stock_amount = parseInt(form.low_stock_amount.value, 10);
            }
        }

        if (!id) {
            payload.sizes = form.sizes.value;
            payload.colors = form.colors.value;
        }

        var path = id ? 'commerce/products/' + id : 'commerce/products';
        var method = id ? 'PUT' : 'POST';

        apiFetch(path, { method: method, body: JSON.stringify(payload) }).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                return;
            }

            if (id) {
                setStatus(scpPanelTextData.productSaved);
                populateForm(result.data);
                return;
            }

            // Just-created product - move to its own edit page (URL now
            // carries a real id), where variants/price rules become
            // available.
            window.location.href = scpPanelData.productsBasePath + '/' + result.data.id;
        });
    });

    loadTaxRates();

    if (productId) {
        apiFetch('commerce/products/' + productId).then(function (result) {
            if (!result.ok) {
                setStatus((result.data && result.data.message) || scpPanelTextData.loadError, true);
                return;
            }

            populateForm(result.data);
        });
    } else {
        populateForm(null);
    }
})();
