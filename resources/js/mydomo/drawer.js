/**
 * Общий drawer нового shell.
 *
 * Разметка — alpha-классы, поведение — своё: без jQuery, MDJAdminPanel и
 * Bootstrap-модалок. Содержимое передаётся DOM-узлом и возвращается на место
 * при закрытии.
 */

const host = document.getElementById('mdAdminDrawerHost');
const body = document.getElementById('mdAdminDrawerBody');
const footer = document.getElementById('mdAdminDrawerFooter');
const titleNode = document.getElementById('mdAdminDrawerTitle');
const subtitleNode = document.getElementById('mdAdminDrawerSubtitle');
const eyebrowNode = document.getElementById('mdAdminDrawerEyebrow');

let current = null;

function close(owner) {
    if (!current || (owner && current.owner !== owner)) {
        return false;
    }

    document.body.classList.remove('md-admin-drawer-open');
    host.setAttribute('aria-hidden', 'true');
    host.hidden = true;
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
    if (current.focus && current.focus.isConnected) {
        current.focus.focus();
    }
    current = null;

    return true;
}

function open(options) {
    if (!host || !body) {
        return false;
    }
    if (current) {
        close(current.owner);
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
