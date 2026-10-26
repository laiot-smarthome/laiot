<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Jobs\JobsController;

return static function (Router $router): void {
    $router->get('/', [JobsController::class, 'index'])->name('index');
    $router->post('/failed/{id}/retry', [JobsController::class, 'retry'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('retry');
    $router->post('/failed/{id}/forget', [JobsController::class, 'forget'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('forget');
};
