/**
 * Колокольчик и drawer уведомлений.
 *
 * Свой рендер на DOM, без alpha-модулей. Открытие списка не отмечает
 * прочитанным: только кнопка «Прочитано».
 * 2026-10-28 — загрузка панели с индикатором и защитой от устаревших ответов.
 */

import {get, post} from './api.js';
import {drawer} from './drawer.js';

const TYPE_ICONS = {
    info: 'info-circle',
    success: 'circle-check',
    warning: 'alert-triangle',
    danger: 'alert-circle',
};
const TYPE_LABELS = {
    info: 'Информация',
    success: 'Успешно',
    warning: 'Предупреждение',
    danger: 'Ошибка',
};

let offset = 0;
let filter = '';
let bound = false;
let drawerRequest = 0;

function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function internalUrl(value) {
    if (typeof value !== 'string') {
        return '';
    }
    try {
        const decoded = decodeURIComponent(value);
        return decoded.startsWith('/') && !decoded.startsWith('//') && !/[\\\x00-\x20\x7f]/.test(decoded) ? value : '';
    } catch (error) {
        return '';
    }
}

function content() {
    return document.getElementById('mdNotificationsContent');
}

function renderList(target, items, preview) {
    if (!target) {
        return;
    }
    target.replaceChildren();
    if (!items.length) {
        const template = content().querySelector('[data-md-notifications-empty]');
        target.append(template.content.cloneNode(true));
        return;
    }
    items.forEach((note) => {
        const type = Object.prototype.hasOwnProperty.call(TYPE_ICONS, note.type) ? note.type : 'info';
        const row = document.createElement('article');
        row.className = 'md-alert md-alert--' + type + ' md-notification';
        if (preview) {
            row.classList.add('md-notification--preview');
        }
        row.dataset.mdNotificationId = String(note.id);

        const icon = document.createElement('i');
        icon.className = 'ti ti-' + TYPE_ICONS[type] + ' md-alert__icon';
        icon.setAttribute('aria-hidden', 'true');
        row.append(icon);

        const body = document.createElement('div');
        body.className = 'md-alert__body';

        const meta = document.createElement('div');
        meta.className = 'md-notification__meta';
        const moduleLink = document.createElement('a');
        moduleLink.className = 'md-badge md-badge--outline';
        moduleLink.textContent = note.module_title || note.module;
        moduleLink.href = internalUrl(note.module_url) || '#';
        meta.append(moduleLink);
        const severity = document.createElement('span');
        severity.className = 'small';
        severity.textContent = TYPE_LABELS[type];
        meta.append(severity);
        const time = document.createElement('time');
        time.className = 'small';
        time.textContent = String(note.added || '');
        meta.append(time);
        body.append(meta);

        if (note.title) {
            const title = document.createElement('div');
            title.className = 'md-alert__title';
            title.textContent = note.title;
            body.append(title);
        }
        const message = document.createElement('div');
        message.className = 'md-alert__text md-notification__text';
        message.textContent = note.message;
        body.append(message);

        const actions = document.createElement('div');
        actions.className = 'md-alert__actions';
        const url = internalUrl(note.url);
        if (url && !preview) {
            const link = document.createElement('a');
            link.className = 'md-btn md-btn--secondary md-btn--sm';
            link.href = url;
            link.textContent = note.label || 'Подробнее';
            actions.append(link);
        }
        const read = document.createElement('button');
        read.type = 'button';
        read.className = preview ? 'md-icon-btn' : 'md-btn md-btn--ghost md-btn--sm';
        read.dataset.mdNotificationRead = String(note.id);
        read.setAttribute('aria-label', 'Отметить прочитанным: ' + (note.title || note.message));
        if (preview) {
            const readIcon = document.createElement('i');
            readIcon.className = 'ti ti-check';
            readIcon.setAttribute('aria-hidden', 'true');
            read.append(readIcon);
        } else {
            read.textContent = 'Прочитано';
        }
        actions.append(read);
        body.append(actions);
        row.append(body);
        target.append(row);
    });
}

function updateBadge(summary) {
    document.querySelectorAll('[data-md-notifications-open]').forEach((button) => {
        const badge = button.querySelector('[data-md-notifications-count]');
        button.setAttribute('aria-label', 'Уведомления: непрочитанных ' + summary.total);
        if (badge) {
            badge.textContent = summary.total > 99 ? '99+' : String(summary.total);
            badge.hidden = summary.total === 0;
        }
    });
}

async function refresh() {
    const contentNode = content();
    if (!contentNode) {
        return;
    }
    const drawerRefresh = drawer.getCurrentOwner() === 'module-notifications' ? loadDrawer() : Promise.resolve();
    try {
        const data = await get('/api/v1/panel/notifications');
        updateBadge(data);
        document.querySelectorAll('[data-md-notifications-preview]').forEach((target) => {
            renderList(target, (data.items || []).slice(0, 5), true);
        });
    } catch (error) {
        document.querySelectorAll('[data-md-notifications-preview]').forEach((target) => {
            target.textContent = 'Не удалось загрузить уведомления: ' + error.message;
        });
    } finally {
        await drawerRefresh;
    }
}

