import { Controller } from '@hotwired/stimulus';

/**
 * Generic detail-page tabs with URL hash support.
 * Usage:
 *   <div data-controller="detail-tabs">
 *       <button data-tab="results" data-detail-tabs-target="tab" data-action="click->detail-tabs#switch">
 *       <div id="tab-results" data-detail-tabs-target="content">
 */
export default class extends Controller {
    static targets = ['tab', 'content'];

    connect() {
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            const tab = this.tabTargets.find(t => t.dataset.tab === hash);
            if (tab) {
                this._activate(tab, hash);
            }
        }
    }

    switch(event) {
        const tabName = event.currentTarget.dataset.tab;
        this._activate(event.currentTarget, tabName);
        history.replaceState(null, '', '#' + tabName);
    }

    _activate(tabEl, tabName) {
        this.tabTargets.forEach(t => t.classList.remove('active'));
        this.contentTargets.forEach(c => c.classList.remove('active'));

        tabEl.classList.add('active');

        const content = this.contentTargets.find(c => c.id === 'tab-' + tabName);
        if (content) {
            content.classList.add('active');
        }
    }
}
