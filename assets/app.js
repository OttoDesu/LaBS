(function () {
    var loginUrl = window.LABS_LOGIN_URL || 'login.html';
    var parsedUser = window.LABS_USER || null;
    if (!parsedUser) {
        var stored = localStorage.getItem('labs_user');
        parsedUser = stored ? JSON.parse(stored) : null;
    }
    if (!parsedUser) {
        window.location.href = loginUrl;
        return;
    }
    var userNameEl = document.getElementById('user-name');
    var userEmailEl = document.getElementById('user-email');
    if (userNameEl) {
        userNameEl.textContent = parsedUser.name || 'User';
    }
    if (userEmailEl) {
        userEmailEl.textContent = parsedUser.email || 'user@example.com';
    }

    var sidebar = document.getElementById('sidebar');
    var toggleButton = document.getElementById('toggle-sidebar');
    if (toggleButton) {
        toggleButton.addEventListener('click', function () {
            if (window.innerWidth <= 960) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    var userMenuToggle = document.getElementById('user-menu-toggle');
    var userMenu = document.getElementById('user-menu');
    if (userMenuToggle && userMenu) {
        var toggleMenu = function () {
            userMenu.classList.toggle('open');
            userMenuToggle.classList.toggle('open');
        };
        userMenuToggle.addEventListener('click', toggleMenu);
        userMenuToggle.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleMenu();
            }
        });
        document.addEventListener('click', function (event) {
            if (!userMenu.contains(event.target) && !userMenuToggle.contains(event.target)) {
                userMenu.classList.remove('open');
                userMenuToggle.classList.remove('open');
            }
        });
    }

    var logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function () {
            localStorage.removeItem('labs_user');
            window.location.href = loginUrl;
        });
    }

    var notificationButtons = Array.prototype.slice.call(document.querySelectorAll('.icon-button[aria-label="Notifications"]'));
    if (notificationButtons.length) {
        var notificationMenu = document.createElement('div');
        notificationMenu.className = 'notification-menu';
        notificationMenu.innerHTML = [
            '<div class="notification-menu-header">',
            '<div>',
            '<h3>Notifications</h3>',
            '<p>Latest booking updates</p>',
            '</div>',
            '<button class="btn ghost small notification-mark-all" type="button">Mark all read</button>',
            '</div>',
            '<div class="notification-menu-list"><div class="notification-empty">Loading notifications...</div></div>'
        ].join('');
        document.body.appendChild(notificationMenu);

        var notificationList = notificationMenu.querySelector('.notification-menu-list');
        var markAllButton = notificationMenu.querySelector('.notification-mark-all');
        var notificationOpenButton = null;

        function formatNotificationDate(value) {
            if (!value) {
                return '';
            }
            var parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) {
                return value;
            }
            var day = String(parsed.getDate()).padStart(2, '0');
            var month = String(parsed.getMonth() + 1).padStart(2, '0');
            var year = parsed.getFullYear();
            var hour = String(parsed.getHours()).padStart(2, '0');
            var minute = String(parsed.getMinutes()).padStart(2, '0');
            return day + '/' + month + '/' + year + ' ' + hour + ':' + minute;
        }

        function setNotificationCount(count) {
            notificationButtons.forEach(function (button) {
                var badge = button.querySelector('.notification-count');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-count';
                    button.appendChild(badge);
                }
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.hidden = false;
                    button.classList.add('has-notifications');
                } else {
                    badge.textContent = '';
                    badge.hidden = true;
                    button.classList.remove('has-notifications');
                }
            });
        }

        function closeNotificationMenu() {
            notificationMenu.classList.remove('open');
            notificationOpenButton = null;
        }

        function positionNotificationMenu(button) {
            var rect = button.getBoundingClientRect();
            notificationMenu.style.top = (window.scrollY + rect.bottom + 10) + 'px';
            notificationMenu.style.left = Math.max(16, window.scrollX + rect.right - 360) + 'px';
        }

        function renderNotifications(payload) {
            var items = Array.isArray(payload.items) ? payload.items : [];
            setNotificationCount(Number(payload.unread_count || 0));
            if (!items.length) {
                notificationList.innerHTML = '<div class="notification-empty">No notifications yet.</div>';
                return;
            }
            notificationList.innerHTML = items.map(function (item) {
                var classes = ['notification-item', 'type-' + String(item.notification_type || 'info')];
                if (!item.is_read) {
                    classes.push('is-unread');
                }
                return [
                    '<button class="' + classes.join(' ') + '" type="button" data-notification-id="' + String(item.notification_id || 0) + '" data-link-url="' + encodeURIComponent(String(item.link_url || '')) + '">',
                    '<div class="notification-item-top">',
                    '<strong>' + String(item.title || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>',
                    (!item.is_read ? '<span class="notification-unread-dot"></span>' : ''),
                    '</div>',
                    '<div class="notification-item-message">' + String(item.message || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>',
                    '<div class="notification-item-time">' + formatNotificationDate(item.created_at || '') + '</div>',
                    '</button>'
                ].join('');
            }).join('');
        }

        function fetchNotifications(openAfterFetch) {
            return fetch('notifications-api.php?action=list', {
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (payload && payload.success) {
                        renderNotifications(payload);
                        if (openAfterFetch && notificationOpenButton) {
                            positionNotificationMenu(notificationOpenButton);
                            notificationMenu.classList.add('open');
                        }
                    }
                })
                .catch(function () {
                    notificationList.innerHTML = '<div class="notification-empty">Unable to load notifications.</div>';
                });
        }

        function markNotificationsRead(notificationId) {
            var body = new URLSearchParams();
            body.set('action', 'mark_read');
            if (notificationId) {
                body.set('notification_id', String(notificationId));
            }
            return fetch('notifications-api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    setNotificationCount(Number(payload.unread_count || 0));
                    return payload;
                });
        }

        notificationButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (notificationMenu.classList.contains('open') && notificationOpenButton === button) {
                    closeNotificationMenu();
                    return;
                }
                notificationOpenButton = button;
                notificationList.innerHTML = '<div class="notification-empty">Loading notifications...</div>';
                fetchNotifications(true);
            });
        });

        markAllButton.addEventListener('click', function () {
            markNotificationsRead().then(function () {
                fetchNotifications(false);
            });
        });

        notificationList.addEventListener('click', function (event) {
            var item = event.target.closest('.notification-item');
            if (!item) {
                return;
            }
            var notificationId = Number(item.getAttribute('data-notification-id') || 0);
            var linkUrl = decodeURIComponent(item.getAttribute('data-link-url') || '');
            markNotificationsRead(notificationId).then(function () {
                if (linkUrl) {
                    window.location.href = linkUrl;
                } else {
                    fetchNotifications(false);
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (!notificationMenu.contains(event.target) && notificationButtons.every(function (button) { return !button.contains(event.target); })) {
                closeNotificationMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (notificationMenu.classList.contains('open') && notificationOpenButton) {
                positionNotificationMenu(notificationOpenButton);
            }
        });

        fetchNotifications(false);
        window.setInterval(function () {
            fetchNotifications(false);
        }, 60000);
    }

    var globalSearch = document.getElementById('global-search');
    if (globalSearch) {
        var searchableSelectors = [
            '.cluster-card',
            '.lab-card',
            '.user-directory-card',
            '.asset-directory-card',
            '.asset-lab-detail',
            '.lab-history-card',
            '.asset-mini-item',
            'table tbody tr'
        ].join(',');

        var getSearchableItems = function () {
            return Array.prototype.slice.call(document.querySelectorAll('.content ' + searchableSelectors));
        };

        var normalizeText = function (value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        };

        var matchesKeywords = function (text, query) {
            if (!query) {
                return true;
            }
            var haystack = normalizeText(text);
            return query.split(/\s+/).every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
        };

        var runGlobalSearch = function () {
            var query = normalizeText(globalSearch.value);
            var items = getSearchableItems();
            items.forEach(function (item) {
                var text = item.getAttribute('data-search-text') || item.textContent || '';
                var visible = matchesKeywords(text, query);
                item.style.display = visible ? '' : 'none';
            });

            document.querySelectorAll('.content .section-stack > .card, .content .card').forEach(function (block) {
                var blockItems = block.querySelectorAll(searchableSelectors);
                if (blockItems.length > 0) {
                    var hasVisibleMatch = Array.prototype.some.call(blockItems, function (item) {
                        return item.style.display !== 'none';
                    });
                    block.style.display = query && !hasVisibleMatch ? 'none' : '';
                }
            });

            document.querySelectorAll('.content .pagination').forEach(function (pagination) {
                pagination.style.display = query ? 'none' : '';
            });
        };

        globalSearch.addEventListener('input', runGlobalSearch);
        globalSearch.addEventListener('change', runGlobalSearch);
        runGlobalSearch();
    }

    document.querySelectorAll('[data-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-modal');
            if (!targetId) {
                return;
            }
            var modal = document.getElementById(targetId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });

    // Nav groups use <details> toggles.
})();
