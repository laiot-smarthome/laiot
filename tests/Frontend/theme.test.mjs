// 2026-10-29 — повреждённая настройка темы не ломает запуск интерфейса.
import assert from 'node:assert/strict';
import test from 'node:test';
import {initTheme} from '../../resources/js/mydomo/theme.js';

test('известная тема применяется, повреждённые cookie безопасно дают светлую тему', () => {
    let theme;
    const controls = ['light', 'dark'].map(value => ({
        value, checked: false, getAttribute: () => value, addEventListener() {},
    }));
    globalThis.document = {
        cookie: '',
        documentElement: {setAttribute: (_key, value) => { theme = value; }},
        querySelectorAll: () => controls,
    };
    for (const [cookie, expected] of [['theme=dark', 'dark'], ['theme=%E0%A4%A', 'light'], ['theme=%', 'light'], ['', 'light']]) {
        document.cookie = cookie;
        initTheme();
        assert.equal(theme, expected);
        assert.equal(controls.find(control => control.value === expected).checked, true);
    }
});
