import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['phaseCard'];

    togglePhase(event) {
        // Don't toggle if clicking on a form element
        if (event.target.closest('form, button, a, input, select, textarea')) {
            return;
        }

        const card = event.currentTarget.closest('.mg-phase-card');
        card.classList.toggle('expanded');
    }
}
