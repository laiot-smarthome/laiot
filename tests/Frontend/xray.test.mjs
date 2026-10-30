import {test} from 'node:test';
import assert from 'node:assert/strict';
import {registerXray} from '../../resources/js/laiot/xray.js';

function setup(storage) {
    globalThis.sessionStorage = storage;
    const factories = new Map(), stores = {};
    registerXray({data: (name, factory) => factories.set(name, factory), store: (name, state) => { stores[name] = state; }});
    const feed = () => Object.assign(factories.get('mdXrayFeed')(), {
        $store: stores, $refs: {feed: {scrollTop: 0, scrollHeight: 1000}}, $nextTick: callback => callback(),
    });
    return {stores, feed};
}

test('выключенное следование и позиция сохраняются после обновления блока', () => {
    const {stores, feed} = setup({getItem: () => '0', setItem() {}});
    const first = feed();
    first.init();
    assert.equal(stores.mdXray.follow, false);
    first.$refs.feed.scrollTop = 275;
    first.rememberPosition();
    const updated = feed();
    updated.init();
    assert.equal(stores.mdXray.follow, false);
    assert.equal(updated.$refs.feed.scrollTop, 275);
});

test('запрещённый storage не сбрасывает выбор при перерисовке', () => {
    const {stores, feed} = setup({getItem() { throw new Error('denied'); }, setItem() { throw new Error('denied'); }});
    stores.mdXray.follow = false;
    stores.mdXray.remember();
    feed().init();
    assert.equal(stores.mdXray.follow, false);
    stores.mdXray.follow = true;
    const updated = feed();
    updated.init();
    assert.equal(updated.$refs.feed.scrollTop, 1000);
});
