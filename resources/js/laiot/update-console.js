import {createSnake, stepSnake, turnSnake} from './update-snake.js';

// 2026-10-29 — общий игровой блок панели и автономного экрана; прогресс не перезагружает игру.
const games = new Map();
const consoles = new WeakSet();
let listening = false;

function initGame(root) {
    const canvas = root.querySelector('canvas'), context = canvas?.getContext('2d');
    if (!context) return;
    const overlay = root.querySelector('[data-snake-overlay]'), start = root.querySelector('[data-snake-start]');
    const pauseButton = root.querySelector('[data-snake-pause]'), message = root.querySelector('[data-snake-message]');
    const score = root.querySelector('[data-snake-score]'), status = root.querySelector('[data-snake-status]');
    let state = createSnake(), playing = false, timer = null;
    const stop = () => { clearInterval(timer); timer = null; playing = false; };
    const paint = () => {
        const styles = getComputedStyle(root);
        const primary = styles.getPropertyValue('--md-ui-primary').trim() || '#6355eb';
        const background = styles.getPropertyValue('--md-ui-bg-soft').trim() || '#eef2f6';
        const grid = styles.getPropertyValue('--md-ui-border-strong').trim() || '#d3dae4';
        const food = styles.getPropertyValue('--md-ui-warning').trim() || '#b25e09';
        const width = canvas.width / state.columns, height = canvas.height / state.rows;
        context.fillStyle = background; context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = grid;
        for (let y = 0; y < state.rows; y++) for (let x = 0; x < state.columns; x++) { context.beginPath(); context.arc((x + .5) * width, (y + .5) * height, 1, 0, Math.PI * 2); context.fill(); }
        if (state.food) { context.fillStyle = food; context.beginPath(); context.arc((state.food[0] + .5) * width, (state.food[1] + .5) * height, width * .32, 0, Math.PI * 2); context.fill(); }
        state.snake.forEach(([x, y], index) => {
            context.globalAlpha = index === 0 ? 1 : .65; context.fillStyle = primary;
            context.beginPath();
            if (typeof context.roundRect === 'function') context.roundRect(x * width + 2, y * height + 2, width - 4, height - 4, 5);
            else context.rect(x * width + 2, y * height + 2, width - 4, height - 4);
            context.fill();
        });
        context.globalAlpha = 1; context.fillStyle = '#fff';
        const [x, y] = state.snake[0];
        for (const offset of [-1, 1]) { context.beginPath(); context.arc((x + .5) * width + state.direction[0] * 4 + state.direction[1] * offset * 3, (y + .5) * height + state.direction[1] * 4 + state.direction[0] * offset * 3, 1.7, 0, Math.PI * 2); context.fill(); }
        score.textContent = String(state.score);
    };
    const pause = () => {
        if (!playing) return;
        stop(); overlay.hidden = false; message.textContent = 'Игра приостановлена'; start.textContent = 'Продолжить'; pauseButton.disabled = true;
        status.textContent = 'Игра приостановлена. Обновление продолжается.';
    };
    start.addEventListener('click', () => {
        if (!state.alive) state = createSnake();
        stop(); playing = true; overlay.hidden = true; pauseButton.disabled = false; canvas.focus({preventScroll: true});
        status.textContent = 'Игра запущена.';
        paint();
        timer = setInterval(() => {
            if (!root.isConnected || document.hidden) { pause(); return; }
            state = stepSnake(state); paint();
            if (!state.alive) { stop(); overlay.hidden = false; message.textContent = state.won ? 'Поле заполнено.' : `Игра завершена. Счёт: ${state.score}`; start.textContent = 'Начать заново'; pauseButton.disabled = true; status.textContent = 'Можно начать новую игру.'; }
        }, 145);
    });
    pauseButton.addEventListener('click', pause);
    root.querySelectorAll('[data-snake-direction]').forEach(button => button.addEventListener('click', () => { if (playing) state = turnSnake(state, button.dataset.snakeDirection); }));
    root.addEventListener('keydown', event => {
        const direction = {ArrowUp: 'up', ArrowRight: 'right', ArrowDown: 'down', ArrowLeft: 'left', w: 'up', d: 'right', s: 'down', a: 'left'}[event.key];
        if (event.key === ' ' && playing) { event.preventDefault(); pause(); }
        if (direction && playing) { event.preventDefault(); state = turnSnake(state, direction); }
    });
    root.closest('details')?.addEventListener('toggle', event => { if (!event.target.open) pause(); });
    games.set(root, {pause, stop, paint}); paint();
}

