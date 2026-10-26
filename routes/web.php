<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\LoginController;
use MajorDoMo\Http\Controllers\YoyoController;
use MajorDoMo\Http\Controllers\YoyoDemoController;
use Symfony\Component\HttpFoundation\RedirectResponse;

return static function (Router $router): void {
    $router->middleware(['request-id'])->group(function (Router $router): void {
        $router->get('/', static fn (): RedirectResponse => new RedirectResponse('/panel/'))->name('home');
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');

        $router->get('/login', [LoginController::class, 'show'])->name('login');
        $router->post('/login', [LoginController::class, 'login'])
            ->middleware('csrf')
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
