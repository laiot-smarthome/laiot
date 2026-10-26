<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Example\Sdk\ExampleController;

return static function (Router $router): void {
    $router->get('/', ExampleController::class)->name('index');
};
