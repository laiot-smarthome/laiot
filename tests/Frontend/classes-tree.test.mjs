// 2026-10-29 — регрессии состояния дерева без браузера и внешних сервисов.
import assert from 'node:assert/strict';
import test from 'node:test';
import {registerClassesTree} from '../../resources/js/mydomo/classes-tree.js';

let factory;
registerClassesTree({data: (_name, create) => { factory = create; }});
const classes = [
    {id: 1, parent_id: 0, depth: 0, title: 'Sensors', objects_deep_count: 1},
    {id: 2, parent_id: 1, depth: 1, title: 'Temperature', objects_deep_count: 1},
    {id: 3, parent_id: 2, depth: 2, title: 'Kitchen', objects_deep_count: 1},
];

function boot(storage = new Map(), overrides = {}) {
    globalThis.window = {localStorage: {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
    }};
    const tree = factory({classes, objects: {3: [{id: 7, title: 'KitchenTemperature', location_title: 'Кухня'}]}, ...overrides});
    const watchers = new Map();
    tree.$watch = (key, callback) => watchers.set(key, callback);
    tree.change = (key, value) => { tree[key] = value; watchers.get(key)?.(); };
    tree.init();
    return tree;
}

test('раскрытие, сворачивание и все ветки восстанавливаются после перезагрузки', () => {
    const storage = new Map();
    let tree = boot(storage);
    assert.equal(tree.shownCount, 2);
    tree.toggle(2);
    tree.toggle(3);
    tree = boot(storage);
    assert.equal(tree.isObjectRowVisible(3, 7), true);
    tree.toggle(1);
    tree = boot(storage);
    assert.equal(tree.shownCount, 1);
    assert.equal(tree.isExpanded(1), false);
    tree.expandAll();
    assert.equal(boot(storage).isExpanded(3), true);
    tree.collapseAll();
    assert.equal(boot(storage).isExpanded(1), false);
});

test('поиск показывает предков и объекты, сброс сохраняет выбранные ветки', () => {
    const tree = boot();
    tree.collapseAll();
    tree.change('query', 'кухня');
    assert.equal(tree.shownCount, 3);
    assert.equal(tree.isObjectRowVisible(3, 7), true);
    tree.toggle(1);
    tree.collapseAll();
    tree.reset();
    tree.refreshMatches();
    assert.equal(tree.shownCount, 1);
    assert.equal(tree.isExpanded(1), false);
    tree.change('filter', 'empty');
    assert.equal(tree.shownCount, 0);
});

test('повреждённый и запрещённый storage не ломают дерево', () => {
    for (const raw of ['{broken', '[]', '42', '{"1":"false"}']) {
        assert.equal(boot(new Map([['mydomo.classes.expanded', raw]])).isExpanded(1), true);
    }
    const tree = boot();
    window.localStorage.setItem = () => { throw new Error('Storage forbidden'); };
    tree.collapseAll();
    assert.equal(tree.shownCount, 1);
});

test('новые корни раскрываются, удалённые id отбрасываются, пользователи независимы', () => {
    const storage = new Map();
    const first = boot(storage, {storageKey: 'user.1'});
    first.collapseAll();
    assert.equal(boot(storage, {storageKey: 'user.2'}).isExpanded(1), true);
    const tree = boot(storage, {storageKey: 'user.1', classes: [{id: 8, parent_id: 0, depth: 0}]});
    assert.deepEqual(tree.expanded, {8: true});
});

test('дерево глубже 100 уровней сохраняет всех предков и защищено от циклов', () => {
    const deep = Array.from({length: 140}, (_, i) => ({id: i + 1, parent_id: i, depth: i}));
    const tree = boot(new Map(), {classes: deep});
    assert.equal(tree.ancestors(140).length, 139);
    tree.toggle(140);
    assert.equal(tree.shownCount, 140);
    tree.parentMap[1] = 140;
    assert.equal(tree.ancestors(140).length, 139);
});
