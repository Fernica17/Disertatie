document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('change-password-form');
    if (!form) return;

    // This form manages its own submit lifecycle (disable/enable in finally).
    // Opt out of the global double-submit guard so a retry after an error is not blocked.
    form.setAttribute('data-allow-resubmit', '');

    const errBox = document.getElementById('password-form-errors');
    const btn = document.getElementById('btn-save-password');

    const showError = (msg) => {
        if (Array.isArray(msg)) {
            const ul = document.createElement('ul');
            ul.style.cssText = 'margin: 0; padding-left: 1.2rem;';
            msg.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
            errBox.innerHTML = '';
            errBox.appendChild(ul);
        } else {
            errBox.textContent = msg;
        }
        errBox.classList.remove('d-none');
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (btn?.disabled) return;

        errBox.classList.add('d-none');
        const origText = btn?.innerHTML || 'Salvează';

        try {
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Se procesează...'; }

            const res = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });

            if (!res.headers.get('content-type')?.includes('application/json')) throw new Error();

            const data = await res.json();

            if (res.ok && data.success) {
                window.bootstrap?.Modal?.getInstance(document.getElementById('changePasswordModal'))?.hide();
                form.reset();
                return window.location.reload();
            }

            showError(data.errors?.length ? data.errors : 'Datele introduse sunt incorecte.');

        } catch (err) {
            showError('Eroare de conexiune la server. Te rugăm să reîncerci.');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
        }
    });
});
