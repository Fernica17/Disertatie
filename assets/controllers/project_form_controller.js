import FormSelectController from './form_select_controller';

export default class extends FormSelectController {
    static targets = ['contract', 'client'];

    connect() {
        super.connect();
        this._syncClientLock();
    }

    // Called when contract select changes
    contractChanged() {
        const clientId = this._getClientIdFromSelect(this.contractTarget);

        if (clientId) {
            this._setClientValue(clientId);
            this._lockClient();
        } else {
            this._unlockClient();
        }
    }

    // On page load, lock client if contract is set
    _syncClientLock() {
        if (this.contractTarget.value) {
            this._lockClient();
        }
    }

    _getClientIdFromSelect(selectEl) {
        const val = selectEl.value;
        if (!val) return null;

        const option = selectEl.querySelector('option[value="' + val + '"]');
        return option ? option.getAttribute('data-client-id') : null;
    }

    _setClientValue(clientId) {
        const ts = this._getClientTomSelect();
        if (ts) {
            ts.setValue(clientId, true);
        } else {
            this.clientTarget.value = clientId;
        }
    }

    _lockClient() {
        const ts = this._getClientTomSelect();
        if (ts) {
            ts.lock();
        } else {
            this.clientTarget.readOnly = true;
        }
    }

    _unlockClient() {
        const ts = this._getClientTomSelect();
        if (ts) {
            ts.unlock();
        } else {
            this.clientTarget.readOnly = false;
        }
    }

    _getClientTomSelect() {
        return this.tomInstances.find(ts => ts.input === this.clientTarget) || null;
    }
}
