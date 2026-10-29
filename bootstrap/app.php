<?php

declare(strict_types=1);

use LaIOT\Foundation\Application;
use LaIOT\Foundation\Config;
use LaIOT\Services\Settings\SettingsSchema;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Config::boot($root);

// 2026-10-27 — схема редактора .env задаёт также рабочие типы и привязки параметров.
$app = new Application($root, array_replace_recursive([
    // Пока мастер не завершён, HTTP уводит страницы на /install. Тесты и
    // отдельные сборки Application включают это правило явно.
    'install_guard' => true,
    'database' => [
        'driver' => 'mysql',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
], SettingsSchema::environment($root)));
$app->boot();

return $app;
