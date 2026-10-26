<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\SystemErrors\SystemErrorsController;

return static function (Router $router): void {
    $router->get('/', [SystemErrorsController::class, 'index'])->name('index');
    $router->post('/{id}/delete', [SystemErrorsController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
