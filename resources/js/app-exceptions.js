// 2026-10-29 — локальное копирование отчёта без запуска runtime панели.
import Alpine from 'alpinejs';
import {createToasts} from './laiot/toasts.js';

function legacyCopy(text) {
    const previous = document.activeElement;
    const area = document.createElement('textarea');
    area.className = 'md-exception__copy-buffer';
    area.value = text;
    area.setAttribute('readonly', '');
    document.body.appendChild(area);
    try {
        area.select();
        if (!document.execCommand('copy')) throw new Error('Браузер запретил копирование');
    } finally {
        area.remove();
        previous?.focus();
    }
}

Alpine.store('mdToasts', createToasts());
Alpine.data('mdException', () => ({
    copying: false,
    manual: false,
    async copy() {
        if (this.copying) return;
        this.copying = true;
        try {
            const text = this.$refs.report.value;
            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                } catch (error) {
                    // На обычном HTTP или при запрете Clipboard API используем
                    // штатное копирование выделенного текста по клику пользователя.
                    legacyCopy(text);
                }
            } else {
                legacyCopy(text);
            }
            Alpine.store('mdToasts').push({tone: 'success', title: 'Ошибка скопирована', text: 'Отчёт можно вставить в ИИ.'});
        } catch (error) {
            this.manual = true;
            this.$nextTick(() => { this.$refs.report.focus(); this.$refs.report.select(); });
            Alpine.store('mdToasts').push({tone: 'danger', title: 'Не удалось скопировать', text: 'Скопируйте выделенный отчёт вручную: Ctrl+C или Cmd+C.'});
        } finally {
            this.copying = false;
        }
    },
}));
Alpine.start();
