import { Controller } from '@hotwired/stimulus';

/**
 * Test detail page actions: generate proposal, confirm consumptions, delete consumption.
 * Usage:
 *   <div data-controller="test-detail"
 *        data-test-detail-csrf-value="..."
 *        data-test-detail-test-id-value="..."
 *        data-test-detail-labels-value='{"title":"...","generate":"...","confirm":"...","deleteText":"...","yesContinue":"...","yesDelete":"...","cancel":"..."}'>
 */
export default class extends Controller {
    static values = {
        csrf: String,
        testId: Number,
        labels: Object,
    };

    generateProposal() {
        const labels = this.labelsValue;

        Swal.fire({
            title: labels.title,
            text: labels.generate,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#109B44',
            cancelButtonColor: '#6c757d',
            confirmButtonText: labels.yesContinue,
            cancelButtonText: labels.cancel,
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            this._postAction('/admin/tests/' + this.testIdValue + '/generate-proposal', 'consumptions');
        });
    }

    confirmConsumptions() {
        const labels = this.labelsValue;

        Swal.fire({
            title: labels.title,
            text: labels.confirm,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#109B44',
            cancelButtonColor: '#6c757d',
            confirmButtonText: labels.yesContinue,
            cancelButtonText: labels.cancel,
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            this._postAction('/admin/tests/' + this.testIdValue + '/confirm-consumptions', 'consumptions');
        });
    }

    generateBulletin() {
        const labels = this.labelsValue;

        Swal.fire({
            title: labels.title,
            text: labels.generateBulletin,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#109B44',
            cancelButtonColor: '#6c757d',
            confirmButtonText: labels.yesContinue,
            cancelButtonText: labels.cancel,
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            this._postAction('/admin/tests/' + this.testIdValue + '/generate-bulletin', 'documents');
        });
    }

    deleteConsumption(event) {
        const consumptionId = event.currentTarget.dataset.consumptionId;
        const labels = this.labelsValue;

        Swal.fire({
            title: labels.title,
            text: labels.deleteText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: labels.yesDelete,
            cancelButtonText: labels.cancel,
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            this._postAction('/admin/tests/consumption/' + consumptionId + '/delete', 'consumptions');
        });
    }

    _postAction(url, hashTab) {
        const errorTitle = this.labelsValue.errorTitle;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfValue,
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.reload) {
                window.location.hash = '#' + hashTab;
                window.location.reload();
            } else {
                Swal.fire({ icon: 'error', title: errorTitle, text: data.message });
            }
        })
        .catch(err => Swal.fire({ icon: 'error', title: errorTitle, text: err.message }));
    }
}
