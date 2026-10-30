<?php

declare(strict_types=1);

/**
 * Безопасные значения по умолчанию для LaIOT.
 *
 * Веб-вход передаёт значения из Config; тесты могут переопределить их
 * без чтения рабочей конфигурации.
 * 2026-10-30 — установка без манифеста релиза начинает нумерацию с 0.0.0.
 * 2026-10-26 — детали исключений по умолчанию скрыты.
 */
return [
    'version' => '0.0.0',
    'timezone' => 'Europe/Moscow',
    'debug' => false,
    // Включается в bootstrap/app.php: до установки все страницы ведут в мастер.
    'install_guard' => false,
    'views' => __DIR__ . '/../resources/views',
    'view_cache' => __DIR__ . '/../var/cache/views',
    'log' => __DIR__ . '/../var/log/laiot.log',
    'database' => [],
];
