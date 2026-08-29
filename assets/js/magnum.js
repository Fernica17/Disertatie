// Magnum ERP - Core UI interactions
// Handles: sidebar toggle, dropdowns, sidebar active state, filter modal TomSelect

import TomSelect from 'tom-select/dist/js/tom-select.complete.min';
import flatpickr from 'flatpickr';

import { Romanian } from 'flatpickr/dist/l10n/ro.js';

document.addEventListener('DOMContentLoaded', function () {

    // --- Sidebar Toggle (responsive) ---
    var sidebar = document.querySelector('.mg-sidebar');
    var overlay = document.querySelector('.mg-sidebar-overlay');
    var toggleBtn = document.querySelector('.mg-sidebar-toggle');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    // --- Sidebar submenu toggle ---
    document.querySelectorAll('.mg-nav-submenu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.mg-nav-submenu').classList.toggle('open');
        });
    });

    // --- Scroll active sidebar link into view (only if off-screen) ---
    var sidebarNav = document.querySelector('.mg-sidebar-nav');
    if (sidebarNav) {
        var activeLink = sidebarNav.querySelector('.mg-nav-link.active');
        if (activeLink) {
            var navRect = sidebarNav.getBoundingClientRect();
            var linkRect = activeLink.getBoundingClientRect();
            var isVisible = linkRect.top >= navRect.top && linkRect.bottom <= navRect.bottom;
            if (!isVisible) {
                var offset = activeLink.offsetTop - (sidebarNav.clientHeight / 2) + (activeLink.offsetHeight / 2);
                sidebarNav.scrollTop = Math.max(0, offset);
            }
        }
    }

    // --- User menu dropdown ---
    var userMenuToggle = document.querySelector('.mg-user-menu-toggle');
    var userMenuDropdown = document.querySelector('.mg-user-menu-dropdown');

    // --- Notifications dropdown ---
    var notifToggle = document.querySelector('.mg-notifications-toggle');
    var notifDropdown = document.querySelector('.mg-notifications-dropdown');

    if (notifToggle && notifDropdown) {
        notifToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
            notifToggle.classList.toggle('active');
            // Close user menu if open
            if (userMenuDropdown) userMenuDropdown.classList.remove('open');
            if (userMenuToggle) userMenuToggle.classList.remove('active');
        });
    }

    if (userMenuToggle && userMenuDropdown) {
        userMenuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('open');
            userMenuToggle.classList.toggle('active');
            // Close notifications if open
            if (notifDropdown) notifDropdown.classList.remove('open');
            if (notifToggle) notifToggle.classList.remove('active');
        });
    }

    // --- Close dropdowns on outside click ---
    document.addEventListener('click', function (e) {
        // Close user menu
        if (userMenuDropdown && !userMenuDropdown.contains(e.target) && userMenuToggle && !userMenuToggle.contains(e.target)) {
            userMenuDropdown.classList.remove('open');
            userMenuToggle.classList.remove('active');
        }

        // Close notifications
        if (notifDropdown && !notifDropdown.contains(e.target) && notifToggle && !notifToggle.contains(e.target)) {
            notifDropdown.classList.remove('open');
            notifToggle.classList.remove('active');
        }

        // Close other dropdowns
        document.querySelectorAll('.mg-dropdown-menu.show').forEach(function (menu) {
            if (!menu.parentElement.contains(e.target)) {
                menu.classList.remove('show');
            }
        });
    });

    document.querySelectorAll('.mg-dropdown-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.mg-dropdown-menu.show').forEach(function (menu) {
                if (menu !== btn.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });
            var menu = this.nextElementSibling;
            if (menu) menu.classList.toggle('show');
        });
    });

    // --- Scroll to first validation error ---
    const firstError = document.querySelector('.mg-form-error li, .invalid-feedback, .form-error-message');
    if (firstError) {
        const errorField = firstError.closest('.mg-settings-field, .mb-3, .form-group') || firstError;
        errorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // --- Global double-submit guard ---
    // Blocks a second submit of the same form while the first is still in flight.
    // Disables submit buttons, shows a spinner and rewrites the label.
    // Opt-out per form: add data-allow-resubmit to the <form>.
    // Opt-out per button: add data-no-loading-text to the <button>.
    (function initDoubleSubmitGuard() {
        const SUBMITTED_ATTR = 'submitted';
        const SAFETY_TIMEOUT_MS = 15000;

        function disableSubmitButtons(form) {
            const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            buttons.forEach(function (btn) {
                if (btn.disabled) return;
                btn.disabled = true;
                btn.classList.add('is-submitting');
                if (btn.tagName === 'BUTTON' && !btn.dataset.originalHtml && !btn.hasAttribute('data-no-loading-text')) {
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> ' + (btn.dataset.loadingText || 'Se salvează...');
                }
            });
            return buttons;
        }

        function resetForm(form) {
            delete form.dataset[SUBMITTED_ATTR];
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                btn.disabled = false;
                btn.classList.remove('is-submitting');
                if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
                }
            });
        }

        // Capture phase so we run before any other submit listeners (Turbo, ajax-modal, etc.)
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.hasAttribute('data-allow-resubmit')) return;

            if (form.dataset[SUBMITTED_ATTR] === '1') {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            form.dataset[SUBMITTED_ATTR] = '1';

            // Disable AFTER this tick so the button value is included in the submission
            // and so we don't cancel the browser's native submit.
            setTimeout(function () {
                disableSubmitButtons(form);
            }, 0);

            // Safety net: if the navigation/fetch never completes (e.g. network error,
            // JS exception, validation re-render failure) re-enable after N seconds.
            setTimeout(function () {
                if (form.isConnected && form.dataset[SUBMITTED_ATTR] === '1') {
                    resetForm(form);
                }
            }, SAFETY_TIMEOUT_MS);
        }, true);

        // Turbo integration: when Turbo finishes a submission (success or failure),
        // reset the form so the user can re-edit + re-submit on validation errors.
        document.addEventListener('turbo:submit-end', function (event) {
            const form = event.target;
            if (form instanceof HTMLFormElement) {
                resetForm(form);
            }
        });

        // When Turbo caches the page (bfcache / turbo cache), reset any stuck flags
        // so that going back/forward doesn't show a frozen button.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('form[data-submitted="1"]').forEach(resetForm);
        });
    })();

    // --- TomSelect on filter modal comparison selects ---
    // EasyAdmin only initializes TomSelect on [data-ea-widget="ea-autocomplete"].
    // The comparison selects ("este"/"nu este") are plain <select> — we init them here.
    const filterModal = document.getElementById('modal-filters');
    if (filterModal) {
        const observer = new MutationObserver(function () {
            for (const select of filterModal.querySelectorAll('.filter-content select:not(.tomselected):not([data-ea-widget])')) {
                // eslint-disable-next-line no-new
                new TomSelect(select, {
                    maxOptions: null,
                    allowEmptyOption: true,
                    controlInput: null,
                });
            }
        });
        // Also init flatpickr on date inputs inside the filter modal
        const dateObserver = new MutationObserver(function () {
            for (const input of filterModal.querySelectorAll('.filter-content input[type="date"], .filter-content input[type="datetime-local"]')) {
                if (input._flatpickr) continue;
                input.type = 'text';
                flatpickr(input, {
                    locale: Romanian,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd.m.Y',
                    allowInput: true,
                });
            }
        });
        dateObserver.observe(filterModal.querySelector('.modal-body') || filterModal, {
            childList: true,
            subtree: true,
        });

        observer.observe(filterModal.querySelector('.modal-body') || filterModal, {
            childList: true,
            subtree: true,
        });
    }

});
