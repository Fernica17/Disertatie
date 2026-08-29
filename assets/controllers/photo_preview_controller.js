import { Controller } from '@hotwired/stimulus';

/**
 * Live preview and removal for the user reference photo.
 *
 * The file input and the "remove photo" checkbox are separate rows rendered by
 * EasyAdmin, so the controller looks them up inside the surrounding form by the
 * suffix Symfony gives their names. Removal only ticks the hidden checkbox —
 * nothing is deleted until the form is actually saved, so navigating away
 * leaves the photo untouched.
 */
export default class extends Controller {
    static targets = ['frame', 'image', 'initials', 'removeButton', 'undoButton', 'pending'];

    connect() {
        this.form = this.element.closest('form');

        if (!this.form) {
            return;
        }

        this.input = this.form.querySelector('input[type="file"][name$="[photoUpload]"]');
        this.removeField = this.form.querySelector('input[type="checkbox"][name$="[removePhoto]"]');

        // The checkbox is driven by the red button, so it should not show twice.
        this.hideCheckboxRow();

        this.originalSrc = this.hasImageTarget ? this.imageTarget.getAttribute('src') : '';
        this.hadPhoto = Boolean(this.originalSrc);

        this.onFileChange = this.onFileChange.bind(this);
        this.input?.addEventListener('change', this.onFileChange);
    }

    disconnect() {
        this.input?.removeEventListener('change', this.onFileChange);
        this.revokePreview();
    }

    onFileChange() {
        const file = this.input?.files?.[0];

        if (!file) {
            this.restoreOriginal();

            return;
        }

        // A new file supersedes a pending removal: it replaces the photo anyway.
        this.clearPendingRemoval();

        this.revokePreview();
        this.previewUrl = URL.createObjectURL(file);
        this.imageTarget.setAttribute('src', this.previewUrl);
        this.frameTarget.hidden = false;
        this.toggleInitials(false);

        // Let the user drop the newly picked file and go back to what was stored.
        this.toggleButton(this.undoButton, true);
        this.toggleButton(this.removeButton, this.hadPhoto);
    }

    /** Marks the stored photo for deletion; applied when the form is saved. */
    remove(event) {
        event.preventDefault();

        if (this.removeField) {
            this.removeField.checked = true;
        }

        this.clearFileInput();
        this.revokePreview();

        this.frameTarget.hidden = true;
        this.toggleInitials(true);
        this.pendingTarget.hidden = false;
        this.toggleButton(this.removeButton, false);
        this.toggleButton(this.undoButton, true);
    }

    /** Cancels either a pending removal or a freshly picked file. */
    undo(event) {
        event.preventDefault();

        this.clearPendingRemoval();
        this.clearFileInput();
        this.restoreOriginal();
    }

    restoreOriginal() {
        this.revokePreview();

        if (this.hadPhoto) {
            this.imageTarget.setAttribute('src', this.originalSrc);
            this.frameTarget.hidden = false;
            this.toggleInitials(false);
        } else {
            this.imageTarget.removeAttribute('src');
            this.frameTarget.hidden = true;
            this.toggleInitials(true);
        }

        this.toggleButton(this.removeButton, this.hadPhoto);
        this.toggleButton(this.undoButton, false);
    }

    clearPendingRemoval() {
        if (this.removeField) {
            this.removeField.checked = false;
        }

        this.pendingTarget.hidden = true;
    }

    clearFileInput() {
        if (this.input) {
            this.input.value = '';
        }
    }

    revokePreview() {
        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = null;
        }
    }

    hideCheckboxRow() {
        const row = this.removeField?.closest('.form-group, .form-widget, .field-boolean');

        if (row) {
            row.hidden = true;
        }
    }

    /** The remove button is absent when the user has no stored photo. */
    toggleButton(button, visible) {
        if (button) {
            button.hidden = !visible;
        }
    }

    /** The initials placeholder only exists when the user has no stored photo. */
    toggleInitials(visible) {
        if (this.hasInitialsTarget) {
            this.initialsTarget.hidden = !visible;
        }
    }

    /** Stimulus throws on a missing target, so resolve optional ones safely. */
    get removeButton() {
        return this.hasRemoveButtonTarget ? this.removeButtonTarget : null;
    }

    get undoButton() {
        return this.hasUndoButtonTarget ? this.undoButtonTarget : null;
    }
}
