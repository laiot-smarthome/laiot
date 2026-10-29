/**
 * Поиск в шапке и полный drawer.
 *
 * Один модуль, чистый DOM и fetch. Никаких alpha MDJAdminApi/partLoad.
 * 2026-10-29 — очистка и новый ввод отменяют старый поиск сразу; закрытая
 * панель исключается из клавиатурной навигации и возвращает фокус.
 */

import {get} from './api.js';
import {trapFocus} from './focus.js';

const TYPE_LABELS = {
    module: 'Модуль',
    class: 'Класс',
    object: 'Объект',
    property: 'Свойство',
    method: 'Метод',
    script: 'Скрипт',
    user: 'Пользователь',
    location: 'Локация',
    item: 'Элемент',
};

const drawerElement = document.getElementById('mdmGlobalSearchDrawer');
const drawerInput = document.getElementById('filter_modules');
const drawerResults = document.getElementById('filter_add');
const drawerSummary = document.getElementById('filter_summary');
const drawerBackdrop = document.querySelector('.md-admin-search-backdrop');
let drawerRequest = null;
let headerRequest = null;
let drawerTimer = 0;
let drawerFocus = null;

function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function openDrawer(term = '') {
    if (!drawerElement) {
        return;
    }
    window.clearTimeout(drawerTimer);
    drawerRequest?.abort();
    if (!document.body.classList.contains('md-admin-search-open')) drawerFocus = document.activeElement;
    document.body.classList.add('md-admin-search-open');
    drawerElement.inert = false;
    drawerElement.setAttribute('aria-hidden', 'false');
    if (drawerSummary) drawerSummary.textContent = '';
    if (drawerInput) {
        drawerInput.value = term;
        drawerInput.focus();
    }
    if (term.trim().length >= 3) {
        performDrawerSearch(term.trim());
    } else if (drawerResults) {
        drawerResults.innerHTML = '<div class="md-admin-search-state">Введите минимум 3 символа.</div>';
    }
}

function closeDrawer() {
    window.clearTimeout(drawerTimer);
    drawerRequest?.abort();
    document.body.classList.remove('md-admin-search-open');
    if (drawerElement) {
        drawerElement.setAttribute('aria-hidden', 'true');
        drawerElement.inert = true;
    }
    if (drawerFocus?.isConnected) drawerFocus.focus();
    drawerFocus = null;
}

function renderItems(items, limit) {
    return items.slice(0, limit).map((item) => {
        const type = String(item.type || 'item');
        const meta = Array.isArray(item.meta)
            ? item.meta.filter(Boolean).map((value) => '<span>' + escapeHtml(value) + '</span>').join('')
            : '';
        return '<a class="md-header-search__item" role="option" href="' + escapeHtml(item.url || '#') + '">' +
            '<span class="md-header-search__type">' + escapeHtml(TYPE_LABELS[type] || type) + '</span>' +
            '<span class="md-header-search__body">' +
            '<strong>' + escapeHtml(item.title) + '</strong>' +
            (item.description ? '<small>' + escapeHtml(item.description) + '</small>' : '') +
            (meta ? '<span class="md-header-search__meta">' + meta + '</span>' : '') +
            '</span></a>';
    }).join('');
}

function renderHeader(data, term) {
    const results = document.getElementById('mdHeaderSearchResults');
    if (!results) {
        return;
    }
    const sections = data && data.sections ? data.sections : {};
    if (!data || !data.total) {
        results.innerHTML = '<div class="md-header-search__state"><strong>Ничего не найдено</strong><span>' +
            escapeHtml(data && data.message ? data.message : 'Попробуйте другое название.') + '</span></div>';
        return;
    }

    let html = '';
    Object.keys(sections).forEach((name) => {
        const items = sections[name] || [];
        if (!items.length) {
            return;
        }
        html += '<section class="md-header-search__section">' +
            '<div class="md-header-search__section-title"><span>' + escapeHtml(name) + '</span><span>' + items.length + '</span></div>' +
            renderItems(items, 4) + '</section>';
    });
    html += '<a class="md-header-search__all" href="#" data-md-search-all>Все результаты по запросу «' +
        escapeHtml(term) + '»</a>';
    results.innerHTML = html;
}

async function performHeaderSearch(term) {
    const results = document.getElementById('mdHeaderSearchResults');
    if (!results) {
        return;
    }
    if (headerRequest) {
        headerRequest.abort();
    }
    const request = headerRequest = new AbortController();
    try {
        const data = await get('/api/v1/panel/search', {q: term, limit: 10}, request.signal);
        if (request.signal.aborted || document.getElementById('mdHeaderSearchInput')?.value.trim() !== term) return;
        renderHeader(data, term);
    } catch (error) {
        if (error.name === 'AbortError' || request.signal.aborted) {
            return;
        }
        results.innerHTML = '<div class="md-header-search__state"><strong>Ошибка поиска</strong><span>' +
            escapeHtml(error.message) + '</span></div>';
    }
}

