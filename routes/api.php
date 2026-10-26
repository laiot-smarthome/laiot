<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\AboutController;
use MajorDoMo\Http\Controllers\ApiMeController;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\ModulesController;
use MajorDoMo\Http\Controllers\NotificationsController;
use MajorDoMo\Http\Controllers\PanelSearchController;

return static function (Router $router): void {
    $router->middleware(['request-id'])->prefix('/api/v1')->group(static function (Router $router): void {
        $router->get('/health', [HealthController::class, '__invoke'])->name('api.health');
        $router->get('/about', AboutController::class)->name('api.about');
        $router->get('/modules', ModulesController::class)->name('api.modules');
        $router->get('/me', ApiMeController::class)
            ->middleware('api.auth')
            ->name('api.me');
        // Поиск в шапке админки: только сессия администратора, без API-токена.
        $router->get('/panel/search', PanelSearchController::class)
            ->middleware('auth')
            ->name('api.panel.search');
        // Центр уведомлений: чтение сессией, прочтение — с CSRF-заголовком.
        $router->get('/panel/notifications', [NotificationsController::class, 'index'])
            ->middleware('auth')
            ->name('api.panel.notifications');
        $router->post('/panel/notifications/dismiss', [NotificationsController::class, 'dismiss'])
            ->middleware(['auth', 'csrf'])
            ->name('api.panel.notifications.dismiss');
    });
};
