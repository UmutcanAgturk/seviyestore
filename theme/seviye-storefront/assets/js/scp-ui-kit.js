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

    /**
     * "Favicon'da arka planda çalışan işlem göstergesi" - üstteki ağ
     * çubuğuyla AYNI sayaca (scpNetworkActivityCount) bağlı, tek
     * farkı sekme arka plandayken (kullanıcı başka bir sekmedeyken) bile
     * görünür olması. Var olan favicon(lar)a DOKUNMUYOR/DEĞİŞTİRMİYOR -
     * WordPress'in kendi `wp_site_icon()`'unun bastığı `<link rel="icon">`
     * etiketleri (varsa) olduğu gibi kalıyor; bunun yerine `<head>`'in
     * SONUNA yeni, ayrı bir `<link>` ekleniyor (tarayıcılar birden fazla
     * `rel="icon"` arasından SONUNCUYU tercih eder) - etkinlik bitince
     * "eski href'e geri dön" diye bir şey İZLEMİYOR, o eklenen linki
     * tamamen KALDIRIYOR, tarayıcı zaten var olan favicon'a kendiliğinden
     * geri dönüyor. `--scp-primary`'yi (koyu modda farklı bir hex)
     * `getComputedStyle` ile OKUYOR, sabit bir renk gömmüyor.
     */
    var scpFaviconIndicatorLink = null;

    function scpFaviconIndicatorHref() {
        var canvas = document.createElement('canvas');
        canvas.width = 16;
        canvas.height = 16;

        var ctx = canvas.getContext('2d');

        if (!ctx) {
            return null;
        }

        var primary = getComputedStyle(document.documentElement).getPropertyValue('--scp-primary').trim() || '#14326b';

        ctx.beginPath();
        ctx.arc(8, 8, 7, 0, Math.PI * 2);
        ctx.fillStyle = primary;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(8, 8, 3, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        return canvas.toDataURL('image/png');
    }

    function scpShowFaviconActivity() {
        if (scpFaviconIndicatorLink) {
            return;
        }

        var href = scpFaviconIndicatorHref();

        if (!href) {
            return;
        }

        var link = document.createElement('link');
        link.rel = 'icon';
        link.href = href;
        document.head.appendChild(link);
        scpFaviconIndicatorLink = link;
    }

    function scpHideFaviconActivity() {
        if (scpFaviconIndicatorLink) {
            scpFaviconIndicatorLink.remove();
            scpFaviconIndicatorLink = null;
        }
    }

    window.scpBeginNetworkActivity = function () {
        scpNetworkActivityCount += 1;
        networkActivityBar().classList.add('scp-network-bar--active');
        scpShowFaviconActivity();
    };

    window.scpEndNetworkActivity = function () {
        scpNetworkActivityCount = Math.max(0, scpNetworkActivityCount - 1);

        if (scpNetworkActivityCount === 0) {
            networkActivityBar().classList.remove('scp-network-bar--active');
            scpHideFaviconActivity();
        }
    };

    /**
     * "Sayfa geçişlerinde üst yükleme çubuğu" - yukarıdaki
     * scpBeginNetworkActivity()/scpEndNetworkActivity() zaten HER AJAX
     * çağrısında (scpApiFetch/scpUploadMedia) bu çubuğu gösteriyordu; bu
     * yalnızca TAM SAYFA gezinmelerini (bir bağlantıya tıklama, bir form
     * gönderimi) de aynı çubuğa bağlıyor - `beforeunload` sayfa gerçekten
     * ayrılmadan hemen önce ateşlenir, yeni sayfa boyanana kadar geçen
     * boşlukta kullanıcı boş bir sekme yerine devam eden bir gösterge
     * görür (WordPress'in tam sayfa yenilemesi tabanlı klasik gezinme
     * modelinde, tarayıcının kendi sekme döner simgesinden daha belirgin).
     */
    window.addEventListener('beforeunload', function () {
        networkActivityBar().classList.add('scp-network-bar--active');
    });

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

    /**
     * "Silme işlemlerinde Geri Al (undo) tost bildirimi" - önceden her
     * silme `window.confirm()` İLE HEMEN silinip devam ediyordu:
     * engelleyici bir tarayıcı diyaloğu + geri dönüşü olmayan bir istek.
     * Bunun yerine Gmail'in "Gönderimi Geri Al"IYLA AYNI, gecikmeli-
     * optimist kalıp: satır/kart HEMEN arayüzden gizleniyor, birkaç
     * saniyelik bir "Geri Al" tost'u gösteriliyor, asıl silme isteği
     * YALNIZCA o süre dolana kadar geri alınmazsa `options.onCommit()`
     * ile ateşleniyor - kayıt sunucuda o ana kadar HİÇ silinmediği için
     * "geri al" gerçek anlamda geri döndürülebilir, ayrı bir "restore"
     * REST uç noktası icat etmeye gerek yok.
     *
     * @param {object} options
     * @param {HTMLElement} options.element - hemen gizlenecek satır/kart
     * @param {string} options.message - tost metni (ör. "Kupon silindi.")
     * @param {function(): void} options.onCommit - gecikme dolunca çağrılır, gerçek DELETE isteğini yapar
     * @param {function(): void} [options.onUndo] - kullanıcı geri alırsa çağrılır
     */
    window.scpConfirmableDelete = function (options) {
        var DELAY_MS = 5000;

        options.element.hidden = true;

        var host = toastHost();
        var toast = document.createElement('div');
        toast.className = 'scp-toast scp-toast--undo';

        var text = document.createElement('span');
        text.textContent = options.message;
        toast.appendChild(text);

        var undoButton = document.createElement('button');
        undoButton.type = 'button';
        undoButton.className = 'scp-toast__undo';
        undoButton.textContent = (typeof scpPanelText !== 'undefined' && scpPanelText.undo) || 'Geri Al';
        toast.appendChild(undoButton);

        host.appendChild(toast);

        function leave() {
            toast.classList.add('scp-toast--leaving');
            toast.addEventListener('animationend', function () {
                toast.remove();
            });
        }

        var timer = window.setTimeout(function () {
            leave();
            options.onCommit();
        }, DELAY_MS);

        undoButton.addEventListener('click', function () {
            window.clearTimeout(timer);
            options.element.hidden = false;
            leave();

            if (typeof options.onUndo === 'function') {
                options.onUndo();
            }
        });
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

    // ---- Avatar (baş harf rozeti) ----

    /**
     * @see inc/setup.php's scp_render_avatar() for the server-rendered
     * counterpart and why the two don't try to produce IDENTICAL colors
     * for the same name.
     */
    var scpAvatarPalette = ['#14326b', '#0f9d63', '#b45309', '#0f5c96', '#7e3af2', '#c0271e', '#0b7a4c'];

    function scpAvatarColorForName(name) {
        var hash = 0;

        for (var i = 0; i < name.length; i++) {
            hash = (hash * 31 + name.charCodeAt(i)) % scpAvatarPalette.length;
        }

        return scpAvatarPalette[Math.abs(hash) % scpAvatarPalette.length];
    }

    function scpAvatarInitials(name) {
        var parts = name.trim().split(/\s+/).filter(Boolean);

        if (parts.length === 0) {
            return '';
        }

        var initials = parts[0].charAt(0);

        if (parts.length > 1) {
            initials += parts[parts.length - 1].charAt(0);
        }

        return initials.toUpperCase();
    }

    /**
     * "Öğrenci profiline fotoğraf/avatar yükleme" - `photoUrl` opsiyonel;
     * verilirse baş harf yerine gerçek bir `<img>` gösteriyor (aynı
     * `.scp-avatar` kutusunun içinde, aynı boyutta - CSS `object-fit:
     * cover` ile kırpıyor), verilmezse (öğrencinin/kullanıcının henüz
     * fotoğrafı yoksa) tamamen ESKİ davranış: baş harfler + isimden
     * türetilen sabit renk.
     *
     * @param {string} name
     * @param {string} [photoUrl]
     * @return {HTMLSpanElement}
     */
    window.scpAvatar = function (name, photoUrl) {
        var span = document.createElement('span');
        span.className = 'scp-avatar';

        if (photoUrl) {
            var img = document.createElement('img');
            img.src = photoUrl;
            img.alt = '';
            span.appendChild(img);

            return span;
        }

        span.style.backgroundColor = scpAvatarColorForName(name || '');
        span.textContent = scpAvatarInitials(name || '');

        return span;
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

        /**
         * "Komut paletinde eşleşen metni vurgulama" - `<mark>` KESİNLİKLE
         * DOM API'siyle inşa ediliyor (innerHTML DEĞİL), `command.label`
         * zaten güvenilir bir kaynaktan (sayfanın kendi `.scp-quicknav`
         * bağlantı metinleri, collectCommands()) geliyor olsa da.
         */
        function appendHighlightedLabel(link, label, normalized) {
            if (normalized === '') {
                link.textContent = label;
                return;
            }

            var matchIndex = label.toLowerCase().indexOf(normalized);

            if (matchIndex === -1) {
                link.textContent = label;
                return;
            }

            if (matchIndex > 0) {
                link.appendChild(document.createTextNode(label.slice(0, matchIndex)));
            }

            var mark = document.createElement('mark');
            mark.className = 'scp-command-palette__match';
            mark.textContent = label.slice(matchIndex, matchIndex + normalized.length);
            link.appendChild(mark);

            var rest = label.slice(matchIndex + normalized.length);

            if (rest !== '') {
                link.appendChild(document.createTextNode(rest));
            }
        }

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
                appendHighlightedLabel(link, command.label, normalized);
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

    // ---- Klavye kısayolları yardım ekranı ----

    /**
     * "?" tuşu (Shift olmadan da event.key her zaman '?' döner, klavye
     * düzeninden bağımsız) bu listeyi açıyor - reuses .scp-modal-overlay/
     * .scp-modal (scpModal()'ın da kullandığı) ama gövdesi düz metin
     * olmadığından scpModal()'ın kendi API'si kullanılamıyor, bu yüzden
     * command palette/quick view'ın da yaptığı gibi kendi overlay'ini
     * elle kuruyor. Bir metin alanına yazarken "?" karakterinin kendisini
     * yakalamaması için odaklı öğe input/textarea/select/contenteditable
     * ise hiç tetiklenmiyor.
     */
    function openShortcutsHelp() {
        var textData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

        var overlay = document.createElement('div');
        overlay.className = 'scp-modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'scp-modal scp-shortcuts-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        var title = document.createElement('h2');
        title.textContent = textData.shortcutsTitle || 'Klavye Kısayolları';
        modal.appendChild(title);

        var list = document.createElement('dl');
        list.className = 'scp-shortcuts-modal__list';

        [
            ['⌘K / Ctrl+K', textData.shortcutsCommandPalette || 'Hızlı arama / komut paletini aç'],
            ['?', textData.shortcutsHelp || 'Bu yardım ekranını aç'],
            ['Esc', textData.shortcutsEscape || 'Açık pencereyi/paneli kapat']
        ].forEach(function (pair) {
            var dt = document.createElement('dt');
            dt.textContent = pair[0];
            var dd = document.createElement('dd');
            dd.textContent = pair[1];
            list.appendChild(dt);
            list.appendChild(dd);
        });

        modal.appendChild(list);

        var actions = document.createElement('div');
        actions.className = 'scp-modal__actions';

        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'scp-btn';
        closeButton.textContent = textData.shortcutsClose || 'Kapat';
        actions.appendChild(closeButton);
        modal.appendChild(actions);

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
            }
        }

        closeButton.addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        closeButton.focus();
    }

    /**
     * "İlk kez giriş yapan personel için onboarding tour" -
     * header.php'nin yalnızca personelin İLK ziyaretinde bastığı
     * `#scp-onboarding-tour-marker` varsa çalışıyor ("görüldü" bayrağı
     * PHP tarafında ZATEN işaretlendi, bu yüzden burada ayrı bir
     * "tamamlandı" isteği YOK). `openShortcutsHelp()`'in AYNI
     * `.scp-modal`/`.scp-modal-overlay` kalıbını çok-adımlı bir sürüme
     * genişletiyor - gerçek DOM elemanlarını "spot" ışığıyla
     * vurgulamıyor (bu, her sayfa/rol için farklı bir kenar çubuğu
     * düzeni olduğundan kırılgan olurdu), yalnızca dört temel özelliği
     * kısa metinlerle anlatıyor.
     */
    function initOnboardingTour() {
        var marker = document.getElementById('scp-onboarding-tour-marker');

        if (!marker) {
            return;
        }

        var textData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

        var steps = [
            {
                title: textData.tourStep1Title || 'Kenar Çubuğu',
                body: textData.tourStep1Body
                    || 'Soldaki menüden yetkili olduğunuz tüm bölümlere ulaşabilirsiniz - gruplar üzerine gelerek alt başlıkları görün.'
            },
            {
                title: textData.tourStep2Title || 'Hızlı Arama',
                body: textData.tourStep2Body
                    || 'Üst menüdeki "Bul" düğmesi (ya da Cmd/Ctrl+K) ile herhangi bir sayfaya veya kayda anında atlayabilirsiniz.'
            },
            {
                title: textData.tourStep3Title || 'Bildirimler',
                body: textData.tourStep3Body
                    || 'Zil simgesi size gelen önemli olayları (yeni sipariş, düşük stok, duyurular...) tek bir yerde toplar.'
            },
            {
                title: textData.tourStep4Title || 'Karanlık Mod',
                body: textData.tourStep4Body
                    || 'Sağ üstteki tema düğmesiyle istediğiniz zaman açık/koyu görünüm arasında geçiş yapabilirsiniz.'
            }
        ];

        var currentStep = 0;

        var overlay = document.createElement('div');
        overlay.className = 'scp-modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'scp-modal scp-tour-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        var eyebrow = document.createElement('p');
        eyebrow.className = 'scp-tour-modal__eyebrow';
        modal.appendChild(eyebrow);

        var title = document.createElement('h2');
        modal.appendChild(title);

        var body = document.createElement('p');
        modal.appendChild(body);

        var actions = document.createElement('div');
        actions.className = 'scp-modal__actions';

        var skipButton = document.createElement('button');
        skipButton.type = 'button';
        skipButton.className = 'scp-btn scp-btn--ghost';
        skipButton.textContent = textData.tourSkip || 'Geç';

        var nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'scp-btn';

        actions.appendChild(skipButton);
        actions.appendChild(nextButton);
        modal.appendChild(actions);

        function render() {
            var step = steps[currentStep];
            eyebrow.textContent = (currentStep + 1) + ' / ' + steps.length;
            title.textContent = step.title;
            body.textContent = step.body;
            nextButton.textContent = currentStep === steps.length - 1
                ? (textData.tourFinish || 'Başlayalım')
                : (textData.tourNext || 'İleri');
        }

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
            }
        }

        nextButton.addEventListener('click', function () {
            if (currentStep === steps.length - 1) {
                close();
                return;
            }

            currentStep += 1;
            render();
        });

        skipButton.addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);

        render();
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        nextButton.focus();
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== '?' || event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        var target = event.target;
        var tag = target && target.tagName ? target.tagName.toLowerCase() : '';

        if (tag === 'input' || tag === 'textarea' || tag === 'select' || (target && target.isContentEditable)) {
            return;
        }

        event.preventDefault();
        openShortcutsHelp();
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

    /**
     * "Mobil sepette swipe-to-remove" - mini sepet çekmecesindeki
     * (`data-scp-mini-cart`, yukarıdaki initMiniCart()) her satır dokunmalı
     * cihazlarda sola kaydırılarak silinebiliyor. Tam sepet sayfası
     * (/sepetim) BİLEREK kapsam DIŞI - orası bir masaüstü-tarzı tablo
     * (bkz. initCartQuantitySteppers()), satır satır swipe orada tablo
     * düzenini bozardı; kayan panel zaten klasik "kaydır-sil" listesi
     * şekline sahip. Silme işlemi kendi AJAX'ını İCAT ETMİYOR - WooCommerce
     * kendi `.remove_from_cart_button` linkine zaten wc-cart-fragments.js
     * üzerinden AJAX kaldırma bağlıyor; eşik aşıldığında bu fonksiyon o
     * linke programatik bir `click()` gönderiyor, gerçek kaldırma işini
     * WC'nin KENDİ kodu yapıyor.
     */
    function initMiniCartSwipeToRemove() {
        var drawer = document.querySelector('[data-scp-mini-cart]');

        if (!drawer) {
            return;
        }

        var SWIPE_THRESHOLD = 72;

        function wireItem(item) {
            if (item.dataset.scpSwipeBound) {
                return;
            }

            item.dataset.scpSwipeBound = '1';

            var startX = null;
            var currentX = 0;

            item.addEventListener('touchstart', function (event) {
                if (event.touches.length !== 1) {
                    return;
                }

                startX = event.touches[0].clientX;
                item.classList.add('scp-swipeable--dragging');
            }, { passive: true });

            item.addEventListener('touchmove', function (event) {
                if (startX === null) {
                    return;
                }

                currentX = Math.min(0, event.touches[0].clientX - startX);
                item.style.transform = 'translateX(' + currentX + 'px)';
            }, { passive: true });

            item.addEventListener('touchend', function () {
                item.classList.remove('scp-swipeable--dragging');

                if (currentX < -SWIPE_THRESHOLD) {
                    var removeLink = item.querySelector('.remove_from_cart_button');

                    if (removeLink) {
                        item.style.transform = 'translateX(-100%)';
                        item.style.opacity = '0';
                        window.setTimeout(function () {
                            removeLink.click();
                        }, 150);
                        startX = null;
                        currentX = 0;
                        return;
                    }
                }

                item.style.transform = '';
                startX = null;
                currentX = 0;
            });
        }

        function wireAll() {
            drawer.querySelectorAll('.mini_cart_item').forEach(wireItem);
        }

        wireAll();

        // Mini sepet içeriği WC'nin kendi AJAX yenilemesiyle (bir ürün
        // eklendiğinde/kaldırıldığında) tamamen yeniden çiziliyor - yeni
        // gelen satırlara da aynı dinleyicileri bağla.
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('wc_fragments_refreshed added_to_cart removed_from_cart', wireAll);
        }
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

    // ---- Manuel karanlık mod anahtarı ----

    /**
     * header.php'nin `[data-scp-theme-toggle]` düğmesi - gerçek erken
     * uygulama inc/setup.php'nin scp_theme_preload_script()'inde (sayfa
     * boyanmadan ÖNCE, `<head>`'de) oluyor; bu yalnızca tıklamayı işleyip
     * `<html data-theme>`'i tersine çeviriyor ve seçimi
     * `localStorage.scpTheme`'e yazıyor, sonraki her sayfa yüklemesinde o
     * satır içi script'in okuyacağı yer. `scpPanelText` yoksa (bu
     * dosyanın kendi docblock'u - paylaşılan temel dosya, tüketici değil)
     * sabit bir Türkçe fallback'e düşüyor, initScrollToTop()'un
     * `scrollToTop` etiketini okuma şekliyle AYNI.
     */
    function initThemeToggle() {
        var button = document.querySelector('[data-scp-theme-toggle]');

        if (!button) {
            return;
        }

        var textData = typeof scpPanelText !== 'undefined' ? scpPanelText : {};

        function currentTheme() {
            var stamped = document.documentElement.getAttribute('data-theme');

            // Site her zaman açık modda başlar - OS'nin prefers-color-scheme
            // tercihi kasıtlı olarak yoksayılıyor (bkz. theme.css'in aynı
            // kararı). data-theme stampalanmamışsa (kullanıcı hiç
            // seçmemiş) varsayılan 'light'.
            return stamped === 'dark' ? 'dark' : 'light';
        }

        function updateLabel() {
            button.textContent = currentTheme() === 'dark'
                ? (textData.themeToggleToLight || 'Aydınlık Mod')
                : (textData.themeToggleToDark || 'Koyu Mod');
        }

        button.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);

            try {
                localStorage.setItem('scpTheme', next);
            } catch (e) {
                // Privacy-mode/iframe contexts can block localStorage - the
                // toggle still works for the rest of THIS page view, it
                // just won't be remembered on the next load.
            }

            updateLabel();
        });

        updateLabel();
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

    /**
     * "Yazdırılabilir sipariş görünümü" - window.scpPrintOrder(), hem
     * orders-panel.js (veli) hem admin-orders-panel.js (admin/şube)
     * tarafından çağrılan TEK paylaşılan implementasyon. Sayfanın geri
     * kalanını (header, sidebar, filtre formu, DİĞER sipariş kartları)
     * gizlemeye çalışmak yerine - ki bu iki sayfanın farklı DOM
     * yerleşimleri için ayrı ayrı mantık gerektirirdi - ham sipariş
     * verisinden TEMİZ, sayfa yerleşiminden bağımsız bir fiş/fatura DOM
     * parçası inşa edip `#scp-print-order-root`'a yazıyor; bu kök normal
     * görünümde CSS ile gizli, yalnızca `body.scp-printing-order`
     * sınıfı VARKEN (yazdırma sırasında) görünür oluyor - bkz. panel.css.
     */
    function printMetaRow(dl, label, value) {
        var dt = document.createElement('dt');
        dt.textContent = label;
        var dd = document.createElement('dd');
        dd.textContent = value;
        dl.appendChild(dt);
        dl.appendChild(dd);
    }

    window.scpPrintOrder = function (order, text, formatMoney, options) {
        options = options || {};

        var root = document.getElementById('scp-print-order-root');

        if (!root) {
            root = document.createElement('div');
            root.id = 'scp-print-order-root';
            document.body.appendChild(root);
        }

        root.innerHTML = '';

        var heading = document.createElement('h1');
        heading.textContent = (text.orderPrintTitle || 'Sipariş') + ' - #' + order.number;
        root.appendChild(heading);

        var meta = document.createElement('dl');
        printMetaRow(meta, text.orderDateLabel, order.date || '');

        if (options.customerName) {
            printMetaRow(meta, text.orderCustomerLabel, options.customerName);
        }

        printMetaRow(meta, text.orderStatusLabel, order.status_label || order.status);
        printMetaRow(meta, text.orderSubtotalLabel, formatMoney(order.subtotal));
        printMetaRow(meta, text.orderTaxLabel, formatMoney(order.total_tax));
        printMetaRow(meta, text.orderTotalLabel, formatMoney(order.total));
        root.appendChild(meta);

        var table = document.createElement('table');
        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        [
            text.orderItemProductLabel,
            text.orderItemStudentLabel,
            text.orderItemQuantityLabel,
            text.orderItemUnitPriceLabel,
            text.orderItemTotalLabel
        ].forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        (order.items || []).forEach(function (item) {
            var row = document.createElement('tr');
            [
                item.name,
                item.student_name || '',
                String(item.quantity),
                formatMoney(item.unit_price),
                formatMoney(item.line_total)
            ].forEach(function (cellText) {
                var cell = document.createElement('td');
                cell.textContent = cellText;
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        root.appendChild(table);

        document.body.classList.add('scp-printing-order');

        var cleanup = function () {
            document.body.classList.remove('scp-printing-order');
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);
        window.print();

        // `afterprint` doesn't fire reliably in every browser/print-preview
        // flow - print dialogs are modal, so by the time this fallback
        // timeout fires the user has already printed or cancelled either way.
        setTimeout(cleanup, 2000);
    };

    /**
     * "Yıllık harcama özeti" - scpPrintOrder()'ın AYNI kalıbını (aynı
     * `#scp-print-order-root`/`body.scp-printing-order`/`window.print()`
     * mekanizması, bkz. panel.css) tek bir siparişin fişi yerine BİR YILIN
     * toplu özetine uyguluyor. Sunucu tarafında yeni bir PDF kütüphanesi
     * (Dompdf vb.) EKLENMEDİ - tarayıcının kendi "Yazdır > PDF olarak
     * kaydet" hedefi zaten bunu karşılıyor, bu platformdaki her "yazdır"
     * özelliğinin (bkz. scpPrintOrder) izlediği aynı sıfır-bağımlılık
     * ilkesi.
     */
    window.scpPrintSpendingSummary = function (summary, year, text, formatMoney, options) {
        options = options || {};

        var root = document.getElementById('scp-print-order-root');

        if (!root) {
            root = document.createElement('div');
            root.id = 'scp-print-order-root';
            document.body.appendChild(root);
        }

        root.innerHTML = '';

        var heading = document.createElement('h1');
        heading.textContent = (text.spendingSummaryPrintTitle || '') + ' - ' + year;
        root.appendChild(heading);

        var meta = document.createElement('dl');

        if (options.customerName) {
            printMetaRow(meta, text.orderCustomerLabel, options.customerName);
        }

        printMetaRow(meta, text.spendingSummaryOrderCountLabel, String(summary.orderCount));
        printMetaRow(meta, text.orderSubtotalLabel, formatMoney(summary.subtotal));
        printMetaRow(meta, text.orderTaxLabel, formatMoney(summary.tax));
        printMetaRow(meta, text.orderTotalLabel, formatMoney(summary.total));
        root.appendChild(meta);

        var studentHeading = document.createElement('h2');
        studentHeading.textContent = text.spendingSummaryByStudentLabel || '';
        root.appendChild(studentHeading);

        var table = document.createElement('table');
        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        [text.orderItemStudentLabel, text.orderItemTotalLabel].forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        Object.keys(summary.byStudent).forEach(function (studentName) {
            var row = document.createElement('tr');
            [studentName, formatMoney(summary.byStudent[studentName])].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        root.appendChild(table);

        document.body.classList.add('scp-printing-order');

        var cleanup = function () {
            document.body.classList.remove('scp-printing-order');
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);
        window.print();
        setTimeout(cleanup, 2000);
    };

    /**
     * "Son görüntülenen ürünler" - `#scp-recently-viewed-marker`'ın
     * data-* öznitelikleri (inc/woocommerce.php'nin
     * scp_render_recently_viewed_marker()'ı) o anki ürünü
     * `localStorage.scpRecentlyViewed`'e kaydediyor (en yeni önde, id'ye
     * göre tekilleştirilmiş, en fazla 8 kayıt), sonra
     * `[data-scp-recently-viewed]` kabı (varsa) o anki ürün HARİÇ en
     * fazla 6 kaydı küçük kartlar olarak render ediyor. Bu sayfa hiçbir
     * ürün sayfası değilse (marker yok) fonksiyon hiçbir şey yapmadan
     * çıkıyor - diğer generic initXxx() fonksiyonlarıyla aynı desen.
     */
    function initRecentlyViewed() {
        var STORAGE_KEY = 'scpRecentlyViewed';
        var MAX_STORED = 8;
        var MAX_SHOWN = 6;

        var marker = document.getElementById('scp-recently-viewed-marker');

        if (marker) {
            var current = {
                id: marker.dataset.id,
                name: marker.dataset.name,
                url: marker.dataset.url,
                image: marker.dataset.image,
                price: marker.dataset.price
            };

            var stored;

            try {
                stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
            } catch (error) {
                stored = [];
            }

            stored = stored.filter(function (entry) {
                return entry.id !== current.id;
            });
            stored.unshift(current);
            stored = stored.slice(0, MAX_STORED);

            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
        }

        var container = document.querySelector('[data-scp-recently-viewed]');

        if (!container) {
            return;
        }

        var all;

        try {
            all = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
        } catch (error) {
            all = [];
        }

        var currentId = marker ? marker.dataset.id : null;
        var others = all.filter(function (entry) {
            return entry.id !== currentId;
        }).slice(0, MAX_SHOWN);

        if (others.length === 0) {
            return;
        }

        var heading = document.createElement('h2');
        heading.className = 'scp-recently-viewed__heading';
        heading.textContent = (typeof scpPanelText !== 'undefined' && scpPanelText.recentlyViewedHeading)
            ? scpPanelText.recentlyViewedHeading
            : 'Son Görüntülenen Ürünler';
        container.appendChild(heading);

        var list = document.createElement('div');
        list.className = 'scp-recently-viewed__list';

        others.forEach(function (entry) {
            var link = document.createElement('a');
            link.className = 'scp-recently-viewed__item';
            link.href = entry.url;

            var img = document.createElement('img');
            img.src = entry.image;
            img.alt = '';
            link.appendChild(img);

            var name = document.createElement('span');
            name.className = 'scp-recently-viewed__name';
            name.textContent = entry.name;
            link.appendChild(name);

            var price = document.createElement('span');
            price.className = 'scp-recently-viewed__price';
            price.textContent = entry.price;
            link.appendChild(price);

            list.appendChild(link);
        });

        container.appendChild(list);
        initImageLazyPlaceholders();
    }

    /**
     * "Görsellerde lazy-loading + blur-up placeholder" - tarayıcının
     * kendi `loading="lazy"` özniteliği (WordPress çekirdeği zaten
     * çoğu görsele otomatik ekliyor, bkz. wp_lazy_loading_enabled) yalnızca
     * İNDİRMEYİ erteliyor, indirme sırasında/öncesinde boş bir kutu
     * kalıyor. Bu, o boşluğu `.scp-skeleton`'IN AYNI shimmer animasyonuyla
     * (panel.css, karanlık mod varyantı dahil - YENİ bir "blur" efekti
     * İCAT ETMİYOR) dolduruyor: görsel `load`/`error` olayı ateşlenene
     * kadar `.scp-skeleton` sınıfı taşıyor, sonra kaldırılıyor - CSS'in
     * kendisi img'nin arkaplanını/üstündeki ::after shimmer'ı gösteriyor,
     * yüklenen görsel doğal olarak onun üstünü kaplıyor. Tarayıcı önbelleğinden
     * anında `complete` gelen görseller shimmer'sız atlanıyor (gereksiz
     * yanıp sönme olmasın diye). Mağaza/sepet/mini-sepet gibi WooCommerce
     * şablonlarının img'leri (`.woocommerce` kapsamı) + `.scp-recently-
     * viewed` (yukarıdaki initRecentlyViewed() tarafından DOMContentLoaded
     * SONRASINDA eklenen görseller için ayrıca çağrılıyor).
     */
    function initImageLazyPlaceholders() {
        document.querySelectorAll('.woocommerce img, .scp-recently-viewed img').forEach(function (img) {
            if (img.dataset.scpLazyBound) {
                return;
            }

            img.dataset.scpLazyBound = '1';

            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }

            if (img.complete && img.naturalWidth > 0) {
                return;
            }

            img.classList.add('scp-skeleton', 'scp-img-placeholder');

            function clear() {
                img.classList.remove('scp-skeleton', 'scp-img-placeholder');
            }

            img.addEventListener('load', clear, { once: true });
            img.addEventListener('error', clear, { once: true });
        });
    }

    /**
     * "Stok gelince haber ver" - inc/woocommerce.php'nin
     * scp_render_stock_subscription()'ı yalnızca stokta olmayan bir
     * ürün sayfasında (ve yalnızca veli için) boş bir kap basıyor; bu
     * fonksiyon o kabın gerçek abonelik durumunu GET ile sorgulayıp
     * abone-ol/aboneliği-iptal-et arasında geçiş yapan tek bir düğme
     * render ediyor. `scpApiFetch`/`scpPanel` bu sayfada zaten mevcut -
     * scp-notifications-bell.js her girişli kullanıcı için HER sayfada
     * (ürün sayfaları dahil) koşulsuz enqueue edildiğinden ve o da AYNI
     * scpPanel/scpPanelText'i localize ettiğinden (inc/assets.php), ayrı
     * bir yerelleştirme gerekmiyor.
     */
    function initStockSubscription() {
        var container = document.querySelector('[data-scp-stock-subscription]');

        if (!container || typeof scpApiFetch === 'undefined' || typeof scpPanel === 'undefined') {
            return;
        }

        var productId = container.dataset.productId;

        var text = {
            subscribe: (typeof scpPanelText !== 'undefined' && scpPanelText.stockSubscribeAction)
                || 'Stok Gelince Haber Ver',
            unsubscribe: (typeof scpPanelText !== 'undefined' && scpPanelText.stockUnsubscribeAction)
                || 'Aboneliği İptal Et',
            subscribed: (typeof scpPanelText !== 'undefined' && scpPanelText.stockSubscribed)
                || 'Bu ürün stoğa girince size haber vereceğiz.'
        };

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'scp-btn scp-btn--ghost';
        button.hidden = true;
        container.appendChild(button);

        var note = document.createElement('p');
        note.className = 'scp-stock-subscription__note';
        note.hidden = true;
        note.textContent = text.subscribed;
        container.appendChild(note);

        function render(subscribed) {
            button.textContent = subscribed ? text.unsubscribe : text.subscribe;
            button.hidden = false;
            note.hidden = !subscribed;
        }

        function toggle(currentlySubscribed) {
            var request = currentlySubscribed
                ? scpApiFetch('commerce/stock-subscriptions/' + productId, { method: 'DELETE' })
                : scpApiFetch('commerce/stock-subscriptions', {
                    method: 'POST',
                    body: JSON.stringify({ product_id: Number(productId) })
                });

            request.then(function (result) {
                if (!result.ok) {
                    return;
                }

                render(Boolean(result.data.subscribed));
                button.onclick = function () {
                    toggle(Boolean(result.data.subscribed));
                };
            });
        }

        scpApiFetch('commerce/stock-subscriptions/' + productId).then(function (result) {
            if (!result.ok) {
                return;
            }

            var subscribed = Boolean(result.data.subscribed);
            render(subscribed);
            button.onclick = function () {
                toggle(subscribed);
            };
        });
    }

    /**
     * "Sepette miktar +/- anlık güncelleme" - WooCommerce'in KENDİ
     * `cart/cart.php` şablonundaki `input.qty` alanının etrafına +/-
     * düğmeleri ekliyor; değişiklikte "Sepeti Güncelle" düğmesine
     * TIKLAMAK yerine (tam sayfa yenilemesi) sepet formunu `fetch` ile
     * AYNI URL'e POST edip dönen HTML'den yalnızca WooCommerce'in kendi
     * sabit `.woocommerce-cart-form`/`.cart-collaterals` bloklarını canlı
     * DOM'da değiştiriyor - bu ikisi WC'nin HER temada aynı kalan kendi
     * şablon sınıfları, temanın kendi sayfa sarmalayıcısına bağımlı
     * değil. Yeni bir REST endpoint YOK - aynı klasik `?update_cart=1`
     * form POST akışı, yalnızca tarayıcı gezintisi olmadan.
     */
    function initCartQuantitySteppers() {
        var form = document.querySelector('form.woocommerce-cart-form');

        if (!form) {
            return;
        }

        function addSteppers(scope) {
            scope.querySelectorAll('input.qty').forEach(function (input) {
                if (input.dataset.scpStepperAdded) {
                    return;
                }

                input.dataset.scpStepperAdded = '1';

                var wrapper = document.createElement('span');
                wrapper.className = 'scp-qty-stepper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);

                var minus = document.createElement('button');
                minus.type = 'button';
                minus.className = 'scp-qty-stepper__btn';
                minus.setAttribute('aria-label', '-');
                minus.textContent = '−';
                minus.addEventListener('click', function () {
                    var min = input.min !== '' ? parseFloat(input.min) : 0;
                    input.value = String(Math.max(min, (parseFloat(input.value) || 0) - 1));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                var plus = document.createElement('button');
                plus.type = 'button';
                plus.className = 'scp-qty-stepper__btn';
                plus.setAttribute('aria-label', '+');
                plus.textContent = '+';
                plus.addEventListener('click', function () {
                    var max = input.max !== '' ? parseFloat(input.max) : Infinity;
                    input.value = String(Math.min(max, (parseFloat(input.value) || 0) + 1));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                wrapper.insertBefore(minus, input);
                wrapper.appendChild(plus);
            });
        }

        addSteppers(form);

        var debounceTimer = null;

        function submitCartUpdate() {
            var formData = new FormData(form);
            formData.set('update_cart', 'Update cart');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var freshForm = doc.querySelector('form.woocommerce-cart-form');
                var currentForm = document.querySelector('form.woocommerce-cart-form');

                if (freshForm && currentForm) {
                    currentForm.replaceWith(freshForm);
                    addSteppers(freshForm);
                }

                var freshTotals = doc.querySelector('.cart-collaterals');
                var currentTotals = document.querySelector('.cart-collaterals');

                if (freshTotals && currentTotals) {
                    currentTotals.replaceWith(freshTotals);
                }

                // initCartStudentGrouping()'in renderFamilyPackageSummary()'si
                // özet kartını `.cart_totals`'ın hemen ÖNÜNE ekliyor - bu
                // yüzden `.cart-collaterals` (o `.cart_totals`'ı SARAN
                // kap) YUKARIDA zaten tazelendikten SONRA çağrılıyor,
                // yoksa kart o kabın değişimiyle birlikte silinirdi.
                if (freshForm && currentForm) {
                    initCartStudentGrouping();
                }

                if (typeof jQuery !== 'undefined') {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                }
            });
        }

        form.addEventListener('change', function (event) {
            if (!event.target.classList || !event.target.classList.contains('qty')) {
                return;
            }

            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(submitCartUpdate, 400);
        });
    }

    /**
     * "Mobilde sabit alt sepet özeti çubuğu" - sepet sayfasında
     * (form.woocommerce-cart-form) mobilde ekranın altına sabitlenen,
     * toplamı ve "Ödemeye Geç" düğmesini gösteren bir çubuk (CSS'i
     * panel.css'in AYNI @media max-width:640px kırılma noktasında -
     * masaüstünde hiç render edilmiyor). Kendi toplam/checkout-link
     * mantığını İCAT ETMİYOR - initCartQuantitySteppers()'ın zaten
     * güncellediği `.cart_totals`'ın kendi `.order-total .amount`'unu ve
     * `a.checkout-button`'unun href/metnini OKUYOR; miktar
     * güncellemesinden sonra initCartQuantitySteppers()'ın zaten
     * tetiklediği `wc_fragment_refresh` olayında yeniden senkronlanıyor -
     * ayrı bir REST endpoint veya polling yok. Aynı ekranda veli için
     * `.scp-mobile-bottom-nav` da sabitse (header.php), çubuk onun
     * ÜSTÜNE oturuyor (bottom: 64px, `.scp-layout`'un o navigasyon için
     * zaten kullandığı AYNI 64px değeri) - iki sabit çubuk üst üste
     * binmiyor.
     */
    function initStickyCartBar() {
        var form = document.querySelector('form.woocommerce-cart-form');

        if (!form) {
            return;
        }

        var bar = document.createElement('div');
        bar.className = 'scp-sticky-cart-bar';
        bar.innerHTML = '<span class="scp-sticky-cart-bar__total" data-scp-sticky-cart-total></span>'
            + '<a class="scp-btn" data-scp-sticky-cart-checkout></a>';
        document.body.appendChild(bar);
        document.body.classList.add('scp-has-sticky-cart-bar');

        var totalEl = bar.querySelector('[data-scp-sticky-cart-total]');
        var checkoutLink = bar.querySelector('[data-scp-sticky-cart-checkout]');

        function sync() {
            var amountEl = document.querySelector('.cart_totals .order-total .amount');
            var checkoutButton = document.querySelector('a.checkout-button');

            if (!amountEl || !checkoutButton) {
                bar.hidden = true;
                document.body.classList.remove('scp-has-sticky-cart-bar');
                return;
            }

            bar.hidden = false;
            document.body.classList.add('scp-has-sticky-cart-bar');
            totalEl.textContent = amountEl.textContent;
            checkoutLink.textContent = checkoutButton.textContent;
            checkoutLink.href = checkoutButton.href;
        }

        sync();

        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('wc_fragment_refresh', sync);
        }
    }

    /**
     * "Ödeme formunda gerçek zamanlı, satır içi doğrulama" - WooCommerce
     * kendi doğrulamasını yalnızca SUBMIT anında yapıyor (checkout.js,
     * dokunulmadı); bu fonksiyon her alanın kendi `blur`'unda WC'nin
     * ZATEN bastığı `validate-required`/`validate-email` sınıflarını
     * (`.form-row`'un kendi sınıfları - her WC checkout alanı bunları
     * taşır, yeni bir işaretleme icat edilmedi) okuyup anlık bir hata
     * mesajı gösteriyor/gizliyor - sunucu tarafı doğrulamanın YERİNE
     * geçmiyor, yalnızca submit'e kadar beklemeden erken geri bildirim.
     */
    function initCheckoutInlineValidation() {
        var form = document.querySelector('form.woocommerce-checkout');

        if (!form) {
            return;
        }

        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function fieldOf(row) {
            return row.querySelector('input, select, textarea');
        }

        function validateRow(row) {
            var field = fieldOf(row);

            if (!field) {
                return;
            }

            var value = field.value.trim();
            var message = '';

            if (row.classList.contains('validate-required') && value === '') {
                message = scpPanelTextValidationRequired();
            } else if (row.classList.contains('validate-email') && value !== '' && !emailPattern.test(value)) {
                message = scpPanelTextValidationEmail();
            }

            var errorEl = row.querySelector('.scp-field-error');

            if (message === '') {
                row.classList.remove('scp-field-invalid');

                if (errorEl) {
                    errorEl.remove();
                }

                return;
            }

            row.classList.add('scp-field-invalid');

            if (!errorEl) {
                errorEl = document.createElement('span');
                errorEl.className = 'scp-field-error';
                row.appendChild(errorEl);
            }

            errorEl.textContent = message;
        }

        function scpPanelTextValidationRequired() {
            return (typeof scpPanelText !== 'undefined' && scpPanelText.checkoutFieldRequired)
                || 'Bu alan zorunludur.';
        }

        function scpPanelTextValidationEmail() {
            return (typeof scpPanelText !== 'undefined' && scpPanelText.checkoutFieldInvalidEmail)
                || 'Geçerli bir e-posta adresi girin.';
        }

        function bindRow(row) {
            var field = fieldOf(row);

            if (!field || field.dataset.scpValidationBound) {
                return;
            }

            field.dataset.scpValidationBound = '1';
            field.addEventListener('blur', function () {
                validateRow(row);
            });
            field.addEventListener('input', function () {
                if (row.classList.contains('scp-field-invalid')) {
                    validateRow(row);
                }
            });
        }

        form.querySelectorAll('.form-row').forEach(bindRow);

        // WooCommerce checkout alanları ödeme yöntemi seçimine göre AJAX
        // ile yeniden çiziliyor (`updated_checkout` - update_order_review
        // sonrası) - yeni gelen alanlara da aynı dinleyicileri bağla.
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('updated_checkout', function () {
                form.querySelectorAll('.form-row').forEach(bindRow);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.scpKebabMenus();
        window.scpSidebarNav();
        initLargeTitleScroll();
        initCharCounters();
        initMiniCart();
        initMiniCartSwipeToRemove();
        initOrderCelebration();
        initQuickView();
        initScrollToTop();
        initResponsiveTables();
        initThemeToggle();
        initRecentlyViewed();
        initStockSubscription();
        initCartQuantitySteppers();
        initCheckoutInlineValidation();
        initStickyCartBar();
        initAriaLiveRegions();
        initImageLazyPlaceholders();
        initCartStudentGrouping();
        initOnboardingTour();
    });

    /**
     * "Çoklu öğrencili velilerde sepet ürünlerini öğrenciye göre
     * gruplama/renklendirme" - WooCommerceCartHooks::displayStudentName()
     * (plugin/seviye-commerce) her sepet satırının ürün adının altına
     * WC'nin KENDİ `woocommerce_get_item_data` meta bloğuyla (`<dl
     * class="variation">`) zaten "Öğrenci: Ahmet Yılmaz" yazıyordu - yeni
     * bir veri kaynağı İCAT EDİLMEDİ, bu metin OKUNUYOR. İki öğrenciden
     * az varsa (0 ya da 1) hiçbir şey yapmıyor - gruplamanın bir anlamı
     * olmaz. Satırları FİZİKSEL olarak öğrenciye göre yeniden sıralayıp
     * (form gönderimini BOZMUYOR - tarayıcı bir <form>'un tüm alt
     * input'larını DOM sırasından BAĞIMSIZ toplar) her grubun başına bir
     * öğrenci alt-başlığı satırı ekliyor, ayrıca her satırın soluna
     * öğrenciye özgü sabit bir renk şeridi koyuyor.
     * initCartQuantitySteppers()'ın miktar güncellemesi tabloyu WC'nin
     * KENDİ HTML'iyle değiştirdiğinde (submitCartUpdate()) bu fonksiyon
     * ORADA DA yeniden çağrılıyor, yoksa gruplama miktar değişikliğinde
     * kaybolurdu.
     */
    function initCartStudentGrouping() {
        var table = document.querySelector('.woocommerce-cart-form table.shop_table');

        if (!table) {
            return;
        }

        var tbody = table.querySelector('tbody');
        var headRow = table.querySelector('thead tr');

        if (!tbody || !headRow) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.cart_item'));

        if (rows.length < 2) {
            return;
        }

        var COLORS = ['#2a5cb8', '#0f9d63', '#c2410c', '#7c3aed', '#be123c', '#0e7490'];

        function studentNameFor(row) {
            var dts = row.querySelectorAll('dl.variation dt');

            for (var i = 0; i < dts.length; i++) {
                if (dts[i].textContent.indexOf(scpCartStudentLabel()) === 0) {
                    var dd = dts[i].nextElementSibling;

                    return dd ? dd.textContent.trim() : null;
                }
            }

            return null;
        }

        function scpCartStudentLabel() {
            return (typeof scpPanelText !== 'undefined' && scpPanelText.cartStudentLabel) || 'Öğrenci';
        }

        var order = [];
        var groups = {};
        var ungrouped = [];

        rows.forEach(function (row) {
            var name = studentNameFor(row);

            if (!name) {
                ungrouped.push(row);
                return;
            }

            if (!groups[name]) {
                groups[name] = [];
                order.push(name);
            }

            groups[name].push(row);
        });

        // Tek öğrenci (ya da hiç öğrenci meta'sı yok) - gruplamanın bir
        // anlamı yok, satırları olduğu gibi bırak.
        if (order.length < 2) {
            return;
        }

        var columnCount = headRow.children.length;

        order.forEach(function (name, index) {
            var color = COLORS[index % COLORS.length];

            var header = document.createElement('tr');
            header.className = 'scp-cart-student-group';

            var cell = document.createElement('td');
            cell.colSpan = columnCount;
            cell.style.borderLeftColor = color;

            var dot = document.createElement('span');
            dot.className = 'scp-cart-student-group__dot';
            dot.style.background = color;
            cell.appendChild(dot);

            cell.appendChild(document.createTextNode(name));
            header.appendChild(cell);
            tbody.appendChild(header);

            groups[name].forEach(function (row) {
                row.style.borderLeft = '3px solid ' + color;
                tbody.appendChild(row);
            });
        });

        ungrouped.forEach(function (row) {
            tbody.appendChild(row);
        });

        renderFamilyPackageSummary(order, groups, COLORS);
    }

    /**
     * Bir metin içindeki tek bir para tutarını (WC'nin kendi basılı
     * `.amount` HTML'i, ör. "1.234,56 ₺") sayıya çeviriyor - JSON'dan
     * gelen HAM bir sayı değil, WC'nin ZATEN render ettiği HTML metni
     * (bu sayfada sepetin kendi ayrı bir REST/JSON temsili yok). Türkçe
     * ("1.234,56") ve ABD ("1,234.56") biçimlerinin İKİSİNİ de doğru
     * ayrıştırmak için EN SONDAKİ ayraç ondalık kabul ediliyor, öncekiler
     * binlik ayracı olarak atılıyor.
     */
    function scpParseAmount(text) {
        var cleaned = (text || '').replace(/[^\d.,]/g, '');
        var decimalIndex = Math.max(cleaned.lastIndexOf(','), cleaned.lastIndexOf('.'));

        if (decimalIndex === -1) {
            return parseFloat(cleaned) || 0;
        }

        var intPart = cleaned.slice(0, decimalIndex).replace(/[.,]/g, '');
        var decPart = cleaned.slice(decimalIndex + 1).replace(/[.,]/g, '');

        return parseFloat(intPart + '.' + decPart) || 0;
    }

    /**
     * "Aile paketi özeti (sepette birden fazla öğrenci)" -
     * initCartStudentGrouping()'in zaten hesapladığı gruplardan, her
     * öğrencinin kendi ürün sayısı + ara toplamını gösteren küçük bir
     * kart. WC'nin KENDİ satır `.product-subtotal .amount`
     * metinlerinin toplamı (scpParseAmount() ile) - ayrı bir fiyat
     * hesaplama mantığı İCAT EDİLMİYOR, yalnızca zaten ekranda basılı
     * olan tutarlar gruplanıyor. `.cart_totals`'ın hemen ÖNÜNE
     * ekleniyor (varsa) - "Ara Toplam/Toplam" ile aynı görsel bölgede.
     */
    function renderFamilyPackageSummary(order, groups, colors) {
        var existing = document.querySelector('.scp-family-summary');

        if (existing) {
            existing.remove();
        }

        var card = document.createElement('div');
        card.className = 'scp-family-summary';

        var heading = document.createElement('h2');
        heading.textContent = (typeof scpPanelText !== 'undefined' && scpPanelText.familySummaryHeading)
            || 'Aile Paketi Özeti';
        card.appendChild(heading);

        var list = document.createElement('ul');

        order.forEach(function (name, index) {
            var rows = groups[name];
            var subtotal = rows.reduce(function (sum, row) {
                var amountEl = row.querySelector('.product-subtotal .amount');

                return sum + (amountEl ? scpParseAmount(amountEl.textContent) : 0);
            }, 0);

            var item = document.createElement('li');

            var dot = document.createElement('span');
            dot.className = 'scp-cart-student-group__dot';
            dot.style.background = colors[index % colors.length];
            item.appendChild(dot);

            var label = document.createElement('span');
            label.className = 'scp-family-summary__name';
            label.textContent = name + ' (' + rows.length + ')';
            item.appendChild(label);

            var amount = document.createElement('span');
            amount.className = 'scp-family-summary__amount';
            amount.textContent = subtotal.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                + ' TRY';
            item.appendChild(amount);

            list.appendChild(item);
        });

        card.appendChild(list);

        var totals = document.querySelector('.cart_totals');

        if (totals && totals.parentNode) {
            totals.parentNode.insertBefore(card, totals);
        } else {
            document.querySelector('.woocommerce-cart-form').insertAdjacentElement('afterend', card);
        }
    }

    /**
     * "Ekran okuyucu için dinamik içerik duyuruları" - onlarca panel
     * scriptinin (orders-panel.js, students-panel.js, ...) ortak
     * `<p class="scp-status" data-scp-...-status>` kalıbı şimdiye kadar
     * SESSİZDİ: `statusEl.textContent = 'Kaydedildi.'` gibi bir atama
     * ekran okuyucuya HİÇBİR ŞEY duyurmuyordu, çünkü elementin kendisi
     * `aria-live` taşımıyordu (scpToast()'ın host'u zaten
     * `aria-live="polite"` taşıyor - bkz. toastHost() yukarıda - ama
     * `.scp-status` bundan AYRI, çok daha yaygın kullanılan bir kalıp,
     * 60'tan fazla dosyada 90'dan fazla kullanım). 91 kullanım yerinin
     * HER BİRİNİ tek tek düzenlemek yerine (bkz. scpApiFetch/
     * scpSkeletonRows'un AYNI "tek boğaz noktası" ilkesi) tek bir DOMContent
     * Loaded taraması `.scp-status` ile eşleşen HER elemente
     * `aria-live="polite"` + `aria-atomic="true"` + `role="status"`
     * damgalıyor - ilk sayfa yüklemesinde DOM'da zaten var olan tüm
     * durum satırlarını kapsıyor (neredeyse tamamı, JS yalnızca
     * `textContent`'lerini sonradan dolduruyor).
     */
    function initAriaLiveRegions() {
        document.querySelectorAll('.scp-status').forEach(function (el) {
            if (!el.hasAttribute('aria-live')) {
                el.setAttribute('aria-live', 'polite');
            }

            if (!el.hasAttribute('aria-atomic')) {
                el.setAttribute('aria-atomic', 'true');
            }

            if (!el.hasAttribute('role')) {
                el.setAttribute('role', 'status');
            }
        });
    }
})();
