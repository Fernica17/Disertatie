/**
 * Tab hash navigation for EasyAdmin.
 * Activates the correct tab from URL hash and updates hash on tab click.
 * Works on both detail and form (edit/new) pages.
 */
export function initTabHash() {
    const hash = window.location.hash;

    // Activate tab from URL hash on page load
    if (hash) {
        const tabLink = document.querySelector(`.nav-tabs a.nav-link[href="${hash}"]`);
        if (tabLink) {
            const bsTab = new bootstrap.Tab(tabLink);
            bsTab.show();
        }
    }

    // Update URL hash when clicking on tabs
    document.querySelectorAll('.nav-tabs a.nav-link[data-bs-toggle="tab"]').forEach(function(tab) {
        tab.addEventListener('shown.bs.tab', function(event) {
            const tabId = event.target.getAttribute('href');
            if (tabId) {
                history.replaceState(null, '', tabId);
            }
        });
    });
}
