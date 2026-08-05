/**
 * Shared-catalog product management for /admin and /sube.
 *
 * Three tiers, mirroring zone.php's own gating:
 *   - scpPanel.canManageProducts (Genel Merkez/Bölge Müdürü/Şube Müdürü):
 *     full panel - create, and per-product `can_manage` (server-computed,
 *     see ProductsRestController::serialize()) edit/delete/variant editing -
 *     Genel Merkez/Bölge Müdürü always, a Şube Müdürü only for a product
 *     THEY THEMSELVES created (never a Genel Merkez product, never another
 *     branch's - see Support\ProductOwnership). Every row is clickable: a
 *     manageable one opens the same edit structure the "Düzenle" button
 *     does; a non-manageable one shows WHO owns it instead of silently
 *     doing nothing (see the row click handler in renderProducts()). The
 *     "Şubeler" per-branch active/passive grid stays canManageAllBranches
 *     (HQ) only regardless of ownership - a Şube Müdürü instead gets a
 *     single toggle for their OWN branch's status on ANY product (see
 *     statusCell()), independent of who created it.
 *   - VIEW_PRODUCTS only (Muhasebe/Depo/Sistem): read-only id/name/price/
 *     category/stock list, no form, no owner/status/actions columns at
 *     all - those table cells and the create/edit form don't even exist
 *     in the DOM (see zone.php), so this script never queries for them
 *     unless canManageProducts is true.
 *
 * "Bedenler"/"Renkler" on the create form (visible only for a NEW product -
 * an existing product's variant structure can't be changed here) produce a
 * variable WooCommerce product; "Varyantları Düzenle" then edits each
 * generated variation's own price/stock - reachable only through the same
 * per-product can_manage-gated edit form as everything else. See
 * plugin/seviye-commerce/src/Http/ProductsRestController.php.
 *
 * Editing an EXISTING product also loads an embedded "Fiyat Kuralları"
 * sub-panel (gated on scpPanel.canManagePricing, a DIFFERENT capability
 * than canManageProducts - see inc/assets.php) so a product's own price
 * rules (general/branch/student, see plugin/seviye-pricing) are managed
 * right there instead of requiring a separate manual product-id lookup.
 * Talks to seviye/v1/pricing/rules/* directly - a plain cross-plugin REST
 * call from the theme, not a PHP dependency between Commerce and Pricing.
 * Hidden entirely for a brand-new (not yet saved) product, since a price
 * rule needs a real product id to attach to.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce, canManageProducts,
 *                  canManageAllBranches, canManagePricing, canManageBasePricing }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-products-panel');

    if (!root || typeof scpPanel === 'undefined') {
        return;
    }

    var statusEl = root.querySelector('[data-scp-products-status]');
    var tableBody = root.querySelector('[data-scp-products-body]');
    var apiFetch = scpApiFetch;

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.classList.toggle('scp-status--error', Boolean(isError));
    }

    function formatPrice(price) {
        return Number(price).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function priceCellText(product) {
        if (product.type !== 'variable') {
            return formatPrice(product.price);
        }

        if (!product.price_range) {
            return scpPanelText.summaryNotSet;
        }

        return product.price_range.min === product.price_range.max
            ? formatPrice(product.price_range.min)
            : formatPrice(product.price_range.min) + ' - ' + formatPrice(product.price_range.max);
    }

    function stockCellText(product) {
        if (product.type === 'variable') {
            return scpPanelText.variantStock;
        }

        return product.manage_stock ? String(product.stock_quantity) : scpPanelText.summaryNotSet;
    }

    function loadProducts() {
        apiFetch('commerce/products').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelText.loadError, true);
                return;
            }

            renderProducts(result.data);
        });
    }

    function textCell(text) {
        var cell = document.createElement('td');
        cell.textContent = text;
        return cell;
    }

    function renderProducts(products) {
        tableBody.innerHTML = '';

        products.forEach(function (product) {
            var row = document.createElement('tr');

            var imageCell = document.createElement('td');

            if (product.image_url) {
                var img = document.createElement('img');
                img.src = product.image_url;
                img.alt = '';
                img.className = 'scp-product-thumb';
                imageCell.appendChild(img);
            }

            row.appendChild(imageCell);
            row.appendChild(textCell(String(product.id)));
            row.appendChild(textCell(product.name));
            row.appendChild(textCell(priceCellText(product)));
            row.appendChild(textCell(product.category || scpPanelText.summaryNotSet));
            row.appendChild(textCell(stockCellText(product)));

            if (scpPanel.canManageProducts) {
                row.appendChild(ownerCell(product));
                row.appendChild(statusCell(product));
                row.appendChild(actionsCell(product));

                // "Ürüne tıklandığında tüm düzenlemeleri için bir yapı
                // açılsın" - the whole row opens the same edit structure the
                // "Düzenle" button does, for anyone this specific product's
                // can_manage flag allows (Genel Merkez/Bölge Müdürü always,
                // a Şube Müdürü only for a product they created themselves -
                // see ProductsRestController::canManageProductFully()).
                // Button clicks inside the row (Şubeler/Durum/Düzenle) stop
                // propagation so they don't ALSO trigger this. A row the
                // user can't manage is still clickable - it shows WHY
                // instead of doing nothing, so "I clicked and nothing
                // happened" never looks like a broken feature.
                row.classList.add('scp-row--clickable');
                row.addEventListener('click', function () {
                    if (!product.can_manage) {
                        var owner = product.owner_branch_name || scpPanelText.productOwnerHq;
                        setStatus(scpPanelText.productNotManageable.replace('%s', owner), true);
                        return;
                    }

                    openProductForm(product);
                });
            }

            tableBody.appendChild(row);
        });
    }

    function ownerCell(product) {
        var cell = document.createElement('td');
        cell.textContent = product.owner_branch_name || scpPanelText.productOwnerHq;

        return cell;
    }

    function statusCell(product) {
        var cell = document.createElement('td');

        if (scpPanel.canManageAllBranches) {
            var branchesButton = document.createElement('button');
            branchesButton.type = 'button';
            branchesButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            branchesButton.textContent = scpPanelText.manageBranches;
            branchesButton.addEventListener('click', function (event) {
                event.stopPropagation();
                openBranchesPanel(product);
            });
            cell.appendChild(branchesButton);

            return cell;
        }

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'scp-badge ' + (product.own_branch_active ? 'scp-badge--active' : 'scp-badge--inactive');
        toggle.textContent = product.own_branch_active ? scpPanelText.statusActive : scpPanelText.statusInactive;
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();

            var nextStatus = product.own_branch_active ? 'passive' : 'active';

            apiFetch('commerce/products/' + product.id + '/branches/own', {
                method: 'PUT',
                body: JSON.stringify({ status: nextStatus })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.branchStatusSaved);
                loadProducts();
            });
        });
        cell.appendChild(toggle);

        return cell;
    }

    function actionsCell(product) {
        var cell = document.createElement('td');

        if (!product.can_manage) {
            return cell;
        }

        var editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        editButton.textContent = scpPanelText.edit;
        editButton.addEventListener('click', function (event) {
            event.stopPropagation();
            openProductForm(product);
        });
        cell.appendChild(editButton);

        return cell;
    }

    var openProductForm = function () {};
    var openBranchesPanel = function () {};
    var openVariationsPanel = function () {};

    if (scpPanel.canManageProducts) {
        var form = root.querySelector('[data-scp-product-form]');
        var deleteButton = root.querySelector('[data-scp-delete-product]');
        var manageStockCheckbox = form.querySelector('[data-scp-manage-stock]');
        var stockQuantityField = form.querySelector('[data-scp-stock-quantity-field]');
        var lowStockField = form.querySelector('[data-scp-low-stock-field]');
        var imageInput = form.querySelector('[data-scp-product-image-input]');
        var imagePreview = form.querySelector('[data-scp-product-image-preview]');
        var imageStatus = form.querySelector('[data-scp-product-image-status]');
        var branchesPanel = root.querySelector('[data-scp-product-branches-panel]');
        var branchesList = root.querySelector('[data-scp-product-branches-list]');
        var variantFields = form.querySelector('[data-scp-variant-fields]');
        var variantHint = form.querySelector('[data-scp-variant-hint]');
        var variantLockedNotice = form.querySelector('[data-scp-variant-locked-notice]');
        var editVariationsButton = form.querySelector('[data-scp-edit-variations]');
        var variationsPanel = root.querySelector('[data-scp-product-variations-panel]');
        var variationsList = root.querySelector('[data-scp-product-variations-list]');
        var allBranches = null;
        var currentProduct = null;

        // "Bir ürün seçilince o ürünün fiyat değişiklikleri de aynı yapıda
        // yapılsın" - a per-product price-rules editor embedded directly in
        // the product edit structure, so managing a product's price rules
        // no longer requires leaving here and typing its id into the
        // separate "Fiyat Kuralları" panel by hand. That standalone panel
        // stays exactly as it was (manual lookup + CSV bulk import) - this
        // is purely additive, and the only path available to a
        // scp_manage_pricing holder who lacks scp_manage_products (Sistem).
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

        if (pricingPanel && !scpPanel.canManageBasePricing) {
            var generalOption = priceScopeSelect.querySelector('[data-scp-product-scope-general]');

            if (generalOption) {
                generalOption.remove();
            }
        }

        function priceScopeLabel(scope) {
            if (scope === 'branch') {
                return scpPanelText.scopeBranch;
            }

            if (scope === 'student') {
                return scpPanelText.scopeStudent;
            }

            return scpPanelText.scopeGeneral;
        }

        function loadProductPriceRules(productId) {
            apiFetch('pricing/rules?product_id=' + productId).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
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
                badge.textContent = isActive ? scpPanelText.statusActive : scpPanelText.statusInactive;
                statusCellEl.appendChild(badge);
                row.appendChild(statusCellEl);

                var ruleActionsCell = document.createElement('td');

                if (rule.scope !== 'general' || scpPanel.canManageBasePricing) {
                    var editRuleButton = document.createElement('button');
                    editRuleButton.type = 'button';
                    editRuleButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
                    editRuleButton.textContent = scpPanelText.edit;
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
            if (scope === 'general' || (scope === 'branch' && !scpPanel.canManageAllBranches)) {
                priceTargetField.hidden = true;
                priceTargetInput.required = false;
                return;
            }

            priceTargetField.hidden = false;
            priceTargetInput.required = true;
            priceTargetLabel.textContent = scope === 'branch' ? scpPanelText.branchIdLabel : scpPanelText.studentIdLabel;
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

                if (!id || !window.confirm(scpPanelText.confirmDeletePriceRule)) {
                    return;
                }

                apiFetch('pricing/rules/' + id, { method: 'DELETE' }).then(function (result) {
                    if (!result.ok) {
                        setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                        return;
                    }

                    setStatus(scpPanelText.priceRuleDeleted);
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
                        setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                        return;
                    }

                    setStatus(scpPanelText.saved);
                    priceRuleForm.hidden = true;
                    loadProductPriceRules(currentProduct.id);
                });
            });
        }

        openProductForm = function (product) {
            form.hidden = false;
            branchesPanel.hidden = true;
            variationsPanel.hidden = true;

            if (pricingPanel) {
                pricingPanel.hidden = !product;
                priceRuleForm.hidden = true;

                if (product) {
                    loadProductPriceRules(product.id);
                }
            }

            setStatus('');
            form.reset();
            currentProduct = product;
            form.id.value = product ? product.id : '';
            form.name.value = product ? product.name : '';
            form.description.value = product ? product.description : '';
            form.category.value = product && product.category ? product.category : '';
            form.image_id.value = product && product.image_id ? product.image_id : '';

            var isVariable = Boolean(product && product.type === 'variable');

            variantFields.hidden = Boolean(product);
            variantHint.hidden = Boolean(product);
            variantLockedNotice.hidden = !isVariable;
            editVariationsButton.hidden = !isVariable;

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

            // "Ürün eğer Genel Merkez'den oluşturulduysa silemez" - can_manage
            // already encodes exactly that rule (see
            // ProductsRestController::canManageProductFully()).
            deleteButton.hidden = !product || !product.can_manage;

            form.scrollIntoView({ block: 'nearest' });
        };

        manageStockCheckbox.addEventListener('change', function () {
            stockQuantityField.hidden = !manageStockCheckbox.checked;
            lowStockField.hidden = !manageStockCheckbox.checked;
        });

        imageInput.addEventListener('change', function () {
            var file = imageInput.files[0];

            if (!file) {
                return;
            }

            imageStatus.textContent = scpPanelText.uploadingImage;

            scpUploadMedia(file).then(function (result) {
                if (!result.ok) {
                    imageStatus.textContent = scpPanelText.imageUploadError;
                    return;
                }

                imageStatus.textContent = '';
                form.image_id.value = result.data.id;
                imagePreview.src = result.data.source_url;
                imagePreview.hidden = false;
            });
        });

        root.querySelector('[data-scp-new-product]').addEventListener('click', function () {
            openProductForm(null);
        });

        root.querySelector('[data-scp-cancel-product]').addEventListener('click', function () {
            form.hidden = true;
        });

        root.querySelector('[data-scp-close-product-branches]').addEventListener('click', function () {
            branchesPanel.hidden = true;
        });

        root.querySelector('[data-scp-close-product-variations]').addEventListener('click', function () {
            variationsPanel.hidden = true;
        });

        editVariationsButton.addEventListener('click', function () {
            if (currentProduct) {
                openVariationsPanel(currentProduct);
            }
        });

        deleteButton.addEventListener('click', function () {
            var id = form.id.value;

            if (!id || !window.confirm(scpPanelText.confirmDeleteProduct)) {
                return;
            }

            apiFetch('commerce/products/' + id, { method: 'DELETE' }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.productDeleted);
                form.hidden = true;
                loadProducts();
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
                manage_stock: isVariable ? false : manageStockCheckbox.checked
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
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.productSaved);
                form.hidden = true;
                loadProducts();
            });
        });

        openBranchesPanel = function (product) {
            form.hidden = true;
            branchesPanel.hidden = false;
            branchesList.innerHTML = '';

            var loadAllBranches = allBranches
                ? Promise.resolve({ ok: true, data: allBranches })
                : apiFetch('branches');

            loadAllBranches.then(function (branchesResult) {
                if (!branchesResult.ok) {
                    return;
                }

                allBranches = branchesResult.data;

                apiFetch('commerce/products/' + product.id + '/branches').then(function (statusResult) {
                    var statuses = {};

                    if (statusResult.ok) {
                        statusResult.data.forEach(function (row) {
                            statuses[row.branch_id] = row.status;
                        });
                    }

                    allBranches.forEach(function (branch) {
                        branchesList.appendChild(
                            renderBranchStatusRow(product.id, branch, statuses[branch.id] || 'active')
                        );
                    });
                });
            });
        };

        openVariationsPanel = function (product) {
            form.hidden = true;
            branchesPanel.hidden = true;
            variationsPanel.hidden = false;
            variationsList.innerHTML = '';

            apiFetch('commerce/products/' + product.id + '/variations').then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.loadError, true);
                    return;
                }

                result.data.forEach(function (variation) {
                    variationsList.appendChild(renderVariationRow(variation));
                });
            });
        };

        root.querySelector('[data-scp-save-variations]').addEventListener('click', function () {
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
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.productSaved);
                loadProducts();
            });
        });
    }

    function renderVariationRow(variation) {
        var row = document.createElement('tr');
        row.setAttribute('data-scp-variation-id', String(variation.id));

        row.appendChild(textCell(variation.label));

        var priceCell = document.createElement('td');
        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.value = variation.price;
        priceInput.setAttribute('data-scp-variation-price', '');
        priceCell.appendChild(priceInput);
        row.appendChild(priceCell);

        var stockCell = document.createElement('td');
        var stockInput = document.createElement('input');
        stockInput.type = 'number';
        stockInput.min = '0';
        stockInput.step = '1';
        stockInput.value = variation.stock_quantity || 0;
        stockInput.setAttribute('data-scp-variation-stock', '');
        stockCell.appendChild(stockInput);
        row.appendChild(stockCell);

        return row;
    }

    function renderBranchStatusRow(productId, branch, currentStatus) {
        var item = document.createElement('li');
        item.className = 'scp-form scp-form--inline';

        var label = document.createElement('span');
        label.textContent = branch.name;
        item.appendChild(label);

        var select = document.createElement('select');
        ['active', 'passive'].forEach(function (value) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = value === 'active' ? scpPanelText.statusActive : scpPanelText.statusInactive;
            option.selected = value === currentStatus;
            select.appendChild(option);
        });
        item.appendChild(select);

        var saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'scp-btn scp-btn--small';
        saveButton.textContent = scpPanelText.save;
        saveButton.addEventListener('click', function () {
            apiFetch('commerce/products/' + productId + '/branches/' + branch.id, {
                method: 'PUT',
                body: JSON.stringify({ status: select.value })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelText.saveError, true);
                    return;
                }

                setStatus(scpPanelText.branchStatusSaved);
            });
        });
        item.appendChild(saveButton);

        return item;
    }

    loadProducts();
})();
