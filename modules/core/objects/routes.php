<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Objects\ObjectsController;

return static function (Router $router): void {
    $router->get('/', [ObjectsController::class, 'index'])->name('index');
    $router->get('/create', [ObjectsController::class, 'create'])->name('create');
    $router->post('/create', [ObjectsController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [ObjectsController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [ObjectsController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [ObjectsController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
