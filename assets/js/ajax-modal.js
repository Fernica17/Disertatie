import TomSelect from 'tom-select';

// AJAX Modal Handler
export function initializeAjaxModals() {
    for (const button of document.querySelectorAll('[data-ajax-modal]')) {
        button.addEventListener('click', handleModalOpen);
    }
}

async function handleModalOpen(event) {
    event.preventDefault();

    const button = event.currentTarget;
    const url = button.dataset.ajaxModal;
    const modalTitle = button.dataset.modalTitle || 'Modal';
    const modalSize = button.dataset.modalSize || 'lg';

    // Show loading state
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Loading...';

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();

        // Create modal
        const modal = createModal(modalTitle, html, modalSize);
        document.body.appendChild(modal);

        // Initialize modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Clean up on hide
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });

        // Initialize form if present
        const form = modal.querySelector('form');
        if (form) {
            initializeModalForm(form, bsModal);
            initializeModalSelects(modal);
        }

    } catch (error) {
        console.error('Error loading modal:', error);

        // Show error in a temporary modal
        const errorModal = createModal(modalTitle, '<div class="alert alert-danger mb-0">A apărut o eroare la încărcarea conținutului.</div>', 'md');
        document.body.appendChild(errorModal);
        const bsErrorModal = new bootstrap.Modal(errorModal);
        bsErrorModal.show();
        errorModal.addEventListener('hidden.bs.modal', () => errorModal.remove());
    } finally {
        // Restore button state
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

function createModal(title, content, size = 'lg') {
    const modalHtml = `
        <div class="modal fade" tabindex="-1">
            <div class="modal-dialog modal-${size}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        </div>
    `;

    const template = document.createElement('template');
    template.innerHTML = modalHtml.trim();
    return template.content.firstChild;
}

function initializeModalForm(form, modal) {
    // AJAX modal has its own submit/disable lifecycle (below). Opt the form out
    // of the global double-submit guard (magnum.js) so a validation-error
    // re-submit is not blocked by a leftover `data-submitted` flag.
    form.setAttribute('data-allow-resubmit', '');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton.disabled) {
            // Already in flight — ignore the extra click.
            return;
        }

        const originalText = submitButton.innerHTML;

        // Show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvare...';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                clearFormAlert(form);
                showFormAlert(form, result.message || 'Operație realizată cu succes.', 'success');

                modal.hide();

                if (result.reload) {
                    setTimeout(() => location.reload(), 500);
                }

                document.dispatchEvent(new CustomEvent('modal-form-success', {
                    detail: result
                }));

            } else {
                clearFormAlert(form);
                if (result.errors) {
                    displayFormErrors(form, result.errors);
                }
                if (result.message) {
                    showFormAlert(form, result.message, 'danger');
                }
            }

        } catch (error) {
            console.error('Form submission error:', error);
            clearFormAlert(form);
            showFormAlert(form, 'A apărut o eroare. Încercați din nou.', 'danger');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    });
}

function initializeModalSelects(modal) {
    for (const select of modal.querySelectorAll('select.of-input')) {
        if (select.classList.contains('tomselected')) continue;
        // eslint-disable-next-line no-new
        new TomSelect(select, {
            allowEmptyOption: true,
            maxOptions: null,
            sortField: { field: 'text', direction: 'asc' },
        });
    }
}

function showFormAlert(form, message, type = 'danger') {
    clearFormAlert(form);
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${type} modal-form-alert`;
    alertEl.style.marginBottom = '16px';
    alertEl.textContent = message;
    form.insertBefore(alertEl, form.firstChild);
}

function clearFormAlert(form) {
    for (const el of form.querySelectorAll('.modal-form-alert')) {
        el.remove();
    }
}

function displayFormErrors(form, errors) {
    // Clear previous errors
    for (const field of form.querySelectorAll('.is-invalid')) {
        field.classList.remove('is-invalid');
    }
    for (const feedback of form.querySelectorAll('.invalid-feedback')) {
        feedback.remove();
    }

    // Display new errors
    for (const [field, messages] of Object.entries(errors)) {
        const input = form.querySelector(`[name="${field}"]`);
        if (input) {
            input.classList.add('is-invalid');

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.innerHTML = Array.isArray(messages) ? messages.join('<br>') : messages;
            input.parentNode.appendChild(feedback);
        }
    }
}

// Auto-initialize on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAjaxModals);
} else {
    initializeAjaxModals();
}
