<?php

declare(strict_types=1);

use MajorDoMo\Core\Config;
use MajorDoMo\Foundation\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Config::boot($root);

$app = new Application($root, [
    'cache' => [
        'driver' => Config::string('CACHE_DRIVER', 'file'),
        'path' => $root . '/var/cache/data',
        'prefix' => 'mydomo.',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => Config::string('DB_HOST', '127.0.0.1'),
        'port' => Config::int('DB_PORT', 3306) ?: 3306,
        'database' => Config::string('DB_NAME'),
        'username' => Config::string('DB_USER'),
        'password' => Config::string('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
]);
$app->boot();

return $app;
