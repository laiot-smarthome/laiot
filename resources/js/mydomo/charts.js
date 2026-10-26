/*
 * Ленивая загрузка ApexCharts.
 *
 * Чанк нужен только для drawer истории с числовыми данными, поэтому основной
 * бандл его не содержит. При ошибке загрузки история остаётся таблицей.
 */

const SCRIPT_URL = '/assets/admin/mydomo-charts.js';
const STYLE_URL = '/assets/admin/mydomo-charts.css';

let loading = null;

export function loadCharts() {
    if (window.MDCharts) {
        return Promise.resolve(window.MDCharts);
    }
    if (loading) {
        return loading;
    }

    loading = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-md-charts]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = STYLE_URL;
            link.dataset.mdCharts = '1';
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => {
            if (window.MDCharts) {
                resolve(window.MDCharts);

                return;
            }
            loading = null;
            reject(new Error('Charts chunk loaded without API'));
        };
        script.onerror = () => {
            loading = null;
            reject(new Error('Charts chunk failed to load'));
        };
        document.head.appendChild(script);
    });

    return loading;
}
