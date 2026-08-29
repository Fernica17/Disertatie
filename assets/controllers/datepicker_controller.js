import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { Romanian } from 'flatpickr/dist/l10n/ro.js';

export default class extends Controller {
    connect() {
        this.fp = flatpickr(this.element, {
            locale: Romanian,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd.m.Y',
            allowInput: true,
        });
    }

    disconnect() {
        this.fp?.destroy();
    }
}
