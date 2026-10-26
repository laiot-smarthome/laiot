<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Classes\ClassesController;

return static function (Router $router): void {
    $router->get('/', [ClassesController::class, 'index'])->name('index');
    $router->get('/create', [ClassesController::class, 'create'])->name('create');
    $router->post('/create', [ClassesController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [ClassesController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [ClassesController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [ClassesController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
