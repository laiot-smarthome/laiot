<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Http\Controllers\HealthController;
use MajorDoMo\Http\Controllers\SettingController;
use MajorDoMo\Http\Controllers\YoyoController;
use MajorDoMo\Http\Controllers\YoyoDemoController;
use Symfony\Component\HttpFoundation\RedirectResponse;

return static function (Router $router): void {
    $router->middleware(['spike-request'])->group(function (Router $router): void {
        $router->get('/', static fn (): RedirectResponse => new RedirectResponse('/panel/'))->name('home');
        $router->get('/health', [HealthController::class, '__invoke'])->name('health');

        $router->get('/db/setting/{name}', [SettingController::class, 'show'])
            ->where('name', '[A-Za-z0-9_.-]+')
            ->name('db.setting');

        $router->get('/yoyo-demo', [YoyoDemoController::class, '__invoke'])->name('yoyo.demo');
        $router->post('/yoyo', [YoyoController::class, '__invoke'])->name('yoyo.update');
    });
};
