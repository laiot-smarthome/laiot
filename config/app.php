<?php

declare(strict_types=1);

/**
 * Конфиг каркаса MyDoMo.
 *
 * Пока это обычный массив, а не .env: срез должен работать без БД и без
 * рабочей конфигурации. На следующем этапе значения переедут в Config.
 */
return [
    'debug' => true,
    'views' => __DIR__ . '/../resources/views',
    'view_cache' => __DIR__ . '/../var/cache/views',
    'log' => __DIR__ . '/../var/log/mydomo.log',
];
