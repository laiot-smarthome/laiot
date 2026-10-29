/**
 * Боковое меню: мобильный drawer, компактный режим на ПК и память
 * раскрытых групп.
 *
 * 2026-10-27 — группы и каталоги меню стали <details>/<summary>: аккордеон
 * работает без JavaScript, а этот модуль только запоминает состояние.
 * На ПК кнопка в шапке сворачивает панель до иконок, на телефоне — открывает
 * drawer; раньше на ПК кнопка ничего не делала.
 */

const COMPACT_KEY = 'laiot.sidebar.compact';
const OPEN_KEY = 'laiot.sidebar.open';

function readJson(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
}

function writeJson(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        /* Приватный режим и запрет storage не должны ломать навигацию. */
    }
}

function readFlag(key) {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch (error) {
        return false;
    }
}

function writeFlag(key, enabled) {
    try {
        window.localStorage.setItem(key, enabled ? '1' : '0');
    } catch (error) {
        /* см. writeJson */
    }
}

/** Состояние групп меню: активная ветка открыта всегда, остальное — из памяти. */
function initAccordion() {
    const nav = document.querySelector('.md-nav');
    if (!nav) {
        return;
    }
    const details = [...nav.querySelectorAll('details[data-md-nav-key]')];
    if (!details.length) {
        return;
    }
    const stored = readJson(OPEN_KEY, null);
    if (Array.isArray(stored)) {
        details.forEach((node) => {
            const key = node.getAttribute('data-md-nav-key');
            if (!key) {
                return;
            }
            // Активный раздел нельзя спрятать: иначе после перехода по ссылке
            // меню выглядит так, будто текущая страница к нему не относится.
            const activeBranch = node.classList.contains('is-active') || Boolean(node.querySelector('.is-active'));
            node.open = activeBranch || stored.includes(key);
        });
    }

    let timer = 0;
    const remember = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            // В компактном режиме все ветки раскрыты принудительно — это не
            // выбор пользователя, и запоминать его нельзя.
            if (document.body.classList.contains('md-sidebar-compact')) {
                return;
            }
            const keys = details
                .filter((node) => node.open)
                .map((node) => node.getAttribute('data-md-nav-key'))
                .filter((key) => typeof key === 'string' && key !== '');
            writeJson(OPEN_KEY, keys);
        }, 120);
    };
    details.forEach((node) => node.addEventListener('toggle', remember));
}

/**
 * Компактная панель показывает только иконки, поэтому все ветки должны быть
 * раскрыты: иначе каталог остался бы закрытым без видимого заголовка.
 * Прежнее состояние возвращается при разворачивании панели.
 */
function setCompactDetails(enabled) {
    document.querySelectorAll('.md-nav details[data-md-nav-key]').forEach((node) => {
        if (enabled) {
            if (!node.hasAttribute('data-md-was-open')) {
                node.setAttribute('data-md-was-open', node.open ? '1' : '0');
            }
            node.open = true;
            return;
        }
        if (node.hasAttribute('data-md-was-open')) {
            node.open = node.getAttribute('data-md-was-open') === '1';
            node.removeAttribute('data-md-was-open');
        }
    });
}

export function initSidebar() {
    const toggle = document.querySelector('[data-md-admin-sidebar-toggle]');
    const sidebar = document.querySelector('.md-admin-sidebar');
    if (!toggle || !sidebar) {
        return;
    }
    initAccordion();

    const mobile = window.matchMedia('(max-width: 991.98px)');

    /** Компактный режим на ПК: панель остаётся на месте, но уже. */
    const setCompact = (enabled, persist) => {
        document.body.classList.toggle('md-sidebar-compact', enabled);
        setCompactDetails(enabled);
        toggle.setAttribute('aria-label', enabled ? 'Развернуть меню' : 'Свернуть меню');
        toggle.setAttribute('aria-expanded', enabled ? 'false' : 'true');
        if (persist) {
            writeFlag(COMPACT_KEY, enabled);
        }
    };

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
        sidebar.inert = mobile.matches;
        if (!mobile.matches) {
            setCompact(document.body.classList.contains('md-sidebar-compact'), false);
        } else {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Открыть меню');
        }
        if (restoreFocus && wasOpen) {
            toggle.focus();
        }
    };

    const syncViewport = () => {
        document.body.classList.remove('md-admin-sidebar-open');
        sidebar.inert = mobile.matches;
        if (mobile.matches) {
            // Компактный режим — только для ПК: на телефоне возвращаем
            // обычный аккордеон, но выбор пользователя не забываем.
            document.body.classList.remove('md-sidebar-compact');
            setCompactDetails(false);
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Открыть меню');
            return;
        }
        setCompact(readFlag(COMPACT_KEY), false);
    };

    syncViewport();
    mobile.addEventListener('change', syncViewport);
    toggle.addEventListener('click', () => {
        if (mobile.matches) {
            if (document.body.classList.contains('md-admin-sidebar-open')) {
                close(true);
            } else {
                open();
            }
            return;
        }
        setCompact(!document.body.classList.contains('md-sidebar-compact'), true);
    });

    document.querySelectorAll('[data-md-sidebar-close]').forEach((element) => {
        element.addEventListener('click', () => close(true));
    });
    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobile.matches) {
                close();
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('md-admin-sidebar-open')) {
            close(true);
        } else if (event.key === 'Tab' && document.body.classList.contains('md-admin-sidebar-open')) {
            const items = [...sidebar.querySelectorAll('a[href], button:not(:disabled), summary')]
                .filter((item) => item.getClientRects().length);
            if (!items.length) {
                return;
            }
            if (event.shiftKey && document.activeElement === items[0]) {
                event.preventDefault();
                items[items.length - 1].focus();
            } else if (!event.shiftKey && document.activeElement === items[items.length - 1]) {
                event.preventDefault();
                items[0].focus();
            }
        }
    });
}
