/**
 * Panel-içi (in-app) notification bell, rendered in header.php for EVERY
 * authenticated page (every zone, plus WooCommerce shop/product pages) -
 * unlike every other panel script, this one is never capability-gated,
 * mirroring account-security.js's "every logged-in user, no RBAC check"
 * reasoning (see inc/assets.php). Talks to
 * seviye/v1/notifications/mine/* (Seviye\Notifications\Http\NotificationsRestController).
 *
 * Expects two globals localized from PHP (see inc/assets.php):
 *   scpPanel     { restUrl, nonce }
 *   scpPanelText { ...translated UI strings }
 */
(function () {
    'use strict';

    var root = document.getElementById('scp-notifications-bell');

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

    var toggle = root.querySelector('[data-scp-notif-toggle]');
    var panel = root.querySelector('[data-scp-notif-panel]');
    var badge = root.querySelector('[data-scp-notif-badge]');
    var statusEl = root.querySelector('[data-scp-notif-status]');
    var list = root.querySelector('[data-scp-notif-list]');

    var apiFetch = scpApiFetch;

    // "Bildirim zili rozet animasyonu" - a brief pop only when the unread
    // count actually GREW since the last check (0 -> N on first load
    // counts, same as a genuinely new notification arriving would), not
    // on every refresh - re-marking something read also calls this and
    // shouldn't re-trigger the pop for a count that just went DOWN.
    var lastKnownUnreadCount = 0;

    function refreshBadge() {
        apiFetch('notifications/mine/unread-count').then(function (result) {
            if (!result.ok) {
                return;
            }

            var count = result.data.unread_count;
            badge.textContent = String(count);
            badge.hidden = count === 0;

            if (count > lastKnownUnreadCount) {
                badge.classList.remove('scp-notif-bell__badge--pop');
                void badge.offsetWidth; // restart the animation even if it's still mid-run
                badge.classList.add('scp-notif-bell__badge--pop');
            }

            lastKnownUnreadCount = count;
        });
    }

    function markRead(id, item) {
        apiFetch('notifications/mine/' + id + '/read', { method: 'POST' }).then(function (result) {
            if (!result.ok) {
                return;
            }

            item.classList.add('scp-notif-item--read');
            refreshBadge();
        });
    }

    function renderNotification(notification) {
        var li = document.createElement('li');
        li.className = notification.read_at ? 'scp-notif-item scp-notif-item--read' : 'scp-notif-item';

        var subject = document.createElement('strong');
        subject.textContent = notification.subject;
        li.appendChild(subject);

        var body = document.createElement('p');
        body.textContent = notification.body;
        li.appendChild(body);

        if (!notification.read_at) {
            li.addEventListener('click', function () {
                markRead(notification.id, li);
            });
        }

        return li;
    }

    /**
     * "Site genelinde eksik boş-durum illüstrasyonlarının tamamlanması" -
     * orders-panel.js'in renderEmptyOrdersState()'iyle AYNI desen
     * (`.scp-empty-state`, panel.css bölüm 169). Bu zilin kendi panel
     * genişliği (360px, bkz. theme.css'in .scp-notif-bell__panel'i)
     * içeriğin 320px'lik max-width'ini rahatça karşılıyor.
     */
    function renderEmptyNotificationsState() {
        // `list` is a <ul> (scp-list) - a bare <div> child would be
        // invalid HTML there, so this returns an <li> instead. Same
        // `.scp-empty-state` class/CSS either way (panel.css only
        // targets the class, not the tag).
        var wrapper = document.createElement('li');
        wrapper.className = 'scp-empty-state';

        var iconWrap = document.createElement('div');
        iconWrap.className = 'scp-empty-state__icon';
        iconWrap.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + '<path d="M6 9a6 6 0 0 1 12 0v5l2 3H4l2-3V9Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>';
        wrapper.appendChild(iconWrap);

        var heading = document.createElement('h3');
        heading.textContent = scpPanelTextData.noNotificationsHeading || scpPanelTextData.noNotifications;
        wrapper.appendChild(heading);

        var message = document.createElement('p');
        message.textContent = scpPanelTextData.noNotifications;
        wrapper.appendChild(message);

        return wrapper;
    }

    function loadList() {
        statusEl.textContent = '';
        statusEl.classList.remove('scp-status--error');

        if (typeof window.scpSkeletonRows === 'function') {
            window.scpSkeletonRows(list, 3);
        } else {
            list.innerHTML = '';
        }

        apiFetch('notifications/mine').then(function (result) {
            list.innerHTML = '';

            if (!result.ok) {
                statusEl.textContent = scpPanelTextData.loadError;
                statusEl.classList.add('scp-status--error');
                return;
            }

            if (result.data.length === 0) {
                list.appendChild(renderEmptyNotificationsState());
                return;
            }

            result.data.forEach(function (notification) {
                list.appendChild(renderNotification(notification));
            });
        });
    }

    // "Panel geçişi" - opening already animated in via the panel's own CSS
    // `animation` (restarts whenever `hidden` flips to false); closing had
    // none - `hidden = true` made it vanish instantly. `openPanel()` is
    // unchanged; `closePanel()` now adds a fade/slide-out class FIRST and
    // only sets `hidden = true` once that animation actually finishes
    // (`animationend`), the same "let the animation end, THEN remove"
    // pattern scpToast() already uses for its own leaving state.
    var panelOpen = false;

    function openPanel() {
        panelOpen = true;
        panel.classList.remove('scp-notif-bell__panel--closing');
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        loadList();
    }

    function closePanel() {
        panelOpen = false;
        toggle.setAttribute('aria-expanded', 'false');
        panel.classList.add('scp-notif-bell__panel--closing');
        panel.addEventListener('animationend', function onAnimationEnd() {
            panel.removeEventListener('animationend', onAnimationEnd);
            panel.classList.remove('scp-notif-bell__panel--closing');
            panel.hidden = true;
        });
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();

        if (panelOpen) {
            closePanel();
        } else {
            openPanel();
        }
    });

    document.addEventListener('click', function (event) {
        if (panelOpen && !root.contains(event.target)) {
            closePanel();
        }
    });

    refreshBadge();
})();
