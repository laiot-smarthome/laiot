/*
 * Клиент WebSocket: обновления свойств и системные события.
 *
 * Сервер присылает только изменения тех свойств, которые размечены на
 * странице атрибутами data-live-object/data-live-property. Перед подключением
 * клиент получает короткоживущий тикет и снимок текущих значений; сокет
 * остаётся строго исходящим.
 *
 * 2026-10-29 — первый клиент: якоря, тикет, снимок, переподключение.
 */

import {post} from './api.js';

const MAX_BACKOFF = 30000;
const MUTATION_DELAY = 250;
const NAME = /^[A-Za-z_][A-Za-z0-9_]*$/;

const state = {
    config: null,
    channels: [],
    anchors: new Map(),
    socket: null,
    cursor: 0,
    lastByChannel: new Map(),
    lastSystemId: 0,
    latest: new Map(),
    connecting: false,
    generation: 0,
    attempt: 0,
    reconnectTimer: null,
    rescanTimer: null,
    closedByPage: false,
};

function positiveInteger(value) {
    const number = Number(value);
    return Number.isInteger(number) && number > 0 ? number : 0;
}

export function channelForElement(element) {
    if (!element || typeof element.getAttribute !== 'function') {
        return '';
    }
    const object = String(element.getAttribute('data-live-object') || '').trim();
    const property = String(element.getAttribute('data-live-property') || '').trim();
    return NAME.test(object) && NAME.test(property) ? object + '.' + property : '';
}

export function collectChannels(root = document) {
    const channels = new Set();
    if (!root || typeof root.querySelectorAll !== 'function') {
        return [];
    }
    root.querySelectorAll('[data-live-object][data-live-property]').forEach((element) => {
        const channel = channelForElement(element);
        if (channel !== '') {
            channels.add(channel);
        }
    });
    return Array.from(channels).sort();
}

function buildAnchors() {
    const anchors = new Map();
    document.querySelectorAll('[data-live-object][data-live-property]').forEach((element) => {
        const channel = channelForElement(element);
        if (channel === '') {
            return;
        }
        bindAnchor(element, channel);
        const list = anchors.get(channel) || [];
        list.push(element);
        anchors.set(channel, list);
    });
    return anchors;
}

function bindAnchor(anchor, channel) {
    const control = anchor.matches && anchor.matches('input, textarea, select')
        ? anchor
        : (anchor.querySelector ? anchor.querySelector('input, textarea, select') : null);
    if (!control || control.dataset.liveBound === '1') {
        return;
    }
    control.dataset.liveBound = '1';
    control.addEventListener('input', () => {
        control.dataset.liveDirty = '1';
    });
    control.addEventListener('blur', () => {
        // Пока поле было в фокусе, пользователь мог ничего не менять:
        // догоняем последнее значение, не перебивая введённый текст.
        if (control.dataset.liveDirty !== '1' && state.latest.has(channel)) {
            applyValue(anchor, state.latest.get(channel));
        }
    });
}

function parseConfig() {
    const meta = document.querySelector('meta[name="websocket-config"]');
    if (!meta) {
        return null;
    }
    try {
        const config = JSON.parse(meta.getAttribute('content') || '{}');
        return config && config.enabled === true && typeof config.ticketUrl === 'string' && config.ticketUrl !== ''
            ? config
            : null;
    } catch (error) {
        return null;
    }
}

function socketUrl(config) {
    if (typeof config.url === 'string' && config.url !== '') {
        return config.url.replace(/^http:/i, 'ws:').replace(/^https:/i, 'wss:');
    }
    const scheme = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const path = typeof config.path === 'string' && config.path !== '' ? config.path : '/websocket';
    return scheme + '//' + window.location.host + (path.startsWith('/') ? path : '/' + path);
}

function setStatus(name, label) {
    const node = document.querySelector('[data-live-status]');
    if (!node) {
        return;
    }
    node.hidden = false;
    node.setAttribute('data-state', name);
    const text = node.querySelector('[data-live-status-label]');
    if (text) {
        text.textContent = label || 'Вебсокет';
    }
}

function hideStatus() {
    const node = document.querySelector('[data-live-status]');
    if (node) {
        node.hidden = true;
        node.setAttribute('data-state', 'offline');
    }
}

function formatValue(element, value) {
    const map = element.getAttribute('data-live-map');
    if (map) {
        try {
            const decoded = JSON.parse(map);
            if (decoded && Object.prototype.hasOwnProperty.call(decoded, value)) {
                return String(decoded[value]);
            }
        } catch (error) {
            /* Некорректная карта не должна ломать обновление. */
        }
    }
    return String(value === null || value === undefined ? '' : value);
}

