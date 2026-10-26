<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Properties\PropertiesController;

return static function (Router $router): void {
    $router->get('/', [PropertiesController::class, 'index'])->name('index');
    $router->get('/create', [PropertiesController::class, 'create'])->name('create');
    $router->post('/create', [PropertiesController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [PropertiesController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [PropertiesController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [PropertiesController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
