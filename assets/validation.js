(function () {
    function setError(id, message) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.style.display = message ? 'block' : 'none';
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isUthmStaffEmail(email) {
        return /^[a-z0-9._%+-]+@uthm\.edu\.my$/i.test(email);
    }

    function isUthmStudentEmail(email) {
        return /^[a-z0-9._%+-]+@student\.uthm\.edu\.my$/i.test(email);
    }

    var signinForm = document.getElementById('signin-form');
    if (signinForm) {
        var signinEmail = document.getElementById('email');
        var signinPassword = document.getElementById('password');
        var signinSubmit = document.getElementById('signin-submit');
        var emailHint = document.getElementById('email-hint');
        var emailNote = document.getElementById('email-note');
        var roleCards = signinForm.querySelectorAll('.role-card');
        var uthmNote = document.getElementById('signin-uthm-note');
        var publicNote = document.getElementById('signin-public-note');

        function getUserType() {
            var selected = signinForm.querySelector('input[name="user_type"]:checked');
            return selected ? selected.value : '';
        }

        function updateRoleCards(userType) {
            roleCards.forEach(function (card) {
                var input = card.querySelector('input[type=\"radio\"]');
                if (!input) {
                    return;
                }
                card.classList.toggle('selected', input.value === userType);
            });
        }

        function updateEmailHints(userType) {
            if (!emailHint || !emailNote) {
                return;
            }
            if (userType === 'uthm') {
                emailNote.textContent = '(UTHM email only)';
                emailHint.textContent = 'Students: matricno@student.uthm.edu.my | Staff: staffname@uthm.edu.my';
            } else {
                emailNote.textContent = '';
                emailHint.textContent = 'Use the email you registered with.';
            }
        }

        function updateButtonLabel(userType) {
            if (!signinSubmit) {
                return;
            }
            if (userType === 'public') {
                signinSubmit.textContent = 'Sign In as Public User';
            } else {
                signinSubmit.textContent = 'Sign In as Warga UTHM';
            }
        }

        function updateSignInNotes(userType) {
            if (uthmNote) {
                uthmNote.style.display = userType === 'uthm' ? 'block' : 'none';
            }
            if (publicNote) {
                publicNote.style.display = userType === 'public' ? 'flex' : 'none';
            }
        }

        function validateSignin() {
            var email = signinEmail.value.trim();
            var password = signinPassword.value.trim();
            var userType = getUserType();

            setError('signin-user-type-error', userType ? '' : 'Please select a user type.');

            if (!email) {
                setError('signin-email-error', 'Email is required.');
            } else if (userType === 'uthm' && !isUthmStaffEmail(email) && !isUthmStudentEmail(email)) {
                setError('signin-email-error', 'Use staffname@uthm.edu.my or matricno@student.uthm.edu.my.');
            } else if (userType === 'public' && !isValidEmail(email)) {
                setError('signin-email-error', 'Enter a valid email.');
            } else {
                setError('signin-email-error', '');
            }

            setError('signin-password-error', password ? '' : 'Password is required.');

            var canSubmit = userType && email && password;
            if (userType === 'uthm') {
                canSubmit = canSubmit && (isUthmStaffEmail(email) || isUthmStudentEmail(email));
            } else if (userType === 'public') {
                canSubmit = canSubmit && isValidEmail(email);
            }
            signinSubmit.disabled = !canSubmit;

            updateRoleCards(userType);
            updateEmailHints(userType);
            updateButtonLabel(userType);
            updateSignInNotes(userType);
        }

        signinForm.addEventListener('input', validateSignin);
        signinForm.addEventListener('change', validateSignin);
        validateSignin();
    }

    var signupForm = document.getElementById('signup-form');
    if (signupForm) {
        var nameInput = document.getElementById('name');
        var icInput = document.getElementById('ic_no');
        var emailInput = document.getElementById('email');
        var passwordInput = document.getElementById('password');
        var confirmInput = document.getElementById('confirm_password');
        var signupSubmit = document.getElementById('signup-submit');
        var emailHint = document.getElementById('signup-email-hint');
        var passwordHint = document.getElementById('signup-password-hint');
        var confirmHint = document.getElementById('signup-confirm-hint');
        var ruleLength = document.getElementById('rule-length');
        var ruleUpper = document.getElementById('rule-upper');
        var ruleNumber = document.getElementById('rule-number');
        var toggleButtons = signupForm.querySelectorAll('.toggle-visibility');

        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-target');
                var targetInput = document.getElementById(targetId);
                if (!targetInput) {
                    return;
                }
                var isPassword = targetInput.type === 'password';
                targetInput.type = isPassword ? 'text' : 'password';
            });
        });

        function validateName() {
            var value = nameInput.value.trim();
            setError('signup-name-error', value ? '' : 'Full name is required.');
            return value.length > 0;
        }

        function validateIc() {
            var value = icInput.value.trim();
            setError('signup-ic-error', /^\d{12}$/.test(value) ? '' : 'IC number must be 12 digits.');
            return /^\d{12}$/.test(value);
        }

        function validateEmail() {
            var value = emailInput.value.trim();
            setError('signup-email-error', isValidEmail(value) ? '' : 'Enter a valid email.');
            return isValidEmail(value);
        }

        function validatePassword() {
            var value = passwordInput.value;
            var validLength = value.length >= 8;
            var hasUpper = /[A-Z]/.test(value);
            var hasNumber = /\d/.test(value);
            var valid = validLength && hasUpper && hasNumber;
            if (ruleLength) {
                ruleLength.className = validLength ? 'valid' : value ? 'invalid' : '';
            }
            if (ruleUpper) {
                ruleUpper.className = hasUpper ? 'valid' : value ? 'invalid' : '';
            }
            if (ruleNumber) {
                ruleNumber.className = hasNumber ? 'valid' : value ? 'invalid' : '';
            }
            setError(
                'signup-password-error',
                valid ? '' : 'At least 8 characters, one uppercase letter, and one number.'
            );
            return valid;
        }

        function validateConfirm() {
            var value = confirmInput.value;
            var valid = value && value === passwordInput.value;
            setError('signup-confirm-error', valid ? '' : 'Passwords must match.');
            return valid;
        }

        function validateSignup() {
            var nameOk = validateName();
            var icOk = validateIc();
            var emailUnlocked = nameOk && icOk;
            if (emailInput) {
                emailInput.disabled = !emailUnlocked;
            }
            if (emailHint) {
                emailHint.textContent = emailUnlocked
                    ? 'Email input is now available.'
                    : 'Complete your name and IC to unlock email input';
            }

            var emailOk = emailUnlocked && validateEmail();
            if (passwordInput) {
                passwordInput.disabled = !emailOk;
            }
            if (passwordHint) {
                passwordHint.textContent = emailOk
                    ? 'Password must contain:'
                    : 'Enter a valid email to continue to password setup.';
            }

            var passwordOk = emailOk && validatePassword();
            if (confirmInput) {
                confirmInput.disabled = !passwordOk;
            }
            if (confirmHint) {
                confirmHint.textContent = passwordOk
                    ? 'Passwords must match.'
                    : 'Complete the password requirements to unlock confirmation.';
            }

            var confirmOk = passwordOk && validateConfirm();
            var checks = [nameOk, icOk, emailOk, passwordOk, confirmOk];
            signupSubmit.disabled = !checks.every(function (item) { return item; });
        }

        signupForm.addEventListener('input', validateSignup);
        signupForm.addEventListener('change', validateSignup);
        validateSignup();
    }
})();
