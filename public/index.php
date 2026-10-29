<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\EarlyErrorHandler;
use LaIOT\Services\Updates\UpdateGateway;

// 2026-10-29 — аварийный экран и запрет записи работают до Composer и БД.
$entryRoot = dirname(__DIR__);
clearstatcache(true);
if (!class_exists(UpdateGateway::class, false)) {
    require $entryRoot . '/app/Services/Updates/UpdateGateway.php';
}
UpdateGateway::enter($entryRoot);
if (is_file($entryRoot . '/current/public/index.php')) {
    require $entryRoot . '/current/public/index.php';
    return;
}

// 2026-10-29 — ошибки Composer и boot перехватываются до загрузки приложения.
require dirname(__DIR__) . '/app/Http/EarlyErrorHandler.php';
(new EarlyErrorHandler(dirname(__DIR__)))->register();

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle(Request::capture())->send();
