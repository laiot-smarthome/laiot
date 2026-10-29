<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\EarlyErrorHandler;

// 2026-10-29 — ошибки Composer и boot перехватываются до загрузки приложения.
require dirname(__DIR__) . '/src/Http/EarlyErrorHandler.php';
(new EarlyErrorHandler(dirname(__DIR__)))->register();

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle(Request::capture())->send();
