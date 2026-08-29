import { Controller } from '@hotwired/stimulus';

/**
 * File upload with drag & drop, preview, and removal of existing attachments.
 * Usage:
 *   <div data-controller="file-upload">
 *       <input type="file" data-file-upload-target="input" multiple>
 *       <label data-file-upload-target="dropArea" data-action="dragenter->file-upload#dragEnter dragover->file-upload#dragOver dragleave->file-upload#dragLeave drop->file-upload#drop">
 *       <div data-file-upload-target="fileList"></div>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'dropArea', 'fileList'];
    static values = {
        maxSize: { type: Number, default: 10485760 }, // 10 MB
        allowedTypes: { type: Array, default: ['application/pdf', 'image/jpeg', 'image/png'] },
    };

    connect() {
        this.selectedFiles = new DataTransfer();
        this.inputTarget.addEventListener('change', () => {
            this._addFiles(this.inputTarget.files);
            this.inputTarget.value = '';
        });
    }

    dragEnter(e) { e.preventDefault(); this.dropAreaTarget.classList.add('dragover'); }
    dragOver(e) { e.preventDefault(); this.dropAreaTarget.classList.add('dragover'); }
    dragLeave(e) { e.preventDefault(); this.dropAreaTarget.classList.remove('dragover'); }

    drop(e) {
        e.preventDefault();
        this.dropAreaTarget.classList.remove('dragover');
        this._addFiles(e.dataTransfer.files);
    }

    removeFile(event) {
        const idx = parseInt(event.currentTarget.dataset.idx);
        const dt = new DataTransfer();
        Array.from(this.selectedFiles.files).forEach((f, i) => {
            if (i !== idx) dt.items.add(f);
        });
        this.selectedFiles = dt;
        this.inputTarget.files = this.selectedFiles.files;
        this._renderFileList();
    }

    toggleRemoveExisting(event) {
        const attachmentId = event.currentTarget.dataset.attachmentId;
        const item = document.getElementById('existing-attachment-' + attachmentId);
        const existingInput = document.querySelector('input[name="removeAttachments[]"][value="' + attachmentId + '"]');

        if (existingInput) {
            existingInput.remove();
            item.style.opacity = '1';
            item.style.textDecoration = 'none';
            event.currentTarget.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removeAttachments[]';
            input.value = attachmentId;
            item.closest('form').appendChild(input);
            item.style.opacity = '0.4';
            item.style.textDecoration = 'line-through';
            event.currentTarget.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
        }
    }

    _addFiles(files) {
        Array.from(files).forEach((file) => {
            if (!this.allowedTypesValue.includes(file.type)) {
                alert('Tip fișier neacceptat: ' + file.name + '. Doar PDF, JPG, PNG.');
                return;
            }
            if (file.size > this.maxSizeValue) {
                alert('Fișierul ' + file.name + ' depășește 10 MB.');
                return;
            }
            this.selectedFiles.items.add(file);
        });
        this.inputTarget.files = this.selectedFiles.files;
        this._renderFileList();
    }

    _renderFileList() {
        this.fileListTarget.innerHTML = '';
        Array.from(this.selectedFiles.files).forEach((file, idx) => {
            const iconClass = file.type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image';
            const sizeStr = file.size < 1024 * 1024
                ? (file.size / 1024).toFixed(0) + ' KB'
                : (file.size / (1024 * 1024)).toFixed(1) + ' MB';

            const div = document.createElement('div');
            div.className = 'of-file-item';
            div.innerHTML =
                '<i class="fa-solid ' + iconClass + '"></i>' +
                '<span class="of-file-name">' + file.name + '</span>' +
                '<span class="of-file-size">' + sizeStr + '</span>' +
                '<button type="button" class="of-file-remove" data-idx="' + idx + '" data-action="click->file-upload#removeFile" title="Elimină"><i class="fa-solid fa-xmark"></i></button>';
            this.fileListTarget.appendChild(div);
        });
    }
}
