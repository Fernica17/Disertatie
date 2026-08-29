import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static targets = [
        'totalValue', 'vatPercent',
        'displayTotal', 'displayVat', 'displayTotalVat',
        'offer', 'client', 'currency', 'title',
    ];

    static values = {
        offerDataMap: Object,
    };

    connect() {
        this.tomInstances = [];
        this._lastAutoFilledTitle = null;
        this._initTomSelect();
        this._initFileInputs();
        this._syncFromOffer();
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

    // --- VAT recalculation ---
    recalcVat() {
        const total = parseFloat(this.totalValueTarget.value) || 0;
        const vatPct = parseFloat(this.vatPercentTarget.value) || 0;
        const vatAmt = total * vatPct / 100;
        const totalVat = total + vatAmt;

        this.displayTotalTarget.textContent = this._fmt(total);
        this.displayVatTarget.textContent = this._fmt(vatAmt);
        this.displayTotalVatTarget.textContent = this._fmt(totalVat);
    }

    // --- Offer changed: auto-fill client, currency, values, title ---
    offerChanged() {
        const offerId = this.offerTarget.value;
        const data = offerId ? this.offerDataMapValue[offerId] : null;
        const clientTs = this._getTomSelectInstance(this.clientTarget);
        const currencyTs = this._getTomSelectInstance(this.currencyTarget);

        if (data) {
            // Client
            if (data.clientId) {
                this._setTomSelectValue(clientTs, this.clientTarget, String(data.clientId));
            }
            if (clientTs) { clientTs.lock(); } else { this.clientTarget.readOnly = true; }

            // Currency
            if (data.currencyId) {
                this._setTomSelectValue(currencyTs, this.currencyTarget, String(data.currencyId));
            }

            // Title - fill if empty OR if user hasn't manually edited the previous auto-filled title
            if (this.hasTitleTarget && data.title) {
                const currentTitle = this.titleTarget.value;
                const isAutoFilled = currentTitle === '' || currentTitle === this._lastAutoFilledTitle;
                if (isAutoFilled) {
                    this.titleTarget.value = data.title;
                    this._lastAutoFilledTitle = data.title;
                }
            }

            // Values
            this.totalValueTarget.value = data.totalValue || '0.00';
            this.vatPercentTarget.value = data.vatPercent || '0.00';
            this.recalcVat();
        } else {
            // Re-enable client field
            if (clientTs) { clientTs.unlock(); } else { this.clientTarget.readOnly = false; }
        }
    }

    // --- Sync state on page load (for edit with offer) ---
    _syncFromOffer() {
        if (this.offerTarget.value) {
            const clientTs = this._getTomSelectInstance(this.clientTarget);
            if (clientTs) { clientTs.lock(); } else { this.clientTarget.readOnly = true; }
        }
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
    _setTomSelectValue(tsInstance, selectEl, value) {
        if (tsInstance) {
            tsInstance.setValue(value, true);
        } else {
            selectEl.value = value;
        }
    }

    _getTomSelectInstance(selectElement) {
        return this.tomInstances.find(ts => ts.input === selectElement) || null;
    }

    _fmt(n) {
        return n.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
