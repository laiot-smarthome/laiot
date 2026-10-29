// 2026-10-29 — каналы «Объект.свойство» и дедупликация событий WebSocket.
import assert from 'node:assert/strict';
import test from 'node:test';
import {channelForElement, collectChannels, selectFreshEvents} from '../../resources/js/laiot/websocket.js';

function element(object, property) {
    return {
        getAttribute(name) {
            if (name === 'data-live-object') return object === null ? null : String(object);
            if (name === 'data-live-property') return property === null ? null : String(property);
            return null;
        },
    };
}

test('якоря собираются без дублей и мусорных значений', () => {
    const root = {
        querySelectorAll: () => [
            element('Kitchen', 'temperature'),
            element('Kitchen', 'temperature'),
            element('Light', 'status'),
            element('', 'status'),
            element('bad.name', 'status'),
        ],
    };
    assert.deepEqual(collectChannels(root), ['Kitchen.temperature', 'Light.status']);
    assert.equal(channelForElement(element('Light', 'status')), 'Light.status');
    assert.equal(channelForElement(element(null, 'status')), '');
});

test('старые и повторные события не применяются', () => {
    const seen = new Map();
    const fresh = selectFreshEvents([
        [10, 'Kitchen.temperature', 'old', 't1'],
        [12, 'Kitchen.temperature', 'new', 't2'],
        [11, 'Kitchen.temperature', 'late', 't1'],
        [13, 'Light.status', 'other', 't3'],
        [0, 'Light.status', 'bad', 't4'],
    ], 10, seen);

    assert.deepEqual(fresh.map(item => [item.id, item.channel, item.value]), [
        [12, 'Kitchen.temperature', 'new'],
        [13, 'Light.status', 'other'],
    ]);
    assert.equal(seen.get('Kitchen.temperature'), 12);
});

test('снимок с курсором отсекает всё, что уже вошло в снимок', () => {
    assert.deepEqual(selectFreshEvents([[5, 'Kitchen.temperature', 'a', 't']], 5, new Map()), []);
});
