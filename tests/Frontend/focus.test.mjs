// 2026-10-29 — Tab не уходит под диалог и пропускает недоступные поля.
import assert from 'node:assert/strict';
import test from 'node:test';
import {focusable, trapFocus} from '../../resources/js/mydomo/focus.js';

test('обход исключает скрытые, disabled и inert элементы и замыкается в диалоге', () => {
    globalThis.document = {activeElement: null};
    globalThis.getComputedStyle = item => ({visibility: item.visibility || 'visible'});
    const node = (overrides = {}) => ({
        tabIndex: 0, disabled: false, inert: false, visible: true,
        matches() { return this.disabled; },
        closest() { return this.inert; },
        getClientRects() { return this.visible ? [1] : []; },
        focus() { document.activeElement = this; }, ...overrides,
    });
    const first = node(), last = node();
    let items = [first, node({disabled: true}), node({inert: true}), node({visible: false}), node({tabIndex: -1}), node({visibility: 'hidden'}), last];
    const dialog = {querySelectorAll: () => items, focus() { document.activeElement = this; }};
    assert.deepEqual(focusable(dialog), [first, last]);
    const tab = (shiftKey = false) => {
        let prevented = false;
        trapFocus({key: 'Tab', shiftKey, preventDefault() { prevented = true; }}, dialog);
        return prevented;
    };
    first.focus(); assert.equal(tab(true), true); assert.equal(document.activeElement, last);
    assert.equal(tab(), true); assert.equal(document.activeElement, first);
    document.activeElement = node(); tab(); assert.equal(document.activeElement, first);
    items = []; tab(); assert.equal(document.activeElement, dialog);
});