export function applyValue(anchor, value) {
    if (!anchor) {
        return false;
    }
    const control = anchor.matches && anchor.matches('input, textarea, select')
        ? anchor
        : (anchor.querySelector ? anchor.querySelector('input, textarea, select') : null);
    if (control) {
        // Не перезаписываем то, что пользователь уже начал править.
        if (document.activeElement === control || control.dataset.liveDirty === '1') {
            return false;
        }
        control.value = formatValue(control, value);
        return true;
    }

    const target = anchor.querySelector ? anchor.querySelector('[data-live-value]') : null;
    if (target) {
        target.textContent = formatValue(target, value);
        return true;
    }
    if (anchor.hasAttribute && (anchor.hasAttribute('data-live-text') || anchor.children.length === 0)) {
        anchor.textContent = formatValue(anchor, value);
        return true;
    }
    return false;
}

function flash(anchor) {
    if (!anchor || !anchor.classList) {
        return;
    }
    anchor.classList.remove('is-live-updated');
    // Перезапуск CSS-анимации на повторном изменении того же поля.
    void anchor.offsetWidth;
    anchor.classList.add('is-live-updated');
    window.setTimeout(() => anchor.classList.remove('is-live-updated'), 1000);
}

export function parseMessage(data) {
    if (typeof data !== 'string') {
        return null;
    }
    try {
        const payload = JSON.parse(data);
        return payload && payload.v === 1 && typeof payload.type === 'string' ? payload : null;
    } catch (error) {
        return null;
    }
}

/**
 * Оставляет по одному самому новому событию на канал и отсекает повторы.
 */
export function selectFreshEvents(events, cursor, lastByChannel) {
    const fresh = [];
    if (!Array.isArray(events)) {
        return fresh;
    }
    events.forEach((event) => {
        if (!Array.isArray(event) || event.length < 4) {
            return;
        }
        const id = positiveInteger(event[0]);
        const channel = String(event[1] || '');
        const parts = channel.split('.');
        if (id === 0 || parts.length !== 2 || !NAME.test(parts[0]) || !NAME.test(parts[1]) || id <= cursor) {
            return;
        }
        if ((lastByChannel.get(channel) || 0) >= id) {
            return;
        }
        lastByChannel.set(channel, id);
        fresh.push({id, channel, value: String(event[2] ?? ''), at: String(event[3] ?? '')});
    });
    return fresh;
}

function applyChanges(payload) {
    const events = selectFreshEvents(payload.events, state.cursor, state.lastByChannel);
    events.forEach((event) => {
        state.latest.set(event.channel, event.value);
        (state.anchors.get(event.channel) || []).forEach((anchor) => {
            if (applyValue(anchor, event.value)) {
                flash(anchor);
            }
        });
    });
}

/**
 * Системные события приходят отдельным сообщением и не привязаны к полю.
 * Страница может слушать window: md:websocket:system.
 */
function applySystem(payload) {
    const id = positiveInteger(payload.id);
    if (id > 0 && id <= state.lastSystemId) {
        return;
    }
    if (id > 0) {
        state.lastSystemId = id;
    }
    const detail = {
        event: String(payload.event || ''),
        data: payload.data && typeof payload.data === 'object' ? payload.data : {},
        at: String(payload.at || ''),
    };
    if (detail.event === '') {
        return;
    }
    window.dispatchEvent(new CustomEvent('md:websocket:system', {detail}));
}

function clearReconnect() {
    if (state.reconnectTimer !== null) {
        window.clearTimeout(state.reconnectTimer);
        state.reconnectTimer = null;
    }
}

function scheduleReconnect() {
    clearReconnect();
    if (state.closedByPage || document.hidden || state.channels.length === 0 || state.attempt >= 8) {
        return;
    }
    const delay = Math.min(MAX_BACKOFF, 1000 * Math.pow(2, state.attempt)) + Math.floor(Math.random() * 400);
    state.attempt += 1;
    state.reconnectTimer = window.setTimeout(() => {
        state.reconnectTimer = null;
        connect();
    }, delay);
    setStatus('offline', 'Нет связи');
}

function closeSocket() {
    state.generation += 1;
    if (state.socket) {
        const socket = state.socket;
        state.socket = null;
        try {
            socket.close(1000, 'reconnect');
        } catch (error) {
            /* Сокет уже закрыт. */
        }
    }
}

