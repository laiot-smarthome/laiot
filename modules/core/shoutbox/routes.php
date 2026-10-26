<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Shoutbox\ShoutboxController;

return static function (Router $router): void {
    $router->get('/', [ShoutboxController::class, 'index'])->name('index');
    $router->post('/', [ShoutboxController::class, 'store'])
        ->middleware('csrf')
        ->name('store');
    $router->post('/{id}/delete', [ShoutboxController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
