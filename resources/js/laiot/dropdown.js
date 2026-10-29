/**
 * Меню действий в верхнем слое браузера: таблица не обрезает пункты.
 * 2026-10-29 — позиция учитывает края экрана, прокрутку и клавиатуру.
 */
import {focusable} from './focus.js';

export function registerDropdown(Alpine) {
    Alpine.data('mdDropdown', (align = 'left') => ({
        open: false,
        reposition: null,
        init() {
            this.reposition = () => { if (this.open) this.position(); };
            window.addEventListener('resize', this.reposition);
            window.addEventListener('scroll', this.reposition, true);
        },
        destroy() {
            window.removeEventListener('resize', this.reposition);
            window.removeEventListener('scroll', this.reposition, true);
        },
        get trigger() { return this.$refs.trigger || this.$refs.splitToggle; },
        position() {
            const panel = this.$refs.panel;
            const rect = this.trigger.getBoundingClientRect();
            const width = panel.offsetWidth, height = panel.offsetHeight;
            const left = align === 'right' ? rect.right - width : rect.left;
            panel.style.left = Math.max(8, Math.min(left, window.innerWidth - width - 8)) + 'px';
            panel.style.top = Math.max(8, rect.bottom + height + 6 <= window.innerHeight
                ? rect.bottom + 6 : rect.top - height - 6) + 'px';
        },
        show(focus = false) {
            this.$refs.panel.showPopover();
            this.open = true;
            this.position();
            if (focus) focusable(this.$refs.panel)[0]?.focus();
        },
        close(restore = false) {
            this.$refs.panel.hidePopover();
            this.open = false;
            if (restore) this.trigger.focus();
        },
        toggle() { if (this.open) this.close(); else this.show(); },
        move(event) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const items = focusable(this.$refs.panel);
            if (!items.length) return;
            event.preventDefault();
            const index = items.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next].focus();
        },
    }));
}
