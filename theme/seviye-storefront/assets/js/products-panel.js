/**
 * Shared-catalog product management for /admin and /sube.
 *
 * Three tiers, mirroring zone.php's own gating:
 *   - scpPanel.canManageProducts (Genel Merkez/Bölge Müdürü/Şube Müdürü):
 *     full panel - create, and (canManageAllBranches only) edit/delete/
 *     per-branch status grid. A Şube Müdürü may create into the shared
 *     catalog and toggle their OWN branch's active/passive status only.
 *   - VIEW_PRODUCTS only (Muhasebe/Depo/Sistem): read-only id/name/price/
 *     category/stock list, no form, no status/actions columns at all -
 *     those table cells and the create/edit form don't even exist in the
 *     DOM (see zone.php), so this script never queries for them unless
 *     canManageProducts is true.
 *
 * See plugin/seviye-commerce/src/Http/ProductsRestController.php.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce, canManageProducts, canManageAllBranches }
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
            row.appendChild(textCell(formatPrice(product.price)));
            row.appendChild(textCell(product.category || scpPanelText.summaryNotSet));
            row.appendChild(textCell(product.manage_stock ? String(product.stock_quantity) : scpPanelText.summaryNotSet));

            if (scpPanel.canManageProducts) {
                row.appendChild(statusCell(product));
                row.appendChild(actionsCell(product));
            }

            tableBody.appendChild(row);
        });
    }

    function statusCell(product) {
        var cell = document.createElement('td');

        if (scpPanel.canManageAllBranches) {
            var branchesButton = document.createElement('button');
            branchesButton.type = 'button';
            branchesButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            branchesButton.textContent = scpPanelText.manageBranches;
            branchesButton.addEventListener('click', function () {
                openBranchesPanel(product);
            });
            cell.appendChild(branchesButton);

            return cell;
        }

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'scp-badge ' + (product.own_branch_active ? 'scp-badge--active' : 'scp-badge--inactive');
        toggle.textContent = product.own_branch_active ? scpPanelText.statusActive : scpPanelText.statusInactive;
        toggle.addEventListener('click', function () {
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

        if (!scpPanel.canManageAllBranches) {
            return cell;
        }

        var editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
        editButton.textContent = scpPanelText.edit;
        editButton.addEventListener('click', function () {
            openProductForm(product);
        });
        cell.appendChild(editButton);

        return cell;
    }

    var openProductForm = function () {};
    var openBranchesPanel = function () {};

    if (scpPanel.canManageProducts) {
        var form = root.querySelector('[data-scp-product-form]');
        var deleteButton = root.querySelector('[data-scp-delete-product]');
        var manageStockCheckbox = form.querySelector('[data-scp-manage-stock]');
        var stockQuantityField = form.querySelector('[data-scp-stock-quantity-field]');
        var imageInput = form.querySelector('[data-scp-product-image-input]');
        var imagePreview = form.querySelector('[data-scp-product-image-preview]');
        var imageStatus = form.querySelector('[data-scp-product-image-status]');
        var branchesPanel = root.querySelector('[data-scp-product-branches-panel]');
        var branchesList = root.querySelector('[data-scp-product-branches-list]');
        var allBranches = null;

        openProductForm = function (product) {
            form.hidden = false;
            branchesPanel.hidden = true;
            setStatus('');
            form.reset();
            form.id.value = product ? product.id : '';
            form.name.value = product ? product.name : '';
            form.description.value = product ? product.description : '';
            form.price.value = product ? product.price : '';
            form.category.value = product && product.category ? product.category : '';
            form.image_id.value = product && product.image_id ? product.image_id : '';

            if (product && product.image_url) {
                imagePreview.src = product.image_url;
                imagePreview.hidden = false;
            } else {
                imagePreview.hidden = true;
            }

            manageStockCheckbox.checked = Boolean(product && product.manage_stock);
            stockQuantityField.hidden = !manageStockCheckbox.checked;
            form.stock_quantity.value = product && product.manage_stock ? product.stock_quantity : '';

            deleteButton.hidden = !product;

            form.scrollIntoView({ block: 'nearest' });
        };

        manageStockCheckbox.addEventListener('change', function () {
            stockQuantityField.hidden = !manageStockCheckbox.checked;
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
            var payload = {
                name: form.name.value,
                description: form.description.value,
                price: parseFloat(form.price.value),
                category: form.category.value,
                manage_stock: manageStockCheckbox.checked
            };

            if (form.image_id.value) {
                payload.image_id = parseInt(form.image_id.value, 10);
            }

            if (manageStockCheckbox.checked) {
                payload.stock_quantity = parseInt(form.stock_quantity.value, 10) || 0;
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
