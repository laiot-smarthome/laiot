/**
 * Тонкий клиент API нового ядра.
 *
 * Только fetch, JSON и CSRF из meta. Никаких jQuery/XHR-обёрток alpha.
 */

async function request(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const url = new URL(path, window.location.origin);
    if (options.query) {
        Object.entries(options.query).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });
    }

    const headers = {Accept: 'application/json'};
    if (method !== 'GET' && method !== 'HEAD') {
        headers['Content-Type'] = 'application/json';
        const meta = document.querySelector('meta[name="csrf-token"]');
        const token = meta ? meta.getAttribute('content') : '';
        if (token) {
            headers['X-CSRF-Token'] = token;
        }
    }

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers,
        signal: options.signal,
        body: method === 'GET' || method === 'HEAD' ? undefined : JSON.stringify(options.body || {}),
    });
    const text = await response.text();
    let payload = null;
    try {
        payload = text ? JSON.parse(text) : null;
    } catch (error) {
        payload = null;
    }
    if (!response.ok || (payload && payload.ok === false)) {
        const error = payload && payload.error ? payload.error : {};
        throw new Error(error.message || ('HTTP ' + response.status));
    }

    return payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;
}

export function get(path, query, signal) {
    return request(path, {query, signal});
}

export function post(path, body) {
    return request(path, {method: 'POST', body});
}
