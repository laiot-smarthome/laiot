<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\SettingController;
use MajorDoMo\Http\Controllers\SpikeController;

return static function (Router $router): void {
    $router->middleware(['spike-request'])->group(function (Router $router): void {
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');
        $router->get('/spike', [SpikeController::class, 'index'])->name('spike');
        $router->get('/spike/{id}', [SpikeController::class, 'show'])
            ->whereNumber('id')
            ->name('spike.show');

        $router->get('/db/setting/{name}', [SettingController::class, 'show'])
            ->where('name', '[A-Za-z0-9_.-]+')
            ->name('db.setting');

        $router->post('/spike/action', static fn (): JsonResponse => new JsonResponse(['ok' => true]));
        $router->post('/spike/post-only', static fn (): JsonResponse => new JsonResponse(['ok' => true]));
    });
};
