import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['linesBody', 'grandTotal', 'modal', 'searchInput', 'searchResults',
        'customToggle', 'customNameField', 'productFields',
        'modalProductId', 'modalProductName', 'modalCustomName',
        'modalCode', 'modalUnit', 'modalQuantity', 'modalNotes',
        'linesData'];

    connect() {
        this.lines = [];
        this.rowIndex = 0;
        this.editingIndex = null;
        this.searchTimeout = null;
        this.searchUrl = this.element.dataset.searchUrl;

        // Build unit value → label map
        this.unitLabels = {};
        try {
            const units = JSON.parse(this.element.dataset.units || '[]');
            units.forEach((u) => {
                this.unitLabels[u.value] = u.label;
            });
        } catch (e) {
            console.error('Failed to parse units data:', e);
        }

        // Load existing lines for edit mode
        if (this.element.dataset.existingLines) {
            try {
                this.lines = JSON.parse(this.element.dataset.existingLines);
                if (this.lines.length > 0) {
                    this._renderTable();
                    this._updateHiddenInputs();
                }
            } catch (e) {
                console.error('Failed to parse existing lines:', e);
            }
        }
    }

    // ==================== MODAL OPEN/CLOSE ====================

    openAddModal() {
        this.editingIndex = null;
        this._resetModal();
        this._showModal('Adaugă material');
    }

    openEditModal(event) {
        const row = event.currentTarget.closest('tr');
        const index = parseInt(row.dataset.lineIndex);
        const line = this.lines[index];
        if (!line) return;

        this.editingIndex = index;
        this._resetModal();

        if (line.isCustom) {
            this.customToggleTarget.checked = true;
            this._toggleCustomProduct();
            this.modalCustomNameTarget.value = line.customName || '';
        } else {
            this.modalProductIdTarget.value = line.productId || '';
            this.modalProductNameTarget.value = line.productName || '';
            this.searchInputTarget.value = line.productName || '';
            this.modalCodeTarget.textContent = line.code || '—';
        }

        this.modalUnitTarget.value = line.unit || '';
        this.modalQuantityTarget.value = line.quantity || '';
        this.modalNotesTarget.value = line.notes || '';

        this._showModal('Editare material');
    }

    closeModal() {
        this.modalTarget.style.display = 'none';
        this.modalTarget.classList.remove('show');
        document.body.style.overflow = '';
    }

    // ==================== MODAL SAVE ====================

    saveLine() {
        const isCustom = this.customToggleTarget.checked;
        const productId = isCustom ? '' : this.modalProductIdTarget.value;
        const productName = isCustom ? '' : this.modalProductNameTarget.value;
        const customName = isCustom ? this.modalCustomNameTarget.value.trim() : '';
        const unit = this.modalUnitTarget.value.trim();
        const quantity = this.modalQuantityTarget.value.trim();
        const notes = this.modalNotesTarget.value.trim();

        // Validation
        if (!isCustom && !productId) {
            this._shakeField(this.searchInputTarget);
            return;
        }
        if (isCustom && !customName) {
            this._shakeField(this.modalCustomNameTarget);
            return;
        }
        if (!quantity || parseFloat(quantity) <= 0) {
            this._shakeField(this.modalQuantityTarget);
            return;
        }

        const code = isCustom ? '' : (this.modalCodeTarget.textContent !== '—' ? this.modalCodeTarget.textContent : '');
        const displayName = isCustom ? customName : productName;

        const lineData = {
            productId,
            productName,
            customName,
            isCustom,
            code,
            unit: unit || 'buc',
            quantity,
            unitPrice: '0.00',
            notes,
            displayName,
        };

        if (this.editingIndex !== null) {
            this.lines[this.editingIndex] = lineData;
        } else {
            this.lines.push(lineData);
        }

        this._renderTable();
        this._updateHiddenInputs();
        this._recalcGrandTotal();
        this.closeModal();
    }

    // ==================== LINE ACTIONS ====================

    removeLine(event) {
        const row = event.currentTarget.closest('tr');
        const index = parseInt(row.dataset.lineIndex);
        this.lines.splice(index, 1);
        this._renderTable();
        this._updateHiddenInputs();
        this._recalcGrandTotal();
    }

    // ==================== PRODUCT SEARCH ====================

    onSearchInput() {
        const query = this.searchInputTarget.value.trim();

        if (this.searchTimeout) clearTimeout(this.searchTimeout);

        if (query.length < 1) {
            this.searchResultsTarget.style.display = 'none';
            return;
        }

        this.searchTimeout = setTimeout(() => this._doSearch(query), 250);
    }

    onSearchFocus() {
        const query = this.searchInputTarget.value.trim();
        if (query.length >= 1 && this.searchResultsTarget.children.length > 0) {
            this.searchResultsTarget.style.display = 'block';
        }
    }

    onSearchBlur() {
        // Delay to allow click on result
        setTimeout(() => {
            this.searchResultsTarget.style.display = 'none';
        }, 200);
    }

    selectProduct(event) {
        const item = event.currentTarget;

        this.modalProductIdTarget.value = item.dataset.productId;
        this.modalProductNameTarget.value = item.dataset.productName;
        this.searchInputTarget.value = item.dataset.productName;
        this.modalCodeTarget.textContent = item.dataset.productCode || '—';
        this.modalUnitTarget.value = item.dataset.productUnit || '';

        this.searchResultsTarget.style.display = 'none';
    }

    // ==================== CUSTOM PRODUCT TOGGLE ====================

    toggleCustomProduct() {
        this._toggleCustomProduct();
    }

    // ==================== PRIVATE METHODS ====================

    _showModal(title) {
        this.modalTarget.querySelector('[data-modal-title]').textContent = title;

        const saveBtn = this.modalTarget.querySelector('[data-action="click->order-form#saveLine"]');
        saveBtn.textContent = this.editingIndex !== null ? 'Salvează' : 'Adaugă';

        this.modalTarget.style.display = 'flex';
        this.modalTarget.classList.add('show');
        document.body.style.overflow = 'hidden';

        if (this.editingIndex === null) {
            setTimeout(() => {
                if (this.customToggleTarget.checked) {
                    this.modalCustomNameTarget.focus();
                } else {
                    this.searchInputTarget.focus();
                }
            }, 100);
        }
    }

    _resetModal() {
        this.modalProductIdTarget.value = '';
        this.modalProductNameTarget.value = '';
        this.modalCustomNameTarget.value = '';
        this.searchInputTarget.value = '';
        this.modalCodeTarget.textContent = '—';
        this.modalUnitTarget.value = '';
        this.modalQuantityTarget.value = '';
        this.modalNotesTarget.value = '';
        this.customToggleTarget.checked = false;
        this.searchResultsTarget.style.display = 'none';
        this._toggleCustomProduct();
    }

    _toggleCustomProduct() {
        const isCustom = this.customToggleTarget.checked;
        this.customNameFieldTarget.style.display = isCustom ? 'block' : 'none';
        this.productFieldsTarget.style.display = isCustom ? 'none' : 'block';
    }

    async _doSearch(query) {
        try {
            const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            this.searchResultsTarget.innerHTML = '';

            if (data.results.length === 0) {
                this.searchResultsTarget.innerHTML = '<div class="of-search-empty">Niciun produs găsit</div>';
            } else {
                data.results.forEach((p) => {
                    const div = document.createElement('div');
                    div.className = 'of-search-item';
                    div.dataset.productId = p.id;
                    div.dataset.productName = p.name;
                    div.dataset.productCode = p.code;
                    div.dataset.productUnit = p.unit;
                    div.dataset.productPrice = p.defaultPrice;
                    div.dataset.action = 'click->order-form#selectProduct';
                    div.innerHTML = `
                        <div class="of-search-item-name">${this._escapeHtml(p.name)}</div>
                        <div class="of-search-item-meta">${this._escapeHtml(p.code || '')}${p.code ? ' · ' : ''}${this._escapeHtml(p.unit)} · ${p.defaultPrice} RON</div>
                    `;
                    this.searchResultsTarget.appendChild(div);
                });
            }

            this.searchResultsTarget.style.display = 'block';
        } catch (e) {
            console.error('Product search failed:', e);
        }
    }

    _renderTable() {
        this.linesBodyTarget.innerHTML = '';

        this.lines.forEach((line, index) => {
            const row = document.createElement('tr');
            row.dataset.lineIndex = index;

            const notesHtml = line.notes
                ? `<div style="font-size:12px; color:var(--mg-text-muted, #6b7280); margin-top:3px;">${this._escapeHtml(line.notes)}</div>`
                : '';

            const customBadge = line.isCustom
                ? ' <span style="font-size:10px; background:var(--mg-warning-light, #fef3c7); color:var(--mg-warning, #d97706); padding:1px 6px; border-radius:10px; font-weight:500;">Produs lipsă din nomenclator</span>'
                : '';

            row.innerHTML = `
                <td style="text-align:center; color:var(--mg-text-muted); font-weight:600;">${index + 1}</td>
                <td>
                    <div style="font-weight:500;">${this._escapeHtml(line.displayName)}${customBadge}</div>
                    ${notesHtml}
                </td>
                <td><span class="mg-cell-mono">${this._escapeHtml(line.code) || '—'}</span></td>
                <td>${this._escapeHtml(this.unitLabels[line.unit] || line.unit)}</td>
                <td style="text-align:right;">${this._formatNumber(parseFloat(line.quantity) || 0)}</td>
                <td style="text-align:right;">
                    <button type="button" class="mg-btn-icon" style="color:var(--mg-primary);" title="Editează"
                            data-action="click->order-form#openEditModal">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="mg-btn-icon" style="color:var(--mg-danger);" title="Șterge"
                            data-action="click->order-form#removeLine">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;

            this.linesBodyTarget.appendChild(row);
        });

        if (this.lines.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="6" style="text-align:center; padding:32px; color:var(--mg-text-light);">
                    <i class="fa-solid fa-cubes" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                    Niciun material adăugat. Click pe <strong>Adaugă material</strong> pentru a începe.
                </td>
            `;
            this.linesBodyTarget.appendChild(emptyRow);
        }
    }

    _updateHiddenInputs() {
        this.linesDataTarget.innerHTML = '';

        this.lines.forEach((line, index) => {
            const prefix = `lines[${index}]`;
            const fields = {
                productId: line.productId,
                customProductName: line.customName,
                unit: line.unit,
                quantity: line.quantity,
                unitPrice: line.unitPrice,
                notes: line.notes,
            };

            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${prefix}[${key}]`;
                input.value = value || '';
                this.linesDataTarget.appendChild(input);
            });
        });
    }

    _recalcGrandTotal() {
        let total = 0;
        this.lines.forEach((line) => {
            total += line.total || 0;
        });
        this.grandTotalTarget.textContent = this._formatNumber(total) + ' RON';
    }

    _shakeField(el) {
        el.style.borderColor = 'var(--mg-danger)';
        el.focus();
        setTimeout(() => {
            el.style.borderColor = '';
        }, 2000);
    }

    _formatNumber(num) {
        return num.toLocaleString('ro-RO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    _escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
