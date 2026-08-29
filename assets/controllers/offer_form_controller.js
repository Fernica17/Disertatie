import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    static targets = [
        'linesBody', 'linesData', 'lineModal',
        'searchInput', 'searchResults', 'customToggle', 'customNameField', 'productFields',
        'modalProductId', 'modalProductName', 'modalCustomName', 'modalCode',
        'modalUnit', 'modalQuantity', 'modalUnitPrice', 'modalDiscount',
        'modalDescription', 'modalNotes', 'modalLineTotal', 'saveLineLabel',
        'subtotalDisplay', 'vatAmountDisplay', 'totalDisplay', 'vatPercent',
        'clientSearchInput', 'clientSearchResults', 'clientId',
        'clientModal', 'quickClientName', 'quickClientCui', 'quickClientEmail', 'quickClientPhone', 'quickClientError',
        'uploadArea', 'fileInput', 'fileList',
        'currencySelect',
        'typeSelect',
    ];

    connect() {
        this.lines = [];
        this.editingIndex = null;
        this.searchTimeout = null;
        this.clientSearchTimeout = null;
        this.searchUrl = this.element.dataset.searchUrl;
        this.clientsSearchUrl = this.element.dataset.clientsSearchUrl;
        this.clientsCreateUrl = this.element.dataset.clientsCreateUrl;

        // Build unit value → label map
        this.unitLabels = {};
        try {
            const units = JSON.parse(this.element.dataset.units || '[]');
            units.forEach((u) => { this.unitLabels[u.value] = u.label; });
        } catch (e) { /* ignore */ }

        // Load i18n strings from data attributes
        this.i18n = {
            addLine: this.element.dataset.i18nAddLine || 'Add line',
            editLine: this.element.dataset.i18nEditLine || 'Edit line',
            save: this.element.dataset.i18nSave || 'Save',
            noProducts: this.element.dataset.i18nNoProducts || 'No products found',
            noClients: this.element.dataset.i18nNoClients || 'No clients found',
            createError: this.element.dataset.i18nCreateError || 'Creation failed',
            networkError: this.element.dataset.i18nNetworkError || 'Network error',
            edit: this.element.dataset.i18nEdit || 'Edit',
            delete: this.element.dataset.i18nDelete || 'Delete',
            noLines: this.element.dataset.i18nNoLines || 'No lines added',
            custom: this.element.dataset.i18nCustom || 'Custom',
            remove: this.element.dataset.i18nRemove || 'Remove',
        };

        // TomSelect on currency and type selects
        this.tomInstances = [];
        if (this.hasCurrencySelectTarget) {
            this.tomInstances.push(new TomSelect(this.currencySelectTarget, { allowEmptyOption: false }));
        }
        if (this.hasTypeSelectTarget) {
            this.tomInstances.push(new TomSelect(this.typeSelectTarget, { allowEmptyOption: true }));
        }

        // Lines form prefix (from CollectionType)
        this.linesPrefix = this.element.dataset.linesPrefix || 'offer_form[lines]';

        // File upload state
        this.selectedFiles = new DataTransfer();
        this._initUploadArea();

        // Load existing lines for edit mode
        if (this.element.dataset.existingLines) {
            try {
                this.lines = JSON.parse(this.element.dataset.existingLines);
                if (this.lines.length > 0) {
                    this._renderTable();
                    this._updateHiddenInputs();
                    this._recalcTotals();
                }
            } catch (e) {
                console.error('Failed to parse existing lines:', e);
            }
        }
    }

    // ==================== LINE MODAL ====================

    openAddModal() {
        this.editingIndex = null;
        this._resetLineModal();
        this._showLineModal(this.i18n.addLine);
    }

    openEditModal(event) {
        const row = event.currentTarget.closest('tr');
        const index = parseInt(row.dataset.lineIndex);
        const line = this.lines[index];
        if (!line) return;

        this.editingIndex = index;
        this._resetLineModal();

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

        if (this.modalUnitTomSelect) {
            this.modalUnitTomSelect.setValue(line.unit || '', true);
        } else {
            this.modalUnitTarget.value = line.unit || '';
        }
        this.modalQuantityTarget.value = line.quantity || '';
        this.modalUnitPriceTarget.value = line.unitPrice || '';
        this.modalDiscountTarget.value = line.discountPercent || '';
        this.modalDescriptionTarget.value = line.description || '';
        this.modalNotesTarget.value = line.notes || '';

        this._calcLineTotal();
        this._showLineModal(this.i18n.editLine);
    }

    closeLineModal() {
        this.lineModalTarget.style.display = 'none';
        this.lineModalTarget.classList.remove('show');
        document.body.style.overflow = '';
    }

    saveLine() {
        const isCustom = this.customToggleTarget.checked;
        const productId = isCustom ? '' : this.modalProductIdTarget.value;
        const productName = isCustom ? '' : this.modalProductNameTarget.value;
        const customName = isCustom ? this.modalCustomNameTarget.value.trim() : '';
        const unit = this.modalUnitTarget.value.trim();
        const quantity = this.modalQuantityTarget.value.trim();
        const unitPrice = this.modalUnitPriceTarget.value.trim();
        const discountPercent = this.modalDiscountTarget.value.trim();
        const description = this.modalDescriptionTarget.value.trim();
        const notes = this.modalNotesTarget.value.trim();

        // Validation
        if (!isCustom && !productId) { this._shakeField(this.searchInputTarget); return; }
        if (isCustom && !customName) { this._shakeField(this.modalCustomNameTarget); return; }
        if (!quantity || parseFloat(quantity) <= 0) { this._shakeField(this.modalQuantityTarget); return; }
        if (!unitPrice || parseFloat(unitPrice) < 0) { this._shakeField(this.modalUnitPriceTarget); return; }
        if (discountPercent && (parseFloat(discountPercent) < 0 || parseFloat(discountPercent) > 100)) { this._shakeField(this.modalDiscountTarget); return; }

        const code = isCustom ? '' : (this.modalCodeTarget.textContent !== '—' ? this.modalCodeTarget.textContent : '');
        const displayName = isCustom ? customName : productName;

        // Calculate total
        let total = parseFloat(quantity) * parseFloat(unitPrice);
        if (discountPercent && parseFloat(discountPercent) > 0) {
            total = total * (1 - parseFloat(discountPercent) / 100);
        }

        const lineData = {
            productId, productName, customName, isCustom, code,
            unit: unit || 'buc',
            quantity, unitPrice,
            discountPercent: discountPercent || '0.00',
            description, notes,
            displayName,
            totalValue: total.toFixed(2),
        };

        if (this.editingIndex !== null) {
            this.lines[this.editingIndex] = lineData;
        } else {
            this.lines.push(lineData);
        }

        this._renderTable();
        this._updateHiddenInputs();
        this._recalcTotals();
        this.closeLineModal();
    }

    removeLine(event) {
        const row = event.currentTarget.closest('tr');
        const index = parseInt(row.dataset.lineIndex);
        this.lines.splice(index, 1);
        this._renderTable();
        this._updateHiddenInputs();
        this._recalcTotals();
    }

    // ==================== PRODUCT SEARCH ====================

    onSearchInput() {
        const query = this.searchInputTarget.value.trim();
        if (this.searchTimeout) clearTimeout(this.searchTimeout);
        if (query.length < 1) { this.searchResultsTarget.style.display = 'none'; return; }
        this.searchTimeout = setTimeout(() => this._doProductSearch(query), 250);
    }

    onSearchFocus() {
        const query = this.searchInputTarget.value.trim();
        if (query.length >= 1 && this.searchResultsTarget.children.length > 0) {
            this.searchResultsTarget.style.display = 'block';
        }
    }

    onSearchBlur() {
        setTimeout(() => { this.searchResultsTarget.style.display = 'none'; }, 200);
    }

    selectProduct(event) {
        const item = event.currentTarget;
        this.modalProductIdTarget.value = item.dataset.productId;
        this.modalProductNameTarget.value = item.dataset.productName;
        this.searchInputTarget.value = item.dataset.productName;
        this.modalCodeTarget.textContent = item.dataset.productCode || '—';

        const unitValue = item.dataset.productUnit || '';
        if (this.modalUnitTomSelect) {
            this.modalUnitTomSelect.setValue(unitValue, true);
        } else {
            this.modalUnitTarget.value = unitValue;
        }

        if (item.dataset.productPrice && item.dataset.productPrice !== '0.00') {
            this.modalUnitPriceTarget.value = item.dataset.productPrice;
        }

        this.searchResultsTarget.style.display = 'none';
        this._calcLineTotal();
    }

    toggleCustomProduct() { this._toggleCustomProduct(); }

    // ==================== MODAL LINE TOTAL LIVE CALC ====================

    onLinePriceChange() { this._calcLineTotal(); }

    // ==================== CLIENT SEARCH ====================

    onClientSearchInput() {
        const query = this.clientSearchInputTarget.value.trim();
        if (this.clientSearchTimeout) clearTimeout(this.clientSearchTimeout);
        if (query.length < 1) { this.clientSearchResultsTarget.style.display = 'none'; return; }
        this.clientSearchTimeout = setTimeout(() => this._doClientSearch(query), 250);
    }

    onClientSearchFocus() {
        const query = this.clientSearchInputTarget.value.trim();
        if (query.length >= 1 && this.clientSearchResultsTarget.children.length > 0) {
            this.clientSearchResultsTarget.style.display = 'block';
        }
    }

    onClientSearchBlur() {
        setTimeout(() => { this.clientSearchResultsTarget.style.display = 'none'; }, 200);
    }

    selectClient(event) {
        const item = event.currentTarget;
        this.clientIdTarget.value = item.dataset.clientId;
        this.clientSearchInputTarget.value = item.dataset.clientName;
        this.clientSearchResultsTarget.style.display = 'none';
    }

    // ==================== QUICK CLIENT MODAL ====================

    openQuickClientModal() {
        this.quickClientNameTarget.value = '';
        this.quickClientCuiTarget.value = '';
        this.quickClientEmailTarget.value = '';
        this.quickClientPhoneTarget.value = '';
        this.quickClientErrorTarget.style.display = 'none';
        this.clientModalTarget.style.display = 'flex';
        this.clientModalTarget.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => this.quickClientNameTarget.focus(), 100);
    }

    closeClientModal() {
        this.clientModalTarget.style.display = 'none';
        this.clientModalTarget.classList.remove('show');
        document.body.style.overflow = '';
    }

    async saveQuickClient() {
        const name = this.quickClientNameTarget.value.trim();
        if (!name) { this._shakeField(this.quickClientNameTarget); return; }

        const body = new FormData();
        body.append('name', name);
        body.append('fiscalCode', this.quickClientCuiTarget.value.trim());
        body.append('email', this.quickClientEmailTarget.value.trim());
        body.append('phone', this.quickClientPhoneTarget.value.trim());

        try {
            const response = await fetch(this.clientsCreateUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });

            const data = await response.json();

            if (!response.ok) {
                this.quickClientErrorTarget.textContent = data.error || this.i18n.createError;
                this.quickClientErrorTarget.style.display = 'block';
                return;
            }

            // Set the new client as selected
            this.clientIdTarget.value = data.id;
            this.clientSearchInputTarget.value = data.name;
            this.closeClientModal();
        } catch (e) {
            this.quickClientErrorTarget.textContent = this.i18n.networkError;
            this.quickClientErrorTarget.style.display = 'block';
        }
    }

    // ==================== FILE UPLOAD ====================

    onFileInputChange() {
        const newFiles = Array.from(this.fileInputTarget.files);
        this.fileInputTarget.value = '';
        this._addNewFiles(newFiles);
    }

    toggleRemoveAttachment(event) {
        const btn = event.currentTarget;
        const attachmentId = btn.dataset.attachmentId;
        const item = document.getElementById('existing-attachment-' + attachmentId);
        const existingInput = this.element.querySelector('input[name="removeAttachments[]"][value="' + attachmentId + '"]');

        if (existingInput) {
            existingInput.remove();
            item.style.opacity = '1';
            item.style.textDecoration = 'none';
            btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removeAttachments[]';
            input.value = attachmentId;
            item.closest('form').appendChild(input);
            item.style.opacity = '0.4';
            item.style.textDecoration = 'line-through';
            btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
        }
    }

    // ==================== TOTALS ====================

    recalcTotals() { this._recalcTotals(); }

    // ==================== PRIVATE ====================

    _showLineModal(title) {
        this.lineModalTarget.querySelector('[data-modal-title]').textContent = title;
        this.saveLineLabelTarget.textContent = this.editingIndex !== null ? this.i18n.save : this.i18n.addLine;
        this.lineModalTarget.style.display = 'flex';
        this.lineModalTarget.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Init TomSelect on unit select if not already
        if (!this.modalUnitTomSelect && this.hasModalUnitTarget) {
            this.modalUnitTomSelect = new TomSelect(this.modalUnitTarget, { allowEmptyOption: true });
        }

        // Add live calc listeners
        this.modalQuantityTarget.addEventListener('input', () => this._calcLineTotal());
        this.modalUnitPriceTarget.addEventListener('input', () => this._calcLineTotal());
        this.modalDiscountTarget.addEventListener('input', () => this._calcLineTotal());

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

    _resetLineModal() {
        this.modalProductIdTarget.value = '';
        this.modalProductNameTarget.value = '';
        this.modalCustomNameTarget.value = '';
        this.searchInputTarget.value = '';
        this.modalCodeTarget.textContent = '—';
        this.modalUnitTarget.value = '';
        this.modalQuantityTarget.value = '';
        this.modalUnitPriceTarget.value = '';
        this.modalDiscountTarget.value = '';
        this.modalDescriptionTarget.value = '';
        this.modalNotesTarget.value = '';
        this.modalLineTotalTarget.textContent = '0,00';
        this.customToggleTarget.checked = false;
        this.searchResultsTarget.style.display = 'none';
        this._toggleCustomProduct();
    }

    _toggleCustomProduct() {
        const isCustom = this.customToggleTarget.checked;
        this.customNameFieldTarget.style.display = isCustom ? 'block' : 'none';
        this.productFieldsTarget.style.display = isCustom ? 'none' : 'block';
    }

    _calcLineTotal() {
        const qty = parseFloat(this.modalQuantityTarget.value) || 0;
        const price = parseFloat(this.modalUnitPriceTarget.value) || 0;
        let discount = parseFloat(this.modalDiscountTarget.value) || 0;

        // Clamp discount to 0-100
        if (discount < 0) {
            discount = 0;
            this.modalDiscountTarget.value = '0';
        } else if (discount > 100) {
            discount = 100;
            this.modalDiscountTarget.value = '100';
        }

        let total = qty * price;
        if (discount > 0) total = total * (1 - discount / 100);
        this.modalLineTotalTarget.textContent = this._formatNumber(total);
    }

    _recalcTotals() {
        let subtotal = 0;
        this.lines.forEach((line) => {
            subtotal += parseFloat(line.totalValue) || 0;
        });

        const vatPercent = parseFloat(this.vatPercentTarget.value) || 0;
        const vatAmount = subtotal * vatPercent / 100;
        const totalWithVat = subtotal + vatAmount;

        this.subtotalDisplayTarget.textContent = this._formatNumber(subtotal);
        this.vatAmountDisplayTarget.textContent = this._formatNumber(vatAmount);
        this.totalDisplayTarget.textContent = this._formatNumber(totalWithVat);
    }

    async _doProductSearch(query) {
        try {
            const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            this.searchResultsTarget.innerHTML = '';

            if (data.results.length === 0) {
                this.searchResultsTarget.innerHTML = `<div class="of-search-empty">${this._escapeHtml(this.i18n.noProducts)}</div>`;
            } else {
                data.results.forEach((p) => {
                    const div = document.createElement('div');
                    div.className = 'of-search-item';
                    div.dataset.productId = p.id;
                    div.dataset.productName = p.name;
                    div.dataset.productCode = p.code;
                    div.dataset.productUnit = p.unit;
                    div.dataset.productPrice = p.defaultPrice;
                    div.dataset.action = 'click->offer-form#selectProduct';
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

    async _doClientSearch(query) {
        try {
            const response = await fetch(`${this.clientsSearchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            this.clientSearchResultsTarget.innerHTML = '';

            if (data.results.length === 0) {
                this.clientSearchResultsTarget.innerHTML = `<div class="of-search-empty">${this._escapeHtml(this.i18n.noClients)}</div>`;
            } else {
                data.results.forEach((c) => {
                    const div = document.createElement('div');
                    div.className = 'of-search-item';
                    div.dataset.clientId = c.id;
                    div.dataset.clientName = c.name;
                    div.dataset.action = 'click->offer-form#selectClient';
                    div.innerHTML = `
                        <div class="of-search-item-name">${this._escapeHtml(c.name)}</div>
                        <div class="of-search-item-meta">${this._escapeHtml(c.fiscalCode || '')}${c.email ? ' · ' + this._escapeHtml(c.email) : ''}</div>
                    `;
                    this.clientSearchResultsTarget.appendChild(div);
                });
            }
            this.clientSearchResultsTarget.style.display = 'block';
        } catch (e) {
            console.error('Client search failed:', e);
        }
    }

    _renderTable() {
        this.linesBodyTarget.innerHTML = '';

        this.lines.forEach((line, index) => {
            const row = document.createElement('tr');
            row.dataset.lineIndex = index;

            const notesHtml = line.notes
                ? `<div style="font-size:12px; color:var(--mg-text-muted); margin-top:3px;">${this._escapeHtml(line.notes)}</div>`
                : '';
            const descHtml = line.description
                ? `<div style="font-size:12px; color:var(--mg-text-light); margin-top:2px;">${this._escapeHtml(line.description)}</div>`
                : '';

            const customBadge = line.isCustom
                ? ` <span style="font-size:10px; background:var(--mg-warning-light,#fef3c7); color:var(--mg-warning,#d97706); padding:1px 6px; border-radius:10px; font-weight:500;">${this._escapeHtml(this.i18n.custom)}</span>`
                : '';

            const discountStr = parseFloat(line.discountPercent) > 0 ? this._formatNumber(parseFloat(line.discountPercent)) + '%' : '—';

            row.innerHTML = `
                <td style="text-align:center; color:var(--mg-text-muted); font-weight:600;">${index + 1}</td>
                <td>
                    <div style="font-weight:500;">${this._escapeHtml(line.displayName)}${customBadge}</div>
                    ${descHtml}${notesHtml}
                </td>
                <td>${this._escapeHtml(this.unitLabels[line.unit] || line.unit)}</td>
                <td style="text-align:right;">${this._formatNumber(parseFloat(line.quantity) || 0)}</td>
                <td style="text-align:right;">${this._formatNumber(parseFloat(line.unitPrice) || 0)}</td>
                <td style="text-align:right;">${discountStr}</td>
                <td style="text-align:right; font-weight:600;">${this._formatNumber(parseFloat(line.totalValue) || 0)}</td>
                <td style="text-align:right;">
                    <button type="button" class="mg-btn-icon" style="color:var(--mg-primary);" title="${this._escapeHtml(this.i18n.edit)}" data-action="click->offer-form#openEditModal">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="mg-btn-icon" style="color:var(--mg-danger);" title="${this._escapeHtml(this.i18n.delete)}" data-action="click->offer-form#removeLine">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;

            this.linesBodyTarget.appendChild(row);
        });

        if (this.lines.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="8" style="text-align:center; padding:32px; color:var(--mg-text-light);">
                    <i class="fa-solid fa-list" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                    ${this._escapeHtml(this.i18n.noLines)}
                </td>
            `;
            this.linesBodyTarget.appendChild(emptyRow);
        }
    }

    _updateHiddenInputs() {
        this.linesDataTarget.innerHTML = '';

        this.lines.forEach((line, index) => {
            const prefix = `${this.linesPrefix}[${index}]`;
            const fields = {
                product: line.productId,
                customProductName: line.customName,
                quantity: line.quantity,
                unit: line.unit,
                unitPrice: line.unitPrice,
                discountPercent: line.discountPercent,
                description: line.description,
                notes: line.notes,
                position: index,
            };

            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${prefix}[${key}]`;
                input.value = value ?? '';
                this.linesDataTarget.appendChild(input);
            });
        });
    }

    _shakeField(el) {
        el.style.borderColor = 'var(--mg-danger)';
        el.focus();
        setTimeout(() => { el.style.borderColor = ''; }, 2000);
    }

    _formatNumber(num) {
        return num.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    _escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _initUploadArea() {
        if (!this.hasUploadAreaTarget) return;

        const area = this.uploadAreaTarget;
        ['dragenter', 'dragover'].forEach((evt) => {
            area.addEventListener(evt, (e) => { e.preventDefault(); area.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            area.addEventListener(evt, (e) => { e.preventDefault(); area.classList.remove('dragover'); });
        });
        area.addEventListener('drop', (e) => {
            this._addNewFiles(e.dataTransfer.files);
        });
    }

    _addNewFiles(fileList) {
        const maxSize = 10 * 1024 * 1024;
        const allowedTypes = [
            'application/pdf', 'image/jpeg', 'image/png',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        const files = fileList instanceof FileList ? Array.from(fileList) : fileList;
        files.forEach((file) => {
            if (!allowedTypes.includes(file.type)) { return; }
            if (file.size > maxSize) { return; }
            this.selectedFiles.items.add(file);
        });

        this.fileInputTarget.files = this.selectedFiles.files;
        this._renderFileList();
    }

    _renderFileList() {
        this.fileListTarget.innerHTML = '';

        Array.from(this.selectedFiles.files).forEach((file, idx) => {
            const iconClass = file.type === 'application/pdf' ? 'fa-file-pdf' : file.type.startsWith('image/') ? 'fa-file-image' : 'fa-file';
            const sizeStr = file.size < 1024 * 1024
                ? (file.size / 1024).toFixed(0) + ' KB'
                : (file.size / (1024 * 1024)).toFixed(1) + ' MB';

            const div = document.createElement('div');
            div.className = 'of-file-item';
            div.innerHTML = `
                <i class="fa-solid ${iconClass}"></i>
                <span class="of-file-name">${this._escapeHtml(file.name)}</span>
                <span class="of-file-size">${sizeStr}</span>
                <button type="button" class="of-file-remove" data-file-idx="${idx}" data-action="click->offer-form#removeFile">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            this.fileListTarget.appendChild(div);
        });
    }

    removeFile(event) {
        const idx = parseInt(event.currentTarget.dataset.fileIdx);
        const dt = new DataTransfer();
        Array.from(this.selectedFiles.files).forEach((f, i) => {
            if (i !== idx) dt.items.add(f);
        });
        this.selectedFiles = dt;
        this.fileInputTarget.files = this.selectedFiles.files;
        this._renderFileList();
    }
}
