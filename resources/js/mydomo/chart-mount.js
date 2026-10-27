/*
 * Монтирование графиков в отрендеренный сервером блок.
 *
 * Yoyo/htmx подменяет HTML компонента, поэтому после каждого swap нужно
 * заново поднять ApexCharts для элементов [data-md-chart]. Сам чанк
 * ApexCharts грузится лениво и только здесь.
 */

import { loadCharts } from './charts.js';

async function init(root) {
    const selector = '[data-md-chart]:not([data-md-chart-ready])';
    const nodes = [...(root instanceof Element && root.matches(selector) ? [root] : []), ...root.querySelectorAll(selector)];
    for (const element of nodes) {
        let points = [];
        try {
            points = JSON.parse(element.dataset.mdChartPoints || '[]');
        } catch {
            points = [];
        }
        if (!Array.isArray(points) || points.length < 2) {
            continue;
        }

        element.dataset.mdChartReady = '1';
        try {
            const charts = await loadCharts();
            if (!element.isConnected) continue;
            charts.render(element, points, { name: element.dataset.mdChartName || '', unit: element.dataset.mdChartUnit || '', compact: element.dataset.mdChartCompact === 'true' });
        } catch (error) {
            delete element.dataset.mdChartReady;
            element.classList.add('md-history-chart__empty');
            element.textContent = 'График недоступен: ' + error.message;
        }
    }
}

export function initChartMounts() {
    void init(document);

    document.body.addEventListener('htmx:afterSwap', (event) => {
        void init(event.target instanceof Element ? event.target : document);
    });
    document.body.addEventListener('htmx:beforeCleanupElement', (event) => {
        if (event.target instanceof Element) window.MDCharts?.destroy(event.target);
    });
}
