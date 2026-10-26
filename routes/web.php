<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\DashboardController;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\LoginController;
use MajorDoMo\Http\Controllers\RolesController;
use MajorDoMo\Http\Controllers\StatusController;
use MajorDoMo\Http\Controllers\TokensController;
use MajorDoMo\Http\Controllers\YoyoController;
use MajorDoMo\Http\Controllers\YoyoDemoController;

return static function (Router $router): void {
    $router->middleware(['request-id'])->group(function (Router $router): void {
        // Корень ведёт в новый вход; старый shell остаётся на /panel/.
        $router->get('/', [LoginController::class, 'show'])->name('home');
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');
        $router->get('/admin', DashboardController::class)
            ->middleware(['auth'])
            ->name('admin.dashboard');
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
            $router->post('/yoyo', [YoyoController::class, '__invoke'])
                ->middleware('csrf')
                ->name('yoyo.update');
        });
    });
};