async function loadDrawer() {
    const contentNode = content();
    if (!contentNode || drawer.getCurrentOwner() !== 'module-notifications') {
        return;
    }
    const status = contentNode.querySelector('[data-md-notifications-status]');
    const list = contentNode.querySelector('[data-md-notifications-list]');
    const pages = contentNode.querySelector('[data-md-notifications-pages]');
    const range = contentNode.querySelector('[data-md-notifications-range]');
    const requestId = ++drawerRequest;
    const refreshButton = document.querySelector('[data-md-notifications-refresh]');
    const refreshIcon = refreshButton?.querySelector('i');
    const initial = !list.childElementCount || !!list.querySelector('.md-notifications-loading');
    list.setAttribute('aria-busy', 'true');
    status.hidden = true;
    if (initial) {
        list.replaceChildren();
        list.append(contentNode.querySelector('[data-md-notifications-loading]').content.cloneNode(true));
    }
    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.setAttribute('aria-busy', 'true');
    }
    refreshIcon?.classList.toggle('md-spin', !initial);
    contentNode.querySelectorAll('[data-md-notifications-page]').forEach(button => { button.disabled = true; });
    try {
        const data = await get('/api/v1/panel/notifications', {module: filter, offset});
        if (requestId !== drawerRequest || drawer.getCurrentOwner() !== 'module-notifications') return;
        if (!data || !Array.isArray(data.items)) throw new Error('Некорректный ответ сервера');
        renderList(list, data.items || [], false);
        if (status) {
            status.hidden = data.total === 0;
            status.textContent = 'Непрочитанных: ' + data.total;
        }
        if (pages) {
            pages.hidden = data.total <= 50;
        }
        if (range) {
            range.textContent = (data.total ? offset + 1 : 0) + '–' + (offset + (data.items || []).length) + ' из ' + data.total;
        }
        contentNode.querySelector('[data-md-notifications-page="-1"]').disabled = offset === 0;
        contentNode.querySelector('[data-md-notifications-page="1"]').disabled = offset + (data.items || []).length >= data.total;
    } catch (error) {
        if (requestId !== drawerRequest || drawer.getCurrentOwner() !== 'module-notifications') return;
        if (initial) list.replaceChildren();
        if (status) {
            status.textContent = 'Не удалось загрузить уведомления: ' + error.message;
            status.hidden = false;
        }
    } finally {
        if (requestId === drawerRequest) {
            list.setAttribute('aria-busy', 'false');
            if (refreshButton) {
                refreshButton.disabled = false;
                refreshButton.removeAttribute('aria-busy');
            }
            refreshIcon?.classList.remove('md-spin');
        }
    }
}

function open(module = '') {
    const contentNode = content();
    if (!contentNode) {
        return;
    }
    filter = typeof module === 'string' ? module : '';
    offset = 0;
    const status = contentNode.querySelector('[data-md-notifications-status]');
    if (status) {
        status.hidden = true;
        status.textContent = '';
    }
    const list = contentNode.querySelector('[data-md-notifications-list]');
    if (list) {
        list.replaceChildren();
    }
    const pages = contentNode.querySelector('[data-md-notifications-pages]');
    if (pages) {
        pages.hidden = true;
    }
    drawer.open({
        owner: 'module-notifications',
        title: 'Уведомления',
        subtitle: filter ? 'Модуль: ' + filter : 'Все модули',
        body: contentNode,
        width: '620px',
    });
    loadDrawer();
}

async function dismiss(id, button) {
    if (button) {
        button.disabled = true;
    }
    try {
        await post('/api/v1/panel/notifications/dismiss', {id: Number(id)});
        const node = document.querySelector('[data-md-notification-id="' + Number(id) + '"]');
        if (node) {
            node.remove();
        }
        await refresh();
    } catch (error) {
        if (button) {
            button.disabled = false;
        }
    }
}

export function initNotifications() {
    const contentNode = content();
    if (!contentNode) {
        return;
    }
    if (!bound) {
        bound = true;
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-md-notifications-open]');
            if (trigger) {
                event.preventDefault();
                open();
                return;
            }
            const read = event.target.closest('[data-md-notification-read]');
            if (read) {
                event.preventDefault();
                dismiss(read.dataset.mdNotificationRead, read);
                return;
            }
            const page = event.target.closest('[data-md-notifications-page]');
            if (page) {
                offset = Math.max(0, offset + Number(page.dataset.mdNotificationsPage) * 50);
                loadDrawer();
                return;
            }
            if (event.target.closest('[data-md-notifications-refresh]')) {
                refresh();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refresh();
            }
        });
        window.setInterval(() => {
            if (!document.hidden) {
                refresh();
            }
        }, 60000);
    }
    refresh();
}
