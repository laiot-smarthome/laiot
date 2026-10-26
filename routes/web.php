<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\ApiDocsController;
use MajorDoMo\Http\Controllers\DashboardController;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\LoginController;
use MajorDoMo\Http\Controllers\MaintenanceController;
use MajorDoMo\Http\Controllers\ModulesPageController;
use MajorDoMo\Http\Controllers\ProfileController;
use MajorDoMo\Http\Controllers\RolesController;
use MajorDoMo\Http\Controllers\StatusController;
use MajorDoMo\Http\Controllers\TokensController;
use MajorDoMo\Http\Controllers\YoyoController;
use MajorDoMo\Http\Controllers\YoyoDemoController;

return static function (Router $router): void {
    $router->middleware(['request-id'])->group(function (Router $router): void {
        // Корень ведёт в новый вход; legacy-панели в MyDoMo нет.
        $router->get('/', [LoginController::class, 'show'])->name('home');
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');
        $router->get('/admin', DashboardController::class)
            ->middleware(['auth'])
            ->name('admin.dashboard');
        $router->get('/admin/modules', ModulesPageController::class)
            ->middleware(['auth'])
            ->name('admin.modules');
        $router->get('/admin/api', ApiDocsController::class)
            ->middleware(['auth'])
            ->name('admin.api');
        $router->get('/admin/maintenance', [MaintenanceController::class, 'show'])
            ->middleware(['auth', 'can:settings.manage'])
            ->name('admin.maintenance');
        $router->post('/admin/maintenance/enable', [MaintenanceController::class, 'enable'])
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.maintenance.enable');
        $router->post('/admin/maintenance/disable', [MaintenanceController::class, 'disable'])
            ->middleware(['auth', 'can:settings.manage', 'csrf'])
            ->name('admin.maintenance.disable');
        $router->get('/admin/profile', [ProfileController::class, 'show'])
            ->middleware(['auth'])
            ->name('admin.profile');
        $router->post('/admin/profile', [ProfileController::class, 'save'])
            ->middleware(['auth', 'csrf'])
            ->name('admin.profile.save');
        $router->get('/status', StatusController::class)
            ->middleware(['auth'])
            ->name('status');
        $router->get('/roles', RolesController::class)
            ->middleware(['auth', 'can:users.manage'])
            ->name('roles');
        $router->get('/admin/tokens', [TokensController::class, 'index'])
            ->middleware(['auth', 'can:users.manage'])
            ->name('admin.tokens');
        $router->post('/admin/tokens', [TokensController::class, 'issue'])
            ->middleware(['auth', 'can:users.manage', 'csrf'])
            ->name('admin.tokens.issue');
        $router->post('/admin/tokens/{id}/revoke', [TokensController::class, 'revoke'])
            ->whereNumber('id')
            ->middleware(['auth', 'can:users.manage', 'csrf'])
            ->name('admin.tokens.revoke');

        $router->get('/login', [LoginController::class, 'show'])->name('login');
        $router->post('/login', [LoginController::class, 'login'])
            ->middleware(['csrf', 'throttle.login'])
            ->name('login.attempt');
        $router->post('/logout', [LoginController::class, 'logout'])
            ->middleware(['auth', 'csrf'])
            ->name('logout');

        $router->middleware('auth')->group(function (Router $router): void {
            $router->get('/yoyo-demo', [YoyoDemoController::class, '__invoke'])->name('yoyo.demo');
            // Yoyo/htmx обновляет компонент GET-запросом. Маршрут только для
            // auth-сессии; изменяющие Yoyo-компоненты должны сами проверять
            // право/идемпотентность, POST-вариант оставлен с CSRF.
            $router->get('/yoyo', [YoyoController::class, '__invoke'])
                ->middleware('auth')
                ->name('yoyo.render');
            $router->post('/yoyo', [YoyoController::class, '__invoke'])
                ->middleware('csrf')
                ->name('yoyo.update');
        });
    });
};
