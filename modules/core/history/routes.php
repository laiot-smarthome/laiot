<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\History\HistoryController;

return static function (Router $router): void {
    $router->get('/', [HistoryController::class, 'index'])->name('index');
    $router->post('/{id}/delete', [HistoryController::class, 'delete'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('delete');
};
