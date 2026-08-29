// Admin entry point
import '../styles/admin.scss';
import '../bootstrap';
import './magnum';
import { initializeAjaxModals } from './ajax-modal';
import { initPasswordStrength } from './password-strength';
import { initTabHash } from './tab-hash';
import Swal from 'sweetalert2';

window.Swal = Swal;

document.addEventListener('DOMContentLoaded', function() {
    initializeAjaxModals();
    initPasswordStrength();
    initTabHash();
    initDeleteTooltips();
    hideExportOnEmptyResults();
});

/**
 * Bootstrap tooltips on delete buttons that already use data-bs-toggle="modal".
 * Since data-bs-toggle can only hold one value, we initialize tooltips via JS
 * using the existing title attribute.
 */
function initDeleteTooltips() {
    document.querySelectorAll('[data-bs-toggle="modal"][title]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
}

/**
 * Hide the export button when the datagrid has no results.
 */
function hideExportOnEmptyResults() {
    if (!document.querySelector('table.datagrid-empty')) return;

    const exportBtn = document.querySelector('.global-actions a.btn[href*="export"]');
    if (exportBtn) {
        exportBtn.style.display = 'none';
    }
}
