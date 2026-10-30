// 2026-10-30 — локальные состояния консоли; сетевые обновления выполняет Yoyo.
export function registerXray(Alpine) {
    let follow = true;
    try { follow = sessionStorage.getItem('laiot.xray.follow') !== '0'; } catch { /* Настройка остаётся в памяти вкладки. */ }
    Alpine.store('mdXray', {
        follow, scrollTop: 0,
        remember() {
            try { sessionStorage.setItem('laiot.xray.follow', this.follow ? '1' : '0'); } catch { /* Запрещённый storage не сбрасывает состояние. */ }
        },
    });
    Alpine.data('mdXrayFeed', () => ({
        init() {
            this.$nextTick(() => {
                const prefs = this.$store.mdXray;
                this.$refs.feed.scrollTop = prefs.follow ? this.$refs.feed.scrollHeight : prefs.scrollTop;
            });
        },
        rememberPosition() {
            if (!this.$store.mdXray.follow) this.$store.mdXray.scrollTop = this.$refs.feed.scrollTop;
        },
    }));
    Alpine.data('mdXrayCountdown', (deadline) => ({
        deadline, now: Date.now(), timer: null,
        init() { this.timer = setInterval(() => { this.now = Date.now(); }, 1000); },
        destroy() { clearInterval(this.timer); },
        get label() {
            const seconds = Math.max(0, Math.ceil((this.deadline - this.now) / 1000));
            if (!seconds) return 'Ожидает выполнения';
            return `${Math.floor(seconds / 3600)}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        },
    }));
}
