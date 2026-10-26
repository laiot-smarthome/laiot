<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Scripts\ScriptsController;

return static function (Router $router): void {
    $router->get('/', [ScriptsController::class, 'index'])->name('index');
    $router->get('/create', [ScriptsController::class, 'create'])->name('create');
    $router->post('/create', [ScriptsController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [ScriptsController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}/edit', [ScriptsController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [ScriptsController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
