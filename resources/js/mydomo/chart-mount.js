/*
 * Монтирование графиков в отрендеренный сервером блок.
 *
 * Yoyo/htmx подменяет HTML компонента, поэтому после каждого swap нужно
 * заново поднять ApexCharts для элементов [data-md-chart]. Сам чанк
 * ApexCharts грузится лениво и только здесь.
 */

import { loadCharts } from './charts.js';

async function init(root) {
    const nodes = root.querySelectorAll('[data-md-chart]:not([data-md-chart-ready])');
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
            charts.render(element, points, { name: element.dataset.mdChartName || '' });
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
}
