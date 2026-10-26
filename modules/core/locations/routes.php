<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Locations\LocationsController;

return static function (Router $router): void {
    $router->get('/', [LocationsController::class, 'index'])->name('index');
    $router->get('/create', [LocationsController::class, 'create'])->name('create');
    $router->post('/create', [LocationsController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [LocationsController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [LocationsController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [LocationsController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
