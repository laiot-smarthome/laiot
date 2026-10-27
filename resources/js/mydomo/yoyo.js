/*
 * CSRF для изменяющих Yoyo-действий.
 *
 * Yoyo/htmx ходит на общий /yoyo. GET служит для UI-состояния, а POST нужен
 * бизнес-операциям (например, категории сценариев), поэтому к запросу
 * добавляется тот же заголовок, что использует локальный API-клиент.
 */

export function initYoyoCsrf() {
    document.addEventListener('htmx:configRequest', (event) => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const token = meta ? meta.getAttribute('content') : '';
        if (!token || !event.detail || !event.detail.headers) {
            return;
        }
        if (!event.detail.headers['X-CSRF-Token']) {
            event.detail.headers['X-CSRF-Token'] = token;
        }
    });
}
