/**
 * Общий drawer нового shell.
 *
 * Разметка — alpha-классы, поведение — своё: без jQuery, MDJAdminPanel и
 * Bootstrap-модалок. Содержимое передаётся DOM-узлом и возвращается на место
 * при закрытии.
 * 2026-10-28 — панель остаётся в DOM для выезда; контент возвращается
 * после завершения закрытия, а не пропадает в начале анимации.
 */

const host = document.getElementById('mdAdminDrawerHost');
const body = document.getElementById('mdAdminDrawerBody');
const footer = document.getElementById('mdAdminDrawerFooter');
const titleNode = document.getElementById('mdAdminDrawerTitle');
const subtitleNode = document.getElementById('mdAdminDrawerSubtitle');
const eyebrowNode = document.getElementById('mdAdminDrawerEyebrow');

let current = null;
let closeTimer = null;

function finishClose() {
    if (!current) return;
    host.style.removeProperty('--md-admin-drawer-width');

    if (current.node && current.parent) {
        if (current.nextSibling && current.nextSibling.parentNode === current.parent) {
            current.parent.insertBefore(current.node, current.nextSibling);
        } else {
            current.parent.appendChild(current.node);
        }
        if (current.wasHidden) {
            current.node.hidden = true;
        }
    }
    body.replaceChildren();
    if (footer) {
        footer.replaceChildren();
        footer.hidden = true;
    }
    current = null;
    closeTimer = null;
}

function close(owner) {
    if (!current || (owner && current.owner !== owner)) return false;
    document.body.classList.remove('md-admin-drawer-open');
    host.setAttribute('aria-hidden', 'true');
    host.inert = true;
    if (current.focus?.isConnected) current.focus.focus();
    window.clearTimeout(closeTimer);
    closeTimer = window.setTimeout(finishClose, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 240);

    return true;
}

function open(options) {
    if (!host || !body) {
        return false;
    }
    if (current) {
        window.clearTimeout(closeTimer);
        finishClose();
    }

    const node = options.body;
    current = {
        owner: String(options.owner || 'drawer'),
        node,
        parent: node ? node.parentNode : null,
        nextSibling: node ? node.nextSibling : null,
        wasHidden: node ? node.hidden : false,
        focus: document.activeElement,
    };

    titleNode.textContent = options.title || '';
    const notificationsRefresh = host.querySelector('[data-md-notifications-refresh]');
    if (notificationsRefresh) {
        notificationsRefresh.hidden = current.owner !== 'module-notifications';
    }
    if (subtitleNode) {
        subtitleNode.textContent = options.subtitle || '';
        subtitleNode.hidden = !options.subtitle;
    }
    if (eyebrowNode) {
        eyebrowNode.textContent = options.eyebrow || '';
        eyebrowNode.hidden = !options.eyebrow;
    }
    body.replaceChildren();
    if (node) {
        node.hidden = false;
        body.appendChild(node);
    }
    if (footer) {
        footer.replaceChildren();
        footer.hidden = true;
    }
    if (options.width) {
        host.style.setProperty('--md-admin-drawer-width', options.width);
    }

    host.hidden = false;
    host.inert = false;
    host.setAttribute('aria-hidden', 'false');
    document.body.classList.add('md-admin-drawer-open');
    const closeButton = host.querySelector('[data-md-drawer-close]');
    if (closeButton) {
        closeButton.focus();
    }

    return true;
}

export function initDrawer() {
    if (!host) {
        return;
    }
    host.hidden = false;
    host.inert = true;
    document.querySelectorAll('.md-admin-drawer-backdrop[data-md-drawer-close]').forEach(element => { element.hidden = false; });
    host.querySelectorAll('[data-md-drawer-close]').forEach((button) => {
        button.addEventListener('click', () => close());
    });
    document.querySelectorAll('[data-md-drawer-close]').forEach((element) => {
        if (!host.contains(element)) {
            element.addEventListener('click', () => close());
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && current) {
            close();
        }
    });
}

export const drawer = {
    open,
    close,
    getCurrentOwner() {
        return current ? current.owner : '';
    },
};
