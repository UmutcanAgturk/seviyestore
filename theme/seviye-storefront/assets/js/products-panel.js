/**
 * Shared-catalog product LIST for /admin/urunler and /sube/urunler.
 *
 * Two tiers, mirroring zone.php's own gating:
 *   - scpPanel.canManageProducts (Genel Merkez/Bölge Müdürü/Şube Müdürü):
 *     "Yeni Ürün" link (see templates/products-admin.php), owner/status/
 *     actions columns, and every row navigates to that product's own edit
 *     page (see templates/product-edit.php, assets/js/product-edit-panel.js)
 *     on click - a manageable one (server-computed `can_manage`, see
 *     ProductsRestController::serialize()) goes straight there; a
 *     non-manageable one (a Şube Müdürü looking at a product THEY didn't
 *     create - see Support\ProductOwnership) shows WHO owns it instead,
 *     rather than navigating to a page they can't write to anyway. The
 *     "Şubeler" per-branch active/passive grid stays canManageAllBranches
 *     (HQ) only regardless of ownership - a Şube Müdürü instead gets a
 *     single toggle for their OWN branch's status on ANY product (see
 *     statusCell()), independent of who created it.
 *   - VIEW_PRODUCTS only (Muhasebe/Depo/Sistem): read-only id/name/price/
 *     category/stock list, no owner/status/actions columns and no
 *     navigation at all - those table cells don't even exist in the DOM
 *     (see products-admin.php) unless canManageProducts is true.
 *
 * All actual editing (name/price/stock/image/category/variants/price
 * rules) lives on the dedicated edit page now, not here - see
 * product-edit-panel.js.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce, canManageProducts,
 *                  canManageAllBranches, productsBasePath }
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

    function goToEditPage(product) {
        window.location.href = scpPanel.productsBasePath + '/' + product.id;
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

                // "Ürüne tıklandığında o ürünün düzenleme sayfası gelsin" -
                // the whole row navigates to the product's own edit page,
                // for anyone this specific product's can_manage flag allows
                // (Genel Merkez/Bölge Müdürü always, a Şube Müdürü only for
                // a product they created themselves - see
                // ProductsRestController::canManageProductFully()). Button
                // clicks inside the row (Şubeler/Durum/Düzenle) stop
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

                    goToEditPage(product);
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
            goToEditPage(product);
        });
        cell.appendChild(editButton);

        return cell;
    }

    var openBranchesPanel = function () {};

    if (scpPanel.canManageAllBranches) {
        var branchesPanel = root.querySelector('[data-scp-product-branches-panel]');
        var branchesList = root.querySelector('[data-scp-product-branches-list]');
        var allBranches = null;

        root.querySelector('[data-scp-close-product-branches]').addEventListener('click', function () {
            branchesPanel.hidden = true;
        });

        openBranchesPanel = function (product) {
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
