import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];
    static values = {
        url: String,
        title: String,
    };

    async open(event) {
        event.preventDefault();
        // Modal logic handled by ajax-modal.js
    }
}