async function connect() {
    if (state.connecting || state.closedByPage || !state.config || state.socket !== null) {
        return;
    }
    const generation = state.generation;
    const channels = collectChannels();
    if (channels.length === 0) {
        closeSocket();
        hideStatus();
        return;
    }
    state.channels = channels;
    state.anchors = buildAnchors();
    state.connecting = true;
    setStatus('connecting', 'Подключение');

    let ticket;
    try {
        ticket = await post(state.config.ticketUrl, {channels});
    } catch (error) {
        if (generation !== state.generation) {
            return;
        }
        state.connecting = false;
        setStatus('offline', 'Нет связи');
        scheduleReconnect();
        return;
    }
    if (generation !== state.generation) {
        return;
    }
    if (!ticket || typeof ticket.ticket !== 'string' || ticket.ticket === '') {
        state.connecting = false;
        setStatus('offline', 'Нет доступа');
        scheduleReconnect();
        return;
    }

    state.cursor = positiveInteger(ticket.cursor);
    state.lastByChannel = new Map();
    state.lastSystemId = state.cursor;
    state.latest = new Map();
    state.anchors = buildAnchors();
    // Снимок закрывает разрыв между HTML страницы и подключением.
    (Array.isArray(ticket.values) ? ticket.values : []).forEach((row) => {
        const object = String((row && row.object) || '');
        const property = String((row && row.property) || '');
        if (!NAME.test(object) || !NAME.test(property)) {
            return;
        }
        const channel = object + '.' + property;
        state.latest.set(channel, String(row.value ?? ''));
        (state.anchors.get(channel) || []).forEach((anchor) => applyValue(anchor, row.value));
    });

    let socket;
    try {
        socket = new WebSocket(socketUrl(state.config), [
            state.config.protocol || 'laiot.v1',
            ticket.ticket,
        ]);
    } catch (error) {
        if (generation !== state.generation) {
            return;
        }
        state.connecting = false;
        setStatus('offline', 'Нет связи');
        scheduleReconnect();
        return;
    }
    state.socket = socket;
    state.connecting = false;

    socket.addEventListener('open', () => {
        state.attempt = 0;
        setStatus('online', 'Вебсокет');
    });
    socket.addEventListener('message', (message) => {
        const payload = parseMessage(message.data);
        if (!payload) {
            return;
        }
        if (payload.type === 'changes') {
            applyChanges(payload);
        } else if (payload.type === 'system') {
            applySystem(payload);
        } else if (payload.type === 'error') {
            setStatus('offline', payload.code === 'unauthorized' ? 'Нет доступа' : 'Нет связи');
        }
    });
    socket.addEventListener('close', (event) => {
        if (state.socket !== socket) {
            return;
        }
        state.socket = null;
        if (event.code === 1000) {
            return;
        }
        setStatus('offline', 'Нет связи');
        if (event.code === 4401 || event.code === 4403) {
            // Повторяем редко: тикет мог истечь, а сессия — уже нет.
            state.attempt = Math.max(state.attempt, 5);
        }
        scheduleReconnect();
    });
    socket.addEventListener('error', () => {
        /* Закрытие придёт следом; здесь не дублируем переподключение. */
    });
}

function restart() {
    clearReconnect();
    closeSocket();
    state.channels = [];
    state.connecting = false;
    state.attempt = 0;
    connect();
}

function rescan() {
    if (!state.config) {
        return;
    }
    const channels = collectChannels();
    if (channels.length === 0) {
        clearReconnect();
        closeSocket();
        state.connecting = false;
        hideStatus();
        return;
    }
    if (channels.join('|') === state.channels.join('|')
        && (state.socket !== null || state.connecting || state.reconnectTimer !== null)) {
        return;
    }
    restart();
}

function debounceRescan() {
    if (state.rescanTimer !== null) {
        window.clearTimeout(state.rescanTimer);
    }
    state.rescanTimer = window.setTimeout(() => {
        state.rescanTimer = null;
        rescan();
    }, MUTATION_DELAY);
}

export function initWebSocket() {
    const config = parseConfig();
    if (!config) {
        return;
    }
    state.config = config;

    if (typeof MutationObserver !== 'undefined' && document.body) {
        const observer = new MutationObserver(debounceRescan);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-live-object', 'data-live-property'],
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && state.socket === null) {
            connect();
        }
    });
    window.addEventListener('online', () => connect());
    window.addEventListener('pageshow', (event) => {
        // Возврат из bfcache: страница снова активна, соединение открываем заново.
        if (event.persisted) {
            state.closedByPage = false;
            state.attempt = 0;
            rescan();
        }
    });
    window.addEventListener('pagehide', () => {
        state.closedByPage = true;
        clearReconnect();
        closeSocket();
    });

    window.LaioTWebSocket = {
        refresh: rescan,
        close: () => {
            state.closedByPage = true;
            clearReconnect();
            closeSocket();
        },
    };

    rescan();
}
