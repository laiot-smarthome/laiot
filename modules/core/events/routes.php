<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Events\EventsController;

return static function (Router $router): void {
    $router->get('/', [EventsController::class, 'index'])->name('index');
};
