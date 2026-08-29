/**
 * Password Strength Indicator
 *
 * Auto-initializes on inputs with [data-password-strength] attribute.
 * Shows a colored progress bar and checklist of password rules.
 */

const RULES = [
    { key: 'length',    test: (pw) => pw.length >= 10,                  label: 'Minim 10 caractere' },
    { key: 'uppercase', test: (pw) => /[A-Z]/.test(pw),                 label: 'Cel putin o litera mare' },
    { key: 'lowercase', test: (pw) => /[a-z]/.test(pw),                 label: 'Cel putin o litera mica' },
    { key: 'digit',     test: (pw) => /[0-9]/.test(pw),                 label: 'Cel putin o cifra' },
    { key: 'special',   test: (pw) => /[!@#$%^&*(),.?":{}|<>_\-+=[\]\\/~]/.test(pw), label: 'Cel putin un caracter special' },
];

const STRENGTH_LEVELS = [
    { max: 0, color: '#e9ecef', label: '' },
    { max: 1, color: '#dc3545', label: 'Foarte slaba' },
    { max: 2, color: '#dc3545', label: 'Slaba' },
    { max: 3, color: '#fd7e14', label: 'Medie' },
    { max: 4, color: '#ffc107', label: 'Buna' },
    { max: 5, color: '#198754', label: 'Puternica' },
];

function initPasswordStrength() {
    const passwordInput = document.querySelector('[data-password-strength]');
    if (!passwordInput) return;

    const container = document.getElementById('password-strength-container');
    const bar = document.getElementById('password-strength-bar');
    const label = document.getElementById('password-strength-label');
    const checklist = document.getElementById('password-checklist');
    const confirmInput = document.getElementById('confirm_password');
    const matchFeedback = document.getElementById('password-match-feedback');

    if (!container || !bar || !label || !checklist) return;

    passwordInput.addEventListener('input', function () {
        const password = this.value;

        if (password.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        let passed = 0;

        RULES.forEach((rule) => {
            const item = checklist.querySelector(`[data-rule="${rule.key}"]`);
            if (!item) return;

            const ok = rule.test(password);
            if (ok) passed++;

            if (ok) {
                item.classList.remove('text-muted');
                item.classList.add('text-success');
                item.querySelector('i').className = 'fa-solid fa-circle-check';
                item.querySelector('i').style.fontSize = '';
                item.querySelector('i').style.verticalAlign = '';
                item.querySelector('i').style.marginRight = '6px';
            } else {
                item.classList.remove('text-success');
                item.classList.add('text-muted');
                item.querySelector('i').className = 'fa-solid fa-circle';
                item.querySelector('i').style.fontSize = '6px';
                item.querySelector('i').style.verticalAlign = 'middle';
                item.querySelector('i').style.marginRight = '6px';
            }
        });

        const level = STRENGTH_LEVELS[passed];
        const widthPercent = (passed / RULES.length) * 100;

        bar.style.width = widthPercent + '%';
        bar.style.backgroundColor = level.color;
        label.textContent = level.label;
        label.style.color = level.color;

        // Update match feedback if confirm field has value
        if (confirmInput && confirmInput.value.length > 0) {
            updateMatchFeedback(password, confirmInput.value, matchFeedback);
        }
    });

    if (confirmInput && matchFeedback) {
        confirmInput.addEventListener('input', function () {
            const password = passwordInput.value;
            const confirm = this.value;

            if (confirm.length === 0) {
                matchFeedback.textContent = '';
                return;
            }

            updateMatchFeedback(password, confirm, matchFeedback);
        });
    }
}

function updateMatchFeedback(password, confirm, feedbackEl) {
    if (password === confirm) {
        feedbackEl.innerHTML = '<i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i>Parolele coincid';
        feedbackEl.style.color = '#198754';
    } else {
        feedbackEl.innerHTML = '<i class="fa-solid fa-circle-xmark" style="margin-right: 4px;"></i>Parolele nu coincid';
        feedbackEl.style.color = '#dc3545';
    }
}

export { initPasswordStrength };
