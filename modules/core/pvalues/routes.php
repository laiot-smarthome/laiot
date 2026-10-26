<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Pvalues\PvaluesController;

return static function (Router $router): void {
    $router->get('/', [PvaluesController::class, 'index'])->name('index');
    $router->post('/{id}', [PvaluesController::class, 'update'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('update');
    $router->post('/{id}/delete', [PvaluesController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
