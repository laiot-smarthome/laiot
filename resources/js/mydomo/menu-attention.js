/**
 * Живые счётчики бокового меню.
 *
 * 2026-10-27 — бейджи ошибок, очереди и уведомлений обновляются без
 * перезагрузки: панель опрашивает `/api/v1/panel/attention`, пока вкладка
 * активна. Это виджет оболочки, поэтому отдельный JSON-роут допустим; данные
 * для страниц по-прежнему считает сервер.
 */

import {get} from './api.js';

const POLL_MS = 90000;
const TONES = ['info', 'success', 'warning', 'danger'];
const RANKS = {info: 0, success: 0, warning: 1, danger: 2};

function label(count) {
    return count > 99 ? '99+' : String(count);
}

function applyGroups() {
    document.querySelectorAll('[data-md-attention-group]').forEach((node) => {
        const scope = node.closest('[data-md-nav-scope]');
        if (!scope) {
            return;
        }
        let count = 0;
        let tone = 'info';
        scope.querySelectorAll('[data-md-attention]').forEach((badge) => {
            if (badge.hidden) {
                return;
            }
            count += Number(badge.getAttribute('data-md-count')) || 0;
            const itemTone = badge.getAttribute('data-md-tone') || 'info';
            if ((RANKS[itemTone] || 0) > (RANKS[tone] || 0)) {
                tone = itemTone;
            }
        });
        node.setAttribute('data-md-count', String(count));
        node.setAttribute('data-md-tone', tone);
        node.textContent = label(count);
        node.hidden = count === 0;
        node.title = count > 0 ? 'Требует внимания: ' + count : '';
        TONES.forEach((name) => node.classList.toggle('md-nav__badge--' + name, name === tone));
    });
}

function applyBadges(badges) {
    if (!badges || typeof badges !== 'object') {
        return;
    }
    document.querySelectorAll('[data-md-attention]').forEach((node) => {
        const key = node.getAttribute('data-md-attention');
        if (!key || !Object.prototype.hasOwnProperty.call(badges, key)) {
            return;
        }
        const badge = badges[key] && typeof badges[key] === 'object' ? badges[key] : {};
        const count = Math.max(0, Number(badge.count) || 0);
        const tone = TONES.includes(badge.tone) ? badge.tone : 'info';
        node.setAttribute('data-md-count', String(count));
        node.setAttribute('data-md-tone', tone);
        node.textContent = label(count);
        node.hidden = count === 0;
        node.title = typeof badge.title === 'string' ? badge.title : '';
        TONES.forEach((name) => node.classList.toggle('md-nav__badge--' + name, name === tone));
    });
    applyGroups();
}

async function refresh() {
    try {
        const data = await get('/api/v1/panel/attention');
        applyBadges(data ? data.badges : null);
    } catch (error) {
        /* Панель не должна шуметь, если счётчики временно недоступны. */
    }
}

export function initMenuAttention() {
    if (!document.querySelector('[data-md-attention]')) {
        return;
    }
    refresh();
    window.setInterval(() => {
        if (!document.hidden) {
            refresh();
        }
    }, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });
}
