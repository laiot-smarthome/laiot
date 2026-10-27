<?php

declare(strict_types=1);

/**
 * Bootstrap только для Feature-тестов MyDoMo.
 *
 * Не подключает legacy loader alpha: тесты работают на autoload нового ядра.
 * 2026-10-26 — запуск требует явно изолированную тестовую конфигурацию.
 */
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('PHPUnit требует APP_ENV=testing и SQLite в памяти.');
}

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Feature/SessionFixture.php';
require __DIR__ . '/Feature/DrainsEvents.php';
