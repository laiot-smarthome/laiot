<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\AboutController;
use MajorDoMo\Http\Controllers\HealthController;

return static function (Router $router): void {
    $router->middleware(['request-id'])->prefix('/api/v1')->group(static function (Router $router): void {
        $router->get('/health', [HealthController::class, '__invoke'])->name('api.health');
        $router->get('/about', AboutController::class)->name('api.about');
    });
};
