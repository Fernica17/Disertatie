import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    connect() {
        this.tomInstances = [];
        this._initTomSelect();
    }

    disconnect() {
        this.tomInstances.forEach(ts => ts.destroy());
    }

    _initTomSelect() {
        this.element.querySelectorAll('select.form-select').forEach(select => {
            if (select.classList.contains('tomselected')) return;
            this.tomInstances.push(new TomSelect(select, {
                allowEmptyOption: false,
                maxOptions: null,
                sortField: { field: 'text', direction: 'asc' },
            }));
        });
    }
}
