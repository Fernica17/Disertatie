import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {
    static targets = [
        'content', 'breadcrumb', 'toolbar', 'searchInput',
        'pagination', 'modal', 'modalTitle', 'modalBody', 'modalSubmitBtn',
        'moveModal', 'moveModalTitle', 'moveModalBody', 'moveSubmitBtn',
        'btnNewFolder', 'btnUpload', 'fileInput', 'statsBar', 'searchClear',
    ];

    static values = {
        treeUrl: String,
        contentsUrl: String,
        createUrl: String,
        searchUrl: String,
        csrfToken: String,
        isClient: String,
        canManage: String,
        folderType: String,
        // i18n labels
        labelCreate: String,
        labelCreateSub: String,
        labelRename: String,
        labelMoveFolder: String,
        labelMoveFile: String,
        labelName: String,
        labelConfirmDeleteFolder: String,
        labelConfirmDeleteFile: String,
        labelEmpty: String,
        labelRootLevel: String,
        labelErrorLoad: String,
        labelErrorUpload: String,
        labelErrorDelete: String,
        labelErrorRename: String,
        labelErrorCreate: String,
        labelErrorMove: String,
        labelSearch: String,
        labelColName: String,
        labelColSize: String,
        labelColDate: String,
        labelColActions: String,
        labelBtnCancel: String,
        labelBtnSave: String,
        labelBtnView: String,
        labelBtnEntity: String,
        labelBtnMove: String,
        labelBtnDownload: String,
        labelBtnDelete: String,
        labelSwalConfirm: String,
        labelSwalCancel: String,
        labelSwalTitle: String,
        labelConfirmMove: String,
        labelConfirmDeleteRecursive: String,
        labelSwalConfirmMove: String,
        labelStatsFolders: String,
        labelStatsFiles: String,
        labelEmptyUpload: String,
        labelDeleteFolder: String,
    };

    connect() {
        this.currentFolderId = null;
        this.currentFolderType = null;
        this.modalInstance = null;
        this.moveModalInstance = null;
        this.pendingAction = null;
        this.searchTimeout = null;

        // Load initial folders from embedded JSON
        try {
            const el = document.getElementById('initialFolders');
            this.initialFolders = el ? JSON.parse(el.textContent) : [];
        } catch {
            this.initialFolders = [];
        }

        // Restore folder from URL on page load
        const urlParams = new URLSearchParams(window.location.search);
        const folderId = urlParams.get('folder');
        if (folderId) {
            this.currentFolderId = folderId;
            this.loadFolderContents(folderId);
        }
    }

    // === Folder navigation ===

    selectFolder(event) {
        event.preventDefault();

        // Don't trigger when clicking action buttons
        if (event.target.closest('.mg-folder-card-actions')) {
            return;
        }

        const folderId = event.currentTarget.dataset.folderId;
        if (!folderId) return;

        this.currentFolderId = folderId;
        this._updateUrl(folderId);
        this.loadFolderContents(folderId);
    }

    navigateToRoot(event) {
        event.preventDefault();
        this.currentFolderId = null;
        this.currentFolderType = null;
        this._updateUrl(null);
        this._renderFolderGrid(this.initialFolders);
        this._renderRootBreadcrumb();
        this._renderStats(this.initialFolders.length, 0);
        this._updateToolbarForRoot();
        this.paginationTarget.innerHTML = '';
    }

    async loadFolderContents(folderId, page = 1) {
        try {
            const url = this.contentsUrlValue.replace('__ID__', folderId) + `?page=${page}`;
            const data = await this._fetchJson(url);

            if (data.folder?.type) {
                this.currentFolderType = data.folder.type;
            }

            this._renderBreadcrumb(data.breadcrumb);

            // If the folder has sub-folders, show them as cards above the file list
            if (data.subfolders && data.subfolders.length > 0) {
                this._renderSubfoldersAndFiles(data.subfolders, data.files, data.folder);
            } else {
                this._renderFiles(data.files, data.folder);
            }

            this._renderStats(data.subfolders ? data.subfolders.length : 0, data.total || data.files.length);
            this._renderPagination(data.page, data.pages, folderId);
            this._updateToolbar(data.folder);
        } catch (error) {
            console.error('Failed to load folder contents:', error);
        }
    }

    // === Folder operations ===

    createFolderAction(event) {
        event.preventDefault();
        event.stopPropagation();
        const parentId = this.currentFolderId || null;
        this._showNameModal(this.labelCreateValue, '', (name) => {
            this._createFolder(name, parentId);
        });
    }

    renameFolder(event) {
        event.preventDefault();
        event.stopPropagation();
        const folderId = event.currentTarget.dataset.folderId;
        const folderName = event.currentTarget.dataset.folderName;
        this._showNameModal(this.labelRenameValue, folderName, (newName) => {
            this._renameFolder(folderId, newName);
        });
    }

    async deleteFolder(event) {
        event.preventDefault();
        event.stopPropagation();
        const folderId = event.currentTarget.dataset.folderId;
        const folderName = event.currentTarget.dataset.folderName;

        let message = this.labelConfirmDeleteFolderValue.replace('{name}', folderName);
        try {
            const stats = await this._fetchJson(`/admin/folders/${folderId}/stats`);
            if (stats.subfolders > 0 || stats.files > 0) {
                const parts = [];
                if (stats.subfolders > 0) parts.push(`${stats.subfolders} subfolder(e)`);
                if (stats.files > 0) parts.push(`${stats.files} fișier(e)`);
                message += '\n\n' + (this.labelConfirmDeleteRecursiveValue || 'Se vor șterge și: ') + parts.join(' și ');
            }
        } catch { /* ignore */ }

        const confirmed = await this._swalConfirm(message);
        if (!confirmed) return;

        try {
            const data = await this._fetchJson(`/admin/folders/${folderId}/delete`, {
                method: 'DELETE',
            });

            if (data.success) {
                this._swalToast(data.message, 'success');
                window.location.reload();
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(error.message || this.labelErrorDeleteValue, 'error');
        }
    }

    moveFolderAction(event) {
        event.preventDefault();
        event.stopPropagation();
        const folderId = event.currentTarget.dataset.folderId;
        const folderName = event.currentTarget.dataset.folderName;
        this._showMoveModal(this.labelMoveFolderValue + ': ' + folderName, 'folder', folderId);
    }

    // === File operations ===

    uploadFiles(event) {
        event.preventDefault();
        if (this.hasFileInputTarget) {
            this.fileInputTarget.click();
        }
    }

    async onFilesSelected(event) {
        const files = event.target.files;
        if (!files || files.length === 0 || !this.currentFolderId) return;

        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        try {
            const response = await fetch(`/admin/folders/${this.currentFolderId}/upload`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                this._swalToast(data.message, 'success');
                this.loadFolderContents(this.currentFolderId);
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(this.labelErrorUploadValue, 'error');
        }

        event.target.value = '';
    }

    moveFileAction(event) {
        event.preventDefault();
        const fileId = event.currentTarget.dataset.fileId;
        this._showMoveModal(this.labelMoveFileValue, 'file', fileId);
    }

    async deleteFileAction(event) {
        event.preventDefault();
        const fileId = event.currentTarget.dataset.fileId;
        const fileName = event.currentTarget.dataset.fileName;

        const confirmed = await this._swalConfirm(
            this.labelConfirmDeleteFileValue.replace('{name}', fileName)
        );
        if (!confirmed) return;

        try {
            const data = await this._fetchJson(`/admin/folders/files/${fileId}/delete`, {
                method: 'DELETE',
            });

            if (data.success) {
                this._swalToast(data.message, 'success');
                this.loadFolderContents(this.currentFolderId);
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(error.message || this.labelErrorDeleteValue, 'error');
        }
    }

    // === Search ===

    onSearchInput(event) {
        clearTimeout(this.searchTimeout);
        const query = event.target.value.trim();

        // Toggle clear button visibility
        if (this.hasSearchClearTarget) {
            this.searchClearTarget.style.display = query.length > 0 ? 'block' : 'none';
        }

        if (query.length < 2) {
            if (this.currentFolderId) {
                this.loadFolderContents(this.currentFolderId);
            } else {
                this._renderFolderGrid(this.initialFolders);
                this._renderRootBreadcrumb();
            }
            return;
        }

        this.searchTimeout = setTimeout(() => {
            this._searchFiles(query);
        }, 300);
    }

    clearSearch() {
        this.searchInputTarget.value = '';
        if (this.hasSearchClearTarget) {
            this.searchClearTarget.style.display = 'none';
        }
        // Restore previous view
        if (this.currentFolderId) {
            this.loadFolderContents(this.currentFolderId);
        } else {
            this._renderFolderGrid(this.initialFolders);
            this._renderRootBreadcrumb();
            this._renderStats(this.initialFolders.length, 0);
            this._updateToolbarForRoot();
        }
    }

    async _searchFiles(query) {
        try {
            const url = `${this.searchUrlValue}?q=${encodeURIComponent(query)}`;
            const data = await this._fetchJson(url);

            this._renderBreadcrumb([{ id: null, name: `${this.labelSearchValue} "${query}"` }]);
            this._renderFiles(data.files, { type: 'search', canUpload: false, canCreateSubfolder: false, canManageFiles: false });
            this._renderPagination(data.page, data.pages);
            this.toolbarTarget.style.display = 'none';
        } catch (error) {
            console.error('Search failed:', error);
        }
    }

    // === Modal handling ===

    _showNameModal(title, currentValue, callback) {
        this.modalTitleTarget.textContent = title;
        this.modalBodyTarget.innerHTML = `
            <div class="mb-3">
                <label class="form-label">${this.labelNameValue}</label>
                <input type="text" class="form-control" id="folderNameInput" value="${this._escapeHtml(currentValue)}" autofocus>
            </div>
        `;
        this.pendingAction = callback;

        if (!this.modalInstance) {
            this.modalInstance = new bootstrap.Modal(this.modalTarget);
        }

        this.modalTarget.addEventListener('shown.bs.modal', () => {
            const input = document.getElementById('folderNameInput');
            if (input) {
                input.focus();
                input.select();
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.modalSubmit();
                    }
                });
            }
        }, { once: true });

        this.modalInstance.show();
    }

    modalSubmit() {
        const input = document.getElementById('folderNameInput');
        if (!input || !this.pendingAction) return;

        const value = input.value.trim();
        if (value === '') return;

        this.modalInstance.hide();
        this.pendingAction(value);
        this.pendingAction = null;
    }

    _showMoveModal(title, type, id) {
        this.moveModalTitleTarget.textContent = title;
        this.pendingMoveType = type;
        this.pendingMoveId = id;
        this.selectedMoveTarget = null;

        this._buildMoveTree();

        if (!this.moveModalInstance) {
            this.moveModalInstance = new bootstrap.Modal(this.moveModalTarget);
        }
        this.moveModalInstance.show();
    }

    async _buildMoveTree() {
        try {
            const treeData = await this._fetchJson(this.treeUrlValue);

            let targetFolders;

            if (this.folderTypeValue === 'client') {
                // Find the client root that contains the current folder
                const clientRoot = this._findClientRootContaining(treeData, this.currentFolderId);
                if (clientRoot) {
                    // Show only non-system-mapped children (custom sub-folders within this client)
                    targetFolders = (clientRoot.children || []).filter(c => !c.hasSystemMapping);
                } else {
                    targetFolders = [];
                }
            } else {
                targetFolders = treeData.filter(f => f.type === 'custom');
            }

            let html = '<div class="mg-move-tree">';

            if (this.pendingMoveType === 'folder' && this.folderTypeValue === 'custom') {
                html += `<div class="mg-move-item" data-target-id="" data-action="click->folder-manager#selectMoveTarget">
                    <i class="fa fa-home"></i> <span>${this.labelRootLevelValue}</span>
                </div>`;
            }

            html += this._renderMoveTreeNodes(targetFolders);
            html += '</div>';

            this.moveModalBodyTarget.innerHTML = html;
        } catch (error) {
            this.moveModalBodyTarget.innerHTML = `<p class="text-danger">${this.labelErrorLoadValue}</p>`;
        }
    }

    _findClientRootContaining(nodes, folderId) {
        for (const node of nodes) {
            if (node.type === 'client' && this._treeContainsId(node, folderId)) {
                return node;
            }
        }
        return null;
    }

    _treeContainsId(node, id) {
        if (String(node.id) === String(id)) return true;
        for (const child of (node.children || [])) {
            if (this._treeContainsId(child, id)) return true;
        }
        return false;
    }

    _renderMoveTreeNodes(nodes, level = 0) {
        let html = '';
        for (const node of nodes) {
            if (this.pendingMoveType === 'folder' && String(node.id) === String(this.pendingMoveId)) continue;

            const padding = level * 20;
            html += `<div class="mg-move-item" style="padding-left: ${padding + 10}px"
                          data-target-id="${node.id}" data-action="click->folder-manager#selectMoveTarget">
                <i class="fa fa-folder"></i> <span>${this._escapeHtml(node.name)}</span>
            </div>`;

            if (node.children && node.children.length > 0) {
                html += this._renderMoveTreeNodes(node.children, level + 1);
            }
        }
        return html;
    }

    selectMoveTarget(event) {
        event.preventDefault();
        this.moveModalBodyTarget.querySelectorAll('.mg-move-item').forEach(el => el.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
        this.selectedMoveTarget = event.currentTarget.dataset.targetId;
    }

    async moveSubmit() {
        if (this.selectedMoveTarget === undefined || this.selectedMoveTarget === null) return;

        const targetId = this.selectedMoveTarget === '' ? null : parseInt(this.selectedMoveTarget);

        const selectedEl = this.moveModalBodyTarget.querySelector('.mg-move-item.selected span');
        const targetName = selectedEl ? selectedEl.textContent : this.labelRootLevelValue;
        const confirmMsg = (this.labelConfirmMoveValue || 'Mutare in "{target}"?').replace('{target}', targetName);

        this.moveModalInstance.hide();

        const confirmed = await this._swalConfirmMove(confirmMsg);
        if (!confirmed) return;

        try {
            let url, body;

            if (this.pendingMoveType === 'folder') {
                url = `/admin/folders/${this.pendingMoveId}/move`;
                body = { targetParentId: targetId };
            } else {
                url = `/admin/folders/files/${this.pendingMoveId}/move`;
                body = { targetFolderId: targetId };
            }

            const data = await this._fetchJson(url, {
                method: 'PUT',
                body: JSON.stringify(body),
            });

            if (data.success) {
                this._swalToast(data.message, 'success');
                window.location.reload();
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(error.message || this.labelErrorMoveValue, 'error');
        }
    }

    // === Private helpers ===

    async _createFolder(name, parentId) {
        try {
            const data = await this._fetchJson(this.createUrlValue, {
                method: 'POST',
                body: JSON.stringify({ name, parentId: parentId ? parseInt(parentId) : null }),
            });

            if (data.success) {
                this._swalToast(data.message, 'success');
                window.location.reload();
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(error.message || this.labelErrorCreateValue, 'error');
        }
    }

    async _renameFolder(folderId, newName) {
        try {
            const data = await this._fetchJson(`/admin/folders/${folderId}/rename`, {
                method: 'PUT',
                body: JSON.stringify({ name: newName }),
            });

            if (data.success) {
                this._swalToast(data.message, 'success');
                window.location.reload();
            } else {
                this._swalToast(data.message, 'error');
            }
        } catch (error) {
            this._swalToast(error.message || this.labelErrorRenameValue, 'error');
        }
    }

    async _fetchJson(url, options = {}) {
        const defaultOptions = {
            headers: {
                'X-CSRF-Token': this.csrfTokenValue,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        };

        if (options.body instanceof FormData) {
            delete defaultOptions.headers['Content-Type'];
        }

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: { ...defaultOptions.headers, ...options.headers },
        };

        const response = await fetch(url, mergedOptions);
        const data = await response.json();

        if (!response.ok && !data.success) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    _updateUrl(folderId) {
        const url = new URL(window.location);
        if (folderId) {
            url.searchParams.set('folder', folderId);
        } else {
            url.searchParams.delete('folder');
        }
        history.pushState({ folderId }, '', url);
    }

    _showLoading() {
        this.contentTarget.innerHTML = `
            <div class="mg-documents-empty">
                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                <p class="mt-2 text-muted" style="font-size: 0.85rem;">Se încarcă...</p>
            </div>`;
        if (this.hasStatsBarTarget) {
            this.statsBarTarget.innerHTML = '';
        }
    }

    _renderStats(folderCount, fileCount) {
        if (!this.hasStatsBarTarget) return;

        const parts = [];
        if (folderCount > 0) {
            parts.push(`<span class="mg-stats-item"><i class="fa fa-folder"></i> ${folderCount} ${this.labelStatsFoldersValue}</span>`);
        }
        if (fileCount > 0) {
            parts.push(`<span class="mg-stats-item"><i class="fa fa-file"></i> ${fileCount} ${this.labelStatsFilesValue}</span>`);
        }

        this.statsBarTarget.innerHTML = parts.join('');
    }

    _renderRootBreadcrumb() {
        const pageTitle = document.querySelector('.mg-page-title');
        const rootName = pageTitle ? pageTitle.textContent : 'Documente';
        this.breadcrumbTarget.innerHTML = `<span class="mg-breadcrumb-item active">${this._escapeHtml(rootName)}</span>`;
    }

    _renderBreadcrumb(items) {
        const pageTitle = document.querySelector('.mg-page-title');
        const rootName = pageTitle ? pageTitle.textContent : 'Documente';

        let html = `<span class="mg-breadcrumb-item">
            <a href="#" data-action="click->folder-manager#navigateToRoot">${this._escapeHtml(rootName)}</a>
            <i class="fa fa-chevron-right mx-1"></i>
        </span>`;

        items.forEach((item, index) => {
            if (index < items.length - 1) {
                html += `<span class="mg-breadcrumb-item">
                    <a href="#" data-action="click->folder-manager#selectFolder" data-folder-id="${item.id}">${this._escapeHtml(item.name)}</a>
                    <i class="fa fa-chevron-right mx-1"></i>
                </span>`;
            } else {
                html += `<span class="mg-breadcrumb-item active">${this._escapeHtml(item.name)}</span>`;
            }
        });
        this.breadcrumbTarget.innerHTML = html;
    }

    _renderFolderGrid(folders) {
        if (folders.length === 0) {
            this.contentTarget.innerHTML = `
                <div class="mg-documents-empty">
                    <i class="fa fa-folder-open fa-3x text-muted"></i>
                    <p class="mt-2 text-muted">${this.labelEmptyValue}</p>
                </div>`;
            return;
        }

        const canManage = this.canManageValue === '1';
        let html = '<table class="table table-hover mg-documents-table"><thead><tr>';
        html += `<th>${this.labelColNameValue}</th><th>${this.labelColSizeValue}</th><th>${this.labelColDateValue}</th><th>${this.labelColActionsValue}</th>`;
        html += '</tr></thead><tbody>';

        for (const folder of folders) {
            html += `<tr class="mg-folder-row" data-action="click->folder-manager#selectFolder" data-folder-id="${folder.id}" style="cursor:pointer;">
                <td>
                    <i class="${folder.icon || 'fa fa-folder'} me-2"></i>
                    ${this._escapeHtml(folder.name)}
                </td>
                <td></td>
                <td></td>
                <td class="mg-file-actions">`;

            if (canManage && folder.type === 'custom') {
                html += `<button class="mg-action-btn" data-action="click->folder-manager#renameFolder"
                            data-folder-id="${folder.id}" data-folder-name="${this._escapeHtml(folder.name)}"
                            data-bs-tooltip title="${this.labelRenameValue}"><i class="fa fa-pen"></i></button>
                    <button class="mg-action-btn" data-action="click->folder-manager#moveFolderAction"
                            data-folder-id="${folder.id}" data-folder-name="${this._escapeHtml(folder.name)}"
                            data-bs-tooltip title="${this.labelMoveFolderValue}"><i class="fa fa-arrows-alt"></i></button>
                    <button class="mg-action-btn mg-action-btn--danger" data-action="click->folder-manager#deleteFolder"
                            data-folder-id="${folder.id}" data-folder-name="${this._escapeHtml(folder.name)}"
                            data-bs-tooltip title="${this.labelDeleteFolderValue || this.labelBtnDeleteValue}"><i class="fa fa-trash"></i></button>`;
            }

            html += '</td></tr>';
        }

        html += '</tbody></table>';
        this.contentTarget.innerHTML = html;
        this._initTooltips();
    }

    _renderSubfoldersAndFiles(subfolders, files, folder) {
        this.contentTarget.innerHTML = this._buildTableHtml(subfolders, files, folder);
        this._initTooltips();
    }

    _renderFiles(files, folder) {
        if (files.length === 0) {
            const canUpload = folder.canUpload && this.canManageValue === '1';
            this.contentTarget.innerHTML = `
                <div class="mg-documents-empty">
                    <i class="fa ${canUpload ? 'fa-cloud-arrow-up' : 'fa-folder-open'} fa-3x"></i>
                    <p class="mt-2">${canUpload ? this.labelEmptyUploadValue : this.labelEmptyValue}</p>
                </div>`;
            return;
        }

        this.contentTarget.innerHTML = this._buildTableHtml([], files, folder);
        this._initTooltips();
    }

    _buildTableHtml(subfolders, files, folder) {
        const canManage = folder.canManageFiles && this.canManageValue === '1';

        let html = '<table class="table table-hover mg-documents-table"><thead><tr>';
        html += `<th>${this.labelColNameValue}</th><th>${this.labelColSizeValue}</th><th>${this.labelColDateValue}</th><th>${this.labelColActionsValue}</th>`;
        html += '</tr></thead><tbody>';

        // Subfolders as table rows first
        for (const sub of subfolders) {
            html += `<tr class="mg-folder-row" data-action="click->folder-manager#selectFolder" data-folder-id="${sub.id}" style="cursor:pointer;">
                <td>
                    <i class="${sub.icon || 'fa fa-folder'} me-2"></i>
                    ${this._escapeHtml(sub.name)}
                </td>
                <td></td>
                <td></td>
                <td class="mg-file-actions">`;

            const isManageableSub = sub.type === 'custom' || (sub.type === 'client' && !sub.hasSystemMapping);
            if (this.canManageValue === '1' && isManageableSub) {
                html += `<button class="mg-action-btn" data-action="click->folder-manager#renameFolder"
                            data-folder-id="${sub.id}" data-folder-name="${this._escapeHtml(sub.name)}"
                            data-bs-tooltip title="${this.labelRenameValue}"><i class="fa fa-pen"></i></button>
                    <button class="mg-action-btn" data-action="click->folder-manager#moveFolderAction"
                            data-folder-id="${sub.id}" data-folder-name="${this._escapeHtml(sub.name)}"
                            data-bs-tooltip title="${this.labelMoveFolderValue}"><i class="fa fa-arrows-alt"></i></button>
                    <button class="mg-action-btn mg-action-btn--danger" data-action="click->folder-manager#deleteFolder"
                            data-folder-id="${sub.id}" data-folder-name="${this._escapeHtml(sub.name)}"
                            data-bs-tooltip title="${this.labelDeleteFolderValue || this.labelBtnDeleteValue}"><i class="fa fa-trash"></i></button>`;
            }

            html += '</td></tr>';
        }

        for (const file of files) {
            const icon = this._getFileIcon(file.mimeType);
            html += `<tr>
                <td>
                    <i class="${icon} me-2"></i>
                    <a href="${file.viewUrl}" target="_blank">${this._escapeHtml(file.originalName)}</a>
                </td>
                <td>${file.formattedSize}</td>
                <td>${file.createdAt || ''}</td>
                <td class="mg-file-actions">
                    <a href="${file.viewUrl}" target="_blank" class="mg-action-btn" data-bs-tooltip title="${this.labelBtnViewValue}">
                        <i class="fa fa-eye"></i>
                    </a>
                    <a href="${file.downloadUrl}" class="mg-action-btn" data-bs-tooltip title="${this.labelBtnDownloadValue}">
                        <i class="fa fa-download"></i>
                    </a>`;

            if (file.entityUrl) {
                html += `
                    <a href="${file.entityUrl}" target="_blank" class="mg-action-btn" data-bs-tooltip title="${this.labelBtnEntityValue || 'Detalii entitate'}">
                        <i class="fa fa-external-link-alt"></i>
                    </a>`;
            }

            if (canManage && file.isCustom) {
                html += `
                    <button class="mg-action-btn" data-bs-tooltip title="${this.labelBtnMoveValue}"
                            data-action="click->folder-manager#moveFileAction"
                            data-file-id="${file.id}">
                        <i class="fa fa-arrows-alt"></i>
                    </button>
                    <button class="mg-action-btn mg-action-btn--danger" data-bs-tooltip title="${this.labelBtnDeleteValue}"
                            data-action="click->folder-manager#deleteFileAction"
                            data-file-id="${file.id}" data-file-name="${this._escapeHtml(file.originalName)}">
                        <i class="fa fa-trash"></i>
                    </button>`;
            }

            html += '</td></tr>';
        }

        html += '</tbody></table>';
        return html;
    }

    _renderPagination(currentPage, totalPages, folderId) {
        if (totalPages <= 1) {
            this.paginationTarget.innerHTML = '';
            return;
        }

        let html = '<nav><ul class="pagination pagination-sm justify-content-center">';

        for (let i = 1; i <= totalPages; i++) {
            const active = i === currentPage ? 'active' : '';
            html += `<li class="page-item ${active}">
                <a class="page-link" href="#" data-action="click->folder-manager#goToPage"
                   data-page="${i}" data-folder-id="${folderId}">${i}</a>
            </li>`;
        }

        html += '</ul></nav>';
        this.paginationTarget.innerHTML = html;
    }

    goToPage(event) {
        event.preventDefault();
        const page = parseInt(event.currentTarget.dataset.page);
        const folderId = event.currentTarget.dataset.folderId || this.currentFolderId;
        if (folderId) {
            this.loadFolderContents(folderId, page);
        }
    }

    _updateToolbar(folder) {
        this.toolbarTarget.style.display = 'flex';

        if (this.hasBtnNewFolderTarget) {
            this.btnNewFolderTarget.style.display = folder.canCreateSubfolder ? 'inline-flex' : 'none';
        }
        if (this.hasBtnUploadTarget) {
            this.btnUploadTarget.style.display = folder.canUpload ? 'inline-flex' : 'none';
        }
    }

    _updateToolbarForRoot() {
        const isCustom = this.folderTypeValue === 'custom';
        const canManage = this.canManageValue === '1';

        if (isCustom && canManage) {
            this.toolbarTarget.style.display = 'flex';
            if (this.hasBtnNewFolderTarget) {
                this.btnNewFolderTarget.style.display = 'inline-flex';
            }
            if (this.hasBtnUploadTarget) {
                this.btnUploadTarget.style.display = 'none';
            }
        } else {
            this.toolbarTarget.style.display = 'none';
        }
    }

    _getFileIcon(mimeType) {
        if (!mimeType) return 'fa fa-file';
        if (mimeType.includes('pdf')) return 'fa fa-file-pdf text-danger';
        if (mimeType.includes('word') || mimeType.includes('document')) return 'fa fa-file-word text-primary';
        if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'fa fa-file-excel text-success';
        if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return 'fa fa-file-powerpoint text-warning';
        if (mimeType.includes('image')) return 'fa fa-file-image text-info';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fa fa-file-zipper text-secondary';
        if (mimeType.includes('text') || mimeType.includes('csv')) return 'fa fa-file-lines text-muted';
        return 'fa fa-file';
    }

    async _swalConfirmMove(message) {
        const result = await Swal.fire({
            title: this.labelSwalTitleValue || 'Confirmare',
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#109B44',
            cancelButtonColor: '#6c757d',
            confirmButtonText: this.labelSwalConfirmMoveValue || 'Da, muta',
            cancelButtonText: this.labelSwalCancelValue || 'Anuleaza',
            reverseButtons: true,
        });
        return result.isConfirmed;
    }

    async _swalConfirm(message) {
        const result = await Swal.fire({
            title: this.labelSwalTitleValue || 'Confirmare',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: this.labelSwalConfirmValue || 'Da, sterge',
            cancelButtonText: this.labelSwalCancelValue || 'Anuleaza',
            reverseButtons: true,
        });
        return result.isConfirmed;
    }

    _swalToast(message, icon = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
        Toast.fire({ icon, title: message });
    }

    _initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        this.element.querySelectorAll('[data-bs-tooltip]').forEach(el => {
            const existing = window.bootstrap.Tooltip.getInstance(el);
            if (existing) existing.dispose();
            new window.bootstrap.Tooltip(el, { trigger: 'hover', container: 'body' });
        });
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
