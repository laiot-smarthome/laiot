<?php

declare(strict_types=1);

use MajorDoMo\Foundation\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$app->boot();

return $app;