function initConsole(root) {
    if (consoles.has(root)) return;
    consoles.add(root);
    const connection = root.querySelector('[data-update-connection]');
    const connectionText = text => { if (connection) { connection.replaceChildren(); const dot = document.createElement('span'); connection.append(dot, document.createTextNode(text)); } };
    let lastStatus = '', eventSignature = '', stopped = false;
    const text = (selector, value) => { const element = root.querySelector(selector); if (element) element.textContent = value; };
    const update = data => {
        const running = ['queued', 'running'].includes(data.status), problem = ['failed', 'interrupted'].includes(data.status), finished = ['succeeded', 'rolled_back'].includes(data.status);
        const statuses = {queued: 'Ожидаем запуска', running: 'Обновление выполняется', interrupted: 'Обновление было прервано', failed: 'Требуется решение', succeeded: 'Обновление завершено', rolled_back: 'Прежняя версия восстановлена'};
        text('[data-update-status]', statuses[data.status] || data.status);
        text('[data-update-headline]', problem ? 'Требуется восстановление' : finished ? 'Операция завершена' : 'Обновление системы');
        text('[data-update-message]', data.message);
        text('[data-update-counter]', `Этап ${data.step} из 8`);
        root.querySelector('[data-update-progress]').value = data.step;
        root.querySelectorAll('[data-step]').forEach(element => { const step = Number(element.dataset.step); element.dataset.state = step < data.step ? 'done' : step === data.step ? 'current' : 'pending'; });
        if (data.snapshot_at) text('[data-update-snapshot]', new Intl.DateTimeFormat('ru', {dateStyle: 'medium', timeStyle: 'short'}).format(new Date(data.snapshot_at)));
        root.querySelector('[data-update-recovery]').hidden = !problem;
        root.querySelector('[data-update-finished]').hidden = !finished;
        root.querySelector('[data-update-error]').hidden = !data.error;
        root.querySelector('[data-update-warning]').hidden = !data.warning;
        text('[data-update-error]', data.error); text('[data-update-warning]', data.warning);
        text('[data-update-interrupted]', data.status === 'interrupted' ? 'Процесс больше не работает. Сервер мог перезагрузиться или завершить службу. Последний завершённый снимок сохранён.' : 'Операция остановлена. Изучите ошибку и выберите повтор или восстановление предыдущей версии.');
        const signature = JSON.stringify(data.events);
        if (signature !== eventSignature) {
            const list = root.querySelector('[data-update-events]'); list.replaceChildren();
            (data.events || []).forEach(event => { const item = document.createElement('li'), time = document.createElement('time'), message = document.createElement('span'); time.textContent = event.at; message.textContent = event.message; item.append(time, message); list.append(item); });
            eventSignature = signature;
        }
        if (lastStatus !== data.status) {
            const mascot = root.querySelector('[data-update-mascot]'); mascot.src = `/assets/img/maskot/${problem ? 'error' : finished ? 'success' : 'work'}.png`;
            root.querySelector('[data-update-tone]').dataset.updateTone = problem ? 'warning' : finished ? 'success' : 'working';
            text('[data-update-caption]', problem ? 'Обновление остановлено' : finished ? 'Система готова к работе' : 'Операция выполняется на сервере');
            if (problem || finished) games.forEach(game => game.pause());
            lastStatus = data.status;
        }
        return running;
    };
    const poll = async () => {
        if (!root.isConnected || stopped) return;
        if (document.hidden) { setTimeout(poll, 3000); return; }
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch('/admin/updates/recovery?status=1', {credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: {Accept: 'application/json'}});
            if (response.status === 403) { stopped = true; connectionText('Нужен ключ. Обновите страницу.'); return; }
            if (!response.ok) throw new Error('connection');
            const data = await response.json();
            if (data.id !== root.dataset.operation) { stopped = true; connectionText('Началась новая операция. Обновите страницу.'); return; }
            connection.dataset.offline = 'false'; connectionText('На связи с сервером');
            stopped = !update(data);
        } catch { connection.dataset.offline = 'true'; connectionText('Связь прервалась. Пробуем снова…'); }
        finally { clearTimeout(timeout); }
        if (!stopped) setTimeout(poll, 3000);
    };
    poll();
}

export function initUpdateConsoles() {
    const refresh = () => {
        games.forEach((game, root) => { if (!root.isConnected) { game.stop(); games.delete(root); } });
        document.querySelectorAll('[data-update-snake]').forEach(root => {
            if (games.has(root)) return;
            try { initGame(root); }
            catch { root.replaceChildren(document.createTextNode('Миниигра недоступна в этом браузере. На обновление это не влияет.')); }
        });
        document.querySelectorAll('[data-update-console]').forEach(initConsole);
    };
    if (!listening) {
        listening = true;
        document.addEventListener('htmx:afterSwap', refresh);
        document.addEventListener('visibilitychange', () => { if (document.hidden) games.forEach(game => game.pause()); });
        document.addEventListener('change', () => games.forEach(game => game.paint()));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh, {once: true});
    else refresh();
}
