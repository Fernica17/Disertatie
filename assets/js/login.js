// Magnum ERP - Login entry point
import '@fortawesome/fontawesome-free/css/all.min.css';
import '../styles/login.scss';

// Stateless CSRF: this module attaches a document-level `submit` listener that
// swaps the placeholder token for a real double-submit token + cookie. The admin
// bundle gets it through Stimulus (assets/bootstrap.js); the security pages load
// only this entry, so it has to be imported explicitly or every login, password
// reset and password change POST is rejected as "Token CSRF este invalid".
import '../controllers/csrf_protection_controller.js';

document.addEventListener('DOMContentLoaded', function () {
    // Password show/hide toggle (supports multiple toggle buttons)
    document.querySelectorAll('.password-toggle').forEach(function (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var input = this.closest('.login-input-group').querySelector('input[type="password"], input[type="text"]');
            var icon = this.querySelector('i');

            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    });

    // Auto-focus email field
    var emailInput = document.getElementById('inputEmail');
    if (emailInput && !emailInput.value) {
        emailInput.focus();
    }
});
