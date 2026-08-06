/**
 * Shared design-system utilities, enqueued globally on every authenticated
 * page (see inc/assets.php) ahead of every panel script, the same
 * "one shared foundation" role scp-api-fetch.js already plays for network
 * calls. Exposes a small set of global helpers rather than a module system
 * (this theme has no bundler) - every panel script may call
 * window.scpToast()/scpKebabMenus()/scpSuccessPulse() etc. directly.
 *
 * Self-initializing pieces (command palette, skip link, large-title
 * scroll, kebab menu auto-wiring) run immediately on DOMContentLoaded;
 * nothing here depends on any specific panel's markup existing.
 */
(function () {
    'use strict';

    // ---- Toast ----

    function toastHost() {
        var host = document.getElementById('scp-toast-host');

        if (host) {
            return host;
        }

        host = document.createElement('div');
        host.id = 'scp-toast-host';
        host.className = 'scp-toast-host';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);

        return host;
    }

    // ---- Ağ etkinliği göstergesi (sayfanın üstünde ince ilerleme çubuğu) ----

    /**
     * "Kaydet düğmesi yükleniyor durumu" - a per-button spinner would need
     * to guess which button triggered which request; instead this hooks
     * the ONE shared choke point every panel script's REST call already
     * goes through (scp-api-fetch.js's scpApiFetch()) and shows a single
     * page-level indicator for as long as ANY request is in flight - works
     * for every fetch (GET list-loads included, not just POST submits)
     * with zero per-panel wiring. A counter (not a boolean) so two
     * overlapping requests don't have the second one's completion hide the
     * bar while the first is still running.
     */
    var scpNetworkActivityCount = 0;
    var scpNetworkActivityBar = null;

    function networkActivityBar() {
        if (!scpNetworkActivityBar) {
            scpNetworkActivityBar = document.createElement('div');
            scpNetworkActivityBar.className = 'scp-network-bar';
            document.body.appendChild(scpNetworkActivityBar);
        }

        return scpNetworkActivityBar;
    }

    window.scpBeginNetworkActivity = function () {
        scpNetworkActivityCount += 1;
        networkActivityBar().classList.add('scp-network-bar--active');
    };

    window.scpEndNetworkActivity = function () {
        scpNetworkActivityCount = Math.max(0, scpNetworkActivityCount - 1);

        if (scpNetworkActivityCount === 0) {
            networkActivityBar().classList.remove('scp-network-bar--active');
        }
    };

    /**
     * @param {string} message
     * @param {'default'|'success'|'error'} [variant]
     */
    window.scpToast = function (message, variant) {
        var host = toastHost();
        var toast = document.createElement('div');
        toast.className = 'scp-toast' + (variant ? ' scp-toast--' + variant : '');
        toast.textContent = message;
        host.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('scp-toast--leaving');
            toast.addEventListener('animationend', function () {
                toast.remove();
            });
        }, 4000);
    };

    // ---- Animated counter (dashboard stat tiles count up from 0 instead
    // of just appearing) ----

    /**
     * @param {HTMLElement} element
     * @param {number} target
     * @param {function(number): string} [formatter] defaults to the plain integer
     */
    window.scpAnimateCounter = function (element, target, formatter) {
        if (!element) {
            return;
        }

        var format = formatter || function (value) {
            return String(Math.round(value));
        };

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            element.textContent = format(target);
            return;
        }

        var duration = 500;
        var start = null;

        function step(timestamp) {
            if (start === null) {
                start = timestamp;
            }

            var progress = Math.min(1, (timestamp - start) / duration);
            // Ease-out cubic, the same decelerate feel --scp-ease approximates.
            var eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = format(target * eased);

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        }

        window.requestAnimationFrame(step);
    };

    // ---- Skeleton loading (placeholder rows shown while a panel's first
    // fetch is in flight - see .scp-skeleton/.scp-skeleton-row in panel.css) ----

    /**
     * @param {HTMLElement} container
     * @param {number} [count]
     */
    window.scpSkeletonRows = function (container, count) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        for (var i = 0; i < (count || 3); i++) {
            var row = document.createElement('div');
            row.className = 'scp-skeleton scp-skeleton-row';
            row.style.width = (70 + (i % 3) * 10) + '%';
            container.appendChild(row);
        }
    };

    // ---- Success pulse (a small checkmark flash on a status element) ----

    window.scpSuccessPulse = function (element) {
        if (!element) {
            return;
        }

        element.classList.add('scp-status--success-pulse');
        window.setTimeout(function () {
            element.classList.remove('scp-status--success-pulse');
        }, 900);
    };

    // ---- Modal (a Promise-based confirm() replacement) ----

    /**
     * @param {{title: string, body?: string, confirmLabel?: string, cancelLabel?: string, danger?: boolean}} options
     * @return {Promise<boolean>}
     */
    window.scpModal = function (options) {
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'scp-modal-overlay';

            var modal = document.createElement('div');
            modal.className = 'scp-modal';
            modal.setAttribute('role', 'alertdialog');
            modal.setAttribute('aria-modal', 'true');

            var title = document.createElement('h2');
            title.textContent = options.title;
            modal.appendChild(title);

            if (options.body) {
                var body = document.createElement('p');
                body.textContent = options.body;
                modal.appendChild(body);
            }

            var actions = document.createElement('div');
            actions.className = 'scp-modal__actions';

            var cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'scp-btn scp-btn--ghost';
            cancelButton.textContent = options.cancelLabel || 'Vazgeç';

            var confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'scp-btn' + (options.danger ? ' scp-btn--danger' : '');
            confirmButton.textContent = options.confirmLabel || 'Onayla';

            function close(result) {
                overlay.remove();
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            }

            function onKeydown(event) {
                if (event.key === 'Escape') {
                    close(false);
                }
            }

            cancelButton.addEventListener('click', function () {
                close(false);
            });
            confirmButton.addEventListener('click', function () {
                close(true);
            });
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    close(false);
                }
            });
            document.addEventListener('keydown', onKeydown);

            actions.appendChild(cancelButton);
            actions.appendChild(confirmButton);
            modal.appendChild(actions);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            confirmButton.focus();
        });
    };

    // ---- Kebab menu auto-wiring ----

    function closeAllKebabMenus(except) {
        document.querySelectorAll('[data-scp-kebab]').forEach(function (root) {
            if (root === except) {
                return;
            }

            var toggle = root.querySelector('.scp-kebab__toggle');
            var menu = root.querySelector('.scp-kebab__menu');

            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }

            if (menu) {
                menu.hidden = true;
            }
        });
    }

    function wireKebabMenu(root) {
        if (root.dataset.scpKebabWired) {
            return;
        }

        root.dataset.scpKebabWired = '1';

        var toggle = root.querySelector('.scp-kebab__toggle');
        var menu = root.querySelector('.scp-kebab__menu');

        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = toggle.getAttribute('aria-expanded') === 'true';
            closeAllKebabMenus(root);
            toggle.setAttribute('aria-expanded', String(!isOpen));
            menu.hidden = isOpen;
        });

        menu.addEventListener('click', function () {
            closeAllKebabMenus();
        });
    }

    /**
     * Called by any panel script that renders `[data-scp-kebab]` markup
     * dynamically (see .scp-kebab's own doc comment in panel.css) - safe
     * to call repeatedly, already-wired menus are skipped.
     */
    window.scpKebabMenus = function () {
        document.querySelectorAll('[data-scp-kebab]').forEach(wireKebabMenu);
    };

    document.addEventListener('click', function () {
        closeAllKebabMenus();
    });

    // ---- Command palette (Cmd+K / Ctrl+K) ----

    function collectCommands() {
        var commands = [];

        document.querySelectorAll('.scp-quicknav a').forEach(function (link) {
            commands.push({ label: link.textContent.trim(), href: link.getAttribute('href') });
        });

        return commands;
    }

    function openCommandPalette() {
        var commands = collectCommands();

        if (commands.length === 0) {
            return;
        }

        var overlay = document.createElement('div');
        overlay.className = 'scp-command-overlay';

        var palette = document.createElement('div');
        palette.className = 'scp-command-palette';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'scp-command-palette__input';
        input.placeholder = (window.scpPanelText && window.scpPanelText.commandPalettePlaceholder)
            || 'Bir bölüme git…';

        var list = document.createElement('ul');
        list.className = 'scp-command-palette__list';

        function render(query) {
            list.innerHTML = '';
            var normalized = query.trim().toLowerCase();
            var matches = commands.filter(function (command) {
                return command.label.toLowerCase().indexOf(normalized) !== -1;
            });

            if (matches.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'scp-command-palette__empty';
                empty.textContent = (window.scpPanelText && window.scpPanelText.commandPaletteEmpty)
                    || 'Eşleşme yok.';
                list.appendChild(empty);
                return;
            }

            matches.forEach(function (command, index) {
                var item = document.createElement('li');
                var link = document.createElement('a');
                link.className = 'scp-command-palette__item' + (index === 0 ? ' scp-command-palette__item--active' : '');
                link.href = command.href;
                link.textContent = command.label;
                item.appendChild(link);
                list.appendChild(item);
            });
        }

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
                return;
            }

            if (event.key === 'Enter') {
                var active = list.querySelector('.scp-command-palette__item');

                if (active) {
                    window.location.href = active.getAttribute('href');
                }
            }
        }

        input.addEventListener('input', function () {
            render(input.value);
        });
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);

        palette.appendChild(input);
        palette.appendChild(list);
        overlay.appendChild(palette);
        document.body.appendChild(overlay);

        render('');
        input.focus();
    }

    document.addEventListener('keydown', function (event) {
        var isModifierK = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';

        if (!isModifierK) {
            return;
        }

        event.preventDefault();
        openCommandPalette();
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-scp-command-trigger]');

        if (trigger) {
            openCommandPalette();
        }
    });

    // ---- Sidebar group flyout - click/tap toggle (bölüm 66, inc/sidebar.php).
    // Hover and :focus-within alone (theme.css) already reveal a group's
    // submenu for mouse and keyboard users; this adds the same for touch,
    // where hover doesn't exist. Accordion-style: opening one group closes
    // any other already-open one, so only one flyout is ever visible at a
    // time. ----

    window.scpSidebarNav = function () {
        var groups = document.querySelectorAll('.scp-sidebar-nav__item--group');

        if (groups.length === 0) {
            return;
        }

        Array.prototype.forEach.call(groups, function (group) {
            var toggle = group.querySelector('[data-scp-sidebar-toggle]');

            if (!toggle || toggle.dataset.scpSidebarWired) {
                return;
            }

            toggle.dataset.scpSidebarWired = '1';

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();

                var isOpen = group.classList.contains('is-open');

                Array.prototype.forEach.call(groups, function (candidate) {
                    candidate.classList.remove('is-open');
                });

                group.classList.toggle('is-open', !isOpen);
            });
        });

        document.addEventListener('click', function () {
            Array.prototype.forEach.call(groups, function (group) {
                group.classList.remove('is-open');
            });
        });
    };

    // ---- Large-title scroll (header brand shrinks once the page's own
    // h1 scrolls out of view) ----

    function initLargeTitleScroll() {
        var header = document.querySelector('.scp-site-header');
        var title = document.querySelector('.scp-panel h1');

        if (!header || !title || typeof IntersectionObserver === 'undefined') {
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    header.classList.toggle('scp-site-header--scrolled', !entry.isIntersecting);
                });
            },
            { rootMargin: '-' + header.offsetHeight + 'px 0px 0px 0px', threshold: 0 }
        );

        observer.observe(title);
    }

    // ---- Character counter ----

    /**
     * "Uzun metin alanlarında karakter sayacı" - any
     * `<textarea maxlength="…" data-scp-char-counter>` in the page gets a
     * small "X / maxlength" label right below it that updates live, no
     * per-panel wiring needed. A panel only has to add the attribute +
     * maxlength to its own textarea; this runs once for every matching
     * field on the page.
     */
    function initCharCounters() {
        document.querySelectorAll('textarea[data-scp-char-counter][maxlength]').forEach(function (textarea) {
            var max = textarea.getAttribute('maxlength');
            var counter = document.createElement('span');
            counter.className = 'scp-char-counter';

            function update() {
                counter.textContent = textarea.value.length + ' / ' + max;
            }

            update();
            textarea.insertAdjacentElement('afterend', counter);
            textarea.addEventListener('input', update);
        });
    }

    // ---- Mobil kart görünümlü tablolar ----

    /**
     * "Mobil kart görünümlü tablolar" - a `<td data-label="…">` per cell
     * (read from the table's own `<thead th>` text) lets panel.css's
     * `.scp-table` narrow-viewport rule show each row as a labeled card
     * instead of a horizontally-scrolled table - no per-panel-script
     * changes needed. Almost every panel builds its table body via its OWN
     * REST fetch (asynchronously, well after DOMContentLoaded) - some fill
     * an already-present-but-empty `<tbody>` (the table itself is in the
     * initial HTML, just `hidden`), a couple (orders-panel.js,
     * admin-orders-panel.js's order line-items table) build the whole
     * `<table>` element from scratch and insert it later. A single
     * document-wide MutationObserver (rather than one per table) covers
     * both: any DOM change anywhere in the page re-scans every
     * `.scp-table` currently present and (re)labels its rows, cheap enough
     * for how infrequently panels actually mutate their own markup.
     * `scp-table--responsive-cards` is only added once labeling actually
     * finds header text, so a table with no `<thead>` keeps the existing,
     * safe horizontal-scroll fallback instead of showing unlabeled cards.
     */
    function labelTableCells(table) {
        var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
            return th.textContent.trim();
        });

        if (headers.length === 0) {
            return;
        }

        table.querySelectorAll('tbody tr').forEach(function (row) {
            Array.prototype.forEach.call(row.children, function (cell, index) {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });

        table.classList.add('scp-table--responsive-cards');
    }

    function initResponsiveTables() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        function labelAllTables() {
            document.querySelectorAll('.scp-table').forEach(labelTableCells);
        }

        labelAllTables();

        var observer = new MutationObserver(labelAllTables);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ---- Mini sepet (slide-out cart drawer) ----

    /**
     * header.php's "Sepetim" link (data-scp-mini-cart-trigger) opens
     * inc/woocommerce.php's scp_render_mini_cart_drawer() panel instead of
     * navigating to the Sepetim page - the link's own href is left intact
     * as a plain-navigation fallback for anything that reaches it without
     * JS (a "middle-click open in new tab", a very old browser, ...).
     * No-op (nothing to wire up) on any page that doesn't have the drawer
     * markup - only rendered for scp_view_own_children (veli) users.
     */
    function initMiniCart() {
        var drawer = document.querySelector('[data-scp-mini-cart]');
        var trigger = document.querySelector('[data-scp-mini-cart-trigger]');

        if (!drawer || !trigger) {
            return;
        }

        function open(event) {
            event.preventDefault();
            drawer.hidden = false;
            document.body.classList.add('scp-mini-cart-open');
        }

        function close() {
            drawer.hidden = true;
            document.body.classList.remove('scp-mini-cart-open');
        }

        trigger.addEventListener('click', open);

        drawer.querySelectorAll('[data-scp-mini-cart-close]').forEach(function (el) {
            el.addEventListener('click', close);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !drawer.hidden) {
                close();
            }
        });
    }

    // ---- Ürün hızlı önizleme ----

    /**
     * inc/woocommerce.php'nin scp_render_quick_view_trigger()'ı mağaza
     * ızgarasındaki her karta bir düğme + o ürünün detaylarını taşıyan
     * gizli bir `<template>` ekliyor. Burada yapılan tek şey, tıklanan
     * düğmenin hedef `<template>`'ini bir modale klonlamak - ekstra bir
     * ağ isteği YOK, detaylar zaten sayfayla birlikte sunucu tarafında
     * render edilmiş durumda. Tek bir delege `click` dinleyicisi
     * kullanılıyor (mağaza ızgarasında onlarca ürün/düğme olabileceğinden
     * her birine ayrı ayrı bağlanmak yerine).
     */
    function initQuickView() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-scp-quick-view-trigger]');

            if (!trigger) {
                return;
            }

            var template = document.getElementById(trigger.getAttribute('data-scp-quick-view-target'));

            if (!template || !('content' in template)) {
                return;
            }

            var overlay = document.createElement('div');
            overlay.className = 'scp-modal-overlay scp-quick-view-overlay';

            var panel = document.createElement('div');
            panel.className = 'scp-quick-view';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.appendChild(template.content.cloneNode(true));

            function close() {
                overlay.remove();
                document.removeEventListener('keydown', onKeydown);
            }

            function onKeydown(keyEvent) {
                if (keyEvent.key === 'Escape') {
                    close();
                }
            }

            var closeButton = panel.querySelector('[data-scp-quick-view-close]');

            if (closeButton) {
                closeButton.addEventListener('click', close);
            }

            overlay.addEventListener('click', function (overlayEvent) {
                if (overlayEvent.target === overlay) {
                    close();
                }
            });
            document.addEventListener('keydown', onKeydown);

            overlay.appendChild(panel);
            document.body.appendChild(overlay);
        });
    }

    // ---- Ödeme sonrası kutlama animasyonu ----

    /**
     * "Sipariş tamamlandı" (order-received) sayfasında bir kereliğine
     * konfeti patlaması. `body.woocommerce-order-received` sınıfı WC'nin
     * KENDİ `wc_body_class()`'ı tarafından zaten ekleniyor (bu tema hiçbir
     * yerde dokunmadı) - `is_order_received_page()` true olduğunda - bu
     * yüzden burada ayrı bir PHP koşulu/enqueue şartı kurmaya gerek yok,
     * yalnızca o body sınıfını kontrol etmek yeterli. Üçüncü parti bir
     * kütüphane KULLANILMADI (CDN'e çıkmayan, kendi kendine yeten script
     * kuralı - bkz. bu dosyanın kendi toast/modal'ı) - birkaç saniyeliğine
     * DOM'a eklenip kaldırılan basit, CSS animasyonlu <span> parçacıkları.
     */
    function initOrderCelebration() {
        if (!document.body.classList.contains('woocommerce-order-received')) {
            return;
        }

        var colors = ['#0f9d63', '#2a5cb8', '#e0a72b', '#c0271e', '#7ea6f2'];
        var container = document.createElement('div');
        container.className = 'scp-confetti';
        container.setAttribute('aria-hidden', 'true');

        for (var i = 0; i < 40; i++) {
            var piece = document.createElement('span');
            piece.className = 'scp-confetti__piece';
            piece.style.left = Math.random() * 100 + '%';
            piece.style.backgroundColor = colors[i % colors.length];
            piece.style.animationDelay = (Math.random() * 0.4) + 's';
            piece.style.animationDuration = (2.2 + Math.random() * 1.2) + 's';
            container.appendChild(piece);
        }

        document.body.appendChild(container);

        window.setTimeout(function () {
            container.remove();
        }, 4000);
    }

    // ---- Yukarı kaydır düğmesi ----

    /**
     * Uzun sayfalarda (raporlar, sipariş listeleri, mağaza ızgarası...)
     * belirli bir kaydırma mesafesinden sonra beliren, sayfanın başına
     * yumuşak kaydıran sabit bir düğme. `scroll` dinleyicisi zaten atılgan
     * değil - yalnızca bir CSS sınıfı ekleyip/kaldırıyor, `scrollTo` çağrısı
     * tek seferlik bir tıklama olayında.
     */
    function initScrollToTop() {
        // scp-ui-kit.js is the shared foundation script, not one of the
        // per-panel handles wp_localize_script() targets (see this file's
        // own docblock) - `scpPanelText` may not exist at all on a page
        // with no panel script enqueued (a plain shop/product page), so
        // this reads it defensively rather than assuming it's there.
        var label = (typeof scpPanelText !== 'undefined' && scpPanelText.scrollToTop)
            ? scpPanelText.scrollToTop
            : 'Yukarı çık';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-scroll-top';
        button.setAttribute('aria-label', label);
        button.hidden = true;
        button.innerHTML = '&uarr;';

        function update() {
            button.hidden = window.scrollY < 400;
        }

        button.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        window.addEventListener('scroll', update, { passive: true });

        document.body.appendChild(button);
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.scpKebabMenus();
        window.scpSidebarNav();
        initLargeTitleScroll();
        initCharCounters();
        initMiniCart();
        initOrderCelebration();
        initQuickView();
        initScrollToTop();
        initResponsiveTables();
    });
})();
