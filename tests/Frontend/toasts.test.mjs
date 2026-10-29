import test from 'node:test';
import assert from 'node:assert/strict';
import {createToasts} from '../../resources/js/laiot/toasts.js';

// 2026-10-29 — время виртуальное: проверки не ждут семь секунд и не обращаются к браузеру.
function setup(t) {
    t.mock.timers.enable({apis: ['Date', 'setTimeout']});
    const previous = globalThis.window;
    globalThis.window = {setTimeout, clearTimeout, dispatchEvent() {}};
    t.after(() => { globalThis.window = previous; });
    return createToasts();
}

test('каждый тон закрывается через семь секунд с завершением анимации', t => {
    const store = setup(t);
    for (const tone of ['info', 'success', 'warning', 'danger']) store.push({tone, text: tone});
    t.mock.timers.tick(6999);
    assert.ok(store.items.every(item => !item.closing));
    t.mock.timers.tick(1);
    assert.ok(store.items.every(item => item.closing));
    t.mock.timers.tick(220);
    assert.equal(store.items.length, 0);
    assert.equal(store.timers.size, 0);
});

test('наведение и фокус независимо удерживают оставшееся время', t => {
    const store = setup(t), id = store.push({text: 'Проверка'});
    t.mock.timers.tick(2000);
    store.pause(id, 'hover'); store.pause(id, 'focus');
    assert.equal(store.items[0].remaining, 5000);
    t.mock.timers.tick(10000);
    store.resume(id, 'hover');
    assert.equal(store.timers.size, 0);
    store.resume(id, 'focus');
    t.mock.timers.tick(4999);
    assert.equal(store.items[0].closing, false);
    t.mock.timers.tick(1);
    assert.equal(store.items[0].closing, true);
});

test('явный нулевой срок сохраняет уведомление до ручного закрытия', t => {
    const store = setup(t), id = store.push({duration: 0});
    t.mock.timers.tick(60000);
    assert.equal(store.items[0].closing, false);
    store.remove(id); store.remove(id);
    t.mock.timers.tick(220);
    assert.equal(store.items.length, 0);
});

test('повтор не дублирует видимое сообщение, закрывающееся не поглощает новое', t => {
    const store = setup(t), id = store.push({text: 'Результат'});
    assert.equal(store.push({text: 'Результат'}), id);
    store.remove(id);
    const next = store.push({text: 'Результат'});
    assert.notEqual(next, id);
    t.mock.timers.tick(220);
    assert.deepEqual(store.items.map(item => item.id), [next]);
});
