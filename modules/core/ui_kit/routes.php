<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\UiKit\UiKitController;

return static function (Router $router): void {
    $router->get('/', [UiKitController::class, 'index'])->name('index');
};
