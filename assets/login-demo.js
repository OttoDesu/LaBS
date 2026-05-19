(function () {
    var form = document.getElementById('demo-login-form');
    if (!form) {
        return;
    }

    var emailInput = document.getElementById('demo-email');
    var passwordInput = document.getElementById('demo-password');
    var submitButton = document.getElementById('demo-login-submit');
    var emailNote = document.getElementById('demo-email-note');
    var emailHint = document.getElementById('demo-email-hint');
    var passwordNote = document.getElementById('demo-password-note');
    var uthmNote = document.getElementById('demo-uthm-note');
    var publicNote = document.getElementById('demo-public-note');
    var roleCards = form.querySelectorAll('.role-card');

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isUthmStaff(email) {
        return /^[a-z0-9._%+-]+@uthm\.edu\.my$/i.test(email);
    }

    function isUthmStudent(email) {
        return /^[a-z0-9._%+-]+@student\.uthm\.edu\.my$/i.test(email);
    }

    function getUserType() {
        var selected = form.querySelector('input[name="user_type"]:checked');
        return selected ? selected.value : '';
    }

    function setError(id, message) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.style.display = message ? 'block' : 'none';
    }

    function updateRoleCards(userType) {
        roleCards.forEach(function (card) {
            var input = card.querySelector('input[type="radio"]');
            if (!input) {
                return;
            }
            card.classList.toggle('selected', input.value === userType);
        });
    }

    function updateHints(userType) {
        if (emailNote && emailHint) {
            if (userType === 'uthm') {
                emailNote.textContent = '(UTHM email only)';
                emailHint.textContent = 'Students: matricno@student.uthm.edu.my | Staff: staffname@uthm.edu.my';
            } else {
                emailNote.textContent = '';
                emailHint.textContent = 'Use the email you registered with.';
            }
        }
        if (passwordNote) {
            if (userType === 'uthm') {
                passwordNote.textContent = '(SMAP account password)';
                passwordNote.style.display = 'inline';
            } else {
                passwordNote.textContent = '';
                passwordNote.style.display = 'none';
            }
        }
        if (uthmNote) {
            uthmNote.style.display = userType === 'uthm' ? 'block' : 'none';
        }
        if (publicNote) {
            publicNote.style.display = userType === 'public' ? 'flex' : 'none';
        }
    }

    function updateButtonLabel(userType) {
        if (!submitButton) {
            return;
        }
        submitButton.textContent = userType === 'public'
            ? 'Sign In as Public User'
            : 'Sign In as Warga UTHM';
    }

    function validateForm() {
        var email = emailInput.value.trim();
        var password = passwordInput.value.trim();
        var userType = getUserType();

        setError('demo-user-type-error', userType ? '' : 'Please select a user type.');

        if (!email) {
            setError('demo-email-error', 'Email is required.');
        } else if (userType === 'uthm' && !isUthmStaff(email) && !isUthmStudent(email)) {
            setError('demo-email-error', 'Use staffname@uthm.edu.my or matricno@student.uthm.edu.my.');
        } else if (userType === 'public' && !isValidEmail(email)) {
            setError('demo-email-error', 'Enter a valid email.');
        } else {
            setError('demo-email-error', '');
        }

        setError('demo-password-error', password ? '' : 'Password is required.');

        var canSubmit = userType && email && password;
        if (userType === 'uthm') {
            canSubmit = canSubmit && (isUthmStaff(email) || isUthmStudent(email));
        } else if (userType === 'public') {
            canSubmit = canSubmit && isValidEmail(email);
        }

        submitButton.disabled = !canSubmit;
        updateRoleCards(userType);
        updateHints(userType);
        updateButtonLabel(userType);
    }

    form.addEventListener('input', validateForm);
    form.addEventListener('change', validateForm);

    form.querySelectorAll('.toggle-visibility').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        validateForm();
        if (submitButton.disabled) {
            return;
        }
        var email = emailInput.value.trim();
        var name = email.split('@')[0].replace(/\./g, ' ');
        var userType = getUserType();
        var displayName = name ? name.charAt(0).toUpperCase() + name.slice(1) : 'User';
        var user = {
            name: displayName,
            email: email,
            userType: userType
        };
        localStorage.setItem('labs_user', JSON.stringify(user));
        window.location.href = 'dashboard.html';
    });

    validateForm();
})();
