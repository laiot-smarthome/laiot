/*
 * MyDoMo admin shell.
 *
 * Полностью новый entry: без jQuery, jquery-migrate, fancybox и
 * alpha-модулей. Bootstrap CSS/Alpine — только визуал и локальные состояния.
 */

import '../css/mydomo.css';
import '../css/mqtt.css';

import Alpine from 'alpinejs';
import 'tom-select/dist/css/tom-select.css';
import {TabulatorFull} from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator.min.css';

import {initTheme} from './mydomo/theme.js';
import {initSidebar} from './mydomo/sidebar.js';
import {initDrawer} from './mydomo/drawer.js';
import {initSearch} from './mydomo/search.js';
import {initNotifications} from './mydomo/notifications.js';
import {initMenuAttention} from './mydomo/menu-attention.js';
import {initConfirm} from './mydomo/confirm.js';
import {initCodeEditors} from './mydomo/code-editor.js';
import {initChartMounts} from './mydomo/chart-mount.js';
import {initYoyoCsrf} from './mydomo/yoyo.js';
import './mydomo/linked-object.js';
import {initSelects} from './mydomo/selects.js';
import {registerClassesTree} from './mydomo/classes-tree.js';
import {registerNameCheck} from './mydomo/name-check.js';

window.Alpine = Alpine;

Alpine.data('mdDateTimePicker', (type, initial) => ({
    type, value: initial, open: false, year: 0, month: 0, hour: '00', minute: '00',
    weekdays: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
    hours: Array.from({length: 24}, (_, i) => String(i).padStart(2, '0')),
    minutes: Array.from({length: 60}, (_, i) => String(i).padStart(2, '0')),
    init() { this.sync(); },
    get today() { const date = new Date(); return this.dateValue(date.getFullYear(), date.getMonth(), date.getDate()); },
    dateValue(year, month, day) { return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`; },
    sync() {
        const date = this.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(this.value) ? new Date(`${this.value}T12:00:00`) : new Date();
        this.year = date.getFullYear(); this.month = date.getMonth();
        if (/^\d{2}:\d{2}/.test(this.value)) [this.hour, this.minute] = this.value.split(':');
    },
    toggle() { if (!this.open) this.sync(); this.open = !this.open; },
    move(amount) { const date = new Date(this.year, this.month + amount, 1); this.year = date.getFullYear(); this.month = date.getMonth(); },
    get monthLabel() { return new Intl.DateTimeFormat('ru-RU', {month: 'long', year: 'numeric'}).format(new Date(this.year, this.month, 1)); },
    get days() {
        const first = new Date(this.year, this.month, 1);
        const blanks = (first.getDay() + 6) % 7;
        const count = new Date(this.year, this.month + 1, 0).getDate();
        return [...Array(blanks).fill(null), ...Array.from({length: count}, (_, index) => {
            const number = index + 1, date = new Date(this.year, this.month, number);
            return {number, value: this.dateValue(this.year, this.month, number), label: new Intl.DateTimeFormat('ru-RU', {dateStyle: 'long'}).format(date)};
        })];
    },
    selectDate(value) { this.value = value; this.open = false; this.$nextTick(() => { this.$refs.trigger.focus(); this.$el.querySelector('input').dispatchEvent(new Event('change', {bubbles: true})); }); },
    selectTime() { this.value = `${this.hour}:${this.minute}`; this.$nextTick(() => this.$el.querySelector('input').dispatchEvent(new Event('change', {bubbles: true}))); },
}));

Alpine.data('mdFileUpload', () => ({
    dragging: false, files: [],
    update() { this.files = Array.from(this.$refs.input.files); },
    drop(event) {
        this.dragging = false;
        if (this.$refs.input.disabled || !event.dataTransfer.files.length) return;
        const transfer = new DataTransfer();
        Array.from(event.dataTransfer.files).slice(0, this.$refs.input.multiple ? undefined : 1).forEach(file => transfer.items.add(file));
        this.$refs.input.files = transfer.files;
        this.$refs.input.dispatchEvent(new Event('change', {bubbles: true}));
    },
    remove(index) {
        const transfer = new DataTransfer();
        this.files.filter((_, i) => i !== index).forEach(file => transfer.items.add(file));
        this.$refs.input.files = transfer.files;
        this.$refs.input.dispatchEvent(new Event('change', {bubbles: true}));
    },
    formatSize(size) { return size < 1024 ? `${size} Б` : size < 1048576 ? `${(size / 1024).toFixed(1)} КБ` : `${(size / 1048576).toFixed(1)} МБ`; },
}));

Alpine.data('mdSearchSelect', () => ({
    init() {
        this.$el.dataset.mdSearchable = 'true';
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

Alpine.data('mdDialog', (id, storeName = 'mdModal') => ({
    previous: null,
    init() {
        this.$watch('$store.' + storeName + '.open', open => {
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
        if (this.$store[storeName].open !== id) return;
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
    attentionAt: -Infinity,
    timers: new Map(),
    push({title = '', text = '', tone = 'info', duration = null} = {}) {
        const existing = this.items.find(item => item.title === String(title) && item.text === String(text) && item.tone === tone);
        if (existing) return existing.id;
        const id = ++this.nextId;
        const type = ['info', 'success', 'warning', 'danger'].includes(tone) ? tone : 'info';
        const timeout = duration === null ? (['warning', 'danger'].includes(type) ? 0 : 5000) : Math.max(0, Number(duration) || 0);
        this.items.push({id, title: String(title), text: String(text), tone: type, remaining: timeout, started: 0});
        this.resume(id);
        const now = Date.now();
        if (now - this.attentionAt >= 1000) {
            this.attentionAt = now;
            window.dispatchEvent(new CustomEvent('md:notify-attention'));
        }
        return id;
    },
    pause(id) {
        const item = this.items.find(item => item.id === id);
        if (!item || !this.timers.has(id)) return;
        window.clearTimeout(this.timers.get(id));
        this.timers.delete(id);
        item.remaining = Math.max(1, item.remaining - (Date.now() - item.started));
    },
    resume(id) {
        const item = this.items.find(item => item.id === id);
        if (!item || item.remaining <= 0 || this.timers.has(id)) return;
        item.started = Date.now();
        this.timers.set(id, window.setTimeout(() => this.remove(id), item.remaining));
    },
    remove(id) {
        window.clearTimeout(this.timers.get(id));
        this.timers.delete(id);
        this.items = this.items.filter(item => item.id !== id);
    },
});

window.addEventListener('md:notify', event => Alpine.store('mdToasts').push(event.detail));
window.addEventListener('md:notify-attention', () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelector('[data-md-notify-attention]')?.animate(
        [{opacity: 0}, {opacity: 0.8, offset: 0.35}, {opacity: 0}],
        {duration: 280, easing: 'ease-out'}
    );
});

initTheme();
initSidebar();
initDrawer();
initSearch();
initNotifications();
initMenuAttention();
initCodeEditors();
initChartMounts();
initYoyoCsrf();
registerClassesTree(Alpine);
registerNameCheck(Alpine);

Alpine.start();
initSelects();
