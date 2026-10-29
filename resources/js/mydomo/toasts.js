// 2026-10-29 — общий Notify для панели и автономной страницы ошибки.
export function createToasts() {
    return {
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
    };
}
