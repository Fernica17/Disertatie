import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

/**
 * Reusable confirmation controller using SweetAlert2.
 * Usage:
 *   <a href="/some/action"
 *      data-controller="confirm"
 *      data-confirm-message-value="Are you sure?"
 *      data-confirm-title-value="Confirmare"
 *      data-confirm-type-value="warning"
 *      data-confirm-method-value="post">
 *      Delete
 *   </a>
 *
 * Values:
 *   message  - Dialog body text (default: "Sunteți sigur?")
 *   title    - Dialog title (default: "Confirmare")
 *   type     - "warning" | "danger" (default: "warning")
 *   method   - "get" | "post" (default: "get")
 */
export default class extends Controller {
    static values = {
        message: { type: String, default: 'Sunteți sigur?' },
        title: { type: String, default: 'Confirmare' },
        type: { type: String, default: 'warning' },
        method: { type: String, default: 'get' },
    };

    connect() {
        this._boundClick = this._handleClick.bind(this);
        this.element.addEventListener('click', this._boundClick);
    }

    disconnect() {
        this.element.removeEventListener('click', this._boundClick);
    }

    _handleClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const isDanger = this.typeValue === 'danger';

        Swal.fire({
            title: this.titleValue,
            text: this.messageValue,
            icon: isDanger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDanger ? '#dc3545' : '#109B44',
            cancelButtonColor: '#6c757d',
            confirmButtonText: isDanger ? 'Da, șterge' : 'Da, continuă',
            cancelButtonText: 'Anulează',
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            const href = this.element.getAttribute('href');
            if (!href || href === '#') return;

            if (this.methodValue === 'post') {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = href;
                form.style.display = 'none';

                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '_token';
                    input.value = csrfMeta.content;
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.requestSubmit();
            } else {
                window.location.href = href;
            }
        });
    }
}
