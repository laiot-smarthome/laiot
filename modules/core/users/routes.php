<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Users\UsersController;

return static function (Router $router): void {
    $router->get('/', [UsersController::class, 'index'])->name('index');
    $router->get('/create', [UsersController::class, 'create'])->name('create');
    $router->post('/create', [UsersController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [UsersController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [UsersController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [UsersController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
