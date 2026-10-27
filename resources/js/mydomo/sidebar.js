/**
 * Мобильное меню: body.md-admin-sidebar-open, фон и Escape.
 */

export function initSidebar() {
    const toggle = document.querySelector('[data-md-admin-sidebar-toggle]');
    const sidebar = document.querySelector('.md-admin-sidebar');
    if (!toggle || !sidebar) {
        return;
    }
    const compact = window.matchMedia('(max-width: 991.98px)');

    const open = () => {
        document.body.classList.add('md-admin-sidebar-open');
        sidebar.inert = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Закрыть меню');
        sidebar.querySelector('a[href], button:not(:disabled)')?.focus();
    };
    const close = (restoreFocus = false) => {
        const wasOpen = document.body.classList.contains('md-admin-sidebar-open');
        document.body.classList.remove('md-admin-sidebar-open');
        sidebar.inert = compact.matches;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Открыть меню');
        if (restoreFocus && wasOpen) toggle.focus();
    };

    toggle.setAttribute('aria-expanded', 'false');
    sidebar.inert = compact.matches;
    compact.addEventListener('change', () => close());
    toggle.addEventListener('click', () => {
        if (document.body.classList.contains('md-admin-sidebar-open')) {
            close(true);
        } else {
            open();
        }
    });

    document.querySelectorAll('[data-md-sidebar-close]').forEach((element) => {
        element.addEventListener('click', () => close(true));
    });
    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (compact.matches) {
                close();
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('md-admin-sidebar-open')) {
            close(true);
        } else if (event.key === 'Tab' && document.body.classList.contains('md-admin-sidebar-open')) {
            const items = [...sidebar.querySelectorAll('a[href], button:not(:disabled)')].filter(item => item.getClientRects().length);
            if (!items.length) return;
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
            else if (!event.shiftKey && document.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
        }
    });
}
