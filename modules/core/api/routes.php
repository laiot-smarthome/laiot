<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Api\WebhookController;

return static function (Router $router): void {
    $router->get('/', [WebhookController::class, 'index'])->name('index');
    $router->get('/create', [WebhookController::class, 'create'])->name('create');
    $router->post('/', [WebhookController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->get('/{id}/edit', [WebhookController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}', [WebhookController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [WebhookController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
    $router->post('/{id}/rotate', [WebhookController::class, 'rotate'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('rotate');
};
