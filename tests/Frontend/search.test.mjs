// 2026-10-29 — запоздалые ответы поиска не возвращают очищенные результаты.
import assert from 'node:assert/strict';
import test from 'node:test';
import {setImmediate} from 'node:timers/promises';

test('новый ввод, очистка и закрытие отменяют предыдущие результаты', async () => {
    const nodes = new Map();
    const timers = new Map();
    const calls = [];
    let nextTimer = 0;
    const classes = new Set();
    const node = id => {
        const value = {
            value: '', hidden: true, inert: true, innerHTML: '', textContent: '', isConnected: true,
            listeners: new Map(),
            addEventListener(type, callback) { this.listeners.set(type, callback); },
            fire(type, event = {}) { this.listeners.get(type)?.(event); },
            setAttribute() {}, replaceChildren() { this.innerHTML = ''; },
            focus() { document.activeElement = this; },
            classList: {toggle() {}}, contains() { return true; },
        };
        nodes.set(id, value);
        return value;
    };
    const header = node('mdHeaderSearchInput');
    const headerResults = node('mdHeaderSearchResults');
    const panel = node('mdmGlobalSearchDrawer');
    const input = node('filter_modules');
    const results = node('filter_add');
    const summary = node('filter_summary');
    const clear = node('btnFilterCloseSearch');
    const wrapper = node('wrapper');
    const opener = node('opener');
    globalThis.document = {
        activeElement: opener,
        body: {classList: {add: key => classes.add(key), remove: key => classes.delete(key), contains: key => classes.has(key)}},
        getElementById: id => nodes.get(id),
        querySelector: selector => selector === '[data-md-header-search]' ? wrapper : null,
        querySelectorAll: () => [], addEventListener() {},
    };
    globalThis.window = {
        location: {origin: 'http://audit.local'},
        setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer; },
        clearTimeout: id => timers.delete(id),
    };
    globalThis.fetch = (_url, options) => new Promise(resolve => { calls.push({options, resolve}); });
    const runTimers = () => { const pending = [...timers.values()]; timers.clear(); pending.forEach(callback => callback()); };
    const answer = async (index, title) => {
        calls[index].resolve({ok: true, text: async () => JSON.stringify({total: 1, sections: {Классы: [{type: 'class', title, url: '/classes'}]}})});
        await setImmediate();
    };
    const {initSearch} = await import('../../resources/js/laiot/search.js');
    initSearch();

    header.value = 'old'; header.fire('input'); runTimers();
    header.value = 'new'; header.fire('input');
    assert.equal(calls[0].options.signal.aborted, true);
    runTimers();
    await answer(1, 'NEW');
    await answer(0, 'OLD');
    assert.match(headerResults.innerHTML, /NEW/);
    assert.doesNotMatch(headerResults.innerHTML, /OLD/);
    header.value = 'third'; header.fire('input'); runTimers();
    header.value = ''; header.fire('input');
    await answer(2, 'STALE');
    assert.equal(headerResults.innerHTML, '');
    assert.equal(headerResults.hidden, true);

    window.LaIOTSearch.open('sensor');
    assert.equal(panel.inert, false);
    clear.fire('click');
    await answer(3, 'CLEARED');
    assert.equal(results.innerHTML, '');
    assert.equal(summary.textContent, '');
    input.value = 'pending'; input.fire('input');
    window.LaIOTSearch.close();
    runTimers();
    assert.equal(calls.length, 4);
    assert.equal(panel.inert, true);
    assert.equal(document.activeElement, opener);
});
