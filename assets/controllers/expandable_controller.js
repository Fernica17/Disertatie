import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];

    connect() {
        this.isExpanded = false;
    }

    toggle(event) {
        event.preventDefault();

        const card = this.element;
        const content = this.contentTarget;

        this.isExpanded = !this.isExpanded;

        if (this.isExpanded) {
            card.classList.add('expanded');
            content.style.maxHeight = content.scrollHeight + 'px';
        } else {
            card.classList.remove('expanded');
            content.style.maxHeight = '0';
        }
    }
}
