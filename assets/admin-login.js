(function () {
    var form = document.getElementById('admin-login-form');
    if (!form) {
        return;
    }

    var emailInput = document.getElementById('email');
    var passwordInput = document.getElementById('password');
    var submitButton = document.getElementById('admin-submit');
    var toggleButtons = form.querySelectorAll('.toggle-visibility');

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

    function validateForm() {
        var email = emailInput.value.trim();
        var password = passwordInput.value.trim();

        if (!email) {
            setError('admin-email-error', 'Admin email is required.');
        } else if (!isValidEmail(email)) {
            setError('admin-email-error', 'Use a valid email address.');
        } else {
            setError('admin-email-error', '');
        }

        setError('admin-password-error', password ? '' : 'Password is required.');

        submitButton.disabled = !(isValidEmail(email) && password);
    }

    form.addEventListener('input', validateForm);
    form.addEventListener('change', validateForm);
    validateForm();
})();
