import FormSelectController from './form_select_controller';

export default class extends FormSelectController {
    static targets = ['contract', 'project', 'client'];

    connect() {
        super.connect();
        this._syncClientLock();
        this._syncInitialContract();
    }

    // On page load, if a project is pre-selected and contract is empty,
    // auto-fill the contract from the project's data-contract-id.
    _syncInitialContract() {
        if (!this.projectTarget.value) {
            return;
        }
        if (!this.contractTarget.value) {
            const contractId = this._getContractIdFromSelect(this.projectTarget);
            if (contractId) {
                this._setContractValue(contractId);
            }
        }
        this._lockContract();
    }

    // Called when contract select changes
    contractChanged() {
        const clientId = this._getClientIdFromSelect(this.contractTarget);

        if (clientId) {
            this._setClientValue(clientId);
            this._lockClient();
        } else if (!this._getClientIdFromSelect(this.projectTarget)) {
            this._unlockClient();
        }
    }

    // Called when project select changes
    projectChanged() {
        const clientId = this._getClientIdFromSelect(this.projectTarget);
        const contractId = this._getContractIdFromSelect(this.projectTarget);

        if (clientId) {
            this._setClientValue(clientId);
            this._lockClient();
        } else if (!this._getClientIdFromSelect(this.contractTarget)) {
            this._unlockClient();
        }

        if (this.projectTarget.value) {
            if (contractId) {
                this._setContractValue(contractId);
            } else {
                this._clearContract();
            }
            this._lockContract();
        } else {
            this._unlockContract();
        }
    }

    // On page load, lock client if contract or project is set
    _syncClientLock() {
        if (this.contractTarget.value || this.projectTarget.value) {
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

    _getContractIdFromSelect(selectEl) {
        const val = selectEl.value;
        if (!val) return null;
        const option = selectEl.querySelector('option[value="' + val + '"]');
        return option ? option.getAttribute('data-contract-id') : null;
    }

    _setContractValue(contractId) {
        const ts = this._getContractTomSelect();
        if (ts) {
            ts.setValue(contractId, true);
        } else {
            this.contractTarget.value = contractId;
        }
    }

    _clearContract() {
        const ts = this._getContractTomSelect();
        if (ts) {
            ts.clear(true);
        } else {
            this.contractTarget.value = '';
        }
    }

    _lockContract() {
        const ts = this._getContractTomSelect();
        if (ts) {
            ts.lock();
        } else {
            this.contractTarget.readOnly = true;
        }
    }

    _unlockContract() {
        const ts = this._getContractTomSelect();
        if (ts) {
            ts.unlock();
        } else {
            this.contractTarget.readOnly = false;
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

    _getContractTomSelect() {
        return this.tomInstances.find(ts => ts.input === this.contractTarget) || null;
    }
}
