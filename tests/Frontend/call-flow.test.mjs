// 2026-10-29 — соединения следуют геометрии и видимости узлов каждой цепочки.
import assert from 'node:assert/strict';
import test from 'node:test';
import {registerCallFlow} from '../../resources/js/laiot/call-flow.js';

test('высокие строки, ветвления, подсветка и сворачивание не разрывают путь', () => {
    let create;
    registerCallFlow({data: (_name, factory) => { create = factory; }});
    globalThis.document = {createElementNS: () => ({attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }})};
    globalThis.getComputedStyle = () => ({paddingRight: '18px', paddingBottom: '18px'});
    globalThis.cancelAnimationFrame = () => {};
    const group = () => ({children: [], replaceChildren(...items) { this.children = items; }});
    const node = (left, top, parent = null, branch = false) => ({
        visible: true, events: [],
        classList: {contains: name => branch && name === 'md-call-flow__branch'},
        parentElement: {closest: () => parent},
        closest() { return this; },
        getClientRects() { return this.visible ? [1] : []; },
        getBoundingClientRect: () => ({left, top}),
        querySelector() { return {getClientRects: () => [1], getBoundingClientRect: () => ({left: left + (branch ? 12 : 0), top, width: 22, height: 22})}; },
        dispatchEvent(event) { this.events.push(event.detail.open); },
    });
    const root = node(120, 150), child = node(160, 450, root);
    const branch = node(200, 535, child, true), leaf = node(240, 600, branch);
    const list = [root, child, branch, leaf];
    const refs = {
        forest: {isConnected: true, offsetLeft: 18, offsetTop: 14, offsetHeight: 650, scrollWidth: 700,
            contains: item => list.includes(item), getClientRects: () => [1],
            querySelectorAll: selector => selector.endsWith('--has-children') ? [root, child] : list},
        flowBody: {clientWidth: 760}, links: {setAttribute() {}, getBoundingClientRect: () => ({left: 100, top: 100})},
        baseLinks: group(), activeLinks: group(),
    };
    const flow = create(); flow.$refs = refs;
    flow.draw();
    assert.equal(refs.baseLinks.children.length, 3);
    assert.match(refs.baseLinks.children[0].attributes.d, /^M 31 61 /);
    assert.match(refs.baseLinks.children[0].attributes.d, / H 71$/);
    flow.highlight(leaf);
    assert.equal(refs.activeLinks.children.length, 3);
    flow.highlight(null, 'focus');
    assert.equal(refs.activeLinks.children.length, 3);
    flow.highlight(null);
    assert.equal(refs.activeLinks.children.length, 0);
    flow.highlight(child, 'focus');
    assert.equal(refs.activeLinks.children.length, 1);
    leaf.visible = false; flow.draw();
    assert.equal(refs.baseLinks.children.length, 2);
    flow.setExpanded(false);
    assert.deepEqual(root.events, [false]);
    assert.deepEqual(child.events, [false]);
    assert.deepEqual(leaf.events, []);
    flow.destroy();
});
