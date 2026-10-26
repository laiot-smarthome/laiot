<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handle(Request::capture())->send();
