import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

const API_BASE = '/admin/location';

export default class extends Controller {
    static targets = [
        'country', 'county', 'city', 'cityHidden',
    ];

    static values = {
        cityId: Number,
    };

    connect() {
        this.tomInstances = [];
        this._initTomSelect();
        this._initLocationCascade();
        this._initFileInputs();

        // Restore location cascade on edit
        if (this.cityIdValue > 0) {
            this._restoreLocation(this.cityIdValue);
        }
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

    // --- Location cascade: Country → County → City ---
    _initLocationCascade() {
        const countryTs = this._getTomSelectInstance(this.countryTarget);
        const countyTs = this._getTomSelectInstance(this.countyTarget);

        if (countryTs) {
            countryTs.on('change', (value) => {
                this._loadCounties(value);
            });
        }

        if (countyTs) {
            countyTs.on('change', (value) => {
                this._loadCities(value);
            });
        }

        // Sync city TomSelect changes to hidden input
        const cityTs = this._getTomSelectInstance(this.cityTarget);
        if (cityTs) {
            cityTs.on('change', (value) => {
                this._syncCityHidden(value);
            });
        }
    }

    _getSelectValue(select) {
        const ts = this._getTomSelectInstance(select);
        return ts ? ts.getValue() : select.value;
    }

    async _loadCounties(countryId) {
        this._clearTomSelect(this.countyTarget);
        this._clearTomSelect(this.cityTarget);
        this._syncCityHidden('');

        if (!countryId) {
            this._toggleField(this.countyTarget, false);
            this._toggleField(this.cityTarget, false);
            return;
        }

        const response = await fetch(`${API_BASE}/counties/${countryId}`);
        const data = await response.json();

        if (data.length === 0) {
            this._toggleField(this.countyTarget, false);
            this._toggleField(this.cityTarget, false);
            return;
        }

        const ts = this._getTomSelectInstance(this.countyTarget);
        if (ts) {
            ts.clearOptions();
            ts.addOption({ value: '', text: '— Județ —' });
            data.forEach(item => {
                ts.addOption({ value: String(item.id), text: item.name });
            });
            ts.setValue('', true);
        }
        this._toggleField(this.countyTarget, true);
    }

    async _loadCities(countyId) {
        this._clearTomSelect(this.cityTarget);
        this._syncCityHidden('');

        if (!countyId) {
            this._toggleField(this.cityTarget, false);
            return;
        }

        const response = await fetch(`${API_BASE}/cities/${countyId}`);
        const data = await response.json();

        if (data.length === 0) {
            this._toggleField(this.cityTarget, false);
            return;
        }

        const ts = this._getTomSelectInstance(this.cityTarget);
        if (ts) {
            ts.clearOptions();
            ts.addOption({ value: '', text: '— Oraș —' });
            data.forEach(item => {
                ts.addOption({ value: String(item.id), text: item.name });
            });
            ts.setValue('', true);
        }
        this._toggleField(this.cityTarget, true);
    }

    _clearTomSelect(select) {
        const ts = this._getTomSelectInstance(select);
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: '—' });
            ts.setValue('', true);
        }
    }

    async _restoreLocation(cityId) {
        try {
            const response = await fetch(`${API_BASE}/city-context/${cityId}`);
            const context = await response.json();

            if (context.countryId) {
                // Set country via TomSelect
                const countryTs = this._getTomSelectInstance(this.countryTarget);
                if (countryTs) {
                    countryTs.setValue(String(context.countryId), true);
                }

                await this._loadCounties(context.countryId);
                if (context.countyId) {
                    const countyTs = this._getTomSelectInstance(this.countyTarget);
                    if (countyTs) {
                        countyTs.setValue(String(context.countyId), true);
                    }
                    await this._loadCities(context.countyId);
                    const cityTs = this._getTomSelectInstance(this.cityTarget);
                    if (cityTs) {
                        cityTs.setValue(String(cityId), true);
                    }
                    this._syncCityHidden(cityId);
                }
            }
        } catch (e) {
            console.error('Failed to restore location:', e);
        }
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

    // --- Sync visible city select with hidden AjaxEntityType input ---
    onCitySelectChange(event) {
        if (this.hasCityHiddenTarget) {
            this.cityHiddenTarget.value = event.currentTarget.value || '';
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

    _syncCityHidden(value) {
        if (this.hasCityHiddenTarget) {
            this.cityHiddenTarget.value = value || '';
        }
    }

    _toggleField(selectTarget, show) {
        // select → .ts-wrapper → div (the column wrapper with d-none)
        const wrap = selectTarget.closest('.ts-wrapper')?.parentElement || selectTarget.parentElement;
        wrap.classList.toggle('d-none', !show);
    }

    // --- Helpers ---
    _getTomSelectInstance(selectElement) {
        return this.tomInstances.find(ts => ts.input === selectElement) || null;
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
