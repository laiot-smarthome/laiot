/*
 * MyDoMo admin shell.
 *
 * Полностью новый entry: без jQuery, jquery-migrate, fancybox, TomSelect и
 * alpha-модулей. Bootstrap CSS/Alpine — только визуал и локальные состояния.
 */

import '../css/mydomo.css';

import Alpine from 'alpinejs';

import {initTheme} from './mydomo/theme.js';
import {initSidebar} from './mydomo/sidebar.js';
import {initDrawer} from './mydomo/drawer.js';
import {initSearch} from './mydomo/search.js';
import {initNotifications} from './mydomo/notifications.js';
import './mydomo/linked-object.js';

window.Alpine = Alpine;

Alpine.store('mdModal', {
    open: null,
    show(id) {
        this.open = String(id);
    },
    close() {
        this.open = null;
    },
});

Alpine.store('mdDrawer', {
    open: null,
    show(id) {
        this.open = String(id);
    },
    close() {
        this.open = null;
    },
});

initTheme();
initSidebar();
initDrawer();
initSearch();
initNotifications();

Alpine.start();
