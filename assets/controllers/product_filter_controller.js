import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'group', 'item', 'selectAll', 'counter', 'groupCount'];

    connect() {
        this.updateCounter();
        this.selectAllTargets.forEach((checkbox, index) => {
            this._updateSelectAllState(index);
        });
        this._updateGroupCounts();
    }

    filter() {
        const query = this.searchTarget.value.toLowerCase().trim();

        this.groupTargets.forEach((group) => {
            const items = group.querySelectorAll('[data-product-filter-target="item"]');
            let visibleCount = 0;

            items.forEach((item) => {
                const label = item.textContent.toLowerCase();
                const match = !query || label.includes(query);
                item.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            group.style.display = visibleCount > 0 ? '' : 'none';
        });
    }

    toggleGroup(event) {
        const selectAllCheckbox = event.currentTarget;
        const group = selectAllCheckbox.closest('[data-product-filter-target="group"]');
        const items = group.querySelectorAll('[data-product-filter-target="item"]');
        const checked = selectAllCheckbox.checked;

        items.forEach((item) => {
            if (item.style.display === 'none') return;
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.checked !== checked) {
                checkbox.checked = checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        this.updateCounter();
        this._updateGroupCounts();
    }

    updateCounter() {
        const allCheckboxes = this._getProductCheckboxes();
        const checkedCount = allCheckboxes.filter((cb) => cb.checked).length;
        const totalCount = allCheckboxes.length;

        this.counterTarget.textContent = `${checkedCount} / ${totalCount} selectate`;
    }

    _updateSelectAllState(index) {
        const group = this.groupTargets[index];
        if (!group) return;

        const selectAll = this.selectAllTargets[index];
        if (!selectAll) return;

        const items = group.querySelectorAll('[data-product-filter-target="item"]');
        const checkboxes = Array.from(items)
            .map((item) => item.querySelector('input[type="checkbox"]'))
            .filter(Boolean);

        const checkedCount = checkboxes.filter((cb) => cb.checked).length;
        const totalCount = checkboxes.length;

        if (checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedCount === totalCount) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    _updateGroupCounts() {
        this.groupTargets.forEach((group, index) => {
            const items = group.querySelectorAll('[data-product-filter-target="item"]');
            const checkboxes = Array.from(items)
                .map((item) => item.querySelector('input[type="checkbox"]'))
                .filter(Boolean);
            const checkedCount = checkboxes.filter((cb) => cb.checked).length;

            const countEl = group.querySelector('[data-product-filter-target="groupCount"]');
            if (countEl) {
                countEl.textContent = checkedCount > 0 ? `${checkedCount} sel.` : '';
            }
        });
    }

    _getProductCheckboxes() {
        return Array.from(
            this.element.querySelectorAll(
                '[data-product-filter-target="item"] input[type="checkbox"]'
            )
        );
    }

    // Listen for individual checkbox changes via event delegation
    itemTargetConnected(element) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                this._onItemChange(element);
            });
        }
    }

    _onItemChange(itemElement) {
        const group = itemElement.closest('[data-product-filter-target="group"]');
        if (group) {
            const groupIndex = this.groupTargets.indexOf(group);
            this._updateSelectAllState(groupIndex);
        }
        this.updateCounter();
        this._updateGroupCounts();
    }
}
