<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Welcome\WelcomeController;

return static function (Router $router): void {
    $router->get('/', [WelcomeController::class, 'index'])->name('index');
    $router->get('/hello/{name}', [WelcomeController::class, 'hello'])
        ->where('name', '[A-Za-zА-Яа-яЁё -]{1,60}')
        ->name('hello');
};
