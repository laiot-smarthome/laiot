<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\AboutController;
use MajorDoMo\Http\Controllers\ApiMeController;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\LinkedObjectController;
use MajorDoMo\Http\Controllers\ModulesController;
use MajorDoMo\Http\Controllers\NotificationsController;
use MajorDoMo\Http\Controllers\PanelSearchController;
use MajorDoMo\Modules\Core\Api\WebhookEndpoint;

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
        // Редактор связей: поиск объектов и свойства/методы выбранного класса.
        $router->get('/panel/linked-object', LinkedObjectController::class)
            ->middleware('auth')
            ->name('api.panel.linked-object');
        // Входящие webhook: аутентификация — секрет в URL, без сессии/токена.
        $router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/webhooks/{secret}', WebhookEndpoint::class)
            ->where('secret', '[a-f0-9]{48}')
            ->name('api.webhooks');
    });
};
