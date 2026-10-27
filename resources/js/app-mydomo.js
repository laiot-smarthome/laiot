/*
 * MyDoMo admin shell.
 *
 * Полностью новый entry: без jQuery, jquery-migrate, fancybox и
 * alpha-модулей. Bootstrap CSS/Alpine — только визуал и локальные состояния.
 */

import '../css/mydomo.css';

import Alpine from 'alpinejs';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import {TabulatorFull} from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator.min.css';

import {initTheme} from './mydomo/theme.js';
import {initSidebar} from './mydomo/sidebar.js';
import {initDrawer} from './mydomo/drawer.js';
import {initSearch} from './mydomo/search.js';
import {initNotifications} from './mydomo/notifications.js';
import {initConfirm} from './mydomo/confirm.js';
import {initCodeEditors} from './mydomo/code-editor.js';
import {initChartMounts} from './mydomo/chart-mount.js';
import './mydomo/linked-object.js';

window.Alpine = Alpine;

Alpine.data('mdSearchSelect', () => ({
    control: null,
    init() {
        this.control = new TomSelect(this.$el, {
            plugins: this.$el.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
        });
    },
    destroy() {
        this.control?.destroy();
    },
}));

Alpine.data('mdDataTable', (columns, rows, pageSize) => ({
    table: null,
    ready: false,
    query: '',
    init() {
        this.table = new TabulatorFull(this.$refs.grid, {
            data: rows,
            columns: columns.map(column => ({title: column.label, field: column.key, formatter: 'plaintext'})),
            layout: 'fitColumns',
            pagination: true,
            paginationSize: pageSize,
            paginationCounter: 'rows',
            placeholder: 'Ничего не найдено',
            locale: 'ru',
            langs: {ru: {pagination: {first: 'Первая', first_title: 'Первая страница', last: 'Последняя', last_title: 'Последняя страница', prev: 'Назад', prev_title: 'Предыдущая страница', next: 'Вперёд', next_title: 'Следующая страница', counter: {showing: 'Показано', of: 'из', rows: 'строк'}}}},
        });
        this.table.on('tableBuilt', () => { this.ready = true; });
    },
    search() {
        const query = this.query.trim().toLocaleLowerCase();
        this.table.setFilter(row => !query || columns.some(column => String(row[column.key] ?? '').toLocaleLowerCase().includes(query)));
    },
    destroy() {
        this.table?.destroy();
    },
}));

Alpine.data('mdDialog', id => ({
    previous: null,
    init() {
        this.$watch('$store.mdModal.open', open => {
            if (open === id) {
                this.previous = document.activeElement;
                this.$nextTick(() => this.$refs.dialog.querySelector('button, input, select, textarea, a[href], [tabindex="0"]')?.focus());
            } else if (this.previous) {
                this.previous.focus();
                this.previous = null;
            }
        });
    },
    trap(event) {
        if (this.$store.mdModal.open !== id) return;
        const items = [...this.$refs.dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]')].filter(item => item.getClientRects().length);
        if (!items.length) { event.preventDefault(); this.$refs.dialog.focus(); return; }
        if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
        else if (!event.shiftKey && document.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
    },
}));

Alpine.data('mdIconBrowser', () => ({
    icons: [], query: '', limit: 96, error: '', copied: '',
    async init() {
        try {
            const response = await fetch('/assets/admin/tabler-icons.json');
            if (!response.ok) throw new Error('Не удалось загрузить каталог иконок');
            this.icons = await response.json();
        } catch (error) { this.error = error.message; }
    },
    get matches() { const query = this.query.trim().toLowerCase(); return this.icons.filter(name => name.includes(query)); },
    async copy(name) {
        try { await navigator.clipboard.writeText('ti ti-' + name); this.copied = 'Скопировано: ti ti-' + name; }
        catch { this.copied = 'Не удалось скопировать: ti ti-' + name; }
    },
}));

Alpine.data('mdCommandPalette', items => ({
    items, query: '', active: 0,
    get matches() { const q = this.query.trim().toLocaleLowerCase(); return this.items.filter(item => (item.label + ' ' + (item.description || '')).toLocaleLowerCase().includes(q)); },
    move(delta) { this.active = Math.max(0, Math.min(this.matches.length - 1, this.active + delta)); },
    run() { const item = this.matches[this.active]; if (item) window.location.assign(item.href); },
}));

Alpine.store('mdModal', {
    open: null,
    show(id) {
        this.open = String(id);
    },
    close() {
        this.open = null;
    },
});

Alpine.store('mdDrawer', {
    open: null,
    show(id) {
        this.open = String(id);
    },
    close() {
        this.open = null;
    },
});

Alpine.store('mdConfirm', initConfirm());

Alpine.store('mdToasts', {
    items: [],
    nextId: 0,
    push({title = '', text = '', tone = 'info', duration = 5000} = {}) {
        const id = ++this.nextId;
        const type = ['info', 'success', 'warning', 'danger'].includes(tone) ? tone : 'info';
        this.items.push({id, title: String(title), text: String(text), tone: type});
        if (duration > 0) window.setTimeout(() => this.remove(id), duration);
        return id;
    },
    remove(id) { this.items = this.items.filter(item => item.id !== id); },
});

initTheme();
initSidebar();
initDrawer();
initSearch();
initNotifications();
initCodeEditors();
initChartMounts();

Alpine.start();
