<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\ActionsLog\ActionsLogController;

return static function (Router $router): void {
    $router->get('/', [ActionsLogController::class, 'index'])->name('index');
    $router->post('/{id}/delete', [ActionsLogController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
