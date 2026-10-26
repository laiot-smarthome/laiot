<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Methods\MethodsController;

return static function (Router $router): void {
    $router->get('/', [MethodsController::class, 'index'])->name('index');
    $router->get('/create', [MethodsController::class, 'create'])->name('create');
    $router->post('/create', [MethodsController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [MethodsController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [MethodsController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [MethodsController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
