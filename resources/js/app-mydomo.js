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
import {initDismiss} from './mydomo/dismiss.js';
import {initDrawer} from './mydomo/drawer.js';
import {initSearch} from './mydomo/search.js';
import {initNotifications} from './mydomo/notifications.js';

window.Alpine = Alpine;

initTheme();
initSidebar();
initDismiss();
initDrawer();
initSearch();
initNotifications();

Alpine.start();
