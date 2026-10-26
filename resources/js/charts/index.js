/*
 * Графики MyDoMo: ApexCharts отдельным lazy-chunk.
 *
 * Чанк грузится только когда открывается drawer истории с числовыми данными.
 * Цвета берутся из токенов дизайн-системы, поэтому тема совпадает с shell.
 */

import ApexCharts from 'apexcharts';
import '../../css/mydomo-charts.css';

let chart = null;

function token(name, fallback) {
    const value = getComputedStyle(document.body).getPropertyValue(name).trim();

    return value || fallback;
}

/**
 * @param {HTMLElement} element
 * @param {Array<{t:number,v:number}>} points
 * @param {{name?:string, unit?:string}} options
 */
export function render(element, points, options = {}) {
    destroy();

    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const primary = token('--md-ui-primary', '#2f7fbd');
    const grid = token('--md-ui-border', '#e4e8ef');
    const muted = token('--md-ui-muted', '#667085');

    chart = new ApexCharts(element, {
        chart: {
            type: 'area',
            height: 220,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches },
            background: 'transparent',
            foreColor: muted,
            fontFamily: 'inherit',
        },
        series: [{
            name: options.name || 'Значение',
            data: points.map((point) => ({ x: point.t, y: point.v })),
        }],
        colors: [primary],
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: grid, strokeDashArray: 4, padding: { left: 8, right: 12 } },
        xaxis: {
            type: 'datetime',
            labels: { style: { colors: muted, fontSize: '11px' } },
            axisBorder: { color: grid },
            axisTicks: { color: grid },
        },
        yaxis: {
            labels: {
                style: { colors: muted, fontSize: '11px' },
                formatter: (value) => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 }),
            },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            x: { format: 'dd.MM.yyyy HH:mm' },
            y: {
                formatter: (value) => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
                    + (options.unit ? ' ' + options.unit : ''),
            },
        },
        markers: { size: 0, hover: { size: 4 } },
    });

    chart.render();

    return chart;
}

export function destroy() {
    if (chart) {
        chart.destroy();
        chart = null;
    }
}

window.MDCharts = { render, destroy };
