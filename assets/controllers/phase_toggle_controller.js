import { Controller } from '@hotwired/stimulus';

/**
 * Toggle expand/collapse for phase cards on project detail page.
 * Usage:
 *   <div class="mg-phase-card" data-controller="phase-toggle" data-action="click->phase-toggle#toggle">
 */
export default class extends Controller {
    toggle(event) {
        // Only toggle when clicking the header area
        const header = event.target.closest('.mg-phase-header');
        if (!header) return;

        this.element.classList.toggle('expanded');
    }
}
