import './bootstrap.js';
import '@coreui/coreui/dist/js/coreui.bundle.min.js';
import './currency-input';

$(function () {
    $('[data-toggle="tooltip"]').tooltip()
})

// ─── Sidebar: scroll persistence + scroll-to-active ──────────────────────────
// Active state and open dropdowns are already rendered server-side via
// request()->routeIs() in menu.blade.php, so we only need to:
//   1. Restore vertical scroll position across full-page navigations
//   2. Smooth-scroll the active item into view after load

const SIDEBAR_SCROLL_KEY = 'sidebarScrollTop';

function saveSidebarScroll() {
    const navEl = document.querySelector('#sidebar .c-sidebar-nav');
    if (navEl) {
        try { sessionStorage.setItem(SIDEBAR_SCROLL_KEY, navEl.scrollTop); } catch (e) {}
    }
}

window.addEventListener('beforeunload', saveSidebarScroll);

document.addEventListener('DOMContentLoaded', function () {
    const navEl = document.querySelector('#sidebar .c-sidebar-nav');

    // Restore sidebar scroll position saved before the last navigation
    if (navEl) {
        try {
            const saved = sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
            if (saved !== null) navEl.scrollTop = parseInt(saved, 10) || 0;
        } catch (e) {}
    }

    // Smooth-scroll the active item into view (wait 150ms for PerfectScrollbar init)
    setTimeout(function () {
        if (!navEl) return;
        const activeLink = navEl.querySelector('a.c-sidebar-nav-link.c-active');
        if (!activeLink) return;
        const navRect  = navEl.getBoundingClientRect();
        const itemRect = activeLink.getBoundingClientRect();
        if (itemRect.top < navRect.top || itemRect.bottom > navRect.bottom) {
            const targetScrollTop = navEl.scrollTop
                + (itemRect.top  - navRect.top)
                - (navEl.clientHeight / 2)
                + (itemRect.height / 2);
            navEl.scrollTo({ top: Math.max(0, targetScrollTop), behavior: 'smooth' });
        }
    }, 150);
});

