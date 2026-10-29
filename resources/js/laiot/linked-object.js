/*
 * Редактор связей в оформлении alpha: combobox и списки в верхнем слое.
 * 2026-10-28 — данные через Yoyo и защита от устаревших ответов.
 */
import Alpine from 'alpinejs';

Alpine.data('mdLinkedObject', (config = {}) => ({
    values: {object: config.value || '', property: config.property || '', method: config.method || ''},
    objects: [], context: null, kind: '', active: -1, filter: '',
    loading: false, error: '', sequence: 0, timer: 0, positionHandler: null,
    init() {
        this.positionHandler = () => this.position();
        window.addEventListener('resize', this.positionHandler);
        window.addEventListener('scroll', this.positionHandler, true);
    },
    destroy() {
        clearTimeout(this.timer);
        window.removeEventListener('resize', this.positionHandler);
        window.removeEventListener('scroll', this.positionHandler, true);
    },
    get matches() {
        const items = this.kind === 'object' ? this.objects : (this.context?.[this.kind === 'property' ? 'properties' : 'methods'] || []);
        const normalize = value => String(value).toLocaleLowerCase().replaceAll('ё', 'е');
        const query = normalize(this.filter.trim());
        const matches = items.filter(item => !query || normalize(`${item.title} ${item.description || ''} ${item.class || ''}`).includes(query));
        return this.kind === 'object'
            ? matches.sort((a, b) => (a.class || 'Без класса').localeCompare(b.class || 'Без класса', 'ru') || a.title.localeCompare(b.title, 'ru'))
            : matches;
    },
    show(kind) {
        this.kind = kind;
        this.filter = '';
        this.active = -1;
        this.$nextTick(() => {
            const menu = this.$refs.menu;
            if (menu.showPopover && !menu.matches(':popover-open')) menu.showPopover();
            this.position();
        });
        if (kind === 'object') this.request('');
    },
    toggle(kind) { if (this.kind === kind) this.close(); else { this.$refs[kind].focus(); this.show(kind); } },
    onInput(kind) {
        if (this.kind !== kind) this.show(kind);
        this.filter = this.values[kind];
        this.active = -1;
        if (kind !== 'object') return;
        this.context = null;
        this.values.property = '';
        this.values.method = '';
        // Ответ предыдущего поиска устаревает ещё до завершения debounce.
        this.sequence++;
        this.objects = [];
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.request(this.values.object), 180);
    },
    request(query = this.values.object) {
        clearTimeout(this.timer);
        const source = this.$root.querySelector('[data-md-linked-data]');
        if (!source) return;
        this.loading = true;
        this.error = '';
        const params = {query, object: this.values.object, sequence: ++this.sequence};
        // Yoyo помещает props в hx-vals; они имеют приоритет над значениями input.
        const state = JSON.parse(source.getAttribute('hx-vals') || '{}');
        source.setAttribute('hx-vals', JSON.stringify({...state, ...params}));
        Object.entries(params).forEach(([name, value]) => { source.querySelector(`[name="${name}"]`).value = value; });
        source.querySelector('[data-md-linked-load]').click();
    },
    receive(data) {
        if (data.sequence !== this.sequence || data.object !== this.values.object) return;
        this.objects = data.objects;
        this.context = data.context;
        this.loading = false;
        this.error = '';
        this.$nextTick(() => this.position());
    },
    failed() { this.loading = false; this.error = 'Не удалось загрузить список. Повторите обновление.'; },
    choose(item) {
        const kind = this.kind;
        if (!kind) return;
        if (kind === 'object' && this.values.object !== item.title) {
            this.values.property = '';
            this.values.method = '';
            this.context = null;
        }
        this.values[kind] = item.title;
        this.close();
        this.notify(kind);
        if (kind === 'object') this.request(item.title);
        this.$refs[kind].focus();
    },
    clear(kind) {
        this.values[kind] = '';
        if (kind === 'object') {
            this.sequence++;
            clearTimeout(this.timer);
            this.context = null;
            this.values.property = '';
            this.values.method = '';
            this.objects = [];
            this.loading = false;
        }
        this.close();
        this.notify(kind);
        this.$refs[kind].focus();
    },
    notify(kind) { this.$nextTick(() => this.$refs[kind].dispatchEvent(new Event('change', {bubbles: true}))); },
    move(delta) {
        if (!this.kind) return;
        this.active = Math.max(0, Math.min(this.matches.length - 1, this.active + delta));
        this.$nextTick(() => this.$refs.menu.querySelector('[aria-selected="true"]')?.scrollIntoView({block: 'nearest'}));
    },
    selectActive() { if (this.matches[this.active]) this.choose(this.matches[this.active]); else this.close(); },
    close() {
        this.kind = '';
        const menu = this.$refs.menu;
        if (menu?.hidePopover && menu.matches(':popover-open')) menu.hidePopover();
    },
    position() {
        if (!this.kind) return;
        const box = this.$refs[this.kind].closest('.md-linkedobject__shell').getBoundingClientRect();
        const menu = this.$refs.menu;
        const height = Math.min(320, window.innerHeight - 16);
        const below = window.innerHeight - box.bottom - 8;
        const above = below < 180 && box.top > below;
        Object.assign(menu.style, {
            left: `${Math.max(8, Math.min(box.left, window.innerWidth - box.width - 8))}px`,
            top: `${above ? Math.max(8, box.top - Math.min(menu.scrollHeight, height) - 4) : box.bottom + 4}px`,
            width: `${Math.min(box.width, window.innerWidth - 16)}px`,
            maxHeight: `${Math.max(80, Math.min(height, above ? box.top - 12 : below))}px`,
        });
    },
}));
