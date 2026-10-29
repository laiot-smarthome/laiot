<?php

declare(strict_types=1);

return [
    'driver' => 'file',
    'path' => __DIR__ . '/../var/cache/data',
    'prefix' => 'laiot.',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'password' => '',
    ],
];
