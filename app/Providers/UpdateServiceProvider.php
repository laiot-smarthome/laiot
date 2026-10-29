<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Updates\UpdateFactory;
use LaIOT\Services\Updates\UpdateService;

/**
 * Веб-панель использует тот же обновитель, что и независимая CLI-служба.
 * 2026-10-29 — ленивое подключение без сетевых запросов при boot.
 */
final class UpdateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(UpdateService::class, fn (): UpdateService => UpdateFactory::make($this->app->root(), [
            'version' => $this->app->config('version'), 'updates' => $this->app->config('updates'),
            'database' => $this->app->config('database'), 'encryption' => $this->app->config('encryption'),
        ]));
    }
}
