import FormSelectController from './form_select_controller';

export default class extends FormSelectController {
    static targets = ['stockItem', 'lot'];

    connect() {
        super.connect();
        // Store all lot options for filtering
        this._lotOptions = [];
        const lotSelect = this.lotTarget;
        let orderIdx = 1;
        lotSelect.querySelectorAll('option[value]').forEach(opt => {
            if (opt.value) {
                this._lotOptions.push({
                    value: opt.value,
                    text: opt.textContent,
                    stockItemId: opt.getAttribute('data-stock-item-id'),
                    $order: orderIdx++
                });
            }
        });
        // Initial filter
        const lotTs = this._getLotTomSelect();
        if (lotTs) {
            lotTs.settings.sortField = [{field: '$order'}];
        }
        this._filterLots();
    }

    stockItemChanged() {
        this._filterLots();
    }

    _filterLots() {
        const selectedItemId = this.stockItemTarget.value;
        const lotTs = this._getLotTomSelect();

        if (lotTs) {
            // Save current value before clearing
            const currentValue = lotTs.getValue();

            lotTs.clear(true);
            lotTs.clearOptions();

            // Add placeholder
            lotTs.addOption({value: '', text: '', $order: 0});

            // Add filtered options
            let currentValueExists = false;
            let firstValidValue = null;
            this._lotOptions.forEach(opt => {
                if (!selectedItemId || opt.stockItemId === selectedItemId) {
                    lotTs.addOption({value: opt.value, text: opt.text, $order: opt.$order});
                    if (opt.value === currentValue) {
                        currentValueExists = true;
                    }
                    if (!firstValidValue) {
                        firstValidValue = opt.value;
                    }
                }
            });

            // Restore value if it still exists in filtered options
            if (currentValue && currentValueExists) {
                lotTs.setValue(currentValue, true);
            } else if (selectedItemId && firstValidValue) {
                lotTs.setValue(firstValidValue, true);
            }

            lotTs.refreshOptions(false);
        } else {
            // Fallback: native select
            const lotSelect = this.lotTarget;
            const currentValue = lotSelect.value;
            // Remove all non-placeholder options
            Array.from(lotSelect.options).forEach(opt => {
                if (opt.value) opt.remove();
            });
            let currentValueExists = false;
            let firstValidValue = null;
            // Re-add filtered
            this._lotOptions.forEach(opt => {
                if (!selectedItemId || opt.stockItemId === selectedItemId) {
                    const newOpt = document.createElement('option');
                    newOpt.value = opt.value;
                    newOpt.textContent = opt.text;
                    lotSelect.appendChild(newOpt);
                    if (opt.value === currentValue) currentValueExists = true;
                    if (!firstValidValue) firstValidValue = opt.value;
                }
            });
            if (currentValue && currentValueExists) {
                lotSelect.value = currentValue;
            } else if (selectedItemId && firstValidValue) {
                lotSelect.value = firstValidValue;
            } else {
                lotSelect.value = '';
            }
        }
    }

    _getLotTomSelect() {
        return this.tomInstances.find(ts => ts.input === this.lotTarget) || null;
    }
}
