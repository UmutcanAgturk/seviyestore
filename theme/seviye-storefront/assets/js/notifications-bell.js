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

    var toggle = root.querySelector('[data-scp-notif-toggle]');
    var panel = root.querySelector('[data-scp-notif-panel]');
    var badge = root.querySelector('[data-scp-notif-badge]');
    var statusEl = root.querySelector('[data-scp-notif-status]');
    var list = root.querySelector('[data-scp-notif-list]');

    var apiFetch = scpApiFetch;

    function refreshBadge() {
        apiFetch('notifications/mine/unread-count').then(function (result) {
            if (!result.ok) {
                return;
            }

            var count = result.data.unread_count;
            badge.textContent = String(count);
            badge.hidden = count === 0;
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

    function loadList() {
        statusEl.textContent = '';
        statusEl.classList.remove('scp-status--error');
        list.innerHTML = '';

        apiFetch('notifications/mine').then(function (result) {
            if (!result.ok) {
                statusEl.textContent = scpPanelText.loadError;
                statusEl.classList.add('scp-status--error');
                return;
            }

            if (result.data.length === 0) {
                statusEl.textContent = scpPanelText.noNotifications;
                return;
            }

            result.data.forEach(function (notification) {
                list.appendChild(renderNotification(notification));
            });
        });
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();

        var wasOpen = !panel.hidden;
        panel.hidden = wasOpen;
        toggle.setAttribute('aria-expanded', String(!wasOpen));

        if (!wasOpen) {
            loadList();
        }
    });

    document.addEventListener('click', function (event) {
        if (!panel.hidden && !root.contains(event.target)) {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    refreshBadge();
})();
