/**
 * Shared-catalog product LIST for /admin/urunler and /sube/urunler.
 *
 * Two tiers, mirroring zone.php's own gating:
 *   - scpPanel.canManageProducts (Genel Merkez/Bölge Müdürü/Şube Müdürü):
 *     "Yeni Ürün" link (see templates/products-admin.php) plus owner/
 *     status columns (ownerCell()/statusCell()) - the "Şubeler" per-branch
 *     active/passive grid stays canManageAllBranches (HQ) only regardless
 *     of ownership, a Şube Müdürü instead gets a single toggle for their
 *     OWN branch's status on ANY product, independent of who created it.
 *   - scpPanel.canViewProducts (Muhasebe/Depo/Sistem): read-only id/name/
 *     price/category/stock list, no owner/status columns - those table
 *     cells don't even exist in the DOM (see products-admin.php) unless
 *     canManageProducts is true.
 *
 * EVERY row is clickable for BOTH tiers ("ürünleri görebiliyorum ama
 * tıklanacak bir yer yok" fix) - it always navigates to that product's own
 * page (see templates/product-edit.php, assets/js/product-edit-panel.js),
 * which renders editable or strictly read-only per the server-computed
 * `can_manage` flag (see ProductsRestController::serialize()) - a
 * VIEW_PRODUCTS-only viewer or a Şube Müdürü looking at a product THEY
 * didn't create both land on the SAME read-only view there, told who
 * actually owns it, rather than a dead end here.
 *
 * All actual editing (name/price/stock/image/category/variants/price
 * rules) lives on the dedicated edit page now, not here - see
 * product-edit-panel.js.
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, wpRestRoot, nonce, canManageProducts,
 *                  canViewProducts, canManageAllBranches, productsBasePath }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-products-panel');

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
            return scpPanelTextData.summaryNotSet;
        }

        return product.price_range.min === product.price_range.max
            ? formatPrice(product.price_range.min)
            : formatPrice(product.price_range.min) + ' - ' + formatPrice(product.price_range.max);
    }

    function stockCellText(product) {
        if (product.type === 'variable') {
            return scpPanelTextData.variantStock;
        }

        return product.manage_stock ? String(product.stock_quantity) : scpPanelTextData.summaryNotSet;
    }

    function loadProducts() {
        apiFetch('commerce/products').then(function (result) {
            if (!result.ok) {
                setStatus(scpPanelTextData.loadError, true);
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
        window.location.href = scpPanelData.productsBasePath + '/' + product.id;
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
            row.appendChild(textCell(product.category || scpPanelTextData.summaryNotSet));
            row.appendChild(textCell(stockCellText(product)));

            if (scpPanelData.canManageProducts) {
                row.appendChild(ownerCell(product));
                row.appendChild(statusCell(product));
            }

            row.appendChild(actionsCell(product));

            // "Ürüne tıklandığında o ürünün düzenleme sayfası gelsin" - the
            // whole row navigates to the product's own page for ANYONE who
            // reached this list at all (canManageProducts OR
            // canViewProducts); the destination page itself decides
            // editable vs. read-only from `product.can_manage`, so there's
            // no "blocked" dead end here to special-case - see this file's
            // own docblock. Button clicks inside the row (Şubeler/Durum/
            // Detay) stop propagation so they don't ALSO trigger this.
            if (scpPanelData.canManageProducts || scpPanelData.canViewProducts) {
                row.classList.add('scp-row--clickable');
                row.addEventListener('click', function () {
                    goToEditPage(product);
                });
            }

            tableBody.appendChild(row);
        });
    }

    function ownerCell(product) {
        var cell = document.createElement('td');
        cell.textContent = product.owner_branch_name || scpPanelTextData.productOwnerHq;

        return cell;
    }

    function statusCell(product) {
        var cell = document.createElement('td');

        if (scpPanelData.canManageAllBranches) {
            var branchesButton = document.createElement('button');
            branchesButton.type = 'button';
            branchesButton.className = 'scp-btn scp-btn--ghost scp-btn--small';
            branchesButton.textContent = scpPanelTextData.manageBranches;
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
        toggle.textContent = product.own_branch_active ? scpPanelTextData.statusActive : scpPanelTextData.statusInactive;
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();

            var nextStatus = product.own_branch_active ? 'passive' : 'active';

            apiFetch('commerce/products/' + product.id + '/branches/own', {
                method: 'PUT',
                body: JSON.stringify({ status: nextStatus })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.branchStatusSaved);
                loadProducts();
            });
        });
        cell.appendChild(toggle);

        return cell;
    }

    function actionsCell(product) {
        var cell = document.createElement('td');

        if (!scpPanelData.canManageProducts && !scpPanelData.canViewProducts) {
            return cell;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-btn scp-btn--ghost scp-btn--small';
        button.textContent = product.can_manage ? scpPanelTextData.edit : scpPanelTextData.details;
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            goToEditPage(product);
        });
        cell.appendChild(button);

        return cell;
    }

    var openBranchesPanel = function () {};

    if (scpPanelData.canManageAllBranches) {
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
            option.textContent = value === 'active' ? scpPanelTextData.statusActive : scpPanelTextData.statusInactive;
            option.selected = value === currentStatus;
            select.appendChild(option);
        });
        item.appendChild(select);

        var saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'scp-btn scp-btn--small';
        saveButton.textContent = scpPanelTextData.save;
        saveButton.addEventListener('click', function () {
            apiFetch('commerce/products/' + productId + '/branches/' + branch.id, {
                method: 'PUT',
                body: JSON.stringify({ status: select.value })
            }).then(function (result) {
                if (!result.ok) {
                    setStatus((result.data && result.data.message) || scpPanelTextData.saveError, true);
                    return;
                }

                setStatus(scpPanelTextData.branchStatusSaved);
            });
        });
        item.appendChild(saveButton);

        return item;
    }

    loadProducts();
})();