async function performDrawerSearch(term) {
    if (!drawerResults) {
        return;
    }
    if (drawerRequest) {
        drawerRequest.abort();
    }
    const request = drawerRequest = new AbortController();
    drawerResults.innerHTML = '<div class="md-admin-search-state">Ищем…</div>';
    try {
        const data = await get('/api/v1/panel/search', {q: term, limit: 50}, request.signal);
        if (request.signal.aborted || drawerInput?.value.trim() !== term) return;
        const sections = data && data.sections ? data.sections : {};
        let html = '';
        Object.keys(sections).forEach((name) => {
            const items = sections[name] || [];
            if (!items.length) {
                return;
            }
            html += '<section class="md-admin-search-section"><header><h3>' + escapeHtml(name) +
                '</h3><span>' + items.length + '</span></header><div class="md-admin-search-section__items">';
            items.forEach((item) => {
                html += '<a class="md-admin-search-result" href="' + escapeHtml(item.url || '#') + '">' +
                    '<span class="md-admin-search-result__type">' +
                    escapeHtml(TYPE_LABELS[item.type] || item.type) + '</span>' +
                    '<span class="md-admin-search-result__content"><strong>' + escapeHtml(item.title) + '</strong>' +
                    (item.description ? '<small>' + escapeHtml(item.description) + '</small>' : '') +
                    '</span></a>';
            });
            html += '</div></section>';
        });
        drawerResults.innerHTML = html || '<div class="md-admin-search-state">Ничего не найдено.</div>';
        if (drawerSummary) {
            drawerSummary.textContent = data && data.total ? 'Найдено: ' + data.total : '';
        }
    } catch (error) {
        if (error.name !== 'AbortError' && !request.signal.aborted) {
            drawerResults.innerHTML = '<div class="md-admin-search-state">Ошибка: ' + escapeHtml(error.message) + '</div>';
        }
    }
}

function initHeader() {
    const wrapper = document.querySelector('[data-md-header-search]');
    const input = document.getElementById('mdHeaderSearchInput');
    const results = document.getElementById('mdHeaderSearchResults');
    if (!wrapper || !input || !results) {
        return;
    }
    let timer = 0;
    let term = '';

    const setOpen = (open) => {
        results.hidden = !open;
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
        wrapper.classList.toggle('is-open', open);
    };

    input.addEventListener('input', () => {
        term = input.value.trim();
        window.clearTimeout(timer);
        headerRequest?.abort();
        if (term.length < 3) {
            results.replaceChildren();
            setOpen(false);
            return;
        }
        setOpen(true);
        results.innerHTML = '<div class="md-header-search__state"><strong>Ищем…</strong><span>' + escapeHtml(term) + '</span></div>';
        timer = window.setTimeout(() => performHeaderSearch(term), 180);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.clearTimeout(timer);
            headerRequest?.abort();
            setOpen(false);
            input.blur();
            return;
        }
        if (event.key === 'Enter') {
            const first = !results.hidden && results.querySelector('.md-header-search__item');
            if (first) {
                event.preventDefault();
                window.location.assign(first.getAttribute('href'));
            }
        }
    });
    results.addEventListener('click', (event) => {
        if (event.target.closest('[data-md-search-all]')) {
            event.preventDefault();
            setOpen(false);
            openDrawer(input.value.trim());
        }
    });
    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) {
            setOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (input.getClientRects().length) input.focus();
            else openDrawer();
        }
    });
}

function initDrawer() {
    if (!drawerElement) {
        return;
    }
    document.querySelectorAll('[data-md-search-open]').forEach(element => {
        element.addEventListener('click', () => openDrawer());
    });
    document.querySelectorAll('[data-md-search-close]').forEach((element) => {
        element.addEventListener('click', closeDrawer);
    });
    if (drawerInput) {
        drawerInput.addEventListener('input', () => {
            window.clearTimeout(drawerTimer);
            drawerRequest?.abort();
            if (drawerSummary) drawerSummary.textContent = '';
            const term = drawerInput.value.trim();
            if (term.length < 3) {
                if (drawerResults) {
                    drawerResults.innerHTML = '<div class="md-admin-search-state">Введите минимум 3 символа.</div>';
                }
                return;
            }
            drawerTimer = window.setTimeout(() => performDrawerSearch(term), 220);
        });
    }
    const clear = document.getElementById('btnFilterCloseSearch');
    if (clear && drawerInput) {
        clear.addEventListener('click', () => {
            window.clearTimeout(drawerTimer);
            drawerRequest?.abort();
            if (drawerSummary) drawerSummary.textContent = '';
            drawerInput.value = '';
            drawerInput.focus();
            if (drawerResults) {
                drawerResults.innerHTML = '';
            }
        });
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }
    document.addEventListener('keydown', (event) => {
        if (!document.body.classList.contains('md-admin-search-open')) return;
        if (event.key === 'Escape') closeDrawer();
        else trapFocus(event, drawerElement);
    });
}

export function initSearch() {
    initHeader();
    initDrawer();
}

window.MyDoMoSearch = {open: openDrawer, close: closeDrawer};
