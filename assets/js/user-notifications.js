(function () {
    'use strict';

    var REFRESH_INTERVAL_MS = 45000;
    var NOTIFICATION_ICONS = {
        pending: 'bi-hourglass-split',
        confirmed: 'bi-check2-circle',
        completed: 'bi-patch-check',
        cancelled: 'bi-x-circle'
    };




    function createTextElement(tagName, text) {
        var element = document.createElement(tagName);
        element.textContent = text == null ? '' : String(text);
        return element;
    }

    function createNotificationItem(item) {
        var link = document.createElement('a');
        link.className = 'navbar-notification-item is-' + (item.status || 'pending');
        link.href = item.href || 'profile.php';

        var iconWrap = document.createElement('span');
        iconWrap.className = 'navbar-notification-item-icon';
        iconWrap.setAttribute('aria-hidden', 'true');
        var glyph = document.createElement('i');
        glyph.className = 'bi ' + (NOTIFICATION_ICONS[item.status] || 'bi-info-circle');
        iconWrap.appendChild(glyph);

        var body = document.createElement('span');
        body.className = 'navbar-notification-item-body';
        body.appendChild(createTextElement('strong', item.title));
        body.appendChild(createTextElement('span', item.message));
        body.appendChild(createTextElement('small', item.meta));

        link.appendChild(iconWrap);
        link.appendChild(body);
        return link;
    }

    function initNotifications() {
        var root = document.querySelector('[data-user-notifications]');
        if (!root) {
            return;
        }

        var toggle = root.querySelector('[data-user-notifications-toggle]');
        var panel = root.querySelector('.navbar-notification-panel');
        var list = root.querySelector('[data-user-notification-list]');
        var empty = root.querySelector('[data-user-notification-empty]');
        var badge = root.querySelector('[data-user-notification-badge]');
        var summary = root.querySelector('[data-user-notification-summary]');
        var endpoint = root.dataset.endpoint || 'user-notifications.php';
        var pollTimer = null;

        if (!toggle || !panel || !list) {
            return;
        }

        function setOpen(isOpen) {
            panel.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            root.classList.toggle('is-open', isOpen);
        }

        function render(feed) {
            var items = Array.isArray(feed.items) ? feed.items : [];
            var count = Number.isSafeInteger(feed.count) ? feed.count : items.length;

            list.replaceChildren();
            items.forEach(function (item) {
                list.appendChild(createNotificationItem(item));
            });

            if (empty) {
                empty.hidden = items.length > 0;
            }
            if (badge) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count === 0;
            }
            if (summary) {
                summary.textContent = count === 0
                    ? 'All caught up'
                    : count + (count === 1 ? ' needs' : ' need') + ' attention';
            }
            toggle.setAttribute(
                'aria-label',
                count === 0 ? 'Notifications, nothing pending' : 'Notifications, ' + count + ' need attention'
            );
        }

        function refresh() {
            if (document.hidden) {
                return;
            }

            fetch(endpoint, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Notifications could not be refreshed (HTTP ' + response.status + ').');
                    }
                    return response.json();
                })
                .then(render)
                .catch(function (error) {
                    if (!root.dataset.warned) {
                        root.dataset.warned = 'true';
                        console.error('User notifications failed:', error);
                    }
                });
        }

        toggle.addEventListener('click', function () {
            var isOpen = panel.hidden;
            setOpen(isOpen);
            if (isOpen) {
                refresh();
            }
        });

        document.addEventListener('click', function (event) {
            if (!panel.hidden && !root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
                toggle.focus();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refresh();
            }
        });

        pollTimer = window.setInterval(refresh, REFRESH_INTERVAL_MS);
        window.addEventListener('pagehide', function () {
            window.clearInterval(pollTimer);
        });

        refresh();
    }

    initNotifications();
})();
