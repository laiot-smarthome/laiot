<?php

declare(strict_types=1);

/**
 * Bootstrap только для Feature-тестов MyDoMo.
 *
 * Не подключает legacy loader alpha: тесты работают на autoload нового ядра.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Feature/SessionFixture.php';
