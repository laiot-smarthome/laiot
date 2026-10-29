// 2026-10-29 — общий Notify: семь секунд, независимые паузы наведения/фокуса и завершение анимации перед удалением.
export function createToasts() {
    return {
        items: [],
        nextId: 0,
        attentionAt: -Infinity,
        timers: new Map(),
        push({title = '', text = '', tone = 'info', duration = null} = {}) {
            const existing = this.items.find(item => !item.closing && item.title === String(title) && item.text === String(text) && item.tone === tone);
            if (existing) return existing.id;
            const id = ++this.nextId;
            const type = ['info', 'success', 'warning', 'danger'].includes(tone) ? tone : 'info';
            const requested = duration === null ? 7000 : Number(duration);
            const timeout = Number.isFinite(requested) && requested >= 0 ? requested : 7000;
            this.items.push({id, title: String(title), text: String(text), tone: type, duration: timeout, remaining: timeout, started: 0, pauses: [], closing: false});
            this.resume(id);
            const now = Date.now();
            if (now - this.attentionAt >= 1000) {
                this.attentionAt = now;
                window.dispatchEvent(new CustomEvent('md:notify-attention'));
            }
            return id;
        },
        pause(id, reason = 'manual') {
            const item = this.items.find(item => item.id === id);
            if (!item || item.closing || item.pauses.includes(reason)) return;
            item.pauses.push(reason);
            if (!this.timers.has(id)) return;
            window.clearTimeout(this.timers.get(id));
            this.timers.delete(id);
            item.remaining = Math.max(1, item.remaining - (Date.now() - item.started));
        },
        resume(id, reason = 'manual') {
            const item = this.items.find(item => item.id === id);
            if (!item || item.closing) return;
            item.pauses = item.pauses.filter(value => value !== reason);
            if (item.pauses.length || item.remaining <= 0 || this.timers.has(id)) return;
            item.started = Date.now();
            this.timers.set(id, window.setTimeout(() => this.remove(id), item.remaining));
        },
        remove(id) {
            const item = this.items.find(item => item.id === id);
            if (!item || item.closing) return;
            window.clearTimeout(this.timers.get(id));
            this.timers.delete(id);
            item.closing = true;
            window.setTimeout(() => { this.items = this.items.filter(item => item.id !== id); }, 220);
        },
    };
}
