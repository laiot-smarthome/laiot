<?php

declare(strict_types=1);

/**
 * Безопасные значения по умолчанию для MyDoMo.
 *
 * Веб-вход передаёт значения из Config; тесты могут переопределить их
 * без чтения рабочей конфигурации.
 * 2026-10-26 — детали исключений по умолчанию скрыты.
 */
return [
    'version' => '2.0.0-dev',
    'timezone' => 'Europe/Moscow',
    'debug' => false,
    'views' => __DIR__ . '/../resources/views',
    'view_cache' => __DIR__ . '/../var/cache/views',
    'log' => __DIR__ . '/../var/log/mydomo.log',
    'database' => [],
];
