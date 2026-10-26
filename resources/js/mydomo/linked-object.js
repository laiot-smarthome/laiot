/*
 * x-md.linked-object: поиск объекта и контекст свойств/методов.
 *
 * Новый Alpine-компонент ядра; старый modules/linkedobject и его jQuery-
 * контроллер не используются.
 */

import Alpine from 'alpinejs';
import {get} from './api.js';

Alpine.data('mdLinkedObject', (config = {}) => ({
    query: config.value || '',
    object: config.value || '',
    property: config.property || '',
    method: config.method || '',
    propertyName: config.propertyName || '',
    methodName: config.methodName || '',
    open: false,
    loading: false,
    results: [],
    context: null,
    active: 0,
    error: '',
    timer: 0,
    controller: null,

    init() {
        if (this.object !== '') {
            this.loadContext();
        }
    },

    onInput() {
        this.open = true;
        this.active = 0;
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.search(), 180);
    },

    async search() {
        const query = this.query.trim();
        if (query === '') {
            this.results = [];
            return;
        }
        if (this.controller) {
            this.controller.abort();
        }
        this.controller = new AbortController();
        this.loading = true;
        this.error = '';
        try {
            const data = await get('/api/v1/panel/linked-object', {q: query, limit: 20}, this.controller.signal);
            this.results = data && Array.isArray(data.objects) ? data.objects : [];
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.error = error.message;
                this.results = [];
            }
        } finally {
            this.loading = false;
        }
    },

    async loadContext() {
        if (this.object === '') {
            this.context = null;
            return;
        }
        try {
            this.context = await get('/api/v1/panel/linked-object', {object: this.object});
        } catch (error) {
            this.context = null;
        }
    },

    choose(item) {
        this.object = item.title;
        this.query = item.title;
        this.open = false;
        this.active = 0;
        this.syncInput();
        this.loadContext();
    },

    clear() {
        this.object = '';
        this.query = '';
        this.property = '';
        this.method = '';
        this.context = null;
        this.results = [];
        this.open = false;
        this.syncInput();
    },

    syncInput() {
        const input = this.$refs.input;
        if (!input) {
            return;
        }
        input.value = this.query;
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    },

    move(delta) {
        if (!this.results.length) {
            return;
        }
        this.active = Math.max(0, Math.min(this.results.length - 1, this.active + delta));
    },

    selectActive() {
        if (this.results[this.active]) {
            this.choose(this.results[this.active]);
        }
    },

    close() {
        this.open = false;
    },
}));
