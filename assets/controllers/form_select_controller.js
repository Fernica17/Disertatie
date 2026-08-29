import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    connect() {
        this.tomInstances = [];
        this._initTomSelect();
        this._initFileInputs();
    }

    disconnect() {
        this.tomInstances.forEach(ts => ts.destroy());
    }

    // --- TomSelect on all selects ---
    _initTomSelect() {
        this.element.querySelectorAll('select.of-input').forEach(select => {
            if (select.classList.contains('tomselected')) return;
            this.tomInstances.push(new TomSelect(select, {
                allowEmptyOption: true,
                maxOptions: null,
                sortField: { field: 'text', direction: 'asc' },
            }));
        });
    }

    // --- File inputs: show selected files preview ---
    _initFileInputs() {
        this.element.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', () => {
                const listId = input.dataset.fileList;
                if (!listId) return;
                const target = document.getElementById(listId);
                if (!target) return;
                target.innerHTML = '';
                for (const file of input.files) {
                    const div = document.createElement('div');
                    div.className = 'ct-selected-file';
                    div.innerHTML = '<i class="fa-solid fa-file"></i> '
                        + this._escapeHtml(file.name)
                        + ' <span class="ct-selected-file-size">('
                        + (file.size / 1024 / 1024).toFixed(2) + ' MB)</span>';
                    target.appendChild(div);
                }
            });
        });
    }

    // --- Toggle file removal ---
    toggleRemoveFile(event) {
        const btn = event.currentTarget;
        const fileId = btn.dataset.fileId;
        const inputName = btn.dataset.inputName;
        const item = btn.closest('.of-file-item');

        const existing = item.querySelector('input[type="hidden"]');
        if (existing) {
            existing.remove();
            item.classList.remove('ct-file-marked-remove');
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = inputName + '[]';
            input.value = fileId;
            item.appendChild(input);
            item.classList.add('ct-file-marked-remove');
        }
    }

    // --- Helpers ---
    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
