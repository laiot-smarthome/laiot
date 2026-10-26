/**
 * Мобильное меню: body.md-admin-sidebar-open, фон и Escape.
 */

export function initSidebar() {
    const toggle = document.querySelector('[data-md-admin-sidebar-toggle]');
    const sidebar = document.querySelector('.md-admin-sidebar');
    if (!toggle || !sidebar) {
        return;
    }

    const open = () => {
        document.body.classList.add('md-admin-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
        document.body.classList.remove('md-admin-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', () => {
        if (document.body.classList.contains('md-admin-sidebar-open')) {
            close();
        } else {
            open();
        }
    });

    document.querySelectorAll('[data-md-sidebar-close]').forEach((element) => {
        element.addEventListener('click', close);
    });
    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 991px)').matches) {
                close();
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}
