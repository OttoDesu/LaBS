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
